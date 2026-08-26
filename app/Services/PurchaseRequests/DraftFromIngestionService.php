<?php

namespace App\Services\PurchaseRequests;

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestEvent;
use App\Models\PurchaseRequestIngestion;
use App\Models\PurchaseSupplier;
use App\Models\User;
use App\Support\Rut;
use Illuminate\Support\Facades\DB;

/**
 * Crea la solicitud a partir de una lectura ya revisada por una persona.
 *
 * Se ejecuta cuando alguien confirma en pantalla, no cuando el modelo termina
 * de leer: una lectura equivocada no debe dejar tras de sí un borrador que
 * después haya que anular.
 */
class DraftFromIngestionService
{
    /**
     * @param  list<array<string, string|null>>  $items  las partidas tal como
     *   quedaron en pantalla, ya corregidas por quien confirma
     */
    public function create(
        PurchaseRequestIngestion $ingestion,
        User $actor,
        array $items,
        ?string $reason = null,
        ?string $department = null,
    ): PurchaseRequest {
        return DB::transaction(function () use ($ingestion, $actor, $items, $reason, $department): PurchaseRequest {
            $solicitud = PurchaseRequest::query()->create([
                'user_id' => $actor->getKey(),
                'requester_name_snapshot' => $actor->name,
                'request_date' => today(),
                'required_date' => today()->addWeek(),
                'department' => $department ?: $this->areaDe($actor),
                // El motivo que escribió quien revisa manda; si lo dejó vacío se
                // usa el que declaraba el documento, y sólo entonces el genérico.
                'reason' => $reason
                    ?: ($this->limpiar($ingestion->extracted['reason'] ?? null)
                        ?: $this->motivoPorDefecto($ingestion)),
                'priority' => 'normal',
                'suggested_suppliers' => $this->proveedorDe($ingestion),
                'status' => PurchaseRequestStatus::DRAFT,
                'revision_number' => 1,
                'lock_version' => 0,
            ]);

            $solicitud->forceFill([
                'folio' => sprintf('SC-%s-%06d', $solicitud->request_date->format('Y'), $solicitud->getKey()),
            ])->save();

            foreach (array_values($items) as $posicion => $item) {
                $producto = trim((string) ($item['product_service'] ?? ''));

                if ($producto === '') {
                    continue;
                }

                $solicitud->items()->create([
                    'sort_order' => $posicion + 1,
                    'product_service' => $producto,
                    'specification' => $this->limpiar($item['specification'] ?? null),
                    'quantity' => $this->normalizarCantidad($item['quantity'] ?? null),
                    'unit' => trim((string) ($item['unit'] ?? '')),
                    'quantity_note' => null,
                    'destination' => null,
                ]);
            }

            $solicitud->events()->create([
                'actor_id' => $actor->getKey(),
                'actor_name_snapshot' => $actor->name,
                'actor_role_snapshot' => $actor->role,
                'event_type' => PurchaseRequestEvent::AI_DRAFTED,
                'from_status' => null,
                'to_status' => PurchaseRequestStatus::DRAFT,
                'revision_number' => 1,
                'comment' => null,
                'metadata' => [
                    'ingestion_id' => $ingestion->getKey(),
                    'documento' => $ingestion->original_name,
                    'modelo' => $ingestion->model_used,
                    'origen' => $ingestion->source_kind,
                    'partidas' => count($items),
                    // Queda registrado que una persona revisó antes de crearla.
                    'confirmado_por' => $actor->name,
                ],
                'ip_address' => request()?->ip(),
                'user_agent' => null,
            ]);

            $ingestion->forceFill(['purchase_request_id' => $solicitud->getKey()])->save();

            return $solicitud;
        });
    }

    /** @return list<string> */
    private function proveedorDe(PurchaseRequestIngestion $ingestion): array
    {
        $rut = Rut::normalize($ingestion->supplier_tax_id);

        if ($rut === null) {
            return filled($ingestion->supplier_name) ? [$ingestion->supplier_name] : [];
        }

        $proveedor = PurchaseSupplier::query()->forCompany()->where('tax_id', $rut)->first();

        if ($proveedor === null) {
            $proveedor = PurchaseSupplier::query()->create([
                'company_code' => 'EHE',
                'tax_id' => $rut,
                'name' => $ingestion->supplier_name,
                'source' => PurchaseSupplier::SOURCE_DOCUMENT,
                'notes' => blank($ingestion->supplier_name)
                    ? 'Detectado al leer un documento. Falta ponerle nombre.'
                    : 'Detectado al leer un documento. Verifica que el nombre sea correcto.',
            ]);
        } elseif ($proveedor->needsName() && filled($ingestion->supplier_name)) {
            $proveedor->forceFill(['name' => $ingestion->supplier_name])->save();
        }

        return [$proveedor->label()];
    }

    private function motivoPorDefecto(PurchaseRequestIngestion $ingestion): string
    {
        $rut = Rut::format($ingestion->supplier_tax_id);
        $nombre = $ingestion->supplier_name;

        if ($nombre !== null && $rut !== null) {
            return sprintf('Compra a %s (RUT %s). Completar el motivo.', $nombre, $rut);
        }

        if ($rut !== null) {
            return sprintf('Compra al proveedor RUT %s. Completar el motivo.', $rut);
        }

        return sprintf('Compra según el documento «%s». Completar el motivo.', $ingestion->original_name);
    }

    private function areaDe(User $actor): string
    {
        $ultima = PurchaseRequest::query()
            ->where('user_id', $actor->getKey())
            ->whereNotNull('department')
            ->latest('id')
            ->value('department');

        return $ultima ?: 'Administración';
    }

    private function normalizarCantidad(?string $valor): string
    {
        if (blank($valor)) {
            return '0';
        }

        $limpio = str_replace([' ', "\u{00A0}"], '', trim($valor));
        $limpio = str_replace(',', '.', $limpio);

        return is_numeric($limpio) ? $limpio : '0';
    }

    private function limpiar(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $limpio = trim($valor);

        return $limpio === '' ? null : $limpio;
    }
}
