<?php

namespace App\Http\Controllers\Vendor;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /** Max gallery images per product. */
    private const MAX_IMAGES = 5;

    /** All money fields are in paise. */
    public function index(Request $request)
    {
        return ProductResource::collection(
            $this->shop($request)->products()
                ->with(['category'])
                ->latest('id')->paginate(20)
        );
    }

    public function store(Request $request): ProductResource
    {
        $data = $this->validated($request);
        $shop = $this->shop($request);

        $data['shop_id'] = $shop->id;
        $data['slug'] = $this->uniqueSlug($data['name']);
        $product = Product::create($data);

        // The 1% draw is global and pools open lazily — nothing to set up here.

        return new ProductResource($product->load('category'));
    }

    public function update(Request $request, Product $product): ProductResource
    {
        $this->authorizeOwner($request, $product);
        $product->update($this->validated($request, $product));

        return new ProductResource($product->fresh('category'));
    }

    public function destroy(Request $request, Product $product): array
    {
        $this->authorizeOwner($request, $product);
        foreach ($product->images ?? [] as $url) {
            $this->deleteFile($url);
        }
        $product->delete();

        return ['message' => 'Product deleted.'];
    }

    /** Upload one or more product images (max 5 in total). */
    public function uploadImages(Request $request, Product $product): ProductResource
    {
        $this->authorizeOwner($request, $product);
        $request->validate([
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:4096'], // 4 MB each
        ]);

        $images = $product->images ?? [];
        $room = self::MAX_IMAGES - count($images);
        if ($room < 1) {
            throw new BusinessException('This product already has '.self::MAX_IMAGES.' images. Remove one first.');
        }

        foreach (array_slice($request->file('images'), 0, $room) as $file) {
            $name = Str::uuid().'.'.$file->getClientOriginalExtension();
            Storage::disk('uploads')->putFileAs("products/{$product->id}", $file, $name);
            $images[] = rtrim(config('filesystems.disks.uploads.url'), '/')."/products/{$product->id}/{$name}";
        }

        return new ProductResource($this->syncImages($product, $images));
    }

    /** Remove one image (and its file). */
    public function deleteImage(Request $request, Product $product): ProductResource
    {
        $this->authorizeOwner($request, $product);
        $url = $request->validate(['url' => ['required', 'string']])['url'];

        $images = array_values(array_filter($product->images ?? [], fn ($u) => $u !== $url));
        $this->deleteFile($url);

        return new ProductResource($this->syncImages($product, $images));
    }

    /** Promote an image to primary (first in the gallery / the card thumbnail). */
    public function setPrimaryImage(Request $request, Product $product): ProductResource
    {
        $this->authorizeOwner($request, $product);
        $url = $request->validate(['url' => ['required', 'string']])['url'];

        $images = $product->images ?? [];
        if (! in_array($url, $images, true)) {
            throw new BusinessException('That image does not belong to this product.');
        }
        $images = array_merge([$url], array_values(array_filter($images, fn ($u) => $u !== $url)));

        return new ProductResource($this->syncImages($product, $images));
    }

    /** Keep `image` (thumbnail) pointing at the first gallery image. */
    private function syncImages(Product $product, array $images): Product
    {
        $images = array_values($images);
        $product->update(['images' => $images, 'image' => $images[0] ?? null]);

        return $product->fresh('category');
    }

    /** Delete a previously uploaded file; ignores external URLs (e.g. seeded stock photos). */
    private function deleteFile(string $url): void
    {
        $base = rtrim(config('filesystems.disks.uploads.url'), '/');
        if (! str_starts_with($url, $base.'/')) {
            return;
        }
        Storage::disk('uploads')->delete(ltrim(substr($url, strlen($base)), '/'));
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'brand' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'description' => ['nullable', 'string'],
            'listed_price' => ['required', 'integer', 'min:100'], // ≥ ₹1, in paise
            'stock' => ['required', 'integer', 'min:0'],
            'allow_full_buy' => ['boolean'],
            'specs' => ['nullable', 'array', 'max:20'],
            'specs.*.label' => ['required_with:specs', 'string', 'max:60'],
            'specs.*.value' => ['required_with:specs', 'string', 'max:300'],
            'platform_fee_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['in:active,inactive'],
        ]);
    }

    private function shop(Request $request)
    {
        return $request->user()->shop
            ?? abort(422, 'Your vendor account has no shop yet. Ask the admin to set one up.');
    }

    private function authorizeOwner(Request $request, Product $product): void
    {
        abort_unless($product->shop_id === $request->user()->shop?->id, 403, 'Not your product.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        while (Product::where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
