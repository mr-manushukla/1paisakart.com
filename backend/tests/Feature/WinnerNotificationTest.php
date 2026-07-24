<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\WinnerWonNotification;
use App\Services\DrawService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WinnerNotificationTest extends TestCase
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

    public function test_the_winner_is_notified_when_the_pool_settles(): void
    {
        Notification::fake();
        config(['draw.batch_size' => 3]); // tiny pool so it settles fast

        $draw = app(DrawService::class);
        $product = $this->product();
        $users = User::factory()->count(3)->create(['role' => 'customer']);
        foreach ($users as $u) {
            $draw->enter($product, $u);
        }

        // Exactly one of the three is the winner and gets the congratulations.
        Notification::assertSentTimes(WinnerWonNotification::class, 1);
    }

    public function test_the_email_carries_the_congratulations_and_product(): void
    {
        $product = $this->product();
        $user = User::factory()->create(['role' => 'customer']);
        $entry = app(DrawService::class)->enter($product, $user); // pool of 1 won't settle at size 100

        $mail = (new WinnerWonNotification($entry))->toMail($user);
        $data = $mail->toArray();

        $this->assertStringContainsString('Congratulations', $data['subject']);
        $this->assertTrue(collect($data['introLines'])->contains(fn ($l) => str_contains($l, $product->name)));
    }
}
