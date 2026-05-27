<?php

namespace Modules\Checkout\Console\Commands;

use Illuminate\Console\Command;
use Modules\Checkout\Services\ExpiredReservationReleaseService;

class ReleaseExpiredIncentiveReservationsCommand extends Command
{
    protected $signature = 'incentives:release-expired-reservations';

    protected $description = 'Release reserved checkout incentives after the configured TTL.';

    public function handle(ExpiredReservationReleaseService $service): int
    {
        $released = $service->releaseExpired();
        $this->info("Released {$released} expired incentive reservations.");

        return self::SUCCESS;
    }
}
