<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Single writer for the wallet. The ledger (wallet_transactions) is the source of
 * truth; users.wallet_balance is a cached mirror written in the same transaction.
 * Invariant: sum(ledger.amount for user) == users.wallet_balance, always.
 */
class WalletService
{
    /** Add restricted credit (e.g. a draw refund). Returns new balance (paise). */
    public function credit(User $user, int $amount, string $type, ?string $refType = null, ?int $refId = null, ?string $note = null): int
    {
        if ($amount <= 0) {
            throw new RuntimeException('Credit amount must be positive.');
        }
        return $this->write($user, $amount, $type, $refType, $refId, $note);
    }

    /** Spend wallet credit (100% buys only — enforced by callers, never for draws). */
    public function debit(User $user, int $amount, string $type, ?string $refType = null, ?int $refId = null, ?string $note = null): int
    {
        if ($amount <= 0) {
            throw new RuntimeException('Debit amount must be positive.');
        }
        return $this->write($user, -$amount, $type, $refType, $refId, $note);
    }

    /**
     * How much wallet credit may be applied to a 100% buy, given the cart's
     * total per-item cap (sum of 10%-of-price caps). Never exceeds the balance.
     */
    public function applicableForPurchase(User $user, int $capTotal): int
    {
        return max(0, min($user->fresh()->wallet_balance, $capTotal));
    }

    private function write(User $user, int $delta, string $type, ?string $refType, ?int $refId, ?string $note): int
    {
        return DB::transaction(function () use ($user, $delta, $type, $refType, $refId, $note) {
            // Lock the user row so concurrent balance updates serialize.
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $newBalance = $locked->wallet_balance + $delta;
            if ($newBalance < 0) {
                throw new BusinessException('Insufficient wallet balance.');
            }

            WalletTransaction::create([
                'user_id' => $locked->id,
                'type' => $type,
                'amount' => $delta,
                'balance_after' => $newBalance,
                'ref_type' => $refType,
                'ref_id' => $refId,
                'note' => $note,
            ]);

            // Direct assign (not ->update): wallet_balance is guarded from mass assignment.
            $locked->wallet_balance = $newBalance;
            $locked->save();
            $user->wallet_balance = $newBalance; // keep the caller's instance fresh
            return $newBalance;
        });
    }
}
