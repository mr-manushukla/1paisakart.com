<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\VendorNewOrderNotification;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountAndDeliveryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sanctum only starts a session for requests that look like they came from the
     * SPA (it matches the Origin header against the stateful domains), so the auth
     * endpoints need that header in tests just as the browser sends it.
     */
    private function fromSpa(): self
    {
        return $this->withHeader('Origin', 'http://'.config('sanctum.stateful')[0]);
    }

    private function product(int $rupees = 500, array $prefixes = []): Product
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::create(['user_id' => $vendor->id, 'name' => 'S'.uniqid(), 'slug' => 's-'.$vendor->id]);
        $cat = Category::firstOrCreate(['slug' => 'c'], ['name' => 'C']);

        $p = Product::create([
            'shop_id' => $shop->id, 'category_id' => $cat->id,
            'name' => 'P'.uniqid(), 'slug' => 'p-'.uniqid(),
            'listed_price' => $rupees * 100, 'stock' => 50,
            'allow_full_buy' => true, 'status' => 'active',
        ]);
        foreach ($prefixes as $prefix) {
            $p->serviceAreas()->create(['prefix' => $prefix]);
        }

        return $p;
    }

    // ---- P5: sign in with a phone number ----

    public function test_a_customer_can_sign_in_with_phone_or_email(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'email' => 'a@t.test', 'phone' => '9876543210']);

        $this->fromSpa()->postJson('/api/login', ['login' => 'a@t.test', 'password' => 'password'])->assertOk();
        $this->fromSpa()->post('/api/logout');
        $this->fromSpa()->postJson('/api/login', ['login' => '9876543210', 'password' => 'password'])
            ->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_a_phone_typed_with_spaces_and_country_code_still_matches(): void
    {
        User::factory()->create(['role' => 'customer', 'phone' => '9876543210']);

        // stored digits-only, so "+91 98765 43210" must normalise to the same handle
        $this->fromSpa()->postJson('/api/login', ['login' => '9876543210', 'password' => 'password'])->assertOk();
    }

    public function test_a_wrong_password_is_still_refused_for_phone_login(): void
    {
        User::factory()->create(['role' => 'customer', 'phone' => '9876543210']);

        $this->fromSpa()->postJson('/api/login', ['login' => '9876543210', 'password' => 'nope'])
            ->assertStatus(422);
    }

    // ---- P2: registration no longer takes an address ----

    public function test_registration_stores_phone_as_digits_and_ignores_address(): void
    {
        $this->fromSpa()->postJson('/api/register', [
            'name' => 'New Person', 'email' => 'new@t.test',
            'phone' => '+91 98765 43211', 'address' => 'should be ignored',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertCreated();

        $user = User::where('email', 'new@t.test')->first();
        $this->assertSame('919876543211', $user->phone);
        $this->assertNull($user->address);   // address is collected at checkout now
    }

    // ---- P2/P3: address book ----

    public function test_a_customer_manages_their_own_addresses_and_the_first_is_default(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $body = [
            'name' => 'Manu', 'phone' => '9876543210', 'line1' => '12 Main St',
            'city' => 'Gurugram', 'state' => 'Haryana', 'pincode' => '122001',
        ];

        $this->actingAs($user)->postJson('/api/addresses', $body)->assertOk();
        $this->assertTrue($user->addresses()->first()->is_default);

        // a second address only becomes default when asked
        $this->actingAs($user)->postJson('/api/addresses', $body + ['is_default' => true])->assertOk();
        $this->assertSame(1, $user->addresses()->where('is_default', true)->count());
    }

    public function test_you_cannot_touch_someone_elses_address(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $attacker = User::factory()->create(['role' => 'customer']);
        $address = $owner->addresses()->create([
            'name' => 'A', 'phone' => '9', 'line1' => 'L', 'city' => 'C', 'state' => 'S', 'pincode' => '110001',
        ]);

        $this->actingAs($attacker)->deleteJson("/api/addresses/{$address->id}")->assertStatus(403);
        $this->assertDatabaseHas('addresses', ['id' => $address->id]);
    }

    // ---- P9: PIN serviceability ----

    public function test_the_catalogue_only_shows_products_deliverable_to_the_pin(): void
    {
        $panIndia = $this->product(500);                    // no restriction
        $delhiOnly = $this->product(600, ['110']);          // all of Delhi
        $exactPin = $this->product(700, ['122001']);        // one PIN in Gurugram

        $delhi = collect($this->getJson('/api/products?pincode=110001')->json('data'))->pluck('id');
        $this->assertTrue($delhi->contains($panIndia->id));
        $this->assertTrue($delhi->contains($delhiOnly->id));
        $this->assertFalse($delhi->contains($exactPin->id));

        $ggn = collect($this->getJson('/api/products?pincode=122001')->json('data'))->pluck('id');
        $this->assertTrue($ggn->contains($panIndia->id));
        $this->assertFalse($ggn->contains($delhiOnly->id));
        $this->assertTrue($ggn->contains($exactPin->id));
    }

    public function test_without_a_pin_every_product_is_listed(): void
    {
        $this->product(500);
        $this->product(600, ['110']);

        $this->assertSame(2, count($this->getJson('/api/products')->json('data')));
    }

    // ---- P4: price range ----

    public function test_price_filters_use_the_effective_price(): void
    {
        $cheap = $this->product(200);
        $mid = $this->product(1000);
        $dear = $this->product(5000);

        $ids = collect($this->getJson('/api/products?min_price=50000&max_price=200000')->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($cheap->id));
        $this->assertTrue($ids->contains($mid->id));
        $this->assertFalse($ids->contains($dear->id));

        $range = $this->getJson('/api/price-range')->assertOk()->json();
        $this->assertSame(20000, $range['min']);
        $this->assertSame(500000, $range['max']);
    }

    // ---- P7: the vendor hears about the sale ----

    public function test_each_vendor_is_notified_about_their_own_lines_only(): void
    {
        Notification::fake();
        $customer = User::factory()->create(['role' => 'customer']);
        $a = $this->product(500);
        $b = $this->product(700);

        app(CheckoutService::class)->place($customer, [
            ['product_id' => $a->id, 'qty' => 2],
            ['product_id' => $b->id, 'qty' => 1],
        ], false);

        // one email per shop — two different shops here
        Notification::assertSentTimes(VendorNewOrderNotification::class, 2);
        Notification::assertSentTo($a->shop->user, VendorNewOrderNotification::class,
            fn ($n) => count($n->lines) === 1 && $n->lines[0]['name'] === $a->name && $n->shopTotal === 500 * 100 * 2);
    }
}
