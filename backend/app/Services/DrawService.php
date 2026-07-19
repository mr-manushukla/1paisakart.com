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
use Illuminate\Support\Facades\DB;

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
    public function __construct(private WalletService $wallet) {}

    /** Book a 1% advance on a product, taking a seat in its club pool. */
    public function enter(Product $product, User $user): DrawEntry
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

        return DB::transaction(function () use ($club, $product, $user, $amount) {
            // Lock the open pool so seat counting serialises and the 100th seat settles once.
            $batch = DrawBatch::where('club_id', $club->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->latest('id')
                ->first() ?? $this->openBatch($club);

            if ($batch->isFull()) {
                throw new BusinessException('That pool just filled — please try again.');
            }

            $max = (int) config('draw.max_entries_per_user', 1);
            if ($batch->entries()->where('user_id', $user->id)->count() >= $max) {
                throw new BusinessException('You have already booked a seat in this club pool.');
            }

            $entry = $batch->entries()->create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'amount' => $amount,
                'status' => 'active',
            ]);

            // Advance is real money (gateway stubbed to instant success for now).
            Payment::create([
                'entry_id' => $entry->id,
                'amount' => $amount,
                'gateway' => 'stub',
                'status' => 'success',
                'ref' => 'advance-'.$entry->id,
            ]);

            $batch->increment('filled_count');
            $batch->refresh();

            if ($batch->filled_count >= $batch->size) {
                // ponytail: settle synchronously (100 small writes). Queue it if latency bites.
                $this->settle($batch);
            }

            return $entry->fresh();
        });
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

            $entries = $batch->entries()->where('status', 'active')->with('product')->get();
            $winner = $entries[random_int(0, $entries->count() - 1)];

            $order = $this->fulfilWinnerOrder($winner);
            $winner->update(['status' => 'won', 'order_id' => $order->id]);

            $deadline = now()->addDays((int) config('draw.choice_window_days', 7));
            foreach ($entries as $entry) {
                if ($entry->id === $winner->id) {
                    continue;
                }
                $entry->update(['status' => 'lost_pending', 'choice_deadline_at' => $deadline]);
            }

            $batch->update([
                'status' => 'drawn',
                'winner_entry_id' => $winner->id,
                'drawn_at' => now(),
            ]);
        });
    }

    /** Option A — pay the remaining 99% and take the booked product. */
    public function convertToPurchase(DrawEntry $entry, bool $applyWallet = false): Order
    {
        return DB::transaction(function () use ($entry, $applyWallet) {
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

            $balance = max(0, $product->listed_price - $entry->amount);
            $walletApplied = $applyWallet
                ? $this->wallet->applicableForPurchase($entry->user, min($product->maxWalletApplicable(), $balance))
                : 0;
            $payable = $balance - $walletApplied;

            $order = Order::create([
                'user_id' => $entry->user_id,
                'subtotal' => $product->listed_price,
                'wallet_applied' => $walletApplied,
                'payable' => $payable,          // the 1% advance is already paid and adjusted
                'status' => 'paid',
                'source' => 'buy',
            ]);
            $order->items()->create([
                'product_id' => $product->id,
                'qty' => 1,
                'unit_price' => $product->listed_price,
                'line_total' => $product->listed_price,
            ]);
            $product->decrement('stock');

            if ($walletApplied > 0) {
                $this->wallet->debit($entry->user, $walletApplied, 'purchase_debit', 'order', $order->id, "Wallet used on order #{$order->id}");
            }
            if ($payable > 0) {
                Payment::create([
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
            'subtotal' => $product->listed_price,
            'wallet_applied' => 0,
            'payable' => $winner->amount,
            'status' => 'fulfilled',
            'source' => 'draw_win',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'qty' => 1,
            'unit_price' => $product->listed_price,
            'line_total' => $product->listed_price,
        ]);
        Product::whereKey($product->id)->where('stock', '>', 0)->decrement('stock');

        return $order;
    }
}
