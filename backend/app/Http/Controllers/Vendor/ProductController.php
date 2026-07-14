<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\DrawService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /** All money fields are in paise. */
    public function index(Request $request)
    {
        return ProductResource::collection(
            $this->shop($request)->products()
                ->with(['category', 'batches' => fn ($q) => $q->where('status', 'open')])
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

        if ($product->allow_draw) {
            app(DrawService::class)->openBatch($product);
        }

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
        $product->delete();

        return ['message' => 'Product deleted.'];
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:2048'],
            'listed_price' => ['required', 'integer', 'min:100'], // ≥ ₹1, in paise
            'stock' => ['required', 'integer', 'min:0'],
            'allow_full_buy' => ['boolean'],
            'allow_draw' => ['boolean'],
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
