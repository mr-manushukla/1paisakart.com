<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Club;
use App\Models\DrawEntry;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\CheckoutService;
use App\Services\DrawService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoneyCoreTest extends TestCase
{
    use RefreshDatabase;

    /** @param  int  $rupees  price in rupees */
    private function makeProduct(int $rupees, array $attrs = []): Product
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::create(['user_id' => $vendor->id, 'name' => 'Shop', 'slug' => 'shop-'.$vendor->id]);
        $cat = Category::firstOrCreate(['slug' => 'cat'], ['name' => 'Cat']);

        return Product::create(array_merge([
            'shop_id' => $shop->id,
            'category_id' => $cat->id,
            'name' => 'Item '.uniqid(),
            'slug' => 'item-'.uniqid(),
            'listed_price' => $rupees * 100,
            'stock' => 500,
            'allow_full_buy' => true,
            'status' => 'active',
        ], $attrs));
    }

    public function test_price_bands_put_similar_products_in_the_same_club(): void
    {
        $a = $this->makeProduct(2000);  // ₹2,000
        $b = $this->makeProduct(4000);  // ₹4,000  → same ₹1,001–₹5,000 club
        $c = $this->makeProduct(12000); // ₹12,000 → different club

        $this->assertSame($a->club()->id, $b->club()->id);
        $this->assertNotSame($a->club()->id, $c->club()->id);
    }

    public function test_draw_is_global_but_needs_a_price_inside_a_club_band(): void
    {
        // No per-product opt-in: any active, in-range product qualifies.
        $this->assertTrue($this->makeProduct(2000)->drawEligible());

        // ₹6,00,000 is above the top band (₹5,00,000) → not eligible, booking refused.
        $tooDear = $this->makeProduct(600000);
        $this->assertFalse($tooDear->drawEligible());

        $this->expectException(\App\Exceptions\BusinessException::class);
        app(DrawService::class)->enter($tooDear, User::factory()->create(['role' => 'customer']));
    }

    public function test_club_pool_fills_across_mixed_products_and_draws_one_winner(): void
    {
        $watch = $this->makeProduct(4000);
        $shoes = $this->makeProduct(2000);
        $draw = app(DrawService::class);

        $users = User::factory()->count(100)->create(['role' => 'customer']);
        foreach ($users as $i => $u) {
            $draw->enter($i < 60 ? $watch : $shoes, $u);
        }

        $batch = $watch->club()->batches()->first()->fresh();
        $this->assertSame('drawn', $batch->status);
        $this->assertSame(100, $batch->filled_count);

        // exactly one winner; everyone else is awaiting their choice (NOT auto-refunded)
        $this->assertSame(1, $batch->entries()->where('status', 'won')->count());
        $this->assertSame(99, $batch->entries()->where('status', 'lost_pending')->count());
        $this->assertSame(0, $batch->entries()->where('status', 'credited')->count());

        // the pool really did mix two different products
        $this->assertSame(2, $batch->entries()->distinct()->count('product_id'));

        // no money has moved to anyone's wallet yet
        $this->assertSame(0, (int) User::whereIn('id', $users->pluck('id'))->sum('wallet_balance'));

        // winner keeps the product THEY booked; platform covers the balance
        $winner = $batch->winnerEntry;
        $order = $winner->order;
        $this->assertSame('draw_win', $order->source);
        $this->assertSame('fulfilled', $order->status);
        $this->assertSame($winner->product_id, $order->items->first()->product_id);
        $this->assertSame($winner->amount, $order->payable);                 // only the 1% was paid
        $this->assertSame($winner->product->listed_price, $order->subtotal);
        // platform subsidy = subtotal - wallet_applied - payable
        $this->assertSame($winner->product->listed_price - $winner->amount, $order->subtotal - $order->wallet_applied - $order->payable);

        // non-winners got a 7-day window
        $pending = $batch->entries()->where('status', 'lost_pending')->first();
        $this->assertNotNull($pending->choice_deadline_at);
        $this->assertTrue($pending->choice_deadline_at->isFuture());
    }

    public function test_option_a_non_winner_pays_remaining_99_percent(): void
    {
        $entry = $this->losingEntry();
        $product = $entry->product;

        $order = app(DrawService::class)->convertToPurchase($entry);

        $this->assertSame('converted', $entry->fresh()->status);
        $this->assertSame($product->listed_price, $order->subtotal);
        // they already paid the 1% advance, so only the balance is charged
        $this->assertSame($product->listed_price - $entry->amount, $order->payable);
        $this->assertSame($product->id, $order->items->first()->product_id);
    }

    public function test_option_b_non_winner_moves_advance_to_wallet(): void
    {
        $entry = $this->losingEntry();

        $balance = app(DrawService::class)->creditToWallet($entry);

        $this->assertSame('credited', $entry->fresh()->status);
        $this->assertSame($entry->amount, $balance);
        $this->assertSame($entry->amount, $entry->user->fresh()->wallet_balance);
        // ledger stays consistent with the cached balance
        $this->assertSame(
            $entry->user->fresh()->wallet_balance,
            (int) WalletTransaction::where('user_id', $entry->user_id)->sum('amount'),
        );
    }

    public function test_advance_auto_credits_to_wallet_after_the_choice_window(): void
    {
        $entry = $this->losingEntry();
        $entry->update(['choice_deadline_at' => now()->subDay()]); // window elapsed

        $moved = app(DrawService::class)->autoCreditExpired();

        $this->assertSame(1, $moved);
        $this->assertSame('credited', $entry->fresh()->status);
        $this->assertSame($entry->amount, $entry->user->fresh()->wallet_balance);
    }

    public function test_expired_entry_can_no_longer_take_option_a(): void
    {
        $entry = $this->losingEntry();
        $entry->update(['choice_deadline_at' => now()->subDay()]);

        $this->expectException(\App\Exceptions\BusinessException::class);
        app(DrawService::class)->convertToPurchase($entry);
    }

    public function test_wallet_is_capped_at_one_percent_on_a_full_buy(): void
    {
        $product = $this->makeProduct(2000); // ₹2,000 = 200000 paise
        $user = User::factory()->create(['role' => 'customer']);
        app(WalletService::class)->credit($user, 500000, 'admin_adjust'); // plenty

        $order = app(CheckoutService::class)->place($user, [['product_id' => $product->id, 'qty' => 1]], true);

        $this->assertSame(2000, $order->wallet_applied);            // 1% of ₹2,000 = ₹20
        $this->assertSame(200000 - 2000, $order->payable);
        $this->assertSame(500000 - 2000, $user->fresh()->wallet_balance);
    }

    public function test_booking_advance_never_spends_wallet(): void
    {
        $product = $this->makeProduct(2000);
        $user = User::factory()->create(['role' => 'customer']);
        app(WalletService::class)->credit($user, 50000, 'admin_adjust');

        app(DrawService::class)->enter($product, $user);

        $this->assertSame(50000, $user->fresh()->wallet_balance); // untouched — advance is real money
    }

    public function test_cancelled_pool_refunds_every_advance(): void
    {
        $product = $this->makeProduct(2000);
        $draw = app(DrawService::class);
        $users = User::factory()->count(5)->create(['role' => 'customer']);
        foreach ($users as $u) {
            $draw->enter($product, $u);
        }
        $batch = $product->club()->batches()->first();

        $draw->cancel($batch);

        $this->assertSame('cancelled', $batch->fresh()->status);
        foreach ($users as $u) {
            $this->assertSame($product->entryPrice(), $u->fresh()->wallet_balance);
        }
    }

    /** Fill a pool and return one entry that did NOT win. */
    private function losingEntry(): DrawEntry
    {
        $product = $this->makeProduct(2000);
        $draw = app(DrawService::class);
        foreach (User::factory()->count(100)->create(['role' => 'customer']) as $u) {
            $draw->enter($product, $u);
        }

        return $product->club()->batches()->first()
            ->entries()->where('status', 'lost_pending')->with(['product', 'user'])->first();
    }
}
