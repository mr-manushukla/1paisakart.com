<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = ['user_id', 'subtotal', 'wallet_applied', 'payable', 'status', 'source'];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'wallet_applied' => 'integer',
            'payable' => 'integer',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
}
