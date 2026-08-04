<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\DrawEntry;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Razorpay\Api\Api;

/**
 * Razorpay checkout, in two steps:
 *
 *   1. createOrder()  — the SERVER computes the amount from the intent (never the
 *                       client), opens a Razorpay order and stores a pending Payment.
 *   2. verifyAndFulfil() — verifies the HMAC signature, then executes the intent
 *                       exactly once (place order / book seats / pay the 99% balance).
 *
 * Nothing is fulfilled until Razorpay's signature checks out.
 */
class RazorpayService
{
    public function __construct(
        private CheckoutService $checkout,
        private DrawService $draw,
        private WalletService $wallet,
        private CouponService $coupons,
    ) {}

    public function configured(): bool
    {
        return filled(config('services.razorpay.key')) && filled(config('services.razorpay.secret'));
    }

    private function api(): Api
    {
        if (! $this->configured()) {
            throw new BusinessException('Payments are not configured.');
        }

        return new Api(config('services.razorpay.key'), config('services.razorpay.secret'));
    }

    /**
     * Open a payment for an intent. Returns what the browser checkout needs.
     *
     * @param  array{intent:string, items?:array, apply_wallet?:bool, product_slug?:string, qty?:int, entry_id?:int}  $input
     */
    public function createOrder(User $user, array $input): array
    {
        [$amount, $payload, $description] = $this->quote($user, $input);

        if ($amount < 100) { // Razorpay's minimum is ₹1
            throw new BusinessException('Amount is below the minimum payable of ₹1.');
        }

        $rzpOrder = $this->api()->order->create([
            'amount' => $amount,          // paise — same unit we store
            'currency' => 'INR',
            'receipt' => 'rcpt_'.$user->id.'_'.now()->timestamp,
            'notes' => ['intent' => $input['intent'], 'user_id' => (string) $user->id],
        ]);

        $payment = Payment::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'gateway' => 'razorpay',
            'status' => 'pending',
            'intent' => $input['intent'],
            'payload' => $payload,
            'rzp_order_id' => $rzpOrder['id'],
            'ref' => $rzpOrder['id'],
        ]);

        return [
            'payment_id' => $payment->id,
            'razorpay_order_id' => $rzpOrder['id'],
            'amount' => $amount,
            'currency' => 'INR',
            'key' => config('services.razorpay.key'),   // publishable key only
            'description' => $description,
            'prefill' => ['name' => $user->name, 'email' => $user->email],
        ];
    }

    /** Verify the signature, then fulfil the intent. Safe to call twice. */
    public function verifyAndFulfil(User $user, array $data): array
    {
        $payment = Payment::where('rzp_order_id', $data['razorpay_order_id'])->firstOrFail();
        abort_unless($payment->user_id === $user->id, 403, 'Not your payment.');

        if ($payment->status === 'success') {
            return ['already_done' => true, 'result' => $payment->only(['order_id', 'entry_id'])];
        }

        try {
            $this->api()->utility->verifyPaymentSignature([
                'razorpay_order_id' => $data['razorpay_order_id'],
                'razorpay_payment_id' => $data['razorpay_payment_id'],
                'razorpay_signature' => $data['razorpay_signature'],
            ]);
        } catch (\Throwable $e) {
            $payment->update(['status' => 'failed', 'rzp_payment_id' => $data['razorpay_payment_id'] ?? null]);
            throw new BusinessException('Payment could not be verified.');
        }

        return DB::transaction(function () use ($payment, $user, $data) {
            $payment->update([
                'status' => 'success',
                'rzp_payment_id' => $data['razorpay_payment_id'],
            ]);

            return $this->fulfil($payment, $user);
        });
    }

    /** Work out the authoritative amount (paise) for an intent. */
    private function quote(User $user, array $input): array
    {
        return match ($input['intent']) {
            'checkout' => $this->quoteCheckout($user, $input),
            'draw' => $this->quoteDraw($user, $input),
            'balance' => $this->quoteBalance($user, $input),
            default => throw new BusinessException('Unknown payment type.'),
        };
    }

    /**
     * A cart may mix normal purchases with 1% advance bookings. Wallet credit can
     * only reduce the purchase part — it may never pay a booking advance.
     */
    private function quoteCheckout(User $user, array $input): array
    {
        $lines = collect($input['items'] ?? [])
            ->map(fn ($i) => ['product_id' => (int) $i['product_id'], 'qty' => max(1, (int) $i['qty'])])
            ->values()->all();
        $drawIds = collect($input['draw_items'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();
        // Non-winners settling the remaining 99% on several bookings at once.
        $balanceIds = collect($input['balance_items'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();

        if (! $lines && ! $drawIds && ! $balanceIds) {
            throw new BusinessException('Cart is empty.');
        }

        $subtotal = 0;
        $cap = 0;
        foreach ($lines as $line) {
            $p = Product::findOrFail($line['product_id']);
            if (! $p->allow_full_buy || $p->status !== 'active') {
                throw new BusinessException("“{$p->name}” is not available to buy.");
            }
            if ($p->stock < $line['qty']) {
                throw new BusinessException("“{$p->name}” is out of stock.");
            }
            $subtotal += $p->effectivePrice() * $line['qty'];
            $cap += $p->maxWalletApplicable() * $line['qty'];
        }

        // Never charge for a seat the customer already holds — enter() would reject
        // it on fulfilment and the advance would only bounce to the restricted wallet
        // (the "paid but couldn't book" double-charge). Drop those before pricing.
        $bookable = [];
        $advances = 0;
        foreach ($drawIds as $id) {
            $p = Product::findOrFail($id);
            if (! $p->drawEligible()) {
                throw new BusinessException("“{$p->name}” is not available for the draw.");
            }
            if ($this->draw->heldSeat($user, $p)) {
                continue; // already booked in the open pool — skip, don't charge again
            }
            $bookable[] = $id;
            $advances += $p->entryPrice();
        }
        $drawIds = $bookable;

        // Each balance must be this customer's own booking and still awaiting a
        // choice. Priced from the snapshot taken at booking time, so a later
        // price change can't move the goalposts.
        $balances = 0;
        foreach ($balanceIds as $entryId) {
            $entry = DrawEntry::with('product')->findOrFail($entryId);
            abort_unless($entry->user_id === $user->id, 403, 'Not your booking.');
            if (! $entry->awaitingChoice()) {
                throw new BusinessException('One of those bookings is no longer awaiting a choice.');
            }
            $balances += $entry->balanceDue();
        }

        if (! $lines && ! $drawIds && ! $balanceIds) {
            throw new BusinessException('You have already booked these items in their pools — nothing left to pay.');
        }

        // Coupons discount purchases only — never a 1% advance.
        $code = $input['coupon_code'] ?? null;
        $applied = $this->coupons->tryApply($code, $user, $lines);
        $afterDiscount = max(0, $subtotal - ($applied['discount'] ?? 0));

        $applyWallet = (bool) ($input['apply_wallet'] ?? false);
        $wallet = $applyWallet ? min($user->fresh()->wallet_balance, $cap, $afterDiscount) : 0;

        return [
            // Advances are always real money. Balances are already net of the 1%
            // advance, and wallet credit stays reserved for the purchase lines.
            ($afterDiscount - $wallet) + $advances + $balances,
            [
                'items' => $lines,
                'draw_items' => $drawIds,
                'balance_items' => $balanceIds,
                'apply_wallet' => $applyWallet,
                'coupon_code' => $applied ? $applied['coupon']->code : null,
                'address_id' => $input['address_id'] ?? null,
            ],
            $lines && $drawIds ? 'Order + 1% booking' : ($drawIds ? '1% advance booking' : 'Order payment'),
        ];
    }

    private function quoteDraw(User $user, array $input): array
    {
        $product = Product::where('slug', $input['product_slug'] ?? '')->firstOrFail();
        if (! $product->drawEligible()) {
            throw new BusinessException('This product is not available for the draw.');
        }
        // Reject before taking money — a repeat would roll back on fulfilment and
        // leave the captured advance stranded.
        if ($this->draw->heldSeat($user, $product)) {
            throw new BusinessException('You have already booked this item in this pool. Choose a different product in the same price range to add another seat.');
        }

        // One seat per product — quantity is always 1.
        return [
            $product->entryPrice(),
            ['product_id' => $product->id],
            '1% advance booking',
        ];
    }

    private function quoteBalance(User $user, array $input): array
    {
        $entry = DrawEntry::with('product')->findOrFail((int) ($input['entry_id'] ?? 0));
        abort_unless($entry->user_id === $user->id, 403, 'Not your booking.');
        if (! $entry->awaitingChoice()) {
            throw new BusinessException('This booking is not awaiting a choice.');
        }

        $balance = $entry->balanceDue();
        $applyWallet = (bool) ($input['apply_wallet'] ?? false);
        $wallet = $applyWallet
            ? min($user->fresh()->wallet_balance, min($entry->product->maxWalletApplicable(), $balance))
            : 0;

        return [
            $balance - $wallet,
            ['entry_id' => $entry->id, 'apply_wallet' => $applyWallet],
            'Remaining 99% balance',
        ];
    }

    /** Execute the paid-for intent and link the result back to the payment row. */
    private function fulfil(Payment $payment, User $user): array
    {
        $p = $payment->payload ?? [];

        return match ($payment->intent) {
            'checkout' => (function () use ($p, $user, $payment) {
                $result = ['type' => 'order', 'seats' => 0];

                if (! empty($p['items'])) {
                    $order = $this->checkout->place($user, $p['items'], (bool) ($p['apply_wallet'] ?? false), $payment, $p['coupon_code'] ?? null, $p['address_id'] ?? null);
                    $payment->update(['order_id' => $order->id]);
                    $result['id'] = $order->id;
                }

                $refunded = [];
                foreach ($p['draw_items'] ?? [] as $productId) {
                    $product = Product::find($productId);
                    if (! $product) {
                        continue;
                    }
                    try {
                        $entry = $this->draw->enter($product, $user, $payment);
                        $result['seats']++;
                        if (! $payment->entry_id) {
                            $payment->update(['entry_id' => $entry->id]);
                        }
                    } catch (\Throwable $e) {
                        // The money is already captured, so we never simply drop the
                        // booking — the advance goes back to the customer's wallet.
                        $this->wallet->credit(
                            $user,
                            $product->entryPrice(),
                            'draw_refund',
                            'product',
                            $product->id,
                            "Booking not completed for {$product->name} — advance returned",
                        );
                        $refunded[] = $product->name;
                    }
                }
                if ($refunded) {
                    $result['refunded_to_wallet'] = $refunded;
                }

                // Settle each 99% balance the customer chose to pay in this cart.
                // Wallet is not reapplied here — it was already priced against the
                // purchase lines, and the balance is net of the advance.
                $converted = [];
                foreach ($p['balance_items'] ?? [] as $entryId) {
                    $entry = DrawEntry::find($entryId);
                    if (! $entry || $entry->user_id !== $user->id || ! $entry->awaitingChoice()) {
                        continue; // already settled or expired between pay and verify
                    }
                    $order = $this->draw->convertToPurchase($entry, false, $payment);
                    $converted[] = $order->id;
                    if (! $payment->order_id) {
                        $payment->update(['order_id' => $order->id]);
                    }
                }
                if ($converted) {
                    $result['balance_orders'] = $converted;
                }

                return $result;
            })(),

            'draw' => (function () use ($p, $user, $payment) {
                $entry = $this->draw->enter(Product::findOrFail($p['product_id']), $user, $payment);
                $payment->update(['entry_id' => $entry->id]);

                return ['type' => 'draw', 'seats' => 1, 'won' => $entry->status === 'won'];
            })(),

            'balance' => (function () use ($p, $user, $payment) {
                $entry = DrawEntry::findOrFail($p['entry_id']);
                abort_unless($entry->user_id === $user->id, 403);
                $order = $this->draw->convertToPurchase($entry, (bool) ($p['apply_wallet'] ?? false), $payment);
                $payment->update(['order_id' => $order->id, 'entry_id' => $entry->id]);

                return ['type' => 'order', 'id' => $order->id];
            })(),

            default => throw new BusinessException('Unknown payment type.'),
        };
    }
}
