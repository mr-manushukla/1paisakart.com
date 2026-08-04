<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\DrawService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A customer may hold several seats in one club pool, but each seat must be a
 * DIFFERENT product in that price band — the same item can't be booked twice.
 * A winner still receives only ONE item.
 */
class MultiSeatDrawTest extends TestCase
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
            'listed_price' => $rupees * 100, 'stock' => 200,
            'allow_full_buy' => true, 'status' => 'active',
        ]);
    }

    public function test_a_customer_can_book_different_items_in_one_pool(): void
    {
        $fridge = $this->product(20000);   // both land in the same ₹20,000 club
        $sheets = $this->product(19000);
        $user = User::factory()->create(['role' => 'customer']);
        $draw = app(DrawService::class);

        $draw->enter($fridge, $user);
        $draw->enter($sheets, $user);

        $batch = $fridge->club()->batches()->first();
        $this->assertSame(2, $batch->filled_count);
        $this->assertSame(2, $batch->entries()->where('user_id', $user->id)->count());
        $this->assertSame(2, $batch->entries()->distinct()->count('product_id'));
        // each seat charged 1% of its own product
        $this->assertSame($fridge->entryPrice() + $sheets->entryPrice(), (int) $batch->entries()->sum('amount'));
    }

    public function test_the_same_item_cannot_be_booked_twice_in_a_pool(): void
    {
        $fridge = $this->product(20000);
        $user = User::factory()->create(['role' => 'customer']);
        $draw = app(DrawService::class);

        $draw->enter($fridge, $user);

        $this->expectException(\App\Exceptions\BusinessException::class);
        $draw->enter($fridge, $user);   // repeat of the same item — refused
    }

    public function test_repeat_of_same_item_is_also_blocked_at_the_database(): void
    {
        $fridge = $this->product(20000);
        $user = User::factory()->create(['role' => 'customer']);
        $entry = app(DrawService::class)->enter($fridge, $user);

        // Bypass the service entirely: the unique index must still refuse it.
        $this->expectException(\Illuminate\Database\QueryException::class);
        \App\Models\DrawEntry::create([
            'batch_id' => $entry->batch_id,
            'user_id' => $user->id,
            'product_id' => $fridge->id,
            'amount' => $fridge->entryPrice(),
            'status' => 'active',
        ]);
    }

    public function test_a_different_customer_may_book_the_same_item(): void
    {
        $fridge = $this->product(20000);
        $draw = app(DrawService::class);
        $a = User::factory()->create(['role' => 'customer']);
        $b = User::factory()->create(['role' => 'customer']);

        $draw->enter($fridge, $a);
        $draw->enter($fridge, $b);   // fine — different people

        $this->assertSame(2, $fridge->club()->batches()->first()->filled_count);
    }

    public function test_winner_keeps_one_item_and_their_other_seats_refund_to_wallet(): void
    {
        $draw = app(DrawService::class);
        // one customer holds 5 seats — five DIFFERENT products in the same band
        $whale = User::factory()->create(['role' => 'customer']);
        $whaleProducts = collect(range(0, 4))->map(fn ($i) => $this->product(20000 - $i * 100));
        $whaleProducts->each(fn ($p) => $draw->enter($p, $whale));

        // 95 other customers fill the pool
        $filler = $this->product(20000);
        foreach (User::factory()->count(95)->create(['role' => 'customer']) as $u) {
            $draw->enter($filler, $u);
        }

        $batch = $filler->club()->batches()->first()->fresh();
        $this->assertSame('drawn', $batch->status);
        $this->assertSame(1, $batch->entries()->where('status', 'won')->count());

        $whaleEntries = $batch->entries()->where('user_id', $whale->id)->get();
        $this->assertCount(5, $whaleEntries);

        if ($whaleEntries->firstWhere('status', 'won')) {
            // Won with one seat. The other 4 are NOT auto-refunded — they get the
            // same 7-day choice as anyone else, so the buyer can still complete
            // those purchases if they want to.
            $this->assertSame(4, $whaleEntries->where('status', 'lost_pending')->count());
            $this->assertSame(0, $whaleEntries->where('status', 'credited')->count());
            $this->assertSame(1, $whale->orders()->where('source', 'draw_win')->count()); // exactly ONE item
            $this->assertSame(0, $whale->fresh()->wallet_balance);                        // nothing force-credited
        } else {
            // didn't win -> all 5 seats get the normal 7-day choice
            $this->assertSame(5, $whaleEntries->where('status', 'lost_pending')->count());
            $this->assertSame(0, $whale->fresh()->wallet_balance);
        }

        // Either way, every seat that isn't the winning one is awaiting a choice.
        $this->assertSame(0, $whaleEntries->where('status', 'credited')->count());
    }

    /**
     * A winner's other seats keep their choice instead of being force-refunded:
     * exactly one item is won, and every remaining seat is left awaiting a
     * decision (buy the rest at 99%, or move the advance to the wallet).
     */
    public function test_a_winners_other_seats_await_a_choice_rather_than_auto_refunding(): void
    {
        config(['draw.batch_size' => 3]);
        $draw = app(DrawService::class);

        // One buyer takes every seat, so they are guaranteed to be the winner.
        $buyer = User::factory()->create(['role' => 'customer']);
        collect(range(0, 2))->each(fn ($i) => $draw->enter($this->product(20000 - $i * 100), $buyer));

        $entries = $buyer->drawEntries()->get();
        $this->assertSame(1, $entries->where('status', 'won')->count(), 'exactly one item is won');
        $this->assertSame(2, $entries->where('status', 'lost_pending')->count(), 'the rest await a choice');
        $this->assertSame(0, $entries->where('status', 'credited')->count(), 'nothing auto-refunded');
        $this->assertSame(0, $buyer->fresh()->wallet_balance);

        // Those seats carry a deadline and are actionable, so the buyer can still
        // complete the purchase — the whole point of the change.
        foreach ($entries->where('status', 'lost_pending') as $e) {
            $this->assertNotNull($e->choice_deadline_at);
            $this->assertTrue($e->awaitingChoice());
            $this->assertGreaterThan(0, $e->balanceDue());
        }
    }

    /** P7: uncapped by default — only pool availability limits a customer. */
    public function test_by_default_a_customer_may_hold_any_number_of_seats(): void
    {
        config(['draw.max_entries_per_user' => 0]);
        $draw = app(DrawService::class);
        $user = User::factory()->create(['role' => 'customer']);

        // 25 distinct items in the same band — far past the old ceiling of 10
        foreach (range(0, 24) as $i) {
            $draw->enter($this->product(20000 - $i * 100), $user);
        }

        $this->assertSame(25, $user->drawEntries()->count());
    }

    public function test_per_user_seat_cap_is_enforced_when_configured(): void
    {
        config(['draw.max_entries_per_user' => 3]);
        $draw = app(DrawService::class);
        $user = User::factory()->create(['role' => 'customer']);
        collect(range(0, 2))->each(fn ($i) => $draw->enter($this->product(20000 - $i * 100), $user));

        // a 4th distinct item must still be refused by the cap
        $this->expectException(\App\Exceptions\BusinessException::class);
        $draw->enter($this->product(19000), $user);
    }

    public function test_last_seat_settles_the_pool(): void
    {
        $product = $this->product(20000);
        $draw = app(DrawService::class);
        foreach (User::factory()->count(100)->create(['role' => 'customer']) as $u) {
            $draw->enter($product, $u);
        }

        $batch = $product->club()->batches()->first()->fresh();
        $this->assertSame('drawn', $batch->status);
        $this->assertSame(100, $batch->filled_count);
        $this->assertSame(1, $batch->entries()->where('status', 'won')->count());
    }
}
