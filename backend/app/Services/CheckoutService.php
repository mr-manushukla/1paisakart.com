<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Notifications\VendorNewOrderNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 100% buy. Wallet credit may cover at most wallet_cap_pct% (10%) of EACH item's
 * price; the rest is real money. Wallet is optional per checkout.
 */
class CheckoutService
{
    public function __construct(
        private WalletService $wallet,
        private CouponService $coupons,
    ) {}

    /**
     * @param  array<int, array{product_id:int, qty:int}>  $lines
     * @param  Payment|null  $payment  already-captured gateway payment (Razorpay); a
     *                                 stub row is written only when this is null.
     */
    public function place(User $user, array $lines, bool $applyWallet, ?Payment $payment = null, ?string $couponCode = null, ?int $addressId = null): Order
    {
        if (empty($lines)) {
            throw new BusinessException('Cart is empty.');
        }

        // Deliver only to an address that belongs to this customer.
        if ($addressId !== null && ! $user->addresses()->whereKey($addressId)->exists()) {
            throw new BusinessException('That delivery address is not on your account.');
        }

        return DB::transaction(function () use ($user, $lines, $applyWallet, $payment, $couponCode, $addressId) {
            $subtotal = 0;
            $walletCap = 0;   // sum of per-item 10% caps
            $prepared = [];

            foreach ($lines as $line) {
                $qty = max(1, (int) ($line['qty'] ?? 1));
                /** @var Product $product */
                $product = Product::whereKey($line['product_id'])->lockForUpdate()->firstOrFail();

                if (! $product->allow_full_buy || $product->status !== 'active') {
                    throw new BusinessException("“{$product->name}” is not available to buy.");
                }
                if ($product->stock < $qty) {
                    throw new BusinessException("“{$product->name}” is out of stock.");
                }

                $lineTotal = $product->effectivePrice() * $qty;
                $subtotal += $lineTotal;
                $walletCap += $product->maxWalletApplicable() * $qty;
                $prepared[] = [$product, $qty, $lineTotal];
            }

            // Coupon first, then wallet on what's left.
            $applied = $this->coupons->tryApply($couponCode, $user, $lines);
            $discount = $applied['discount'] ?? 0;
            $afterDiscount = max(0, $subtotal - $discount);

            $walletApplied = $applyWallet
                ? min($this->wallet->applicableForPurchase($user, $walletCap), $afterDiscount)
                : 0;
            $payable = $afterDiscount - $walletApplied;

            $order = Order::create([
                'user_id' => $user->id,
                'address_id' => $addressId,
                'subtotal' => $subtotal,
                'coupon_id' => $applied['coupon']->id ?? null,
                'discount' => $discount,
                'wallet_applied' => $walletApplied,
                'payable' => $payable,
                'status' => 'paid',
                'source' => 'buy',
            ]);

            if ($applied) {
                $this->coupons->redeem($applied['coupon'], $user, $discount, $order->id);
            }

            foreach ($prepared as [$product, $qty, $lineTotal]) {
                $order->items()->create([
                    'product_id' => $product->id,
                    'qty' => $qty,
                    'unit_price' => $product->effectivePrice(),
                    'line_total' => $lineTotal,
                ]);
                $product->decrement('stock', $qty);
            }

            if ($walletApplied > 0) {
                $this->wallet->debit($user, $walletApplied, 'purchase_debit', 'order', $order->id, "Wallet used on order #{$order->id}");
            }

            if ($payable > 0 && ! $payment) {
                Payment::create([
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'amount' => $payable,
                    'gateway' => 'stub',
                    'status' => 'success',
                    'ref' => 'order-'.$order->id,
                ]);
            }

            // Tell each vendor about their part of the order once the sale commits.
            DB::afterCommit(fn () => $this->notifyVendors($order, $prepared));

            return $order->load('items');
        });
    }

    /**
     * One email per shop, listing only that shop's lines. Best-effort: a failing
     * mailbox must never break a paid order, so every send is caught and logged.
     *
     * @param  array<int, array{0:Product, 1:int, 2:int}>  $prepared
     */
    private function notifyVendors(Order $order, array $prepared): void
    {
        $byShop = [];
        foreach ($prepared as [$product, $qty, $lineTotal]) {
            $byShop[$product->shop_id]['lines'][] = ['name' => $product->name, 'qty' => $qty, 'line_total' => $lineTotal];
            $byShop[$product->shop_id]['total'] = ($byShop[$product->shop_id]['total'] ?? 0) + $lineTotal;
        }

        foreach ($byShop as $shopId => $group) {
            try {
                $vendor = \App\Models\Shop::with('user')->find($shopId)?->user;
                if (! $vendor) {
                    continue;
                }
                $vendor->notify(new VendorNewOrderNotification($order, $group['lines'], $group['total']));
            } catch (\Throwable $e) {
                Log::warning("Vendor order email failed for shop #{$shopId} on order #{$order->id}: ".$e->getMessage());
            }
        }
    }
}
