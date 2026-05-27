<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Modules\Catalog\Console\Commands\SyncIikoCatalogCommand;
use Modules\Catalog\Console\Commands\SyncIikoCombosCommand;
use Modules\Catalog\Console\Commands\SyncIikoMenusCommand;
use Modules\Catalog\Console\Commands\SyncIikoProductsCommand;
use Modules\Checkout\Console\Commands\ReleaseExpiredIncentiveReservationsCommand;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        ReleaseExpiredIncentiveReservationsCommand::class,
        SyncIikoCatalogCommand::class,
        SyncIikoCombosCommand::class,
        SyncIikoMenusCommand::class,
        SyncIikoProductsCommand::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('incentives:release-expired-reservations')->everyMinute();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
