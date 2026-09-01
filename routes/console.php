<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * NOTA: este proyecto NO ejecuta el planificador de Laravel.
 *
 * No hay ningún `schedule:run` en el cron del VPS: cada tarea periódica es su
 * propia línea de crontab llamando a artisan —gmail:leer-xml, odoo:sync-moves,
 * bancochile:sync—. Definir aquí un Schedule::command() no haría nada, y es
 * peor que no definirlo: parecería programado sin estarlo.
 *
 * Para programar algo nuevo, añadir su línea al crontab del VPS.
 */
