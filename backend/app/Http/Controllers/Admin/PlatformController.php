<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DrawBatch;
use App\Models\Setting;
use App\Services\DrawService;
use Illuminate\Http\Request;

class PlatformController extends Controller
{
    /** All club pools across the platform. */
    public function batches()
    {
        return DrawBatch::with(['club:id,label', 'winnerEntry.user', 'winnerEntry.product'])
            ->withSum('entries as pooled', 'amount')
            ->latest('id')->paginate(30)
            ->through(fn ($b) => [
                'id' => $b->id,
                'club' => $b->club?->label,
                'batch_no' => $b->batch_no,
                'filled' => $b->filled_count,
                'size' => $b->size,
                'status' => $b->status,
                'pooled' => (int) ($b->pooled ?? 0), // total advances held, paise
                'drawn_at' => $b->drawn_at,
                'winner' => $this->winnerDetails($b),
            ]);
    }

    /** Full winner record for a drawn pool — for shipping the prize. Null until drawn. */
    private function winnerDetails(DrawBatch $batch): ?array
    {
        $entry = $batch->winnerEntry;
        if (! $entry) {
            return null;
        }
        $user = $entry->user;
        $product = $entry->product;

        return [
            'entry_id' => $entry->id,
            'product_name' => $product?->name,
            'product_value' => $product ? $product->effectivePrice() : null, // paise
            'advance_paid' => $entry->amount,                                 // the 1% they paid, paise
            'name' => $user?->name,
            'aid' => $user ? 'AID-'.$user->id : null,
            'email' => $user?->email,
            'phone' => $user?->phone,
            'address' => $user?->address,
        ];
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
