<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** P3: the old 99-unit cap is gone — stock is the only limit. */
class CartQuantityTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $stock): Product
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::create(['user_id' => $vendor->id, 'name' => 'S', 'slug' => 's-'.$vendor->id]);
        $cat = Category::firstOrCreate(['slug' => 'c'], ['name' => 'C']);

        return Product::create([
            'shop_id' => $shop->id, 'category_id' => $cat->id,
            'name' => 'P'.uniqid(), 'slug' => 'p-'.uniqid(),
            'listed_price' => 10000, 'stock' => $stock,
            'allow_full_buy' => true, 'status' => 'active',
        ]);
    }

    public function test_a_quantity_above_the_old_99_cap_is_allowed_up_to_stock(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $product = $this->product(stock: 200);

        $order = app(CheckoutService::class)->place($user, [
            ['product_id' => $product->id, 'qty' => 150],
        ], false);

        $this->assertSame(150, $order->items()->first()->qty);
        $this->assertSame(10000 * 150, $order->subtotal);
        $this->assertSame(50, $product->fresh()->stock);
    }

    public function test_ordering_beyond_stock_is_still_refused(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $product = $this->product(stock: 200);

        $this->expectException(BusinessException::class);
        app(CheckoutService::class)->place($user, [
            ['product_id' => $product->id, 'qty' => 250],
        ], false);
    }

    public function test_the_checkout_endpoint_no_longer_rejects_a_large_quantity_at_validation(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $product = $this->product(stock: 500);

        // 150 > old max:99 — must not be a 422 validation error.
        $this->actingAs($user)->postJson('/api/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 150]],
            'apply_wallet' => false,
        ])->assertCreated();
    }
}
