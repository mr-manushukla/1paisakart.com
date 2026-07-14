<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 100% buy. Wallet credit may cover at most wallet_cap_pct% (10%) of EACH item's
 * price; the rest is real money. Wallet is optional per checkout.
 */
class CheckoutService
{
    public function __construct(private WalletService $wallet) {}

    /**
     * @param  array<int, array{product_id:int, qty:int}>  $lines
     */
    public function place(User $user, array $lines, bool $applyWallet): Order
    {
        if (empty($lines)) {
            throw new BusinessException('Cart is empty.');
        }

        return DB::transaction(function () use ($user, $lines, $applyWallet) {
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

                $lineTotal = $product->listed_price * $qty;
                $subtotal += $lineTotal;
                $walletCap += $product->maxWalletApplicable() * $qty;
                $prepared[] = [$product, $qty, $lineTotal];
            }

            $walletApplied = $applyWallet ? $this->wallet->applicableForPurchase($user, $walletCap) : 0;
            $payable = $subtotal - $walletApplied;

            $order = Order::create([
                'user_id' => $user->id,
                'subtotal' => $subtotal,
                'wallet_applied' => $walletApplied,
                'payable' => $payable,
                'status' => 'paid',
                'source' => 'buy',
            ]);

            foreach ($prepared as [$product, $qty, $lineTotal]) {
                $order->items()->create([
                    'product_id' => $product->id,
                    'qty' => $qty,
                    'unit_price' => $product->listed_price,
                    'line_total' => $lineTotal,
                ]);
                $product->decrement('stock', $qty);
            }

            if ($walletApplied > 0) {
                $this->wallet->debit($user, $walletApplied, 'purchase_debit', 'order', $order->id, "Wallet used on order #{$order->id}");
            }

            if ($payable > 0) {
                Payment::create([
                    'order_id' => $order->id,
                    'amount' => $payable,
                    'gateway' => 'stub',
                    'status' => 'success',
                    'ref' => 'order-'.$order->id,
                ]);
            }

            return $order->load('items');
        });
    }
}
