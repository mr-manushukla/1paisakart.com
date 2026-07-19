<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\DrawBatch;
use App\Models\Order;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    /** Orders containing this vendor's products. */
    public function orders(Request $request)
    {
        $shopId = $this->shopId($request);

        return OrderResource::collection(
            Order::whereHas('items.product', fn ($q) => $q->where('shop_id', $shopId))
                ->with('items.product')->latest('id')->paginate(20)
        );
    }

    /**
     * Club pools containing bookings for this vendor's products. Read-only —
     * a pool is shared across vendors, so only an admin may cancel one.
     */
    public function batches(Request $request)
    {
        $shopId = $this->shopId($request);

        return DrawBatch::whereHas('entries.product', fn ($q) => $q->where('shop_id', $shopId))
            ->with('club:id,label')
            ->withCount(['entries as my_bookings' => fn ($q) => $q->whereHas('product', fn ($p) => $p->where('shop_id', $shopId))])
            ->latest('id')->paginate(20)
            ->through(fn ($b) => [
                'id' => $b->id,
                'club' => $b->club?->label,
                'batch_no' => $b->batch_no,
                'filled' => $b->filled_count,
                'size' => $b->size,
                'my_bookings' => $b->my_bookings,
                'status' => $b->status,
                'drawn_at' => $b->drawn_at,
            ]);
    }

    private function shopId(Request $request): int
    {
        return $request->user()->shop?->id
            ?? abort(422, 'Your vendor account has no shop yet.');
    }
}
