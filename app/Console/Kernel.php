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

    protected function schedule(Schedule $schedule)
    {
        // aquí puedes programar comandos si quieres
        $schedule->job(new CheckStockJob)->everyFiveMinutes();
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
