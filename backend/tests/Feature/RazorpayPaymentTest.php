<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fulfilment must happen only after Razorpay's signature verifies.
 * Signature checking is a local HMAC, so these run without network.
 */
class RazorpayPaymentTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test_secret_key';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.razorpay.key' => 'rzp_test_dummy', 'services.razorpay.secret' => $this->secret]);
    }

    private function product(int $rupees = 20000): Product
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

    private function pendingPayment(User $user, string $intent, array $payload, int $amount): Payment
    {
        return Payment::create([
            'user_id' => $user->id, 'amount' => $amount,
            'gateway' => 'razorpay', 'status' => 'pending',
            'intent' => $intent, 'payload' => $payload,
            'rzp_order_id' => 'order_'.uniqid(), 'ref' => 'test',
        ]);
    }

    private function sign(string $orderId, string $paymentId): string
    {
        return hash_hmac('sha256', $orderId.'|'.$paymentId, $this->secret);
    }

    public function test_valid_signature_fulfils_a_draw_booking(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $product = $this->product();
        $payment = $this->pendingPayment($user, 'draw', ['product_id' => $product->id], $product->entryPrice());

        $this->actingAs($user)->postJson('/api/payments/verify', [
            'razorpay_order_id' => $payment->rzp_order_id,
            'razorpay_payment_id' => 'pay_ok',
            'razorpay_signature' => $this->sign($payment->rzp_order_id, 'pay_ok'),
        ])->assertOk()->assertJsonPath('seats', 1);

        $this->assertSame('success', $payment->fresh()->status);
        $this->assertSame(1, $user->drawEntries()->count());
    }

    public function test_forged_signature_is_rejected_and_nothing_is_fulfilled(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $product = $this->product();
        $payment = $this->pendingPayment($user, 'draw', ['product_id' => $product->id], $product->entryPrice());

        $this->actingAs($user)->postJson('/api/payments/verify', [
            'razorpay_order_id' => $payment->rzp_order_id,
            'razorpay_payment_id' => 'pay_bad',
            'razorpay_signature' => 'totally-forged',
        ])->assertStatus(422);

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame(0, $user->drawEntries()->count()); // no seats granted
    }

    public function test_replaying_a_verified_payment_does_not_fulfil_twice(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $product = $this->product();
        $payment = $this->pendingPayment($user, 'draw', ['product_id' => $product->id], $product->entryPrice());
        $body = [
            'razorpay_order_id' => $payment->rzp_order_id,
            'razorpay_payment_id' => 'pay_once',
            'razorpay_signature' => $this->sign($payment->rzp_order_id, 'pay_once'),
        ];

        $this->actingAs($user)->postJson('/api/payments/verify', $body)->assertOk();
        $this->actingAs($user)->postJson('/api/payments/verify', $body)->assertOk()->assertJsonPath('already_done', true);

        $this->assertSame(1, $user->drawEntries()->count()); // still 1, not 2
    }

    public function test_you_cannot_verify_someone_elses_payment(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $attacker = User::factory()->create(['role' => 'customer']);
        $product = $this->product();
        $payment = $this->pendingPayment($owner, 'draw', ['product_id' => $product->id], $product->entryPrice());

        $this->actingAs($attacker)->postJson('/api/payments/verify', [
            'razorpay_order_id' => $payment->rzp_order_id,
            'razorpay_payment_id' => 'pay_x',
            'razorpay_signature' => $this->sign($payment->rzp_order_id, 'pay_x'),
        ])->assertStatus(403);

        $this->assertSame(0, $owner->drawEntries()->count());
    }

    public function test_checkout_intent_creates_the_order_after_payment(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $product = $this->product(5000);
        $payment = $this->pendingPayment($user, 'checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 2]], 'apply_wallet' => false,
        ], $product->listed_price * 2);

        $this->actingAs($user)->postJson('/api/payments/verify', [
            'razorpay_order_id' => $payment->rzp_order_id,
            'razorpay_payment_id' => 'pay_co',
            'razorpay_signature' => $this->sign($payment->rzp_order_id, 'pay_co'),
        ])->assertOk()->assertJsonPath('type', 'order');

        $this->assertSame(1, $user->orders()->count());
        $this->assertSame($product->listed_price * 2, $user->orders()->first()->subtotal);
        $this->assertSame($payment->fresh()->order_id, $user->orders()->first()->id);
    }

    public function test_a_mixed_cart_creates_both_the_order_and_the_booking(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $buy = $this->product(5000);
        $book = $this->product(20000);

        $payment = $this->pendingPayment($user, 'checkout', [
            'items' => [['product_id' => $buy->id, 'qty' => 1]],
            'draw_items' => [$book->id],
            'apply_wallet' => false,
        ], $buy->listed_price + $book->entryPrice());

        $this->actingAs($user)->postJson('/api/payments/verify', [
            'razorpay_order_id' => $payment->rzp_order_id,
            'razorpay_payment_id' => 'pay_mix',
            'razorpay_signature' => $this->sign($payment->rzp_order_id, 'pay_mix'),
        ])->assertOk()->assertJsonPath('seats', 1);

        $this->assertSame(1, $user->orders()->count());                       // the purchase
        $this->assertSame(1, $user->drawEntries()->count());                  // the 1% booking
        $this->assertSame($book->id, $user->drawEntries()->first()->product_id);
    }

    public function test_an_unbookable_seat_is_returned_to_the_wallet_not_lost(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $book = $this->product(20000);
        // already holds a seat for this item, so a second one must be refused
        app(\App\Services\DrawService::class)->enter($book, $user);
        $this->assertSame(0, $user->fresh()->wallet_balance);

        $payment = $this->pendingPayment($user, 'checkout', [
            'items' => [], 'draw_items' => [$book->id], 'apply_wallet' => false,
        ], $book->entryPrice());

        $this->actingAs($user)->postJson('/api/payments/verify', [
            'razorpay_order_id' => $payment->rzp_order_id,
            'razorpay_payment_id' => 'pay_dupe',
            'razorpay_signature' => $this->sign($payment->rzp_order_id, 'pay_dupe'),
        ])->assertOk()->assertJsonPath('seats', 0);

        // money was captured, so the advance must come back as wallet credit
        $this->assertSame($book->entryPrice(), $user->fresh()->wallet_balance);
        $this->assertSame(1, $user->drawEntries()->count()); // still just the original seat
    }
}
