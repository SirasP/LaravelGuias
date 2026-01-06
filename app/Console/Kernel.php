<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Console\Commands\AgrakNormalizeExisting;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        AgrakNormalizeExisting::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        // aquí puedes programar comandos si quieres
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
