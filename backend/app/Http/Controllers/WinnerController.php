<?php

namespace App\Http\Controllers;

use App\Models\DrawBatch;
use App\Support\NameMask;

/**
 * Public winner board: every pool that has drawn, its winner highlighted among
 * the participants. Transparency only — names are masked and NO contact details
 * are exposed here (those live in the admin view).
 */
class WinnerController extends Controller
{
    public function index()
    {
        return DrawBatch::where('status', 'drawn')
            ->with(['club:id,label', 'winnerEntry.product', 'winnerEntry.user', 'entries.user', 'entries.product'])
            ->withSum('entries as pooled', 'amount')
            ->latest('drawn_at')
            ->paginate(12)
            ->through(function (DrawBatch $b) {
                $participants = $b->entries->sortBy('id')->values()->map(fn ($e, $i) => [
                    'seat' => $i + 1,
                    'name' => NameMask::name($e->user?->name),
                    'product' => $e->product?->name,
                    'is_winner' => $e->id === $b->winner_entry_id,
                ]);

                $w = $b->winnerEntry;

                return [
                    'id' => $b->id,
                    'club' => $b->club?->label,
                    'batch_no' => $b->batch_no,
                    'size' => $b->size,
                    'pooled' => (int) ($b->pooled ?? 0), // total advances collected, paise
                    'drawn_at' => $b->drawn_at,
                    'winner' => $w ? [
                        'seat' => $participants->firstWhere('is_winner')['seat'] ?? null,
                        'name' => NameMask::name($w->user?->name),
                        'product' => $w->product?->name,
                        'product_value' => $w->product?->effectivePrice(),
                        'product_image' => $w->product?->image,
                    ] : null,
                    'participants' => $participants,
                ];
            });
    }
}
