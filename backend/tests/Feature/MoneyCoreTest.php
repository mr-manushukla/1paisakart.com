<?php

namespace Tests\Feature;

use App\Models\Category;
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

    private function makeProduct(array $attrs = []): Product
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::create(['user_id' => $vendor->id, 'name' => 'Shop', 'slug' => 'shop-'.$vendor->id]);
        $cat = Category::create(['name' => 'Cat', 'slug' => 'cat-'.uniqid()]);

        return Product::create(array_merge([
            'shop_id' => $shop->id,
            'category_id' => $cat->id,
            'name' => 'Phone',
            'slug' => 'phone-'.uniqid(),
            'listed_price' => 100000, // ₹1000
            'stock' => 10,
            'allow_full_buy' => true,
            'allow_draw' => true,
            'status' => 'active',
        ], $attrs));
    }

    public function test_full_batch_draws_one_winner_and_refunds_99(): void
    {
        $product = $this->makeProduct();           // entry = 1% of ₹1000 = ₹10 = 1000 paise
        $draw = app(DrawService::class);

        foreach (User::factory()->count(100)->create(['role' => 'customer']) as $u) {
            $draw->enter($product, $u);
        }

        $batch = $product->batches()->first()->fresh();
        $this->assertSame('drawn', $batch->status);
        $this->assertSame(1, $batch->entries()->where('status', 'won')->count());
        $this->assertSame(99, $batch->entries()->where('status', 'refunded')->count());

        // every loser refunded exactly the entry price, and the ledger invariant holds
        foreach ($batch->entries()->where('status', 'refunded')->get() as $entry) {
            $user = $entry->user->fresh();
            $this->assertSame(1000, $user->wallet_balance);
            $this->assertSame(
                $user->wallet_balance,
                (int) WalletTransaction::where('user_id', $user->id)->sum('amount'),
            );
        }

        // winner keeps 0 wallet and gets one fulfilled draw_win order
        $winner = $batch->winnerEntry->user->fresh();
        $this->assertSame(0, $winner->wallet_balance);
        $this->assertSame(1, $winner->orders()->where('source', 'draw_win')->where('status', 'fulfilled')->count());
    }

    public function test_draw_entry_never_spends_wallet(): void
    {
        $product = $this->makeProduct();
        $user = User::factory()->create(['role' => 'customer']);
        app(WalletService::class)->credit($user, 5000, 'admin_adjust');

        app(DrawService::class)->enter($product, $user);

        $this->assertSame(5000, $user->fresh()->wallet_balance); // untouched by the draw
    }

    public function test_wallet_capped_at_10_percent_on_full_buy(): void
    {
        $product = $this->makeProduct(['allow_draw' => false]); // ₹1000
        $user = User::factory()->create(['role' => 'customer']);
        app(WalletService::class)->credit($user, 100000, 'admin_adjust'); // plenty of balance

        $order = app(CheckoutService::class)->place($user, [['product_id' => $product->id, 'qty' => 1]], true);

        $this->assertSame(10000, $order->wallet_applied);          // exactly 10% of ₹1000
        $this->assertSame(90000, $order->payable);
        $this->assertSame(90000, $user->fresh()->wallet_balance);  // debited by the applied amount only
    }

    public function test_cancel_batch_refunds_all_entries(): void
    {
        $product = $this->makeProduct();
        $draw = app(DrawService::class);
        $users = User::factory()->count(5)->create(['role' => 'customer']);
        foreach ($users as $u) {
            $draw->enter($product, $u);
        }

        $draw->cancel($product->batches()->first());

        foreach ($users as $u) {
            $this->assertSame(1000, $u->fresh()->wallet_balance);
        }
        $this->assertSame('cancelled', $product->batches()->first()->fresh()->status);
    }
}
