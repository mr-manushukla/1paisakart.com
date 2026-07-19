<?php

namespace App\Http\Resources;

use App\Models\Club;
use App\Models\DrawBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'brand' => $this->brand,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->image,
            'images' => $this->gallery(),
            'specs' => $this->specs ?? [],
            'in_wishlist' => in_array($this->id, $this->wishedIds($request), true),
            'listed_price' => $this->listed_price,            // paise
            'stock' => $this->stock,
            'allow_full_buy' => $this->allow_full_buy,
            'draw_eligible' => $this->isDrawEligible($request), // 1% draw is global, not per-product
            'entry_price' => $this->isDrawEligible($request) ? $this->entryPrice() : null,
            'max_wallet_applicable' => $this->maxWalletApplicable(),
            'rating' => $this->reviews_avg_rating !== null ? round((float) $this->reviews_avg_rating, 1) : null,
            'reviews_count' => $this->reviews_count ?? 0,
            'category' => $this->whenLoaded('category', fn () => [
                'name' => $this->category?->name,
                'slug' => $this->category?->slug,
            ]),
            'shop' => $this->whenLoaded('shop', fn () => [
                'name' => $this->shop?->name,
                'slug' => $this->shop?->slug,
            ]),
            'open_batch' => $this->openPool($request),
        ];
    }

    /**
     * Draw pools are club-scoped, so there's no per-product relation to eager load.
     * Clubs + open pools are each fetched once per request and reused.
     */
    /** The club whose band contains this price — clubs are loaded once per request. */
    private function clubFor(Request $request)
    {
        if (! $request->attributes->has('draw_clubs')) {
            $request->attributes->set('draw_clubs', Club::orderBy('min_price')->get());
            $request->attributes->set('draw_open_batches', DrawBatch::where('status', 'open')->get()->keyBy('club_id'));
        }

        return $request->attributes->get('draw_clubs')
            ->first(fn ($c) => $this->listed_price >= $c->min_price && $this->listed_price <= $c->max_price);
    }

    private function isDrawEligible(Request $request): bool
    {
        return $this->status === 'active' && $this->clubFor($request) !== null;
    }

    private function openPool(Request $request): ?array
    {
        $club = $this->clubFor($request);
        if (! $club || $this->status !== 'active') {
            return null;
        }

        $batch = $request->attributes->get('draw_open_batches')->get($club->id);
        $size = $batch?->size ?? (int) config('draw.batch_size', 100);
        $filled = $batch?->filled_count ?? 0;

        return [
            'club' => ['id' => $club->id, 'label' => $club->label],
            'batch_no' => $batch?->batch_no,
            'size' => $size,
            'filled' => $filled,
            'remaining' => $size - $filled,
        ];
    }

    /** The auth user's wished product ids — fetched once per request, no N+1. */
    private function wishedIds(Request $request): array
    {
        if (! $request->user()) {
            return [];
        }
        if (! $request->attributes->has('wished_ids')) {
            $request->attributes->set('wished_ids', $request->user()->wishlists()->pluck('product_id')->all());
        }

        return $request->attributes->get('wished_ids');
    }
}
