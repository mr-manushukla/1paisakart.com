<?php

namespace App\Http\Resources;

use App\Support\NameMask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public transparency view of a club pool: how full it is and WHO is in it
 * (names masked, plus the product each seat booked). Load `entries.user` +
 * `entries.product` before returning.
 *
 * @mixin \App\Models\DrawBatch
 */
class BatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'club' => $this->whenLoaded('club', fn () => [
                'id' => $this->club?->id,
                'label' => $this->club?->label,
            ]),
            'batch_no' => $this->batch_no,
            'size' => $this->size,
            'filled' => $this->filled_count,
            'remaining' => $this->size - $this->filled_count,
            'status' => $this->status,
            'odds' => '1 in '.$this->size,
            'participants' => $this->whenLoaded('entries', fn () => $this->entries
                ->sortBy('id')
                ->values()
                ->map(fn ($e, $i) => [
                    'seat' => $i + 1,
                    'name' => NameMask::name($e->user?->name),
                    'product' => $e->product?->name,
                    'advance' => $e->amount,   // paise — differs per product in the same club
                    'joined_at' => $e->created_at,
                    'status' => $e->status,
                ])),
            'winner_seat' => $this->when(
                $this->status === 'drawn' && $this->relationLoaded('entries'),
                fn () => $this->entries->sortBy('id')->values()->search(fn ($e) => $e->id === $this->winner_entry_id) + 1,
            ),
        ];
    }
}
