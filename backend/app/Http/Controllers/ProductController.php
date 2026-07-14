<?php

namespace App\Http\Controllers;

use App\Http\Resources\BatchResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /** Public catalog with optional ?category=slug, ?q=search, ?mode=draw|buy. */
    public function index(Request $request)
    {
        $products = Product::query()
            ->where('status', 'active')
            ->withAvg('reviews', 'rating')->withCount('reviews')
            ->with(['category', 'shop', 'batches' => fn ($q) => $q->where('status', 'open')])
            ->when($request->filled('category'), fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $request->string('category'))))
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->when($request->input('mode') === 'draw', fn ($q) => $q->where('allow_draw', true))
            ->when($request->input('mode') === 'buy', fn ($q) => $q->where('allow_full_buy', true))
            ->latest('id')
            ->paginate(12);

        return ProductResource::collection($products);
    }

    public function show(Product $product): ProductResource
    {
        abort_if($product->status !== 'active', 404);

        $product->load(['category', 'shop', 'batches' => fn ($q) => $q->where('status', 'open')])
            ->loadAvg('reviews', 'rating')->loadCount('reviews');

        return new ProductResource($product);
    }

    /** Cross-sell: other active products in the same category. */
    public function related(Product $product)
    {
        return ProductResource::collection(
            Product::where('status', 'active')
                ->where('id', '!=', $product->id)
                ->where('category_id', $product->category_id)
                ->withAvg('reviews', 'rating')->withCount('reviews')
                ->with(['category', 'batches' => fn ($q) => $q->where('status', 'open')])
                ->inRandomOrder()->limit(4)->get()
        );
    }

    /** Transparency: the product's current open batch — fill + masked participants. */
    public function batch(Product $product): BatchResource|array
    {
        $batch = $product->batches()
            ->where('status', 'open')
            ->with(['entries.user'])
            ->latest('id')
            ->first();

        if (! $batch) {
            return ['open' => false];
        }

        return new BatchResource($batch);
    }
}
