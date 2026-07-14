<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Services\CheckoutService;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(private CheckoutService $checkout) {}

    /** 100% buy. Optionally apply wallet (capped at 10% of each item's price). */
    public function store(Request $request): OrderResource
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:99'],
            'apply_wallet' => ['boolean'],
        ]);

        $order = $this->checkout->place(
            $request->user(),
            $data['items'],
            (bool) ($data['apply_wallet'] ?? false),
        );

        return new OrderResource($order->load('items.product'));
    }
}
