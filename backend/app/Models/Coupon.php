<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A vendor coupon (valid on that shop's products) or a platform-wide admin coupon. */
class Coupon extends Model
{
    protected $fillable = [
        'shop_id', 'code', 'type', 'value', 'min_order', 'max_discount',
        'usage_limit', 'per_user_limit', 'starts_at', 'ends_at', 'active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'min_order' => 'integer',
            'max_discount' => 'integer',
            'usage_limit' => 'integer',
            'per_user_limit' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function shop(): BelongsTo { return $this->belongsTo(Shop::class); }
    public function redemptions(): HasMany { return $this->hasMany(CouponRedemption::class); }

    public function isPlatformWide(): bool { return $this->shop_id === null; }

    /** Live now? (active, within its window, and not exhausted) */
    public function isLive(): bool
    {
        return $this->active
            && ! ($this->starts_at && $this->starts_at->isFuture())
            && ! ($this->ends_at && $this->ends_at->isPast())
            && ! ($this->usage_limit !== null && $this->used_count >= $this->usage_limit);
    }

    /** Discount (paise) for an eligible subtotal, honouring the cap. */
    public function discountFor(int $eligibleSubtotal): int
    {
        $raw = $this->type === 'percent'
            ? intdiv($eligibleSubtotal * $this->value, 100)
            : $this->value;

        if ($this->max_discount) {
            $raw = min($raw, $this->max_discount);
        }

        return max(0, min($raw, $eligibleSubtotal)); // never exceed what's being bought
    }
}
