<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\DrawService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The two admin purchase tabs must classify by HOW the customer paid:
 * a 1% advance, or the full product price.
 */
class AdminPurchaseAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $rupees = 2000): Product
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::create(['user_id' => $vendor->id, 'name' => 'S'.uniqid(), 'slug' => 's-'.$vendor->id]);
        $cat = Category::firstOrCreate(['slug' => 'c'], ['name' => 'C']);

        return Product::create([
            'shop_id' => $shop->id, 'category_id' => $cat->id,
            'name' => 'P'.uniqid(), 'slug' => 'p-'.uniqid(),
            'listed_price' => $rupees * 100, 'stock' => 50,
            'allow_full_buy' => true, 'status' => 'active',
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_the_one_percent_tab_lists_every_advance_booking(): void
    {
        $buyer = User::factory()->create(['role' => 'customer', 'name' => 'Booker']);
        $p = $this->product();
        app(DrawService::class)->enter($p, $buyer);

        $res = $this->actingAs($this->admin())->getJson('/api/admin/purchases/draws')->assertOk();

        $res->assertJsonPath('data.0.user.name', 'Booker')
            ->assertJsonPath('data.0.product', $p->name)
            ->assertJsonPath('data.0.advance', $p->entryPrice())
            ->assertJsonPath('data.0.status', 'active');

        $this->assertSame(1, $res->json('summary.bookings'));
        $this->assertSame(1, $res->json('summary.customers'));
        $this->assertSame($p->entryPrice(), $res->json('summary.collected'));
    }

    public function test_the_full_payment_tab_lists_direct_buys(): void
    {
        $buyer = User::factory()->create(['role' => 'customer', 'name' => 'Shopper']);
        $p = $this->product(1000);
        app(CheckoutService::class)->place($buyer, [['product_id' => $p->id, 'qty' => 2]], false);

        $res = $this->actingAs($this->admin())->getJson('/api/admin/purchases/full')->assertOk();

        $res->assertJsonPath('data.0.user.name', 'Shopper')
            ->assertJsonPath('data.0.items.0.qty', 2)
            ->assertJsonPath('data.0.origin', 'direct');

        $this->assertSame(1, $res->json('summary.orders'));
        $this->assertSame(1000 * 100 * 2, $res->json('summary.revenue'));
        $this->assertSame(1, $res->json('summary.direct'));
        $this->assertSame(0, $res->json('summary.from_draw'));
    }

    /**
     * A non-winner who settles the remaining 99% belongs in the full-payment tab,
     * tagged as coming from a draw — not counted as a plain Buy Now.
     */
    public function test_a_ninety_nine_percent_settlement_is_a_full_payment_from_a_draw(): void
    {
        config(['draw.batch_size' => 2]);
        $draw = app(DrawService::class);
        $a = User::factory()->create(['role' => 'customer']);
        $b = User::factory()->create(['role' => 'customer']);
        $p1 = $this->product(2000);
        $p2 = $this->product(2000);
        $draw->enter($p1, $a);
        $draw->enter($p2, $b);   // fills the pool -> draws

        $loser = \App\Models\DrawEntry::where('status', 'lost_pending')->firstOrFail();
        $draw->convertToPurchase($loser);

        $res = $this->actingAs($this->admin())->getJson('/api/admin/purchases/full')->assertOk();

        $this->assertSame(1, $res->json('summary.orders'));
        $this->assertSame(1, $res->json('summary.from_draw'));
        $this->assertSame(0, $res->json('summary.direct'));
        $res->assertJsonPath('data.0.origin', 'draw_99');
    }

    /** The winner only ever paid 1%, so their order must NOT count as full payment. */
    public function test_a_winners_order_is_excluded_from_full_payment(): void
    {
        config(['draw.batch_size' => 2]);
        $draw = app(DrawService::class);
        $p1 = $this->product(2000);
        $p2 = $this->product(2000);
        $draw->enter($p1, User::factory()->create(['role' => 'customer']));
        $draw->enter($p2, User::factory()->create(['role' => 'customer']));

        $this->assertSame(1, \App\Models\Order::where('source', 'draw_win')->count());

        $res = $this->actingAs($this->admin())->getJson('/api/admin/purchases/full')->assertOk();
        $this->assertSame(0, $res->json('summary.orders'), 'a draw win is not a full payment');

        // ...but it is visible on the 1% side, as a won booking.
        $draws = $this->actingAs($this->admin())->getJson('/api/admin/purchases/draws')->assertOk();
        $this->assertSame(1, $draws->json('summary.won'));
    }

    public function test_search_narrows_by_customer_or_product(): void
    {
        $wanted = User::factory()->create(['role' => 'customer', 'name' => 'Findme Singh']);
        $other = User::factory()->create(['role' => 'customer', 'name' => 'Someone Else']);
        $draw = app(DrawService::class);
        $draw->enter($this->product(2000), $wanted);
        $draw->enter($this->product(2100), $other);

        $res = $this->actingAs($this->admin())->getJson('/api/admin/purchases/draws?q=Findme')->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('Findme Singh', $res->json('data.0.user.name'));
    }

    /** A search must narrow the headline numbers too, or they contradict the list. */
    public function test_the_summary_follows_the_search(): void
    {
        $draw = app(DrawService::class);
        $draw->enter($this->product(2000), User::factory()->create(['role' => 'customer', 'name' => 'Findme Singh']));
        $draw->enter($this->product(2100), User::factory()->create(['role' => 'customer', 'name' => 'Someone Else']));

        $res = $this->actingAs($this->admin())->getJson('/api/admin/purchases/draws?q=Findme')->assertOk();

        $this->assertSame(1, $res->json('summary.bookings'), 'summary must match the filtered list');
        $this->assertSame(1, $res->json('summary.customers'));
    }

    /**
     * The q filter ORs user against product. Ungrouped, that OR would escape
     * the source='buy' filter and drag a winner's order into full payment.
     */
    public function test_searching_a_product_never_leaks_a_draw_win_into_full_payment(): void
    {
        config(['draw.batch_size' => 2]);
        $draw = app(DrawService::class);
        $p1 = $this->product(2000);
        $p2 = $this->product(2000);
        $draw->enter($p1, User::factory()->create(['role' => 'customer']));
        $draw->enter($p2, User::factory()->create(['role' => 'customer']));

        $won = \App\Models\DrawEntry::where('status', 'won')->firstOrFail();

        $res = $this->actingAs($this->admin())
            ->getJson('/api/admin/purchases/full?q='.urlencode($won->product->name))->assertOk();

        $this->assertSame(0, $res->json('summary.orders'));
        $this->assertCount(0, $res->json('data'));
    }

    /** Both ends of the range are inclusive whole days, and the totals follow. */
    public function test_the_date_range_filters_rows_and_summary(): void
    {
        $draw = app(DrawService::class);
        foreach (['2026-07-01', '2026-07-15', '2026-08-01'] as $day) {
            $this->travelTo(\Illuminate\Support\Carbon::parse($day.' 10:00:00'));
            $draw->enter($this->product(2000), User::factory()->create(['role' => 'customer']));
        }
        $this->travelBack();

        $admin = $this->actingAs($this->admin());

        // The window's own edge days must be included, not clipped.
        $res = $admin->getJson('/api/admin/purchases/draws?from=2026-07-01&to=2026-07-15')->assertOk();
        $this->assertSame(2, $res->json('summary.bookings'));
        $this->assertCount(2, $res->json('data'));

        $this->assertSame(1, $admin->getJson('/api/admin/purchases/draws?from=2026-08-01')->json('summary.bookings'));
        $this->assertSame(2, $admin->getJson('/api/admin/purchases/draws?to=2026-07-15')->json('summary.bookings'));
        $this->assertSame(3, $admin->getJson('/api/admin/purchases/draws')->json('summary.bookings'));
    }

    public function test_a_junk_date_is_rejected_at_the_boundary(): void
    {
        $this->actingAs($this->admin())
            ->getJson('/api/admin/purchases/draws?from=not-a-date')
            ->assertStatus(422);
    }

    public function test_only_admins_may_read_purchase_analytics(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->getJson('/api/admin/purchases/draws')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'vendor']))
            ->getJson('/api/admin/purchases/full')->assertForbidden();
    }
}
