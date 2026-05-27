<?php

namespace Modules\Loyalty\Services;

use Modules\Loyalty\Models\LoyaltyAccount;
use Modules\Loyalty\Models\LoyaltyLedgerTransaction;
use Modules\Shared\Enums\FailureReason;
use Modules\Shared\Enums\LedgerTransactionStatus;
use Modules\Shared\Enums\LedgerTransactionType;
use Modules\Shared\Exceptions\IncentiveRejectedException;

class BellCoinReserveService
{
    public function validateRedemption(int $customerId, int $amount, int $orderTotal, int $deliveryFee = 0): void
    {
        if ($amount <= 0) {
            return;
        }

        $productsAmount = max(0, $orderTotal - $deliveryFee);
        $maxByPercent = (int) floor($productsAmount * (config('loyalty.max_bellcoin_redeem_percent', 100) / 100));
        $maxAllowed = min($maxByPercent, (int) config('loyalty.max_bellcoin_redeem_amount', 80000));

        if ($amount > $maxAllowed) {
            throw new IncentiveRejectedException(FailureReason::BellCoinRedemptionLimitExceeded);
        }

        $account = LoyaltyAccount::query()->where('customer_id', $customerId)->first();

        if (! $account) {
            throw new IncentiveRejectedException(FailureReason::BellCoinAccountNotFound);
        }

        if ($account->availableBalance() < $amount) {
            throw new IncentiveRejectedException(FailureReason::BellCoinInsufficientBalance);
        }
    }

    public function reserve(int $customerId, int $amount, array $metadata = []): ?LoyaltyLedgerTransaction
    {
        if ($amount <= 0) {
            return null;
        }

        $account = LoyaltyAccount::query()
            ->where('customer_id', $customerId)
            ->lockForUpdate()
            ->first();

        if (! $account) {
            throw new IncentiveRejectedException(FailureReason::BellCoinAccountNotFound);
        }

        if ($account->availableBalance() < $amount) {
            throw new IncentiveRejectedException(FailureReason::BellCoinInsufficientBalance);
        }

        $account->increment('reserved_balance', $amount);
        $account->refresh();

        return LoyaltyLedgerTransaction::query()->create([
            'customer_id' => $customerId,
            'account_id' => $account->id,
            'type' => LedgerTransactionType::Reservation,
            'amount' => -$amount,
            'balance_after' => $account->balance,
            'status' => LedgerTransactionStatus::Pending,
            'reason' => 'CHECKOUT_RESERVATION',
            'metadata' => $metadata,
        ]);
    }
}
