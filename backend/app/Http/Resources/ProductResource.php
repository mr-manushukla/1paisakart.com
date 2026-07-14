<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Present only when the caller eager-loaded open batches (avoids N+1 in lists).
        $open = $this->relationLoaded('batches') ? $this->batches->firstWhere('status', 'open') : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->image,
            'listed_price' => $this->listed_price,            // paise
            'stock' => $this->stock,
            'allow_full_buy' => $this->allow_full_buy,
            'allow_draw' => $this->allow_draw,
            'entry_price' => $this->allow_draw ? $this->entryPrice() : null,
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
            'open_batch' => $open ? [
                'id' => $open->id,
                'batch_no' => $open->batch_no,
                'size' => $open->size,
                'filled' => $open->filled_count,
                'remaining' => $open->size - $open->filled_count,
            ] : null,
        ];
    }
}
