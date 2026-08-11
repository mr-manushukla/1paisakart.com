<?php

namespace App\Http\Controllers;

use App\Http\Resources\DrawEntryResource;
use App\Http\Resources\OrderResource;
use App\Models\DrawEntry;
use App\Models\Product;
use App\Services\DrawService;
use Illuminate\Http\Request;

class DrawController extends Controller
{
    public function __construct(private DrawService $draw) {}

    /** Pay the 1% advance to book a seat for this product in its club pool. */
    public function enter(Request $request, Product $product): array
    {
        $entry = $this->draw->enter($product, $request->user());

        return [
            'entry' => [
                'id' => $entry->id,
                'status' => $entry->status,   // active | won (if this seat filled & won the pool)
                'amount' => $entry->amount,
            ],
            'message' => $entry->status === 'won'
                ? 'The pool filled and you won! The product is yours. 🎉'
                : 'Your seat is booked. Watch the pool fill up.',
        ];
    }

    /** The signed-in customer's own bookings, newest first, with a participation summary. */
    /**
     * The customer's bookings, newest first.
     *
     * ?status=won (or any entry status) narrows the list. Without it the results
     * are paged, so a heavy booker's wins can sit pages deep — the Winnings tab
     * asks for status=won so it never depends on where a win happens to land.
     *
     * ?from / ?to bound the list by booking date (inclusive whole days), which is
     * what the month / year / custom-range pickers send.
     */
    public function myDraws(Request $request)
    {
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date']);

        $user = $request->user();
        $status = $request->string('status')->toString();

        $query = $user->drawEntries()->with(['product', 'batch.club', 'batch.winnerEntry'])->latest('id')
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($request->input('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->input('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d));

        return DrawEntryResource::collection($query->paginate($status !== '' ? 50 : 20))
            ->additional(['summary' => $user->drawSummary()]);
    }

    /** What this win costs to release — prize value, TDS, and what's payable. */
    public function claimQuote(Request $request, DrawEntry $entry): array
    {
        $this->authorizeOwner($request, $entry);
        abort_unless($entry->status === 'won', 422, 'This booking did not win a draw.');

        return [
            'entry' => new DrawEntryResource($entry->load(['product', 'batch.club'])),
            'claimed_at' => $entry->claimed_at,
            'quote' => $this->draw->claimQuote($entry),
        ];
    }

    /**
     * Complete a claim with no money to move (TDS switched off). Anything payable
     * goes through the gateway instead — the server prices it either way.
     */
    public function claim(Request $request, DrawEntry $entry): OrderResource
    {
        $this->authorizeOwner($request, $entry);
        $data = $request->validate(['address_id' => 'required|integer']);

        abort_unless(
            $this->draw->claimQuote($entry)['payable'] === 0,
            422,
            'This claim needs the TDS paid first.'
        );
        $order = $this->draw->claim($entry, (int) $data['address_id']);

        return new OrderResource($order->load('items.product'));
    }

    /** Option A — pay the remaining 99% and take the booked product. */
    public function purchase(Request $request, DrawEntry $entry): OrderResource
    {
        $this->authorizeOwner($request, $entry);
        $order = $this->draw->convertToPurchase($entry, $request->boolean('apply_wallet'));

        return new OrderResource($order->load('items.product'));
    }

    /** Option B — move the 1% advance to the wallet. */
    public function credit(Request $request, DrawEntry $entry): array
    {
        $this->authorizeOwner($request, $entry);
        $balance = $this->draw->creditToWallet($entry);

        return ['message' => 'Advance moved to your wallet.', 'wallet_balance' => $balance];
    }

    private function authorizeOwner(Request $request, DrawEntry $entry): void
    {
        abort_unless($entry->user_id === $request->user()->id, 403, 'Not your booking.');
    }
}
