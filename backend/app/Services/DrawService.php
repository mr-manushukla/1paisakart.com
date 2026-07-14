<?php

namespace App\Services;

use App\Models\DrawBatch;
use App\Models\DrawEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Exceptions\BusinessException;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The 1% lucky-draw engine.
 *  - Entries cost 1% of listed price and are paid with REAL money only (never wallet).
 *  - Batches hold 100 entries. The entry that fills the batch triggers settlement
 *    exactly once: one random winner keeps the product for their 1%, the other 99
 *    are refunded to their (restricted) wallet.
 */
class DrawService
{
    public function __construct(private WalletService $wallet) {}

    /** Customer joins the current open batch of a product. Returns their entry. */
    public function enter(Product $product, User $user): DrawEntry
    {
        if (! $product->allow_draw) {
            throw new BusinessException('This product is not available for the 1% draw.');
        }

        return DB::transaction(function () use ($product, $user) {
            // Lock the open batch row so filled_count increments serialize and the
            // 100th entry is detected exactly once. Create the first batch if none open.
            $batch = DrawBatch::where('product_id', $product->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->latest('id')
                ->first() ?? $this->openBatch($product);

            if ($batch->isFull()) {
                // Extremely rare race: filled between our read and lock. Caller may retry.
                throw new BusinessException('That batch just filled — please try again.');
            }

            $max = (int) config('draw.max_entries_per_user', 1);
            if ($batch->entries()->where('user_id', $user->id)->count() >= $max) {
                throw new BusinessException('You have already joined this batch.');
            }

            $entry = $batch->entries()->create([
                'user_id' => $user->id,
                'amount' => $batch->entry_price,
                'status' => 'active',
            ]);

            // Real-money payment (gateway stubbed to instant success for now).
            Payment::create([
                'entry_id' => $entry->id,
                'amount' => $batch->entry_price,
                'gateway' => 'stub',
                'status' => 'success',
                'ref' => 'draw-entry-'.$entry->id,
            ]);

            $batch->increment('filled_count');
            $batch->refresh();

            if ($batch->filled_count >= $batch->size) {
                // ponytail: settle synchronously (99 small writes). If batch settlement
                // latency ever matters, dispatch this to a queued job instead.
                $this->settle($batch);
            }

            return $entry->fresh();
        });
    }

    /** Open a fresh batch for a product (lazy — only when someone needs one). */
    public function openBatch(Product $product): DrawBatch
    {
        $nextNo = (int) $product->batches()->max('batch_no') + 1;

        return $product->batches()->create([
            'batch_no' => $nextNo,
            'size' => (int) config('draw.batch_size', 100),
            'entry_price' => $product->entryPrice(),
            'status' => 'open',
            'filled_count' => 0,
        ]);
    }

    /** Pick one winner, refund the other 99. Runs once (guarded on status). */
    public function settle(DrawBatch $batch): void
    {
        DB::transaction(function () use ($batch) {
            $batch = DrawBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($batch->status !== 'open') {
                return; // already settled/cancelled — exactly-once guard
            }

            $entries = $batch->entries()->where('status', 'active')->get();
            $winner = $entries[random_int(0, $entries->count() - 1)];

            $winner->update(['status' => 'won']);
            $this->fulfilWinnerOrder($batch, $winner);

            foreach ($entries as $entry) {
                if ($entry->id === $winner->id) {
                    continue;
                }
                $entry->update(['status' => 'refunded']);
                $this->wallet->credit(
                    $entry->user,
                    $entry->amount,
                    'draw_refund',
                    'draw_batch',
                    $batch->id,
                    "Refund — draw batch #{$batch->batch_no} of {$batch->product->name}",
                );
            }

            $batch->update([
                'status' => 'drawn',
                'winner_entry_id' => $winner->id,
                'drawn_at' => now(),
            ]);

            $batch->product()->where('stock', '>', 0)->decrement('stock');
        });
    }

    /** Admin/vendor cancels an unfilled batch: refund every active entry. */
    public function cancel(DrawBatch $batch): void
    {
        DB::transaction(function () use ($batch) {
            $batch = DrawBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if (! in_array($batch->status, ['open', 'filled'], true)) {
                throw new BusinessException('Only open batches can be cancelled.');
            }

            foreach ($batch->entries()->where('status', 'active')->get() as $entry) {
                $entry->update(['status' => 'refunded']);
                $this->wallet->credit(
                    $entry->user,
                    $entry->amount,
                    'draw_refund',
                    'draw_batch',
                    $batch->id,
                    "Refund — cancelled batch #{$batch->batch_no}",
                );
            }

            $batch->update(['status' => 'cancelled']);
        });
    }

    private function fulfilWinnerOrder(DrawBatch $batch, DrawEntry $winner): void
    {
        // Winner keeps the product for the 1% they already paid. The order records
        // the fulfilment; money actually moved is entry_price (kept consistent).
        $order = Order::create([
            'user_id' => $winner->user_id,
            'subtotal' => $winner->amount,
            'wallet_applied' => 0,
            'payable' => $winner->amount,
            'status' => 'fulfilled',
            'source' => 'draw_win',
        ]);

        $order->items()->create([
            'product_id' => $batch->product_id,
            'qty' => 1,
            'unit_price' => $winner->amount,
            'line_total' => $winner->amount,
        ]);
    }
}
