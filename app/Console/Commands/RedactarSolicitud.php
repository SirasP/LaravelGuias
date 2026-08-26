<?php

namespace App\Console\Commands;

use App\Models\UnitOfMeasure;
use App\Services\PurchaseRequests\Drafting\PurchaseRequestDrafter;
use Illuminate\Console\Command;

/**
 * Prueba el asistente de texto libre, sin crear nada.
 *
 *   php artisan solicitudes:redactar "pañuelos desechables 2, confort 2"
 */
class RedactarSolicitud extends Command
{
    protected $signature = 'solicitudes:redactar {texto : Lo que se necesita, escrito de corrido}';

    protected $description = 'Convierte una frase en partidas y muestra el resultado, sin guardar nada';

    public function handle(PurchaseRequestDrafter $drafter): int
    {
        if (! $drafter->isEnabled()) {
            $this->warn('El asistente está apagado. Enciéndelo con PURCHASE_REQUESTS_READER=true.');

            return self::FAILURE;
        }

        $texto = (string) $this->argument('texto');
        $this->line('Texto: «'.$texto.'»');

        $inicio = microtime(true);
        $sugerencia = $drafter->draftFromText(
            $texto,
            UnitOfMeasure::query()->forCompany()->active()->ordered()->pluck('name')->all(),
        );
        $segundos = round(microtime(true) - $inicio, 1);

        if (! $sugerencia->available) {
            $this->error($sugerencia->error);

            return self::FAILURE;
        }

        $this->info(sprintf('Listo en %ss · %d partidas', $segundos, count($sugerencia->items)));

        foreach ([
            'Motivo' => $sugerencia->reason,
            'Para' => $sugerencia->requestedForName,
            'Proveedor' => $sugerencia->supplier,
            'Prioridad' => $sugerencia->priority === 'urgent' ? 'URGENTE' : null,
            'Por qué urge' => $sugerencia->urgentReason,
            'Entregar en' => $sugerencia->deliveryLocation,
        ] as $etiqueta => $valor) {
            if (filled($valor)) {
                $this->line('  '.$etiqueta.': '.$valor);
            }
        }

        $this->newLine();
        $this->table(
            ['N°', 'Producto / Servicio', 'Cantidad', 'Unidad'],
            collect($sugerencia->items)->map(fn (array $i, int $n): array => [
                $n + 1,
                mb_strimwidth($i['product_service'], 0, 40, '…'),
                $i['quantity'] ?? '—',
                $i['unit'] ?? '—',
            ])->all(),
        );

        foreach ($sugerencia->warnings as $aviso) {
            $this->warn('  · '.$aviso);
        }

        return self::SUCCESS;
    }
}
