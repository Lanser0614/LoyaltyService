<?php

namespace Modules\CustomerWallet\Services;

use Modules\CustomerWallet\Models\CustomerCoupon;
use Modules\Promotion\Models\Coupon;
use Modules\Shared\Enums\CustomerCouponStatus;
use Illuminate\Support\Str;

class CustomerCouponIssueService
{
    public function issue(Coupon $coupon, int $customerId, string $reason, ?string $campaignKey = null, array $metadata = []): CustomerCoupon
    {
        return CustomerCoupon::query()->firstOrCreate(
            [
                'customer_id' => $customerId,
                'coupon_id' => $coupon->id,
                'campaign_key' => $campaignKey,
            ],
            [
                'code' => $coupon->code ? $coupon->code.'-'.$customerId : Str::upper(Str::random(10)),
                'status' => CustomerCouponStatus::Available,
                'issued_reason' => $reason,
                'issued_by_type' => 'system',
                'starts_at' => $coupon->starts_at,
                'expires_at' => $coupon->ends_at,
                'metadata' => $metadata,
            ],
        );
    }
}
