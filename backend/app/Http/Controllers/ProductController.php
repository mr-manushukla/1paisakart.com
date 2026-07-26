<?php

namespace App\Http\Controllers;

use App\Http\Resources\BatchResource;
use App\Http\Resources\ProductResource;
use App\Models\Club;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Public catalog. Filters: ?category=slug, ?q=search, ?mode=draw|buy,
     * ?min_price/?max_price (paise) and ?pincode (delivery serviceability).
     */
    public function index(Request $request)
    {
        // The price customers actually pay — the same expression the sort/filter
        // must use, or a discounted item would filter on its struck-through MRP.
        $effective = 'COALESCE(NULLIF(sale_price,0), listed_price)';

        $products = Product::query()
            ->where('status', 'active')
            ->withAvg('reviews', 'rating')->withCount('reviews')
            ->with(['category', 'shop'])
            ->when($request->filled('category'), fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $request->string('category'))))
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            // Draw is global: eligible = active and priced inside a club band.
            ->when($request->input('mode') === 'draw', fn ($q) => $q->whereRaw("$effective BETWEEN ? AND ?", [Club::min('min_price'), Club::max('max_price')]))
            ->when($request->input('mode') === 'buy', fn ($q) => $q->where('allow_full_buy', true))
            ->when($request->filled('min_price'), fn ($q) => $q->whereRaw("$effective >= ?", [(int) $request->input('min_price')]))
            ->when($request->filled('max_price'), fn ($q) => $q->whereRaw("$effective <= ?", [(int) $request->input('max_price')]))
            ->serviceableIn($request->input('pincode'))
            ->latest('id')
            ->paginate(12);

        return ProductResource::collection($products);
    }

    /** Bounds for the price-range filter, in paise. Respects the PIN filter. */
    public function priceRange(Request $request): array
    {
        $effective = 'COALESCE(NULLIF(sale_price,0), listed_price)';

        $row = Product::query()
            ->where('status', 'active')
            ->serviceableIn($request->input('pincode'))
            ->selectRaw("MIN($effective) as min_price, MAX($effective) as max_price")
            ->first();

        return [
            'min' => (int) ($row->min_price ?? 0),
            'max' => (int) ($row->max_price ?? 0),
        ];
    }

    public function show(Product $product): ProductResource
    {
        abort_if($product->status !== 'active', 404);

        $product->load(['category', 'shop'])->loadAvg('reviews', 'rating')->loadCount('reviews');

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
                ->with(['category'])
                ->inRandomOrder()->limit(4)->get()
        );
    }

    /**
     * Transparency: the open pool of this product's price-band club — fill,
     * masked participants, and which product each of them booked.
     */
    public function batch(Product $product): BatchResource|array
    {
        $club = $product->club();
        if (! $club) {
            return ['open' => false];
        }

        $batch = $club->batches()
            ->where('status', 'open')
            ->with(['club', 'entries.user', 'entries.product'])
            ->latest('id')
            ->first();

        if (! $batch) {
            return ['open' => false, 'club' => ['id' => $club->id, 'label' => $club->label]];
        }

        return new BatchResource($batch);
    }
}
