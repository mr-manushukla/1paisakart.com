<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VendorProductImageTest extends TestCase
{
    use RefreshDatabase;

    private function vendorWithProduct(): array
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::create(['user_id' => $vendor->id, 'name' => 'Shop', 'slug' => 'shop-'.$vendor->id]);
        $product = Product::create([
            'shop_id' => $shop->id, 'name' => 'Thing', 'slug' => 'thing-'.uniqid(),
            'listed_price' => 200000, 'stock' => 5, 'allow_full_buy' => true, 'status' => 'active',
        ]);

        return [$vendor, $product];
    }

    public function test_vendor_uploads_images_and_first_becomes_the_thumbnail(): void
    {
        Storage::fake('uploads');
        [$vendor, $product] = $this->vendorWithProduct();

        $this->actingAs($vendor)
            ->postJson("/api/vendor/products/{$product->id}/images", [
                'images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png')],
            ])->assertOk();

        $product->refresh();
        $this->assertCount(2, $product->images);
        $this->assertSame($product->images[0], $product->image); // thumbnail synced
        $this->assertCount(2, Storage::disk('uploads')->allFiles("products/{$product->id}"));
    }

    public function test_gallery_is_capped_at_five_images(): void
    {
        Storage::fake('uploads');
        [$vendor, $product] = $this->vendorWithProduct();

        // 6 at once → only 5 stored
        $this->actingAs($vendor)
            ->postJson("/api/vendor/products/{$product->id}/images", [
                'images' => array_map(fn ($i) => UploadedFile::fake()->image("$i.jpg"), range(1, 6)),
            ])->assertOk();
        $this->assertCount(5, $product->refresh()->images);

        // another upload is refused outright
        $this->actingAs($vendor)
            ->postJson("/api/vendor/products/{$product->id}/images", ['images' => [UploadedFile::fake()->image('x.jpg')]])
            ->assertStatus(422);
    }

    public function test_set_primary_and_delete(): void
    {
        Storage::fake('uploads');
        [$vendor, $product] = $this->vendorWithProduct();
        $this->actingAs($vendor)->postJson("/api/vendor/products/{$product->id}/images", [
            'images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ]);
        $second = $product->refresh()->images[1];

        $this->actingAs($vendor)
            ->postJson("/api/vendor/products/{$product->id}/images/primary", ['url' => $second])->assertOk();
        $this->assertSame($second, $product->refresh()->images[0]);
        $this->assertSame($second, $product->image);

        $this->actingAs($vendor)
            ->deleteJson("/api/vendor/products/{$product->id}/images", ['url' => $second])->assertOk();
        $product->refresh();
        $this->assertCount(1, $product->images);
        $this->assertNotContains($second, $product->images);
    }

    public function test_another_vendor_cannot_touch_the_images(): void
    {
        Storage::fake('uploads');
        [, $product] = $this->vendorWithProduct();
        $other = User::factory()->create(['role' => 'vendor']);
        Shop::create(['user_id' => $other->id, 'name' => 'Other', 'slug' => 'other-'.$other->id]);

        $this->actingAs($other)
            ->postJson("/api/vendor/products/{$product->id}/images", ['images' => [UploadedFile::fake()->image('a.jpg')]])
            ->assertStatus(403);
    }
}
