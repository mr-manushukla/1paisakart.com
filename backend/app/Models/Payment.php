<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'user_id', 'order_id', 'entry_id', 'amount', 'gateway', 'status', 'ref',
        'intent', 'payload', 'rzp_order_id', 'rzp_payment_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'payload' => 'array',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function entry(): BelongsTo { return $this->belongsTo(DrawEntry::class, 'entry_id'); }
}
