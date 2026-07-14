<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DrawBatch;
use App\Models\Setting;
use App\Services\DrawService;
use Illuminate\Http\Request;

class PlatformController extends Controller
{
    /** All draw batches across the platform. */
    public function batches()
    {
        return DrawBatch::with('product:id,name,slug')
            ->latest('id')->paginate(30)
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

    public function cancelBatch(DrawBatch $batch): array
    {
        app(DrawService::class)->cancel($batch);

        return ['message' => 'Batch cancelled and all entries refunded.'];
    }

    public function settings(): array
    {
        return [
            'platform_fee_pct' => (float) Setting::get('platform_fee_pct', config('draw.default_platform_fee_pct')),
        ];
    }

    public function updateSettings(Request $request): array
    {
        $data = $request->validate([
            'platform_fee_pct' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
        Setting::put('platform_fee_pct', $data['platform_fee_pct']);

        return ['message' => 'Settings updated.', 'platform_fee_pct' => (float) $data['platform_fee_pct']];
    }
}
