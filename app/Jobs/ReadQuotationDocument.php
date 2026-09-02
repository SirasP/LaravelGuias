<?php

namespace App\Jobs;

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestEvent;
use App\Models\PurchaseRequestIngestion;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Notifications\QuotationDraftReady;
use App\Notifications\QuotationWaitingForReader;
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

    /**
     * Los reintentos se limitan por tiempo, no por número: mientras el modelo
     * no esté accesible el documento se vuelve a encolar, y eso consume vidas
     * sin que nadie haya fallado en nada.
     */
    public int $tries = 0;

    /** Hasta cuándo vale la pena seguir esperando a que el lector vuelva. */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours((int) config('purchase_requests.reader.wait_hours', 12));
    }

    public function __construct(public readonly PurchaseRequestIngestion $ingestion) {}

    public function handle(QuotationReader $reader): void
    {
        $ingestion = $this->ingestion->fresh();

        if ($ingestion === null || $ingestion->isFinished()) {
            return;
        }

        $comenzo = microtime(true);

        // Hay que quedarse con el estado ANTES de marcarlo como «leyendo»: si no,
        // al llegar el momento de decidir si ya estaba esperando, ese dato ya se
        // perdió y se avisa en cada reintento.
        $estadoPrevio = $ingestion->status;

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

        // El modelo no estaba: la Mac que lo aloja se duerme, y el túnel con
        // ella. El documento no tiene nada malo, así que se deja esperando en
        // vez de darlo por ilegible.
        if ($lectura->unreachable) {
            $this->esperarAlLector($ingestion, $lectura->error ?? 'El lector no está disponible.', $estadoPrevio);

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

        if ($lectura->taxTreatment !== null
            && $lectura->taxTreatment->kind === \App\Services\PurchaseRequests\Reading\TaxTreatment::SIN_DETERMINAR
            && collect($lectura->items)->contains(fn (array $i): bool => filled($i['unit_price'] ?? null))) {
            $lectura = $lectura->conAviso($lectura->taxTreatment->explanation.' Revisa el IVA antes de enviar a Odoo.');
        }

        // El job ya NO crea la solicitud. Deja la lectura preparada para que
        // una persona la revise en pantalla y decida: una lectura equivocada
        // no debe dejar un borrador que después haya que anular.
        $ingestion->forceFill([
            // Una lectura con dudas se marca: el borrador existe, pero hay que
            // mirarlo con más atención antes de enviarlo.
            'status' => $lectura->isDoubtful()
                ? PurchaseRequestIngestion::NEEDS_REVIEW
                : PurchaseRequestIngestion::COMPLETED,
            'source_kind' => $lectura->sourceKind,
            'model_used' => $lectura->model ?? $ingestion->model_used,
            'prices_include_tax' => $lectura->taxTreatment?->pricesIncludeTax(),
            'supplier_name' => $lectura->supplier,
            'supplier_tax_id' => $lectura->supplierTaxId,
            'customer_tax_id' => $lectura->customerTaxId,
            'customer_matches_company' => $lectura->isForOurCompany(),
            'extracted' => $lectura->toArray(),
            'warnings' => $lectura->warnings,
            'finished_at' => now(),
            'duration_ms' => (int) round((microtime(true) - $comenzo) * 1000),
        ])->save();

        $this->avisar($ingestion, null, $lectura);
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
                'reason' => $this->motivoDe($lectura, $ingestion),
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
     * El motivo de la compra.
     *
     * Se usa el del documento sólo si éste lo declara. Un documento comercial
     * casi nunca dice por qué compras: dice a qué se dedica quien vende. En
     * una prueba real el giro del proveedor —«Importación y Exportación
     * Compra Venta…»— terminó escrito como motivo de la solicitud.
     *
     * Sin motivo declarado se deja constancia de con quién es la compra, que
     * es lo único que el documento sí afirma, y la persona lo completa.
     */
    private function motivoDe(QuotationReading $lectura, PurchaseRequestIngestion $ingestion): string
    {
        if (filled($lectura->reason)) {
            return $lectura->reason;
        }

        $proveedor = $lectura->supplier;
        $rut = \App\Support\Rut::format($lectura->supplierTaxId);

        if ($proveedor !== null && $rut !== null) {
            return sprintf('Compra a %s (RUT %s). Completar el motivo.', $proveedor, $rut);
        }

        if ($proveedor !== null) {
            return sprintf('Compra a %s. Completar el motivo.', $proveedor);
        }

        if ($rut !== null) {
            return sprintf('Compra al proveedor RUT %s. Completar el motivo.', $rut);
        }

        return sprintf('Compra según el documento «%s». Completar el motivo.', $ingestion->original_name);
    }

    /**
     * El proveedor de la solicitud, resuelto contra el catálogo.
     *
     * El RUT manda: si ya está registrado, se usa el nombre que la empresa le
     * puso, sin depender de que el modelo lo lea bien. Si es nuevo, se
     * registra con lo que se haya podido leer para que alguien lo complete
     * después: así el catálogo se llena solo con el uso.
     *
     * @return list<string>
     */
    private function proveedorSugerido(QuotationReading $lectura): array
    {
        $rut = \App\Support\Rut::normalize($lectura->supplierTaxId);

        // Sin RUT no hay a quién registrar; se deja el nombre leído, si lo hay.
        if ($rut === null) {
            return $lectura->supplier !== null ? [$lectura->supplier] : [];
        }

        $proveedor = \App\Models\PurchaseSupplier::query()
            ->forCompany()
            ->where('tax_id', $rut)
            ->first();

        // Hay documentos cuyo encabezado es una imagen: en el texto no queda
        // más que el RUT. Antes de dejar el nombre vacío se le pregunta a
        // Odoo, que ya conoce a la mayoría de los proveedores.
        $nombre = $lectura->supplier ?? $this->nombreSegunOdoo($rut);

        if ($proveedor === null) {
            $proveedor = \App\Models\PurchaseSupplier::query()->create([
                'company_code' => 'EHE',
                'tax_id' => $rut,
                'name' => $nombre,
                'source' => \App\Models\PurchaseSupplier::SOURCE_DOCUMENT,
                'notes' => $nombre === null
                    ? 'Detectado al leer un documento. Falta ponerle nombre.'
                    : 'Detectado al leer un documento. Verifica que el nombre sea correcto.',
            ]);
        } elseif ($proveedor->needsName() && $nombre !== null) {
            // El catálogo lo tenía sin nombre y el documento trajo uno: se
            // guarda como propuesta, para que alguien lo confirme.
            $proveedor->forceFill(['name' => $nombre])->save();
        }

        return [$proveedor->label()];
    }

    /**
     * Cómo se llama en Odoo el dueño de ese RUT.
     *
     * Sólo lee: es la misma búsqueda que hace una persona en la pantalla de
     * confirmación, hecha sola. Si Odoo está apagado o no contesta, la
     * lectura sigue sin nombre, que es lo que pasaba antes.
     */
    private function nombreSegunOdoo(string $rut): ?string
    {
        $exportador = app(\App\Services\PurchaseRequests\Odoo\PurchaseRequestExporter::class);

        if (! $exportador instanceof \App\Services\PurchaseRequests\Odoo\OdooPurchaseRequestExporter) {
            return null;
        }

        try {
            $encontrados = $exportador->buscarProveedores($rut);
        } catch (Throwable $e) {
            Log::warning('[solicitudes] Odoo no respondió al buscar el proveedor '.$rut.': '.$e->getMessage());

            return null;
        }

        return $encontrados === [] ? null : $encontrados[0]['name'];
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

    /**
     * Deja el documento esperando y lo devuelve a la cola.
     *
     * Se avisa una sola vez, al entrar en espera: un documento que espera tres
     * horas no puede mandar treinta correos por el camino.
     */
    private function esperarAlLector(
        PurchaseRequestIngestion $ingestion,
        string $motivo,
        ?string $estadoPrevio,
    ): void {
        $yaEstaba = $estadoPrevio === PurchaseRequestIngestion::WAITING;

        $ingestion->forceFill([
            'status' => PurchaseRequestIngestion::WAITING,
            'error_message' => $motivo,
            // No se marca como terminado: sigue vivo, sólo que en pausa.
            'finished_at' => null,
        ])->save();

        if (! $yaEstaba) {
            Log::info('Una cotización quedó esperando al lector.', [
                'ingestion' => $ingestion->public_id,
                'archivo' => $ingestion->original_name,
            ]);

            $this->avisarQueEspera($ingestion);
        }

        // Los primeros reintentos son rápidos: la Mac suele volver enseguida.
        // Después se espacian, para no golpear el túnel durante horas.
        $intentos = $this->attempts();
        $demora = match (true) {
            $intentos <= 3 => 60,
            $intentos <= 8 => 300,
            default => 900,
        };

        $this->release($demora);
    }

    private function avisarQueEspera(PurchaseRequestIngestion $ingestion): void
    {
        try {
            $ingestion->uploader?->notify(new QuotationWaitingForReader($ingestion));
        } catch (Throwable $e) {
            Log::warning('No se pudo avisar de la espera.', ['motivo' => $e->getMessage()]);
        }
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
     * Avisa el resultado a quien subió el documento y al administrador.
     *
     * El administrador tiene que enterarse aunque no haya subido nada: el
     * caso que motiva todo esto es un trabajador que cotiza en terreno y una
     * cotización que después nadie recuerda. Si el aviso fuera sólo para quien
     * sube, el documento seguiría perdiéndose igual.
     */
    private function avisar(PurchaseRequestIngestion $ingestion, ?PurchaseRequest $borrador, ?QuotationReading $lectura): void
    {
        $fresco = $ingestion->fresh();
        $destinatarios = collect();

        if ($ingestion->uploader !== null) {
            $destinatarios->push($ingestion->uploader);
        }

        $destinatarios = $destinatarios->merge(
            User::query()
                ->where('role', User::ROLE_ADMIN)
                ->where('is_active', true)
                ->get(),
        )->unique(fn (User $u): int => (int) $u->getKey());

        foreach ($destinatarios as $destinatario) {
            try {
                $destinatario->notify(new QuotationDraftReady($fresco, $borrador));
            } catch (Throwable $e) {
                Log::warning('No se pudo avisar del resultado de la lectura.', [
                    'ingestion' => $ingestion->public_id,
                    'destinatario' => $destinatario->getKey(),
                    'motivo' => $e->getMessage(),
                ]);
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        $ingestion = $this->ingestion->fresh();

        if ($ingestion === null || $ingestion->isFinished()) {
            return;
        }

        $esperaba = $ingestion->status === PurchaseRequestIngestion::WAITING;

        $ingestion->forceFill([
            'status' => PurchaseRequestIngestion::FAILED,
            'error_message' => $esperaba
                ? 'El lector no volvió a estar disponible dentro del plazo. Puedes volver a leer el documento cuando lo esté.'
                : 'El trabajo falló: '.$exception->getMessage(),
            'finished_at' => now(),
        ])->save();
    }
}
