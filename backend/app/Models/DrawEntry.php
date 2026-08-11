<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrawEntry extends Model
{
    protected $fillable = [
        'batch_id', 'user_id', 'product_id', 'amount', 'product_price', 'status', 'choice_deadline_at', 'claimed_at', 'order_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'product_price' => 'integer',
            'choice_deadline_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    /**
     * What the product cost when this seat was booked. Snapshotted so a later
     * price change (or a vendor discount) can't move the goalposts on a booking
     * that's already been paid for.
     */
    public function productPrice(): int
    {
        return (int) ($this->product_price ?? $this->product?->effectivePrice() ?? 0);
    }

    public function batch(): BelongsTo { return $this->belongsTo(DrawBatch::class, 'batch_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }

    /** Remaining balance to own the booked product outright (Option A). */
    public function balanceDue(): int
    {
        return max(0, $this->productPrice() - $this->amount);
    }

    public function awaitingChoice(): bool
    {
        return $this->status === 'lost_pending';
    }

    /** Won, but the winner hasn't yet given an address and settled the TDS. */
    public function awaitingClaim(): bool
    {
        return $this->status === 'won' && $this->claimed_at === null;
    }
}
