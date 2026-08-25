<?php

namespace App\Console\Commands;

use App\Models\UnitOfMeasure;
use App\Services\PurchaseRequests\Reading\QuotationReader;
use Illuminate\Console\Command;

/**
 * Prueba el asistente de lectura contra un archivo, sin crear nada.
 *
 * Sirve para ver qué entiende el modelo antes de dejarlo suelto, y para
 * diagnosticar cuando una lectura sale mal.
 *
 *   php artisan solicitudes:leer ruta/al/documento.pdf
 */
class LeerCotizacion extends Command
{
    protected $signature = 'solicitudes:leer {archivo : Ruta del PDF o imagen a leer}';

    protected $description = 'Lee una cotización con el asistente y muestra lo que entiende, sin guardar nada';

    public function handle(QuotationReader $reader): int
    {
        $archivo = (string) $this->argument('archivo');

        if (! is_readable($archivo)) {
            $this->error('No se puede leer el archivo: '.$archivo);

            return self::FAILURE;
        }

        if (! $reader->isEnabled()) {
            $this->warn('El asistente está apagado. Enciéndelo con PURCHASE_REQUESTS_READER=true.');

            return self::FAILURE;
        }

        $this->line('Motor: '.$reader->describe());
        $this->line('Archivo: '.basename($archivo));

        $unidades = UnitOfMeasure::query()->forCompany()->active()->ordered()->pluck('name')->all();
        $mime = mime_content_type($archivo) ?: 'application/octet-stream';

        $inicio = microtime(true);
        $lectura = $reader->read($archivo, $mime, $unidades);
        $segundos = round(microtime(true) - $inicio, 1);

        $this->newLine();

        if (! $lectura->successful) {
            $this->error('No se pudo leer: '.$lectura->error);

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Leído en %ss · origen: %s · %d partidas%s',
            $segundos,
            $lectura->sourceKind ?? '?',
            count($lectura->items),
            $lectura->isDoubtful() ? ' · CON DUDAS' : '',
        ));

        if (filled($lectura->supplier)) {
            $this->line('Proveedor: '.$lectura->supplier);
        }

        $this->newLine();
        $this->table(
            ['N°', 'Producto / Servicio', 'Especificación', 'Cantidad', 'Unidad'],
            collect($lectura->items)->map(fn (array $i, int $n): array => [
                $n + 1,
                mb_strimwidth($i['product_service'], 0, 42, '…'),
                mb_strimwidth((string) ($i['specification'] ?? ''), 0, 20, '…'),
                $i['quantity'] ?? '—',
                $i['unit'] ?? '—',
            ])->all(),
        );

        if ($lectura->warnings !== []) {
            $this->newLine();
            $this->warn('Avisos:');
            foreach ($lectura->warnings as $aviso) {
                $this->line('  · '.$aviso);
            }
        }

        return self::SUCCESS;
    }
}
