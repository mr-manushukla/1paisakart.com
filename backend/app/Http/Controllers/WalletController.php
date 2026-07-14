<?php

namespace App\Http\Controllers;

use App\Http\Resources\WalletTransactionResource;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    /** Balance + recent ledger. Wallet is restricted credit (see docs/BUSINESS_RULES.md). */
    public function show(Request $request): array
    {
        $user = $request->user();

        return [
            'balance' => $user->wallet_balance, // paise
            'transactions' => WalletTransactionResource::collection(
                $user->walletTransactions()->latest('id')->limit(50)->get()
            ),
        ];
    }
}
