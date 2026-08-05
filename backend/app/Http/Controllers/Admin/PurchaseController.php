<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DrawEntry;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Who bought what, split by how they paid.
 *
 *  - draws() — the 1% option: every advance booking, whatever became of it.
 *  - full()  — full payment: orders where the customer paid the product price.
 *              That's both a straight Buy Now AND a non-winner settling the
 *              remaining 99%; both are stored as source 'buy'. A winner's
 *              fulfilment order is 'draw_win' and is deliberately excluded —
 *              they only ever paid the 1%.
 *
 * The summary numbers are computed from the *same* filtered query as the rows,
 * so a search shows the totals for what's on screen, not for the whole platform.
 */
class PurchaseController extends Controller
{
    /** 1% purchases: one row per advance booking. */
    public function draws(Request $request)
    {
        $base = $this->search(DrawEntry::query(), $request, 'product');

        $rows = (clone $base)
            ->with(['user:id,name,email,phone', 'product:id,name', 'batch.club:id,label'])
            ->latest('id')
            ->paginate(30)
            ->through(fn (DrawEntry $e) => [
                'id' => $e->id,
                'at' => $e->created_at,
                'user' => [
                    'id' => $e->user?->id,
                    'name' => $e->user?->name,
                    'email' => $e->user?->email,
                    'phone' => $e->user?->phone,
                ],
                'product' => $e->product?->name,
                'club' => $e->batch?->club?->label,
                'pool_no' => $e->batch?->batch_no,
                'advance' => $e->amount,            // the 1% actually paid, paise
                'product_price' => $e->productPrice(),
                'status' => $e->status,             // active|won|lost_pending|converted|credited|refunded
            ]);

        // toArray() gives data + pagination keys; the summary rides alongside.
        return array_merge($rows->toArray(), ['summary' => $this->drawSummary(clone $base)]);
    }

    /** Full-payment purchases: one row per order the customer paid in full. */
    public function full(Request $request)
    {
        // Orders created by settling a booking's 99% — used to label the origin.
        $fromDraw = DrawEntry::whereNotNull('order_id')->where('status', 'converted')->pluck('order_id')->all();

        $base = $this->search(Order::where('source', 'buy'), $request, 'items.product');

        $rows = (clone $base)
            ->with(['user:id,name,email,phone', 'items.product:id,name'])
            ->latest('id')
            ->paginate(30)
            ->through(fn (Order $o) => [
                'id' => $o->id,
                'at' => $o->created_at,
                'user' => [
                    'id' => $o->user?->id,
                    'name' => $o->user?->name,
                    'email' => $o->user?->email,
                    'phone' => $o->user?->phone,
                ],
                'items' => $o->items->map(fn ($i) => [
                    'product' => $i->product?->name,
                    'qty' => $i->qty,
                    'line_total' => $i->line_total,
                ])->all(),
                'subtotal' => $o->subtotal,
                'discount' => $o->discount,
                'wallet_applied' => $o->wallet_applied,
                'payable' => $o->payable,           // what they actually paid, paise
                'status' => $o->status,
                // 'draw_99' = a non-winner completing their booking at the balance.
                'origin' => in_array($o->id, $fromDraw, true) ? 'draw_99' : 'direct',
            ]);

        return array_merge($rows->toArray(), ['summary' => $this->fullSummary(clone $base, $fromDraw)]);
    }

    /**
     * Narrow by customer/product text and by date range. The text search is
     * grouped, so the OR can't escape an earlier filter — without the group,
     * full()'s source='buy' would only bind to the user branch and leak
     * winner orders in. Both dates are inclusive whole days.
     */
    private function search($query, Request $request, string $productPath)
    {
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date']);
        $q = $request->string('q')->toString();

        return $query
            ->when($q !== '', fn ($b) => $b->where(fn ($g) => $g
                ->whereHas('user', fn ($u) => $u->where('name', 'like', "%$q%")->orWhere('email', 'like', "%$q%"))
                ->orWhereHas($productPath, fn ($p) => $p->where('name', 'like', "%$q%"))))
            ->when($request->input('from'), fn ($b, $d) => $b->whereDate('created_at', '>=', $d))
            ->when($request->input('to'), fn ($b, $d) => $b->whereDate('created_at', '<=', $d));
    }

    /** Headline numbers for the 1% tab, over the rows currently shown. */
    private function drawSummary($base): array
    {
        $byStatus = (clone $base)->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');

        return [
            'bookings' => (int) (clone $base)->count(),
            'customers' => (int) (clone $base)->distinct()->count('user_id'),
            'collected' => (int) (clone $base)->sum('amount'),      // total 1% advances taken, paise
            'won' => (int) ($byStatus['won'] ?? 0),
            'awaiting_choice' => (int) ($byStatus['lost_pending'] ?? 0),
            'converted' => (int) ($byStatus['converted'] ?? 0),     // went on to pay the 99%
            'credited' => (int) ($byStatus['credited'] ?? 0),       // moved to wallet
        ];
    }

    /** Headline numbers for the full-payment tab, over the rows currently shown. */
    private function fullSummary($base, array $fromDraw): array
    {
        return [
            'orders' => (int) (clone $base)->count(),
            'customers' => (int) (clone $base)->distinct()->count('user_id'),
            'revenue' => (int) (clone $base)->sum('payable'),       // real money taken, paise
            'wallet_applied' => (int) (clone $base)->sum('wallet_applied'),
            'from_draw' => (int) (clone $base)->whereIn('orders.id', $fromDraw)->count(),
            'direct' => (int) (clone $base)->whereNotIn('orders.id', $fromDraw)->count(),
        ];
    }
}
