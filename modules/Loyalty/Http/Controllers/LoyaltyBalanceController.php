<?php

namespace Modules\Loyalty\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Loyalty\Services\BellCoinBalanceService;
use Illuminate\Http\JsonResponse;

class LoyaltyBalanceController extends Controller
{
    public function __construct(private readonly BellCoinBalanceService $balances)
    {
    }

    public function show(int $customerId): JsonResponse
    {
        return response()->json([
            'customer_id' => $customerId,
            'available_balance' => $this->balances->availableBalance($customerId),
        ]);
    }
}
