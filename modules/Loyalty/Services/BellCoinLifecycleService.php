<?php

namespace Modules\Loyalty\Services;

use Modules\Loyalty\Models\LoyaltyAccount;
use Modules\Loyalty\Models\LoyaltyLedgerTransaction;
use Modules\Shared\Enums\FailureReason;
use Modules\Shared\Enums\LedgerTransactionStatus;
use Modules\Shared\Enums\LedgerTransactionType;
use Modules\Shared\Exceptions\IncentiveRejectedException;

class BellCoinLifecycleService
{
    public function commitReservation(int $reservationTransactionId, ?int $orderId = null): LoyaltyLedgerTransaction
    {
        $reservation = $this->reservation($reservationTransactionId);
        $amount = abs($reservation->amount);
        $account = LoyaltyAccount::query()->lockForUpdate()->findOrFail($reservation->account_id);

        $account->decrement('reserved_balance', $amount);
        $account->decrement('balance', $amount);
        $account->refresh();

        $reservation->update([
            'status' => LedgerTransactionStatus::Completed,
            'order_id' => $orderId,
        ]);

        return LoyaltyLedgerTransaction::query()->create([
            'customer_id' => $reservation->customer_id,
            'account_id' => $account->id,
            'order_id' => $orderId,
            'type' => LedgerTransactionType::Redemption,
            'amount' => -$amount,
            'balance_after' => $account->balance,
            'status' => LedgerTransactionStatus::Completed,
            'reason' => 'CHECKOUT_COMMIT',
            'related_transaction_id' => $reservation->id,
            'metadata' => $reservation->metadata,
        ]);
    }

    public function releaseReservation(int $reservationTransactionId, string $reason = 'CHECKOUT_RELEASE'): LoyaltyLedgerTransaction
    {
        $reservation = $this->reservation($reservationTransactionId);
        $amount = abs($reservation->amount);
        $account = LoyaltyAccount::query()->lockForUpdate()->findOrFail($reservation->account_id);

        $account->decrement('reserved_balance', $amount);
        $account->refresh();

        $reservation->update(['status' => LedgerTransactionStatus::Cancelled]);

        return LoyaltyLedgerTransaction::query()->create([
            'customer_id' => $reservation->customer_id,
            'account_id' => $account->id,
            'type' => LedgerTransactionType::Release,
            'amount' => $amount,
            'balance_after' => $account->balance,
            'status' => LedgerTransactionStatus::Completed,
            'reason' => $reason,
            'related_transaction_id' => $reservation->id,
            'metadata' => $reservation->metadata,
        ]);
    }

    private function reservation(int $id): LoyaltyLedgerTransaction
    {
        $reservation = LoyaltyLedgerTransaction::query()
            ->where('type', LedgerTransactionType::Reservation)
            ->where('status', LedgerTransactionStatus::Pending)
            ->find($id);

        if (! $reservation) {
            throw new IncentiveRejectedException(FailureReason::BellCoinReservationNotFound);
        }

        return $reservation;
    }
}
