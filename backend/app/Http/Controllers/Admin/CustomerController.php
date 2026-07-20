<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\DrawEntryResource;
use App\Models\User;
use Illuminate\Http\Request;

/** Admin-only: which pools a given customer has taken part in. */
class CustomerController extends Controller
{
    /** Searchable customer list with draw activity. */
    public function index(Request $request)
    {
        return User::where('role', 'customer')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$request->string('q').'%')
                ->orWhere('email', 'like', '%'.$request->string('q').'%')))
            ->withCount('drawEntries as bookings')
            ->orderByDesc('bookings')->orderBy('name')
            ->paginate(20)
            ->through(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'wallet_balance' => $u->wallet_balance,
                'bookings' => $u->bookings,
            ]);
    }

    /** Every pool this customer has participated in, plus a summary. */
    public function draws(User $user)
    {
        abort_unless($user->role === 'customer', 404, 'Not a customer account.');

        return DrawEntryResource::collection(
            $user->drawEntries()->with(['product', 'batch.club'])->latest('id')->get()
        )->additional([
            'customer' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'wallet_balance' => $user->wallet_balance,
            ],
            'summary' => $user->drawSummary(),
        ]);
    }
}
