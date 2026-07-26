<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** The signed-in customer's delivery addresses. Everything here is scoped to them. */
class AddressController extends Controller
{
    public function index(Request $request)
    {
        return ['data' => $request->user()->addresses()->latest('is_default')->latest('id')->get()];
    }

    public function store(Request $request): array
    {
        $data = $this->validated($request);
        $address = DB::transaction(function () use ($request, $data) {
            $address = $request->user()->addresses()->create($data);
            // First address is automatically the default; only one may hold the flag.
            $isFirst = $request->user()->addresses()->count() === 1;
            if (($data['is_default'] ?? false) || $isFirst) {
                $this->makeDefault($address);
            }

            return $address;
        });

        return ['message' => 'Address saved.', 'data' => $address->fresh()];
    }

    public function update(Request $request, Address $address): array
    {
        $this->authorizeOwner($request, $address);
        $data = $this->validated($request);

        DB::transaction(function () use ($address, $data) {
            $address->update($data);
            if ($data['is_default'] ?? false) {
                $this->makeDefault($address);
            }
        });

        return ['message' => 'Address updated.', 'data' => $address->fresh()];
    }

    public function destroy(Request $request, Address $address): array
    {
        $this->authorizeOwner($request, $address);
        $address->delete();

        // Never leave the customer without a default when they still have addresses.
        $next = $request->user()->addresses()->first();
        if ($next && ! $request->user()->addresses()->where('is_default', true)->exists()) {
            $this->makeDefault($next);
        }

        return ['message' => 'Address removed.'];
    }

    private function makeDefault(Address $address): void
    {
        // Clear the flag on the OTHERS only. Including this row would blank it in
        // the DB while the in-memory model still reads true, so the save() below
        // would find nothing dirty and the default would silently vanish.
        Address::where('user_id', $address->user_id)
            ->whereKeyNot($address->getKey())
            ->update(['is_default' => false]);

        if (! $address->is_default) {
            $address->forceFill(['is_default' => true])->save();
        }
    }

    private function authorizeOwner(Request $request, Address $address): void
    {
        abort_unless($address->user_id === $request->user()->id, 403, 'Not your address.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'label' => ['nullable', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:120'],
            'pincode' => ['required', 'string', 'regex:/^\d{6}$/'],
            'is_default' => ['boolean'],
        ], ['pincode.regex' => 'Enter a valid 6-digit PIN code.']);
    }
}
