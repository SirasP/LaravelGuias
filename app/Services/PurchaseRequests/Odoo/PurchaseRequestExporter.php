<?php

namespace App\Services\PurchaseRequests\Odoo;

use App\Models\PurchaseRequest;

/**
 * Puerto de integración con Odoo.
 *
 * El MVP no integra: define el contrato y usa un adaptador simulado. Cuando
 * se implemente de verdad, la implementación deberá respetar estas reglas:
 *
 *  - Ejecutarse sólo tras una aprobación y por una acción explícita de Compras.
 *  - Crear a lo más una RFQ en estado borrador; nunca confirmar, recibir,
 *    facturar ni pagar.
 *  - Ser idempotente y guardar el vínculo local/Odoo.
 *  - No inventar un proveedor ficticio para representar la solicitud interna.
 *  - No crear productos ni unidades a partir de textos ambiguos.
 *  - No escribir jamás directo en la base de datos de Odoo.
 */
interface PurchaseRequestExporter
{
    /**
     * Exporta una solicitud aprobada como RFQ borrador.
     *
     * @return PurchaseRequestExportResult resultado del intento, nunca una excepción de red
     */
    public function exportApproved(PurchaseRequest $purchaseRequest): PurchaseRequestExportResult;

    /** Indica si la integración está habilitada en este entorno. */
    public function isEnabled(): bool;
}
