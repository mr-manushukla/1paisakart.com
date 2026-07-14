<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Public transparency view of a draw batch: how full the pool is and WHO is in it
 * (display names masked). Load `entries.user` before returning.
 *
 * @mixin \App\Models\DrawBatch
 */
class BatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch_no' => $this->batch_no,
            'size' => $this->size,
            'filled' => $this->filled_count,
            'remaining' => $this->size - $this->filled_count,
            'entry_price' => $this->entry_price,   // paise
            'status' => $this->status,
            'participants' => $this->whenLoaded('entries', fn () => $this->entries
                ->sortBy('id')
                ->values()
                ->map(fn ($e, $i) => [
                    'seat' => $i + 1,
                    'name' => $this->mask($e->user?->name ?? 'Guest'),
                    'joined_at' => $e->created_at,
                    'status' => $e->status,
                ])),
            'winner_seat' => $this->when(
                $this->status === 'drawn' && $this->relationLoaded('entries'),
                fn () => $this->entries->sortBy('id')->values()->search(fn ($e) => $e->id === $this->winner_entry_id) + 1,
            ),
        ];
    }

    /** "Customer 12" -> "Cu***12" — enough to see a real, distinct participant, not their identity. */
    private function mask(string $name): string
    {
        $name = trim($name);
        if (Str::length($name) <= 3) {
            return Str::substr($name, 0, 1).'**';
        }

        return Str::substr($name, 0, 2).'***'.Str::substr($name, -2);
    }
}
