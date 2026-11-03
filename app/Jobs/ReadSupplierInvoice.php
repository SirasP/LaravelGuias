<?php

namespace App\Jobs;

use App\Models\SupplierInvoiceUpload;
use App\Services\SupplierInvoices\LocalInvoiceReader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Lee una factura subida, en segundo plano, como las cotizaciones: el modelo
 * puede tardar y nadie tiene que quedarse mirando la página.
 */
class ReadSupplierInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    /** Mientras la IA no esté, se reintenta; el límite es de tiempo. */
    public int $tries = 0;

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours((int) config('purchase_requests.reader.wait_hours', 12));
    }

    public function __construct(public readonly SupplierInvoiceUpload $upload) {}

    public function handle(LocalInvoiceReader $reader): void
    {
        $factura = $this->upload->fresh();

        if ($factura === null || ! $factura->isReading()) {
            return;
        }

        $factura->forceFill(['status' => SupplierInvoiceUpload::READING, 'attempts' => $factura->attempts + 1, 'model_used' => $reader->describe()])->save();

        try {
            $lectura = $reader->read(Storage::disk($factura->disk)->path($factura->path), $factura->mime_type);
        } catch (Throwable $e) {
            $lectura = ['ok' => false, 'unreachable' => false, 'error' => $e->getMessage()];
        }

        // La Mac del modelo se durmió: el documento no tiene la culpa.
        if ($lectura['unreachable'] ?? false) {
            $factura->forceFill(['status' => SupplierInvoiceUpload::WAITING, 'error_message' => $lectura['error']])->save();
            $this->release(min(60 * 2 ** min($this->attempts(), 5), 1800));

            return;
        }

        $factura->forceFill([
            // Aunque no se lea, queda para llenarla a mano: el PDF está guardado.
            'status' => ($lectura['ok'] ?? false) ? SupplierInvoiceUpload::READ : SupplierInvoiceUpload::FAILED,
            'error_message' => $lectura['error'] ?? null,
            'data' => $lectura['data'] ?? $factura->data,
            'warnings' => $lectura['warnings'] ?? [],
            'raw_response' => $lectura['raw'] ?? null,
            'model_used' => $lectura['model'] ?? $factura->model_used,
            'source_kind' => $lectura['source_kind'] ?? null,
            'finished_at' => now(),
        ])->save();
    }
}
