<?php

namespace Modules\Loyalty\Services;

use Modules\Loyalty\Models\LoyaltyAccount;

class BellCoinBalanceService
{
    public function availableBalance(int $customerId): int
    {
        $account = LoyaltyAccount::query()->where('customer_id', $customerId)->first();

        return $account?->availableBalance() ?? 0;
    }
}
