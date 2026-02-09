<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Console\Commands\AgrakNormalizeExisting;
use App\Jobs\CheckStockJob;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        AgrakNormalizeExisting::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->job(new CheckStockJob)
            ->everyMinute()
            ->evenInMaintenanceMode();
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
