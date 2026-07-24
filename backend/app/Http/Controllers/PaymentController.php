<?php

namespace App\Http\Controllers;

use App\Services\RazorpayService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private RazorpayService $razorpay) {}

    /** Open a Razorpay order. The amount is computed server-side from the intent. */
    public function createOrder(Request $request): array
    {
        $data = $request->validate([
            'intent' => ['required', 'in:checkout,draw,balance'],
            // checkout — a cart may hold purchases, 1% bookings, or both
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['required_with:items', 'integer', 'exists:products,id'],
            // No artificial cap — stock is the only bound (enforced at fulfilment).
            'items.*.qty' => ['required_with:items', 'integer', 'min:1'],
            'draw_items' => ['nullable', 'array'],
            'draw_items.*' => ['integer', 'exists:products,id'],
            // draw — always a single seat (one seat per product per pool)
            'product_slug' => ['required_if:intent,draw', 'string'],
            // balance
            'entry_id' => ['required_if:intent,balance', 'integer'],
            'apply_wallet' => ['boolean'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
        ]);

        return $this->razorpay->createOrder($request->user(), $data);
    }

    /** Verify Razorpay's signature, then fulfil the paid-for intent. */
    public function verify(Request $request): array
    {
        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        return $this->razorpay->verifyAndFulfil($request->user(), $data);
    }
}
