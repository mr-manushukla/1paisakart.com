<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'source' => $this->source,
            'subtotal' => $this->subtotal,
            'wallet_applied' => $this->wallet_applied,
            'payable' => $this->payable,
            'created_at' => $this->created_at,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'product' => $item->product?->name,
                'slug' => $item->product?->slug,
                'qty' => $item->qty,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
            ])),
        ];
    }
}
