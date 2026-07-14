<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = ['order_id', 'entry_id', 'amount', 'gateway', 'status', 'ref'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function entry(): BelongsTo { return $this->belongsTo(DrawEntry::class, 'entry_id'); }
}
