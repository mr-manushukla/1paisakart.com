<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Vendors issue coupons valid only on their own products. */
class CouponController extends Controller
{
    public function index(Request $request)
    {
        return Coupon::where('shop_id', $this->shopId($request))
            ->withCount('redemptions')
            ->latest('id')->get()
            ->map(fn ($c) => $this->present($c));
    }

    public function store(Request $request): array
    {
        $data = $this->validated($request);
        $data['shop_id'] = $this->shopId($request);
        $data['code'] = strtoupper($data['code'] ?? Str::upper(Str::random(8)));

        return $this->present(Coupon::create($data));
    }

    public function update(Request $request, Coupon $coupon): array
    {
        $this->authorizeOwner($request, $coupon);
        $coupon->update($this->validated($request, $coupon));

        return $this->present($coupon->fresh());
    }

    public function destroy(Request $request, Coupon $coupon): array
    {
        $this->authorizeOwner($request, $coupon);
        $coupon->delete();

        return ['message' => 'Coupon deleted.'];
    }

    private function present(Coupon $c): array
    {
        return [
            'id' => $c->id,
            'code' => $c->code,
            'type' => $c->type,
            'value' => $c->value,
            'min_order' => $c->min_order,
            'max_discount' => $c->max_discount,
            'usage_limit' => $c->usage_limit,
            'per_user_limit' => $c->per_user_limit,
            'used_count' => $c->used_count,
            'redemptions' => $c->redemptions_count ?? $c->redemptions()->count(),
            'starts_at' => $c->starts_at,
            'ends_at' => $c->ends_at,
            'active' => $c->active,
            'live' => $c->isLive(),
        ];
    }

    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        return $request->validate([
            'code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'integer', 'min:1', $request->input('type') === 'percent' ? 'max:100' : 'max:100000000'],
            'min_order' => ['nullable', 'integer', 'min:0'],
            'max_discount' => ['nullable', 'integer', 'min:1'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'active' => ['boolean'],
        ]);
    }

    private function shopId(Request $request): int
    {
        return $request->user()->shop?->id ?? abort(422, 'Your vendor account has no shop yet.');
    }

    private function authorizeOwner(Request $request, Coupon $coupon): void
    {
        abort_unless($coupon->shop_id === $request->user()->shop?->id, 403, 'Not your coupon.');
    }
}
