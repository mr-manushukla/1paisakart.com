<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\DrawService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoolParticipationLookupTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $rupees): Product
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::create(['user_id' => $vendor->id, 'name' => 'S', 'slug' => 's-'.$vendor->id]);
        $cat = Category::firstOrCreate(['slug' => 'c'], ['name' => 'C']);

        return Product::create([
            'shop_id' => $shop->id, 'category_id' => $cat->id,
            'name' => 'P'.uniqid(), 'slug' => 'p-'.uniqid(),
            'listed_price' => $rupees * 100, 'stock' => 50,
            'allow_full_buy' => true, 'status' => 'active',
        ]);
    }

    /** A customer who booked two different products in two different clubs. */
    private function participatingCustomer(): User
    {
        $customer = User::factory()->create(['role' => 'customer', 'name' => 'Asha Verma', 'email' => 'asha@example.test']);
        $draw = app(DrawService::class);
        $draw->enter($this->product(2000), $customer);   // ₹1,001–₹5,000 club
        $draw->enter($this->product(12000), $customer);  // ₹10,001–₹15,000 club

        return $customer;
    }

    public function test_customer_sees_their_own_participation_summary(): void
    {
        $customer = $this->participatingCustomer();

        $res = $this->actingAs($customer)->getJson('/api/my-draws')->assertOk();

        $res->assertJsonPath('summary.pools_joined', 2)
            ->assertJsonPath('summary.products', 2)
            ->assertJsonPath('summary.active', 2)
            ->assertJsonPath('summary.total_advanced', 2000 + 12000); // 1% of each
        $this->assertCount(2, $res->json('data'));
        // pool identity is present so the customer knows which club they're in
        $this->assertNotEmpty($res->json('data.0.pool.club'));
    }

    public function test_admin_can_look_up_any_customers_pools(): void
    {
        $customer = $this->participatingCustomer();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->getJson('/api/admin/customers?q=asha')
            ->assertOk()
            ->assertJsonPath('data.0.email', 'asha@example.test')
            ->assertJsonPath('data.0.bookings', 2);

        $this->actingAs($admin)->getJson("/api/admin/customers/{$customer->id}/draws")
            ->assertOk()
            ->assertJsonPath('customer.name', 'Asha Verma')
            ->assertJsonPath('summary.pools_joined', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_customers_and_vendors_cannot_use_the_admin_lookup(): void
    {
        $customer = $this->participatingCustomer();
        $other = User::factory()->create(['role' => 'customer']);
        $vendor = User::factory()->create(['role' => 'vendor']);

        // another customer must not be able to inspect someone else's participation
        $this->actingAs($other)->getJson("/api/admin/customers/{$customer->id}/draws")->assertStatus(403);
        $this->actingAs($vendor)->getJson("/api/admin/customers/{$customer->id}/draws")->assertStatus(403);
        $this->actingAs($other)->getJson('/api/admin/customers')->assertStatus(403);
    }

    public function test_my_draws_only_ever_returns_your_own_bookings(): void
    {
        $this->participatingCustomer();
        $other = User::factory()->create(['role' => 'customer']);

        $this->actingAs($other)->getJson('/api/my-draws')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('summary.pools_joined', 0);
    }
}
