<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\DrawService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WinnerBoardTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $rupees = 2000): Product
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::create(['user_id' => $vendor->id, 'name' => 'S', 'slug' => 's-'.$vendor->id]);
        $cat = Category::firstOrCreate(['slug' => 'c'], ['name' => 'C']);

        return Product::create([
            'shop_id' => $shop->id, 'category_id' => $cat->id,
            'name' => 'P'.uniqid(), 'slug' => 'p-'.uniqid(),
            'listed_price' => $rupees * 100, 'stock' => 20,
            'allow_full_buy' => true, 'status' => 'active',
        ]);
    }

    /** Fill a small pool so it draws, and return the settled batch. */
    private function drawnPool(): void
    {
        config(['draw.batch_size' => 3]);
        $draw = app(DrawService::class);
        $product = $this->product();
        User::factory()->count(3)->create(['role' => 'customer'])->each(fn ($u) => $draw->enter($product, $u));
    }

    public function test_the_public_board_lists_drawn_pools_with_a_highlighted_winner(): void
    {
        $this->drawnPool();

        $res = $this->getJson('/api/winners')->assertOk();
        $pool = $res->json('data.0');

        $this->assertNotNull($pool['winner']);
        $this->assertSame(3, count($pool['participants']));
        $this->assertSame(1, collect($pool['participants'])->where('is_winner', true)->count());
    }

    public function test_the_public_board_masks_names_and_leaks_no_contact_details(): void
    {
        $this->drawnPool();

        $body = $this->getJson('/api/winners')->assertOk()->getContent();
        // Masked names use *** and no raw email/phone/address keys are exposed.
        $this->assertStringContainsString('***', $body);
        $this->assertStringNotContainsString('@', $body);
        $winner = $this->getJson('/api/winners')->json('data.0.winner');
        $this->assertArrayNotHasKey('email', $winner);
        $this->assertArrayNotHasKey('phone', $winner);
        $this->assertArrayNotHasKey('address', $winner);
    }

    public function test_a_product_shows_already_booked_only_for_the_holder(): void
    {
        $product = $this->product(2000);
        $holder = User::factory()->create(['role' => 'customer']);
        $other = User::factory()->create(['role' => 'customer']);
        app(DrawService::class)->enter($product, $holder);

        $this->actingAs($holder)->getJson("/api/products/{$product->slug}")
            ->assertOk()->assertJsonPath('data.already_booked', true);

        $this->actingAs($other)->getJson("/api/products/{$product->slug}")
            ->assertOk()->assertJsonPath('data.already_booked', false);

        $this->getJson("/api/products/{$product->slug}") // guest
            ->assertOk()->assertJsonPath('data.already_booked', false);
    }
}
