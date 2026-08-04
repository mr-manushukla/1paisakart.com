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
     */
    public function myDraws(Request $request)
    {
        $user = $request->user();
        $status = $request->string('status')->toString();

        $query = $user->drawEntries()->with(['product', 'batch.club', 'batch.winnerEntry'])->latest('id');
        if ($status !== '') {
            $query->where('status', $status);
        }

        return DrawEntryResource::collection($query->paginate($status !== '' ? 100 : 20))
            ->additional(['summary' => $user->drawSummary()]);
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
