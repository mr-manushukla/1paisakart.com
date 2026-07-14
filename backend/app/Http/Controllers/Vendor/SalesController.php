<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\DrawBatch;
use App\Models\Order;
use App\Services\DrawService;
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

    /** This vendor's draw batches, newest first. */
    public function batches(Request $request)
    {
        $shopId = $this->shopId($request);

        return DrawBatch::whereHas('product', fn ($q) => $q->where('shop_id', $shopId))
            ->with('product:id,name,slug')
            ->latest('id')->paginate(20)
            ->through(fn ($b) => [
                'id' => $b->id,
                'product' => $b->product?->name,
                'batch_no' => $b->batch_no,
                'filled' => $b->filled_count,
                'size' => $b->size,
                'status' => $b->status,
                'entry_price' => $b->entry_price,
                'drawn_at' => $b->drawn_at,
            ]);
    }

    public function cancelBatch(Request $request, DrawBatch $batch): array
    {
        abort_unless($batch->product->shop_id === $this->shopId($request), 403, 'Not your batch.');
        app(DrawService::class)->cancel($batch);

        return ['message' => 'Batch cancelled and all entries refunded.'];
    }

    private function shopId(Request $request): int
    {
        return $request->user()->shop?->id
            ?? abort(422, 'Your vendor account has no shop yet.');
    }
}
