<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrawEntry extends Model
{
    protected $fillable = [
        'batch_id', 'user_id', 'product_id', 'amount', 'status', 'choice_deadline_at', 'order_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'choice_deadline_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo { return $this->belongsTo(DrawBatch::class, 'batch_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }

    /** Remaining balance to own the booked product outright (Option A). */
    public function balanceDue(): int
    {
        return max(0, $this->product->listed_price - $this->amount);
    }

    public function awaitingChoice(): bool
    {
        return $this->status === 'lost_pending';
    }
}
