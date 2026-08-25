<?php

namespace App\Jobs;

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestEvent;
use App\Models\PurchaseRequestIngestion;
use App\Models\UnitOfMeasure;
use App\Notifications\QuotationDraftReady;
use App\Services\PurchaseRequests\Reading\QuotationReader;
use App\Services\PurchaseRequests\Reading\QuotationReading;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Lee una cotización en segundo plano y deja un borrador listo para confirmar.
 *
 * Corre fuera de la petición a propósito: un modelo puede tardar minutos y
 * nadie debe quedar mirando una página cargando. Quien sube el archivo recibe
 * una respuesta inmediata y el aviso llega cuando el trabajo termina.
 *
 * Lo que produce es SIEMPRE un borrador. El asistente nunca envía una
 * solicitud a revisión por su cuenta: una lectura equivocada se corrige antes
 * de que exista una solicitud formal.
 */
class ReadQuotationDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Un modelo en CPU puede tardar; se le da margen antes de rendirse. */
    public int $timeout = 600;

    public int $tries = 2;

    public function __construct(public readonly PurchaseRequestIngestion $ingestion)
    {
    }

    public function handle(QuotationReader $reader): void
    {
        $ingestion = $this->ingestion->fresh();

        if ($ingestion === null || $ingestion->isFinished()) {
            return;
        }

        $comenzo = microtime(true);

        $ingestion->forceFill([
            'status' => PurchaseRequestIngestion::PROCESSING,
            'started_at' => now(),
            'attempts' => $ingestion->attempts + 1,
            'model_used' => $reader->describe(),
        ])->save();

        try {
            $lectura = $reader->read(
                Storage::disk($ingestion->disk)->path($ingestion->path),
                $ingestion->mime_type,
                UnitOfMeasure::query()->forCompany()->active()->ordered()->pluck('name')->all(),
            );
        } catch (Throwable $e) {
            $this->registrarFallo($ingestion, $e->getMessage(), $comenzo);

            return;
        }

        if (! $lectura->successful || ! $lectura->hasItems()) {
            $this->registrarFallo(
                $ingestion,
                $lectura->error ?? 'No se reconoció ninguna partida en el documento.',
                $comenzo,
                $lectura,
            );

            return;
        }

        $borrador = $this->crearBorrador($ingestion, $lectura);

        $ingestion->forceFill([
            'purchase_request_id' => $borrador->getKey(),
            // Una lectura con dudas se marca: el borrador existe, pero hay que
            // mirarlo con más atención antes de enviarlo.
            'status' => $lectura->isDoubtful()
                ? PurchaseRequestIngestion::NEEDS_REVIEW
                : PurchaseRequestIngestion::COMPLETED,
            'source_kind' => $lectura->sourceKind,
            'model_used' => $lectura->model ?? $ingestion->model_used,
            'supplier_name' => $lectura->supplier,
            'supplier_tax_id' => $lectura->supplierTaxId,
            'customer_tax_id' => $lectura->customerTaxId,
            'customer_matches_company' => $lectura->isForOurCompany(),
            'extracted' => $lectura->toArray(),
            'warnings' => $lectura->warnings,
            'finished_at' => now(),
            'duration_ms' => (int) round((microtime(true) - $comenzo) * 1000),
        ])->save();

        $this->avisar($ingestion, $borrador, $lectura);
    }

    private function crearBorrador(PurchaseRequestIngestion $ingestion, QuotationReading $lectura): PurchaseRequest
    {
        return DB::transaction(function () use ($ingestion, $lectura): PurchaseRequest {
            $autor = $ingestion->uploader;

            $solicitud = PurchaseRequest::query()->create([
                'user_id' => $ingestion->user_id,
                'requester_name_snapshot' => $ingestion->uploader_name_snapshot,
                'request_date' => today(),
                // Una semana, igual que el valor por defecto del formulario.
                'required_date' => today()->addWeek(),
                'department' => $this->areaDe($autor),
                'reason' => $lectura->reason
                    ?? 'Cotización leída del documento «'.$ingestion->original_name.'».',
                'priority' => 'normal',
                // El proveedor se guarda con su RUT cuando se pudo identificar:
                // el nombre viene escrito de mil formas, el RUT no.
                'suggested_suppliers' => $this->proveedorSugerido($lectura),
                'status' => PurchaseRequestStatus::DRAFT,
                'revision_number' => 1,
                'lock_version' => 0,
            ]);

            $solicitud->forceFill([
                'folio' => sprintf('SC-%s-%06d', $solicitud->request_date->format('Y'), $solicitud->getKey()),
            ])->save();

            foreach ($lectura->items as $posicion => $item) {
                $solicitud->items()->create([
                    'sort_order' => $posicion + 1,
                    'product_service' => $item['product_service'],
                    'specification' => $item['specification'] ?? null,
                    // Sin cantidad legible se deja en cero para que la persona
                    // la complete: enviar exige una cantidad mayor que cero, así
                    // que el borrador no puede salir a medias por descuido.
                    'quantity' => $this->normalizarCantidad($item['quantity'] ?? null),
                    'unit' => $item['unit'] ?? '',
                    'quantity_note' => null,
                    'destination' => null,
                ]);
            }

            $solicitud->events()->create([
                'actor_id' => $ingestion->user_id,
                'actor_name_snapshot' => $ingestion->uploader_name_snapshot,
                'actor_role_snapshot' => $autor?->role,
                'event_type' => PurchaseRequestEvent::AI_DRAFTED,
                'from_status' => null,
                'to_status' => PurchaseRequestStatus::DRAFT,
                'revision_number' => 1,
                'comment' => null,
                'metadata' => [
                    'ingestion_id' => $ingestion->getKey(),
                    'documento' => $ingestion->original_name,
                    'modelo' => $lectura->model,
                    'origen' => $lectura->sourceKind,
                    'partidas' => count($lectura->items),
                    'avisos' => $lectura->warnings,
                ],
                'ip_address' => null,
                'user_agent' => null,
            ]);

            return $solicitud;
        });
    }

    /**
     * @return list<string>
     */
    private function proveedorSugerido(QuotationReading $lectura): array
    {
        $rut = \App\Support\Rut::format($lectura->supplierTaxId);
        $nombre = $lectura->supplier;

        if ($nombre === null && $rut === null) {
            return [];
        }

        if ($nombre === null) {
            return ['RUT '.$rut];
        }

        return [$rut === null ? $nombre : $nombre.' (RUT '.$rut.')'];
    }

    private function areaDe($autor): string
    {
        $ultima = $autor === null ? null : PurchaseRequest::query()
            ->where('user_id', $autor->getKey())
            ->whereNotNull('department')
            ->latest('id')
            ->value('department');

        return $ultima ?: 'Administración';
    }

    /** Acepta la coma decimal chilena y deja 0 cuando no se pudo leer. */
    private function normalizarCantidad(?string $valor): string
    {
        if (blank($valor)) {
            return '0';
        }

        $limpio = str_replace([' ', "\u{00A0}"], '', trim($valor));
        $limpio = str_replace(',', '.', $limpio);

        return is_numeric($limpio) ? $limpio : '0';
    }

    private function registrarFallo(
        PurchaseRequestIngestion $ingestion,
        string $motivo,
        float $comenzo,
        ?QuotationReading $lectura = null,
    ): void {
        $ingestion->forceFill([
            'status' => PurchaseRequestIngestion::FAILED,
            'error_message' => $motivo,
            'source_kind' => $lectura?->sourceKind,
            'extracted' => $lectura?->toArray(),
            'finished_at' => now(),
            'duration_ms' => (int) round((microtime(true) - $comenzo) * 1000),
        ])->save();

        Log::warning('El asistente no pudo leer una cotización.', [
            'ingestion' => $ingestion->public_id,
            'archivo' => $ingestion->original_name,
            'motivo' => $motivo,
        ]);

        $this->avisar($ingestion, null, $lectura);
    }

    /**
     * Avisa a quien subió el documento, haya salido bien o mal. Nadie más:
     * es su documento y su borrador.
     */
    private function avisar(PurchaseRequestIngestion $ingestion, ?PurchaseRequest $borrador, ?QuotationReading $lectura): void
    {
        $destinatario = $ingestion->uploader;

        if ($destinatario === null) {
            return;
        }

        try {
            $destinatario->notify(new QuotationDraftReady($ingestion->fresh(), $borrador));
        } catch (Throwable $e) {
            Log::warning('No se pudo avisar del resultado de la lectura.', [
                'ingestion' => $ingestion->public_id,
                'motivo' => $e->getMessage(),
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        $ingestion = $this->ingestion->fresh();

        if ($ingestion === null || $ingestion->isFinished()) {
            return;
        }

        $ingestion->forceFill([
            'status' => PurchaseRequestIngestion::FAILED,
            'error_message' => 'El trabajo falló: '.$exception->getMessage(),
            'finished_at' => now(),
        ])->save();
    }
}
