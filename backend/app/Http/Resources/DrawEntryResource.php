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
            // A win isn't dispatched until it's claimed — address given, TDS settled.
            'awaiting_claim' => $this->awaitingClaim(),
            'claimed_at' => $this->claimed_at,
            // True when this customer won this pool with a DIFFERENT seat. Their
            // other seats still need a decision, but telling them "you didn't win"
            // would be plainly wrong.
            'won_other_in_pool' => $this->whenLoaded('batch', fn () => $this->batch?->winnerEntry !== null
                && $this->batch->winnerEntry->user_id === $this->user_id
                && $this->batch->winner_entry_id !== $this->id),
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
