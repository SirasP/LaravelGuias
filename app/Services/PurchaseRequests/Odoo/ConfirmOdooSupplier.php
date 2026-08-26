<?php

namespace App\Services\PurchaseRequests\Odoo;

use App\Models\PurchaseRequest;
use App\Models\PurchaseSupplier;
use App\Support\Rut;
use Illuminate\Support\Str;

/**
 * Deja anotado qué proveedor de Odoo es el que se escribió en la solicitud.
 *
 * Es el paso que convierte una respuesta humana en conocimiento del sistema:
 * quien resuelve una vez que «Vicat» es ARIDOS VICAT SUR SPA no debería tener
 * que resolverlo nunca más, ni él ni nadie.
 */
class ConfirmOdooSupplier
{
    public function __invoke(PurchaseRequest $purchaseRequest, int $odooPartnerId, string $name, ?string $vat): PurchaseSupplier
    {
        $rut = filled($vat) ? Rut::normalize($vat) : null;

        // Los nombres tal como se escribieron en esta solicitud: son los que
        // hay que reconocer la próxima vez.
        $alias = collect((array) ($purchaseRequest->suggested_suppliers ?? []))
            ->map(fn ($s): string => Str::lower(trim((string) $s)))
            ->filter()
            ->all();

        $proveedor = $rut !== null
            ? PurchaseSupplier::query()->firstOrNew(['tax_id' => $rut])
            : PurchaseSupplier::query()->firstOrNew(['odoo_partner_id' => $odooPartnerId]);

        $proveedor->fill([
            'tax_id' => $rut ?? $proveedor->tax_id,
            'name' => $name,
            'odoo_partner_id' => $odooPartnerId,
            'aliases' => array_values(array_unique([...(array) ($proveedor->aliases ?? []), ...$alias])),
            'source' => $proveedor->exists ? $proveedor->source : 'odoo',
            'is_active' => true,
        ])->save();

        return $proveedor;
    }
}
