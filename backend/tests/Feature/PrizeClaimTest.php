<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\Address;
use App\Models\Category;
use App\Models\DrawEntry;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\DrawService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A prize won in kind is only released once the s.194B tax is settled, so the
 * win goes out for dispatch after a claim — never on the draw itself.
 */
class PrizeClaimTest extends TestCase
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

    private function address(User $u): Address
    {
        return $u->addresses()->create([
            'name' => 'A', 'phone' => '9999999999',
            'line1' => 'L1', 'city' => 'C', 'state' => 'S', 'pincode' => '110001',
        ]);
    }

    /** Fill a two-seat pool and hand back the winning entry. */
    private function winner(int $rupees = 2000): DrawEntry
    {
        config(['draw.batch_size' => 2]);
        $draw = app(DrawService::class);
        $draw->enter($this->product($rupees), User::factory()->create(['role' => 'customer']));
        $draw->enter($this->product($rupees), User::factory()->create(['role' => 'customer']));

        return DrawEntry::where('status', 'won')->firstOrFail();
    }

    public function test_tds_is_thirty_percent_of_the_prize_and_the_product_costs_nothing_more(): void
    {
        $win = $this->winner(2000);                 // ₹2,000 prize
        $quote = app(DrawService::class)->claimQuote($win);

        $this->assertSame(200000, $quote['prize_value']);
        $this->assertSame(30, $quote['tds_pct']);
        $this->assertSame(60000, $quote['tds_amount']);   // ₹600
        // The 1% already bought the product — the claim only settles the tax.
        $this->assertSame($quote['tds_amount'], $quote['payable']);
    }

    /** Tax that must be deposited in full may never be rounded down. */
    public function test_tds_rounds_up_to_the_paisa(): void
    {
        config(['draw.tds_pct' => 30]);
        $win = $this->winner(2000);
        $win->update(['product_price' => 333]);      // ₹3.33 → 99.9 paise of tax

        $this->assertSame(100, app(DrawService::class)->claimQuote($win->fresh())['tds_amount']);
    }

    public function test_claiming_attaches_the_address_records_the_tds_and_releases_the_order(): void
    {
        $win = $this->winner(2000);
        $addr = $this->address($win->user);

        $order = app(DrawService::class)->claim($win, $addr->id);

        $this->assertSame($addr->id, $order->address_id);
        $this->assertSame(60000, $order->tds_amount);
        $this->assertSame('paid', $order->status);       // cleared for dispatch
        $this->assertNotNull($win->fresh()->claimed_at);
        // The prize itself is still covered by the 1% — payable never grew.
        $this->assertSame($win->amount, $order->payable);
    }

    public function test_a_prize_can_only_be_claimed_once(): void
    {
        $win = $this->winner();
        $addr = $this->address($win->user);
        app(DrawService::class)->claim($win, $addr->id);

        $this->expectException(BusinessException::class);
        app(DrawService::class)->claim($win->fresh(), $addr->id);
    }

    public function test_a_claim_cannot_ship_to_someone_elses_address(): void
    {
        $win = $this->winner();
        $stranger = $this->address(User::factory()->create(['role' => 'customer']));

        $this->expectException(BusinessException::class);
        app(DrawService::class)->claim($win, $stranger->id);
    }

    public function test_a_losing_entry_cannot_be_claimed(): void
    {
        $this->winner();
        $loser = DrawEntry::where('status', 'lost_pending')->firstOrFail();

        $this->expectException(BusinessException::class);
        app(DrawService::class)->claim($loser, $this->address($loser->user)->id);
    }

    public function test_the_quote_endpoint_shows_the_winner_what_they_owe(): void
    {
        $win = $this->winner(2000);

        $this->actingAs($win->user)->getJson("/api/draw-entries/{$win->id}/claim")
            ->assertOk()
            ->assertJsonPath('quote.tds_amount', 60000)
            ->assertJsonPath('quote.tds_pct', 30)
            ->assertJsonPath('claimed_at', null);
    }

    public function test_a_winner_cannot_read_another_winners_claim(): void
    {
        $win = $this->winner();
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->getJson("/api/draw-entries/{$win->id}/claim")
            ->assertForbidden();
    }

    /** With money owed, the free endpoint must refuse — payment goes via the gateway. */
    public function test_the_free_claim_endpoint_refuses_while_tds_is_payable(): void
    {
        $win = $this->winner();
        $addr = $this->address($win->user);

        $this->actingAs($win->user)
            ->postJson("/api/draw-entries/{$win->id}/claim", ['address_id' => $addr->id])
            ->assertStatus(422);

        $this->assertNull($win->fresh()->claimed_at, 'the prize must not be released unpaid');
    }

    /** With TDS switched off there is nothing to pay, so the claim completes directly. */
    public function test_the_free_claim_endpoint_works_when_tds_is_zero(): void
    {
        config(['draw.tds_pct' => 0]);
        $win = $this->winner();
        $addr = $this->address($win->user);

        $this->actingAs($win->user)
            ->postJson("/api/draw-entries/{$win->id}/claim", ['address_id' => $addr->id])
            ->assertOk();

        $this->assertNotNull($win->fresh()->claimed_at);
    }

    /**
     * The gateway quote is the boundary that takes money, so it has to price the
     * claim itself and reject an address the winner doesn't own — a client that
     * posts a stranger's address must not get a payable prize out of it.
     */
    public function test_the_payment_quote_prices_the_claim_and_rejects_a_foreign_address(): void
    {
        $win = $this->winner(2000);
        $rzp = app(\App\Services\RazorpayService::class);
        $quote = new \ReflectionMethod($rzp, 'quoteClaim');

        $mine = $this->address($win->user);
        [$amount, $payload] = $quote->invoke($rzp, $win->user, ['entry_id' => $win->id, 'address_id' => $mine->id]);
        $this->assertSame(60000, $amount, 'the server, not the client, sets the amount');
        $this->assertSame($mine->id, $payload['address_id']);

        $stranger = $this->address(User::factory()->create(['role' => 'customer']));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $quote->invoke($rzp, $win->user, ['entry_id' => $win->id, 'address_id' => $stranger->id]);
    }

    public function test_wins_can_be_filtered_by_date_range(): void
    {
        $win = $this->winner();
        // created_at isn't fillable — go round the model to backdate it.
        DrawEntry::whereKey($win->id)->update(['created_at' => '2026-05-10 10:00:00']);

        $user = $win->user;
        $this->assertCount(1, $this->actingAs($user)
            ->getJson('/api/my-draws?status=won&from=2026-05-01&to=2026-05-31')->json('data'));
        $this->assertCount(0, $this->actingAs($user)
            ->getJson('/api/my-draws?status=won&from=2026-06-01')->json('data'));
    }
}
