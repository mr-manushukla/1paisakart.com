<?php

namespace App\Http\Controllers;

use App\Services\CouponService;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function __construct(private CouponService $coupons) {}

    /** Preview what a code is worth on the current cart (the server prices it). */
    public function validateCode(Request $request): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $applied = $this->coupons->apply($data['code'], $request->user(), $data['items']);

        return [
            'code' => $applied['coupon']->code,
            'discount' => $applied['discount'],          // paise
            'eligible_subtotal' => $applied['eligible'],
            'scope' => $applied['coupon']->isPlatformWide() ? 'platform' : ($applied['coupon']->shop?->name ?? 'seller'),
        ];
    }
}
