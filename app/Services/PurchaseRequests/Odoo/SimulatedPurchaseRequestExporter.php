<?php

namespace App\Services\PurchaseRequests\Odoo;

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use Illuminate\Support\Facades\Log;

/**
 * Adaptador simulado: el único que existe en este MVP.
 *
 * No abre conexiones, no llama a XML-RPC y no escribe en ninguna base de datos
 * remota. Sólo calcula qué RFQ se crearía y lo deja registrado, de modo que la
 * suite pueda comprobar que no hay tráfico real hacia Odoo.
 */
class SimulatedPurchaseRequestExporter implements PurchaseRequestExporter
{
    public function isEnabled(): bool
    {
        // Deliberadamente apagado. Encenderlo exige implementar un adaptador
        // real y una acción explícita de Compras, no cambiar esta línea.
        return false;
    }

    public function exportApproved(PurchaseRequest $purchaseRequest): PurchaseRequestExportResult
    {
        if ($purchaseRequest->status !== PurchaseRequestStatus::APPROVED) {
            return PurchaseRequestExportResult::skipped(
                'Sólo se exportan solicitudes aprobadas.',
            );
        }

        $reference = 'SIMULADO-RFQ-'.$purchaseRequest->folio;

        Log::info('Exportación a Odoo simulada; no se realizó ninguna llamada remota.', [
            'folio' => $purchaseRequest->folio,
            'revision' => $purchaseRequest->revision_number,
            'items' => $purchaseRequest->items()->count(),
            'reference' => $reference,
        ]);

        return PurchaseRequestExportResult::simulated(
            $reference,
            'Integración no implementada: se registró la RFQ que se crearía, sin contactar a Odoo.',
        );
    }
}
