<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = ['user_id', 'address_id', 'subtotal', 'coupon_id', 'discount', 'wallet_applied', 'payable', 'status', 'source'];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'discount' => 'integer',
            'wallet_applied' => 'integer',
            'payable' => 'integer',
        ];
    }

    public function coupon(): BelongsTo { return $this->belongsTo(Coupon::class); }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
}
