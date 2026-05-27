<?php

namespace Modules\Checkout\Services;

use Modules\Checkout\Models\OrderIncentiveApplication;
use Modules\Shared\Enums\IncentiveApplicationStatus;

class ExpiredReservationReleaseService
{
    public function __construct(private readonly CheckoutIncentiveService $checkoutIncentives)
    {
    }

    public function releaseExpired(): int
    {
        $expiredAt = now()->subMinutes(config('loyalty.reservation_ttl_minutes', 5));
        $released = 0;

        OrderIncentiveApplication::query()
            ->where('status', IncentiveApplicationStatus::Reserved->value)
            ->where('created_at', '<', $expiredAt)
            ->orderBy('id')
            ->chunkById(100, function ($applications) use (&$released) {
                foreach ($applications as $application) {
                    $this->checkoutIncentives->cancel($application->incentive_application_id);
                    $released++;
                }
            });

        return $released;
    }
}
