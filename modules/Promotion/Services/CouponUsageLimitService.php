<?php

namespace Modules\Promotion\Services;

use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Models\CouponUsage;
use Modules\Shared\Enums\CouponUsageStatus;
use Modules\Shared\Enums\FailureReason;
use Modules\Shared\Exceptions\IncentiveRejectedException;

class CouponUsageLimitService
{
    public function assertCanUse(Coupon $coupon, int $customerId): void
    {
        if ($coupon->usage_limit_total !== null) {
            $totalApplied = CouponUsage::query()
                ->where('coupon_id', $coupon->id)
                ->whereIn('status', [CouponUsageStatus::Reserved->value, CouponUsageStatus::Applied->value])
                ->count();

            if ($totalApplied >= $coupon->usage_limit_total) {
                throw new IncentiveRejectedException(FailureReason::CouponUsageLimitReached);
            }
        }

        if ($coupon->usage_limit_per_customer !== null) {
            $customerApplied = CouponUsage::query()
                ->where('coupon_id', $coupon->id)
                ->where('customer_id', $customerId)
                ->whereIn('status', [CouponUsageStatus::Reserved->value, CouponUsageStatus::Applied->value])
                ->count();

            if ($customerApplied >= $coupon->usage_limit_per_customer) {
                throw new IncentiveRejectedException(FailureReason::CouponCustomerLimitReached);
            }
        }
    }
}
