<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DrawEntry */
class DrawEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,          // active|won|lost_pending|converted|credited|refunded
            'advance' => $this->amount,         // the 1% paid, paise
            'balance_due' => $this->product ? $this->balanceDue() : null,
            'choice_deadline_at' => $this->choice_deadline_at,
            'awaiting_choice' => $this->awaitingChoice(),
            'order_id' => $this->order_id,
            'created_at' => $this->created_at,
            'product' => $this->whenLoaded('product', fn () => [
                'name' => $this->product?->name,
                'slug' => $this->product?->slug,
                'image' => $this->product?->image,
                'listed_price' => $this->productPrice(),
                'max_wallet_applicable' => $this->product?->maxWalletApplicable(),
            ]),
            'pool' => $this->whenLoaded('batch', fn () => [
                'batch_no' => $this->batch?->batch_no,
                'size' => $this->batch?->size,
                'filled' => $this->batch?->filled_count,
                'status' => $this->batch?->status,
                'club' => $this->batch?->club?->label,
            ]),
        ];
    }
}
