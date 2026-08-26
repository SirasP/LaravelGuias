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

        $this->newLine();
        $this->line('<comment>Partes del documento</comment>');
        $this->line('  Proveedor : '.($lectura->supplier ?? '(sin nombre)')
            .' · RUT '.(\App\Support\Rut::format($lectura->supplierTaxId) ?? 'no identificado'));
        $this->line('  Cliente   : RUT '.(\App\Support\Rut::format($lectura->customerTaxId) ?? 'no identificado')
            .match ($lectura->isForOurCompany()) {
                true => ' ✓ es '.config('purchase_requests.company.name'),
                false => ' ✕ NO es '.config('purchase_requests.company.name'),
                null => '',
            });

        $this->newLine();
        $this->table(
            ['N°', 'Producto / Servicio', 'Especificación', 'Cantidad', 'Unidad', 'Precio unit.', 'Total'],
            collect($lectura->items)->map(fn (array $i, int $n): array => [
                $n + 1,
                mb_strimwidth($i['product_service'], 0, 42, '…'),
                mb_strimwidth((string) ($i['specification'] ?? ''), 0, 20, '…'),
                $i['quantity'] ?? '—',
                $i['unit'] ?? '—',
                filled($i['unit_price'] ?? null) ? number_format((float) $i['unit_price'], 0, ',', '.') : '—',
                filled($i['unit_price'] ?? null) && filled($i['quantity'] ?? null)
                    ? number_format((float) $i['unit_price'] * (float) str_replace(',', '.', (string) $i['quantity']), 0, ',', '.')
                    : '—',
            ])->all(),
        );

        if ($lectura->taxTreatment !== null) {
            $this->newLine();
            $this->line('IVA');
            $this->line('  veredicto : '.match ($lectura->taxTreatment->kind) {
                'net' => 'precios NETOS · al exportar se suma el 19%',
                'gross' => 'precios CON IVA dentro · no se le suma nada',
                default => 'no se pudo determinar · no se toca el monto',
            });
            $this->line('  porque    : '.$lectura->taxTreatment->explanation);
        }

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
