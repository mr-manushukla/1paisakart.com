<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Club;
use App\Models\DrawBatch;
use App\Models\DrawEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Notifications\WinnerWonNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Lucky Draw Purchase Scheme.
 *
 *  - A customer pays a 1% advance to book a product; that books a seat in the
 *    PRICE-BAND CLUB pool the product falls into (products in a pool may differ).
 *  - At 100 seats the pool draws ONE winner (odds 1 in 100). The winner keeps the
 *    product they booked — their 1% covers it and the platform absorbs the balance.
 *  - The other 99 do NOT get auto-refunded. Each chooses, within the choice window:
 *      Option A  convertToPurchase() — pay the remaining 99% and take the product
 *      Option B  creditToWallet()    — move the 1% to wallet for any other product
 *    If they don't choose in time, autoCreditExpired() applies Option B.
 *  - The advance is real money only; wallet credit never funds a booking.
 */
class DrawService
{
    public function __construct(
        private WalletService $wallet,
        private SmsService $sms,
    ) {}

    /**
     * Book the 1% advance on a product — one seat in that product's club pool.
     *
     * A customer may hold several seats in the same pool, but each must be a
     * DIFFERENT product in that price band; the same item can't be booked twice.
     */
    public function enter(Product $product, User $user, ?Payment $payment = null): DrawEntry
    {
        // The draw is global: any active product priced inside a club band qualifies.
        if ($product->status !== 'active') {
            throw new BusinessException('This product is not available.');
        }
        $club = $product->club();
        if (! $club) {
            throw new BusinessException('This product’s price is outside the draw range.');
        }
        $amount = $product->entryPrice();
        if ($amount < 1) {
            throw new BusinessException('This product’s price is too low to book.');
        }
        return DB::transaction(function () use ($club, $product, $user, $amount, $payment) {
            // Lock the open pool so seat counting serialises and the last seat settles once.
            $batch = DrawBatch::where('club_id', $club->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->latest('id')
                ->first() ?? $this->openBatch($club);

            if ($batch->isFull()) {
                throw new BusinessException('That pool just filled — please try again.');
            }

            // One seat per product: you can add more seats to this pool, but only
            // by booking a DIFFERENT item in the same price band.
            if ($batch->entries()->where('user_id', $user->id)->where('product_id', $product->id)->exists()) {
                throw new BusinessException('You have already booked this item in this pool. Choose a different product in the same price range to add another seat.');
            }

            // Secondary guard: how many distinct items one customer may hold in a pool.
            $max = (int) config('draw.max_entries_per_user', 10);
            $held = $batch->entries()->where('user_id', $user->id)->count();
            if ($held >= $max) {
                throw new BusinessException("You can hold at most {$max} seats in one pool (you already have {$held}).");
            }

            $entry = $batch->entries()->create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'amount' => $amount,
                'product_price' => $product->effectivePrice(), // locked in at booking time
                'status' => 'active',
            ]);

            // Advance is real money. When Razorpay captured it, that payment row
            // already exists; a stub is written only for direct service-level
            // bookings (seeding, admin, tests).
            if (! $payment) {
                Payment::create([
                    'user_id' => $user->id,
                    'entry_id' => $entry->id,
                    'amount' => $amount,
                    'gateway' => 'stub',
                    'status' => 'success',
                    'ref' => 'advance-'.$entry->id,
                ]);
            }

            $batch->increment('filled_count');
            $batch->refresh();

            if ($batch->filled_count >= $batch->size) {
                // ponytail: settle synchronously (100 small writes). Queue it if latency bites.
                $this->settle($batch);
            }

            return $entry->fresh();
        });
    }

    /**
     * Does this customer already hold a seat for this product in its CURRENT open
     * pool? enter() rejects a repeat, so charging for one is money taken for a seat
     * that can never be granted — the advance would only bounce to the wallet. The
     * quote boundary calls this to drop such items BEFORE taking payment.
     */
    public function heldSeat(User $user, Product $product): bool
    {
        $club = $product->club();
        if (! $club) {
            return false;
        }
        $batch = DrawBatch::where('club_id', $club->id)
            ->where('status', 'open')
            ->latest('id')
            ->first();

        return $batch
            && $batch->entries()->where('user_id', $user->id)->where('product_id', $product->id)->exists();
    }

    /** Open a fresh pool for a club (lazily — only when someone needs a seat). */
    public function openBatch(Club $club): DrawBatch
    {
        $nextNo = (int) $club->batches()->max('batch_no') + 1;

        return $club->batches()->create([
            'batch_no' => $nextNo,
            'size' => (int) config('draw.batch_size', 100),
            'status' => 'open',
            'filled_count' => 0,
        ]);
    }

    /** Draw one winner; everyone else moves to "choice pending". Runs once. */
    public function settle(DrawBatch $batch): void
    {
        DB::transaction(function () use ($batch) {
            $batch = DrawBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($batch->status !== 'open') {
                return; // already settled/cancelled — exactly-once guard
            }

            $entries = $batch->entries()->where('status', 'active')->with(['product', 'user'])->get();
            $winner = $entries[random_int(0, $entries->count() - 1)];

            $order = $this->fulfilWinnerOrder($winner);
            $winner->update(['status' => 'won', 'order_id' => $order->id]);

            $deadline = now()->addDays((int) config('draw.choice_window_days', 7));
            foreach ($entries as $entry) {
                if ($entry->id === $winner->id) {
                    continue;
                }

                // A winner takes exactly ONE item. Any other seats they hold are
                // refunded to wallet straight away — no choice window for those.
                if ($entry->user_id === $winner->user_id) {
                    $this->wallet->credit(
                        $entry->user,
                        $entry->amount,
                        'draw_refund',
                        'draw_entry',
                        $entry->id,
                        'Extra seat refunded — you already won this pool',
                    );
                    $entry->update(['status' => 'credited']);

                    continue;
                }

                $entry->update(['status' => 'lost_pending', 'choice_deadline_at' => $deadline]);
            }

            $batch->update([
                'status' => 'drawn',
                'winner_entry_id' => $winner->id,
                'drawn_at' => now(),
            ]);

            // Congratulate the winner AFTER the draw commits — never inside the
            // transaction, so a slow/failing send can't hold locks or roll back
            // a settled draw. afterCommit also means it won't fire on a rollback.
            DB::afterCommit(fn () => $this->notifyWinner($winner));
        });
    }

    /** Email + SMS the winner. Best-effort: any failure is logged, never thrown. */
    private function notifyWinner(DrawEntry $winner): void
    {
        $user = $winner->user;
        if (! $user) {
            return;
        }
        $product = $winner->product?->name ?? 'your product';
        $sms = "Congratulations! 🎉 You won the prize ({$product}). You will receive your product soon. Thank you for choosing 1paisakart.";

        try {
            $user->notify(new WinnerWonNotification($winner));
        } catch (\Throwable $e) {
            Log::warning('Winner email failed for entry #'.$winner->id.': '.$e->getMessage());
        }
        try {
            $this->sms->send($user->phone, $sms);
        } catch (\Throwable $e) {
            Log::warning('Winner SMS failed for entry #'.$winner->id.': '.$e->getMessage());
        }
    }

    /** Option A — pay the remaining 99% and take the booked product. */
    public function convertToPurchase(DrawEntry $entry, bool $applyWallet = false, ?Payment $payment = null): Order
    {
        return DB::transaction(function () use ($entry, $applyWallet, $payment) {
            $entry = DrawEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            if (! $entry->awaitingChoice()) {
                throw new BusinessException('This booking is not awaiting a choice.');
            }
            if ($entry->choice_deadline_at && $entry->choice_deadline_at->isPast()) {
                throw new BusinessException('The choice window has closed — the advance went to your wallet.');
            }

            $product = Product::whereKey($entry->product_id)->lockForUpdate()->firstOrFail();
            if ($product->stock < 1) {
                throw new BusinessException("“{$product->name}” is out of stock.");
            }

            $balance = max(0, $entry->productPrice() - $entry->amount);
            $walletApplied = $applyWallet
                ? $this->wallet->applicableForPurchase($entry->user, min($product->maxWalletApplicable(), $balance))
                : 0;
            $payable = $balance - $walletApplied;

            $order = Order::create([
                'user_id' => $entry->user_id,
                'subtotal' => $product->effectivePrice(),
                'wallet_applied' => $walletApplied,
                'payable' => $payable,          // the 1% advance is already paid and adjusted
                'status' => 'paid',
                'source' => 'buy',
            ]);
            $order->items()->create([
                'product_id' => $product->id,
                'qty' => 1,
                'unit_price' => $product->effectivePrice(),
                'line_total' => $product->effectivePrice(),
            ]);
            $product->decrement('stock');

            if ($walletApplied > 0) {
                $this->wallet->debit($entry->user, $walletApplied, 'purchase_debit', 'order', $order->id, "Wallet used on order #{$order->id}");
            }
            if ($payable > 0 && ! $payment) {
                Payment::create([
                    'user_id' => $entry->user_id,
                    'order_id' => $order->id,
                    'amount' => $payable,
                    'gateway' => 'stub',
                    'status' => 'success',
                    'ref' => 'balance-'.$order->id,
                ]);
            }

            $entry->update(['status' => 'converted', 'order_id' => $order->id]);

            return $order->load('items');
        });
    }

    /** Option B — move the 1% advance into the wallet. Returns the new balance. */
    public function creditToWallet(DrawEntry $entry): int
    {
        return DB::transaction(function () use ($entry) {
            $entry = DrawEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            if (! $entry->awaitingChoice()) {
                throw new BusinessException('This booking is not awaiting a choice.');
            }

            $balance = $this->wallet->credit(
                $entry->user,
                $entry->amount,
                'draw_refund',
                'draw_entry',
                $entry->id,
                'Advance moved to wallet — '.$entry->product->name,
            );
            $entry->update(['status' => 'credited']);

            return $balance;
        });
    }

    /** Anyone who didn't choose in time gets Option B. Run from schedule/cron. */
    public function autoCreditExpired(): int
    {
        $done = 0;
        DrawEntry::where('status', 'lost_pending')
            ->whereNotNull('choice_deadline_at')
            ->where('choice_deadline_at', '<=', now())
            ->with(['user', 'product'])
            ->chunkById(100, function ($entries) use (&$done) {
                foreach ($entries as $entry) {
                    $this->creditToWallet($entry);
                    $done++;
                }
            });

        return $done;
    }

    /** Admin cancels an unfilled pool: every active advance goes back to wallet. */
    public function cancel(DrawBatch $batch): void
    {
        DB::transaction(function () use ($batch) {
            $batch = DrawBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($batch->status !== 'open') {
                throw new BusinessException('Only open pools can be cancelled.');
            }

            foreach ($batch->entries()->where('status', 'active')->with('product')->get() as $entry) {
                $this->wallet->credit(
                    $entry->user,
                    $entry->amount,
                    'draw_refund',
                    'draw_batch',
                    $batch->id,
                    "Refund — cancelled pool #{$batch->batch_no}",
                );
                $entry->update(['status' => 'refunded']);
            }

            $batch->update(['status' => 'cancelled']);
        });
    }

    /**
     * Winner keeps the product for the 1% already paid; the platform absorbs the rest.
     * Platform subsidy is derivable as subtotal - wallet_applied - payable.
     */
    private function fulfilWinnerOrder(DrawEntry $winner): Order
    {
        $product = $winner->product;

        $order = Order::create([
            'user_id' => $winner->user_id,
            'subtotal' => $product->effectivePrice(),
            'wallet_applied' => 0,
            'payable' => $winner->amount,
            'status' => 'fulfilled',
            'source' => 'draw_win',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'qty' => 1,
            'unit_price' => $product->effectivePrice(),
            'line_total' => $product->effectivePrice(),
        ]);
        Product::whereKey($product->id)->where('stock', '>', 0)->decrement('stock');

        return $order;
    }
}
