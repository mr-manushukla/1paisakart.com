<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'wallet_balance' => $this->wallet_balance, // paise
            'shop' => $this->whenLoaded('shop', fn () => [
                'name' => $this->shop?->name,
                'slug' => $this->shop?->slug,
            ]),
        ];
    }
}
