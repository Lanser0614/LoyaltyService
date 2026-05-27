<?php

namespace Modules\IncentivePolicy\Services;

use Modules\Promotion\Models\Coupon;
use Modules\Shared\Enums\FailureReason;
use Modules\Shared\Enums\IncentiveType;
use Modules\Shared\Exceptions\IncentiveRejectedException;

class StackingPolicyService
{
    public function assertAllowed(?Coupon $coupon, bool $usesBellCoin, array $couponActionTypes, int $totalDiscountAmount): void
    {
        if ($coupon && $usesBellCoin && ! $coupon->is_stackable_with_bellcoin) {
            throw new IncentiveRejectedException(FailureReason::CouponAndBellCoinNotStackable);
        }

        if ($usesBellCoin && in_array(IncentiveType::FreeDelivery, $couponActionTypes, true)) {
            throw new IncentiveRejectedException(FailureReason::FreeDeliveryAndBellCoinNotStackable);
        }

        if ($usesBellCoin && in_array(IncentiveType::FreeProduct, $couponActionTypes, true)) {
            throw new IncentiveRejectedException(FailureReason::FreeProductAndBellCoinNotStackable);
        }

        if ($usesBellCoin && in_array(IncentiveType::FreeCombo, $couponActionTypes, true)) {
            throw new IncentiveRejectedException(FailureReason::FreeComboAndBellCoinNotStackable);
        }

        if ($totalDiscountAmount > config('loyalty.max_total_discount_amount', 80000)) {
            throw new IncentiveRejectedException(FailureReason::MaxTotalDiscountExceeded);
        }
    }
}
