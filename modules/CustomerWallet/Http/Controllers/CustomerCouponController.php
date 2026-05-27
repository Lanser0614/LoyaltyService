<?php

namespace Modules\CustomerWallet\Http\Controllers;

use Modules\CustomerWallet\Models\CustomerCoupon;
use App\Http\Controllers\Controller;
use Modules\Shared\Enums\CustomerCouponStatus;
use Illuminate\Http\JsonResponse;

class CustomerCouponController extends Controller
{
    public function available(int $customerId): JsonResponse
    {
        $coupons = CustomerCoupon::query()
            ->with('coupon.actions')
            ->where('customer_id', $customerId)
            ->whereIn('status', [CustomerCouponStatus::Issued->value, CustomerCouponStatus::Available->value])
            ->where(function ($query) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $coupons]);
    }
}
