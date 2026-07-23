<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Admin issues platform-wide coupons and oversees every vendor coupon. */
class CouponController extends Controller
{
    public function index()
    {
        return Coupon::with('shop:id,name')->withCount('redemptions')->latest('id')->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'scope' => $c->isPlatformWide() ? 'Platform-wide' : ($c->shop?->name ?? '—'),
                'type' => $c->type,
                'value' => $c->value,
                'min_order' => $c->min_order,
                'max_discount' => $c->max_discount,
                'usage_limit' => $c->usage_limit,
                'used_count' => $c->used_count,
                'redemptions' => $c->redemptions_count,
                'ends_at' => $c->ends_at,
                'active' => $c->active,
                'live' => $c->isLive(),
            ]);
    }

    /** Platform-wide coupon (shop_id stays null). */
    public function store(Request $request): array
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:coupons,code'],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'integer', 'min:1', $request->input('type') === 'percent' ? 'max:100' : 'max:100000000'],
            'min_order' => ['nullable', 'integer', 'min:0'],
            'max_discount' => ['nullable', 'integer', 'min:1'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);
        $data['code'] = strtoupper($data['code'] ?? Str::upper(Str::random(8)));

        return ['message' => 'Coupon created.', 'id' => Coupon::create($data)->id];
    }

    /** Admin can disable/enable any coupon, including a vendor's. */
    public function toggle(Coupon $coupon): array
    {
        $coupon->update(['active' => ! $coupon->active]);

        return ['message' => $coupon->active ? 'Coupon enabled.' : 'Coupon disabled.', 'active' => $coupon->active];
    }

    public function destroy(Coupon $coupon): array
    {
        $coupon->delete();

        return ['message' => 'Coupon deleted.'];
    }
}
