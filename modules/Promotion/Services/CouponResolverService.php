<?php

namespace Modules\Promotion\Services;

use Modules\Checkout\DTOs\CheckoutIncentiveRequest;
use Modules\Promotion\Models\Coupon;
use Modules\Shared\Enums\CouponKind;
use Modules\Shared\Enums\CouponStatus;
use Modules\Shared\Enums\FailureReason;
use Modules\Shared\Exceptions\IncentiveRejectedException;
use Illuminate\Support\Carbon;

class CouponResolverService
{
    public function resolve(CheckoutIncentiveRequest $request): ?Coupon
    {
        if ($request->couponCode === null) {
            return null;
        }

        $coupon = Coupon::query()
            ->where('code', $request->couponCode)
            ->where(function ($query) {
                $query
                    ->where('coupon_kind', '!=', CouponKind::IssuedCustomerCoupon->value)
                    ->orWhereNotNull('issued_from_coupon_id');
            })
            ->first();

        if (! $coupon) {
            throw new IncentiveRejectedException(FailureReason::CouponNotFound);
        }

        return $coupon;
    }

    public function assertCouponActive(Coupon $coupon): void
    {
        $now = Carbon::now();

        if ($coupon->status !== CouponStatus::Active) {
            throw new IncentiveRejectedException(FailureReason::CouponNotActive);
        }

        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            throw new IncentiveRejectedException(FailureReason::CouponNotActive);
        }

        if ($coupon->ends_at && $coupon->ends_at->lessThanOrEqualTo($now)) {
            throw new IncentiveRejectedException(FailureReason::CouponExpired);
        }
    }
}
