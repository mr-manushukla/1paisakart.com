<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\DrawService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponAndDiscountTest extends TestCase
{
    use RefreshDatabase;

    private function shop(): Shop
    {
        $vendor = User::factory()->create(['role' => 'vendor']);

        return Shop::create(['user_id' => $vendor->id, 'name' => 'Shop'.uniqid(), 'slug' => 's-'.$vendor->id]);
    }

    private function product(Shop $shop, int $mrp, ?int $sale = null): Product
    {
        $cat = Category::firstOrCreate(['slug' => 'c'], ['name' => 'C']);

        return Product::create([
            'shop_id' => $shop->id, 'category_id' => $cat->id,
            'name' => 'P'.uniqid(), 'slug' => 'p-'.uniqid(),
            'listed_price' => $mrp * 100, 'sale_price' => $sale ? $sale * 100 : null,
            'stock' => 50, 'allow_full_buy' => true, 'status' => 'active',
        ]);
    }

    // ---------- product discounts ----------

    public function test_sale_price_becomes_the_effective_price_and_shows_a_percentage(): void
    {
        $p = $this->product($this->shop(), 40999, 31999);

        $this->assertSame(3199900, $p->effectivePrice());
        $this->assertTrue($p->onSale());
        $this->assertSame(22, $p->discountPct());   // matches the ↓22% style badge
    }

    public function test_a_discount_moves_the_product_into_a_different_club_and_lowers_the_advance(): void
    {
        $shop = $this->shop();
        $full = $this->product($shop, 20000);            // ₹20,000
        $discounted = $this->product($shop, 20000, 9000); // same MRP, sells at ₹9,000

        // 1% advance follows the price the customer actually pays
        $this->assertSame(20000, $full->entryPrice());     // 1% of ₹20,000
        $this->assertSame(9000, $discounted->entryPrice()); // 1% of ₹9,000

        // ...and the club band shifts with it
        $this->assertNotSame($full->club()->id, $discounted->club()->id);
        $this->assertSame($discounted->club()->id, \App\Models\Club::forPrice(900000)->id);
    }

    public function test_checkout_charges_the_discounted_price(): void
    {
        $p = $this->product($this->shop(), 10000, 8000);
        $user = User::factory()->create(['role' => 'customer']);

        $order = app(CheckoutService::class)->place($user, [['product_id' => $p->id, 'qty' => 2]], false);

        $this->assertSame(800000 * 2, $order->subtotal);          // sale price, not MRP
        $this->assertSame(800000, $order->items->first()->unit_price);
    }

    public function test_a_later_price_change_does_not_move_an_existing_bookers_balance(): void
    {
        $p = $this->product($this->shop(), 20000);
        $user = User::factory()->create(['role' => 'customer']);
        $entry = app(DrawService::class)->enter($p, $user);
        $this->assertSame(2000000 - 20000, $entry->balanceDue());

        $p->update(['listed_price' => 50000 * 100]);   // vendor hikes the price afterwards

        $this->assertSame(2000000 - 20000, $entry->fresh()->balanceDue()); // unchanged
    }

    // ---------- coupons ----------

    public function test_percent_coupon_with_a_cap(): void
    {
        $shop = $this->shop();
        $p = $this->product($shop, 10000);
        $user = User::factory()->create(['role' => 'customer']);
        Coupon::create(['shop_id' => $shop->id, 'code' => 'SAVE20', 'type' => 'percent', 'value' => 20, 'max_discount' => 100000]);

        $order = app(CheckoutService::class)->place($user, [['product_id' => $p->id, 'qty' => 1]], false, null, 'SAVE20');

        $this->assertSame(1000000, $order->subtotal);
        $this->assertSame(100000, $order->discount);          // 20% = ₹2,000, capped at ₹1,000
        $this->assertSame(900000, $order->payable);
    }

    public function test_a_vendor_coupon_only_discounts_that_vendors_items(): void
    {
        $mine = $this->shop();
        $other = $this->shop();
        $a = $this->product($mine, 5000);
        $b = $this->product($other, 5000);
        $user = User::factory()->create(['role' => 'customer']);
        Coupon::create(['shop_id' => $mine->id, 'code' => 'MINE10', 'type' => 'percent', 'value' => 10]);

        $order = app(CheckoutService::class)->place($user, [
            ['product_id' => $a->id, 'qty' => 1],
            ['product_id' => $b->id, 'qty' => 1],
        ], false, null, 'MINE10');

        $this->assertSame(1000000, $order->subtotal);   // both items
        $this->assertSame(50000, $order->discount);     // 10% of only the ₹5,000 eligible item
    }

    public function test_platform_coupon_applies_across_all_vendors(): void
    {
        $a = $this->product($this->shop(), 5000);
        $b = $this->product($this->shop(), 5000);
        $user = User::factory()->create(['role' => 'customer']);
        Coupon::create(['shop_id' => null, 'code' => 'ALL10', 'type' => 'percent', 'value' => 10]);

        $order = app(CheckoutService::class)->place($user, [
            ['product_id' => $a->id, 'qty' => 1],
            ['product_id' => $b->id, 'qty' => 1],
        ], false, null, 'ALL10');

        $this->assertSame(100000, $order->discount);    // 10% of the whole ₹10,000
    }

    public function test_coupon_rules_are_enforced(): void
    {
        $shop = $this->shop();
        $p = $this->product($shop, 1000);
        $user = User::factory()->create(['role' => 'customer']);
        $svc = app(\App\Services\CouponService::class);
        $lines = [['product_id' => $p->id, 'qty' => 1]];

        Coupon::create(['shop_id' => $shop->id, 'code' => 'BIG', 'type' => 'percent', 'value' => 10, 'min_order' => 500000]);
        Coupon::create(['shop_id' => $shop->id, 'code' => 'DEAD', 'type' => 'percent', 'value' => 10, 'active' => false]);
        Coupon::create(['shop_id' => $shop->id, 'code' => 'GONE', 'type' => 'percent', 'value' => 10, 'ends_at' => now()->subDay()]);

        foreach (['NOPE' => 'does not exist', 'BIG' => 'Spend at least', 'DEAD' => 'no longer active', 'GONE' => 'expired'] as $code => $_) {
            try {
                $svc->apply($code, $user, $lines);
                $this->fail("Coupon {$code} should have been rejected");
            } catch (\App\Exceptions\BusinessException $e) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_a_coupon_cannot_be_reused_beyond_its_per_user_limit(): void
    {
        $shop = $this->shop();
        $p = $this->product($shop, 5000);
        $user = User::factory()->create(['role' => 'customer']);
        Coupon::create(['shop_id' => $shop->id, 'code' => 'ONCE', 'type' => 'fixed', 'value' => 10000, 'per_user_limit' => 1]);

        $first = app(CheckoutService::class)->place($user, [['product_id' => $p->id, 'qty' => 1]], false, null, 'ONCE');
        $this->assertSame(10000, $first->discount);

        // second attempt silently gets no discount rather than blocking the order
        $second = app(CheckoutService::class)->place($user, [['product_id' => $p->id, 'qty' => 1]], false, null, 'ONCE');
        $this->assertSame(0, $second->discount);
    }

    public function test_coupon_then_wallet_and_never_below_zero(): void
    {
        $shop = $this->shop();
        $p = $this->product($shop, 10000);          // ₹10,000 → wallet cap 1% = ₹100
        $user = User::factory()->create(['role' => 'customer']);
        app(\App\Services\WalletService::class)->credit($user, 500000, 'admin_adjust');
        Coupon::create(['shop_id' => $shop->id, 'code' => 'HALF', 'type' => 'percent', 'value' => 50]);

        $order = app(CheckoutService::class)->place($user, [['product_id' => $p->id, 'qty' => 1]], true, null, 'HALF');

        $this->assertSame(1000000, $order->subtotal);
        $this->assertSame(500000, $order->discount);           // 50% off
        $this->assertSame(10000, $order->wallet_applied);      // wallet still capped at 1% of price
        $this->assertSame(1000000 - 500000 - 10000, $order->payable);
        $this->assertGreaterThanOrEqual(0, $order->payable);
    }

    public function test_customer_can_preview_a_coupon_over_the_api(): void
    {
        $shop = $this->shop();
        $p = $this->product($shop, 5000);
        $user = User::factory()->create(['role' => 'customer']);
        Coupon::create(['shop_id' => $shop->id, 'code' => 'PEEK', 'type' => 'fixed', 'value' => 25000]);

        $this->actingAs($user)->postJson('/api/coupons/validate', [
            'code' => 'peek',   // case-insensitive
            'items' => [['product_id' => $p->id, 'qty' => 1]],
        ])->assertOk()->assertJsonPath('discount', 25000)->assertJsonPath('code', 'PEEK');
    }

    public function test_vendors_cannot_touch_another_vendors_coupon(): void
    {
        $mine = $this->shop();
        $other = $this->shop();
        $coupon = Coupon::create(['shop_id' => $other->id, 'code' => 'THEIRS', 'type' => 'percent', 'value' => 10]);

        $this->actingAs($mine->user)->putJson("/api/vendor/coupons/{$coupon->id}", [
            'type' => 'percent', 'value' => 99,
        ])->assertStatus(403);

        $this->assertSame(10, $coupon->fresh()->value);
    }
}
