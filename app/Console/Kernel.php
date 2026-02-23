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

        /*
        |--------------------------------------------------------------------------
        | app/Console/Kernel.php  —  agregar schedule
        |--------------------------------------------------------------------------
        */

        // En el método schedule(Schedule $schedule):
        $schedule->command('gmail:leer-xml')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground()
            ->onFailure(function () {
                // opcional: Log::error('gmail:leer-xml falló');
            });

        // Leer respuestas de proveedores a cotizaciones
        $schedule->command('cotizaciones:check-replies')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();


        /*
        |--------------------------------------------------------------------------
        | GOOGLE CLOUD CONSOLE
        | Configurar la URI de redirección autorizada:
        |
        |   https://tu-dominio.com/gmail/callback
        |
        | (o http://localhost/gmail/callback en desarrollo)
        |--------------------------------------------------------------------------
        */

    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
