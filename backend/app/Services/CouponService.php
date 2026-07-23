<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Product;
use App\Models\User;

/**
 * Coupon codes at checkout.
 *
 *  - A VENDOR coupon only discounts that shop's lines; an ADMIN coupon discounts
 *    the whole cart.
 *  - Coupons apply to full-price purchases only. A 1% advance is never discounted
 *    (it is already 1% of the product's effective, possibly-discounted price, and
 *    the pool maths depends on that).
 *  - One coupon per order.
 */
class CouponService
{
    /**
     * Price a code against a cart.
     *
     * @param  array<int, array{product_id:int, qty:int}>  $lines
     * @return array{coupon: Coupon, discount: int, eligible: int}
     */
    public function apply(string $code, User $user, array $lines): array
    {
        $coupon = Coupon::whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])->first();
        if (! $coupon) {
            throw new BusinessException('That coupon code does not exist.');
        }
        if (! $coupon->active) {
            throw new BusinessException('This coupon is no longer active.');
        }
        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            throw new BusinessException('This coupon is not valid yet.');
        }
        if ($coupon->ends_at && $coupon->ends_at->isPast()) {
            throw new BusinessException('This coupon has expired.');
        }
        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            throw new BusinessException('This coupon has been fully redeemed.');
        }

        $usedByUser = CouponRedemption::where('coupon_id', $coupon->id)->where('user_id', $user->id)->count();
        if ($coupon->per_user_limit && $usedByUser >= $coupon->per_user_limit) {
            throw new BusinessException('You have already used this coupon.');
        }

        $eligible = $this->eligibleSubtotal($coupon, $lines);
        if ($eligible < 1) {
            throw new BusinessException($coupon->isPlatformWide()
                ? 'This coupon does not apply to anything in your cart.'
                : 'This coupon only applies to items from '.($coupon->shop?->name ?? 'that seller').'.');
        }
        if ($eligible < $coupon->min_order) {
            throw new BusinessException('Spend at least ₹'.number_format($coupon->min_order / 100).' on eligible items to use this coupon.');
        }

        $discount = $coupon->discountFor($eligible);
        if ($discount < 1) {
            throw new BusinessException('This coupon gives no discount on your cart.');
        }

        return ['coupon' => $coupon, 'discount' => $discount, 'eligible' => $eligible];
    }

    /** Same as apply() but returns null instead of throwing — for optional re-pricing. */
    public function tryApply(?string $code, User $user, array $lines): ?array
    {
        if (! filled($code)) {
            return null;
        }
        try {
            return $this->apply($code, $user, $lines);
        } catch (BusinessException) {
            return null;
        }
    }

    /** Record the redemption once the order exists. */
    public function redeem(Coupon $coupon, User $user, int $discount, ?int $orderId = null): void
    {
        CouponRedemption::create([
            'coupon_id' => $coupon->id,
            'user_id' => $user->id,
            'order_id' => $orderId,
            'discount' => $discount,
        ]);
        $coupon->increment('used_count');
    }

    /** Sum of the cart lines this coupon is allowed to discount (paise). */
    private function eligibleSubtotal(Coupon $coupon, array $lines): int
    {
        $total = 0;
        foreach ($lines as $line) {
            $product = Product::find($line['product_id']);
            if (! $product) {
                continue;
            }
            if ($coupon->shop_id && $product->shop_id !== $coupon->shop_id) {
                continue; // vendor coupon — only their own products count
            }
            $total += $product->effectivePrice() * max(1, (int) $line['qty']);
        }

        return $total;
    }
}
