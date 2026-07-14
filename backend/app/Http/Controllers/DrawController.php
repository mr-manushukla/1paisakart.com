<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\DrawService;
use Illuminate\Http\Request;

class DrawController extends Controller
{
    public function __construct(private DrawService $draw) {}

    /** Customer pays 1% to join the product's current open batch. */
    public function enter(Request $request, Product $product): array
    {
        $entry = $this->draw->enter($product, $request->user());

        return [
            'entry' => [
                'id' => $entry->id,
                'status' => $entry->status,   // active | won (if this entry filled & won the batch)
                'amount' => $entry->amount,
            ],
            'message' => $entry->status === 'won'
                ? 'The batch filled and you won! 🎉'
                : 'You are in the draw. Watch the pool fill up.',
        ];
    }
}
