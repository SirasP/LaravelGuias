<?php

namespace App\Services\PurchaseRequests\Odoo;

use App\Enums\PurchaseRequestStatus;
use App\Models\OdooPurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestEvent;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Las cotizaciones que alguien hizo directo en Odoo, sin solicitud, pasan a
 * ser una solicitud de Laravel, como si se hubieran hecho aquí.
 *
 * Luis Silva mandó desde Odoo la P00380 a Entre Riegos, la P00381 a Climent y
 * la P00382 a Austral, sin solicitud detrás: no había dónde subir lo que
 * respondieran. Con una solicitud unida a esas órdenes, las cotizaciones se
 * suben en un solo lugar y sus precios llegan a cada orden como siempre.
 *
 * Se adopta una vez por grupo de alternativas (o por orden suelta), sólo
 * mientras sigan siendo cotizaciones y desde la fecha configurada: las de antes
 * ya se resolvieron sin Laravel. En Odoo no se escribe nada; la unión queda en
 * la solicitud y sus partidas, que guardan el id de la orden y de cada línea.
 */
class AdoptOdooQuotations
{
    /** @return list<PurchaseRequest> las solicitudes creadas */
    public function adoptar(): array
    {
        $desde = config('purchase_requests.odoo.adopt_since');
        $duenio = $this->duenio();

        if (blank($desde) || $duenio === null) {
            return [];
        }

        $unidas = PurchaseRequest::whereNotNull('odoo_order_id')->pluck('odoo_order_id')
            ->merge(PurchaseRequestItem::whereNotNull('odoo_order_id')->pluck('odoo_order_id'))
            ->map(fn ($id): int => (int) $id)->flip();

        $candidatas = OdooPurchaseOrder::with('lines')
            ->whereIn('state', OdooPurchaseOrder::QUOTATION_STATES)
            ->where('odoo_created_at', '>=', $desde)
            ->orderBy('odoo_id')
            ->get()
            // La que ya nombra una solicitud en su origen ya tiene dónde vivir.
            ->reject(fn (OdooPurchaseOrder $o): bool => $o->requestFolio() !== null);

        $creadas = [];

        foreach ($candidatas->groupBy(fn (OdooPurchaseOrder $o): string => $o->purchase_group_id ? 'g'.$o->purchase_group_id : 'o'.$o->odoo_id) as $grupo) {
            $todas = $this->grupoCompleto($grupo);

            if ($todas->contains(fn (OdooPurchaseOrder $o): bool => $unidas->has($o->odoo_id) || $o->requestFolio() !== null)) {
                continue;
            }

            $base = $todas->sortBy('odoo_id')->first(fn (OdooPurchaseOrder $o): bool => $o->lines->reject->isNote()->isNotEmpty());

            if ($base === null) {
                continue;
            }

            $creadas[] = $this->crear($base, $todas, $duenio);
            $unidas = $unidas->merge($todas->pluck('odoo_id')->flip());
        }

        return $creadas;
    }

    /** El grupo entero, también las alternativas que ya no son cotización. */
    private function grupoCompleto(Collection $grupo): Collection
    {
        $id = $grupo->first()->purchase_group_id;

        return $id ? OdooPurchaseOrder::with('lines')->where('purchase_group_id', $id)->orderBy('odoo_id')->get() : $grupo;
    }

    private function crear(OdooPurchaseOrder $base, Collection $todas, User $duenio): PurchaseRequest
    {
        return DB::transaction(function () use ($base, $todas, $duenio): PurchaseRequest {
            $fecha = ($base->date_order ?? $base->odoo_created_at ?? now())->copy()->startOfDay();
            $proveedores = $todas->map(fn (OdooPurchaseOrder $o): string => $o->name.' '.\Illuminate\Support\Str::before((string) $o->partner_name, ','))->join(', ', ' y ');

            $solicitud = PurchaseRequest::create([
                'company_code' => PurchaseRequest::COMPANY_CODE,
                'company_name_snapshot' => PurchaseRequest::COMPANY_NAME,
                'user_id' => $duenio->id,
                'requester_name_snapshot' => $duenio->name,
                'requested_for_name' => $base->created_by_name,
                'request_date' => $fecha,
                'required_date' => $fecha,
                'department' => 'Administración',
                'reason' => trim('Cotización hecha en Odoo: '.$proveedores.".\n\n".($base->notes ?? '')),
                'priority' => 'normal',
                'currency' => $base->currency ?: 'CLP',
                'prices_include_tax' => false,
                'odoo_order_id' => $base->odoo_id,
                'odoo_reference' => $base->name,
                'status' => PurchaseRequestStatus::APPROVED,
                'submitted_at' => now(),
                'reviewed_at' => now(),
            ]);
            $solicitud->update(['folio' => sprintf('SC-%s-%06d', $solicitud->request_date->format('Y'), $solicitud->id)]);

            foreach ($base->lines->reject->isNote()->values() as $i => $linea) {
                $solicitud->items()->create([
                    'sort_order' => $i + 1,
                    'product_service' => trim((string) preg_replace('/\s+/', ' ', (string) $linea->name)),
                    'quantity' => $linea->product_qty,
                    'unit' => $this->unidad($linea->uom_name),
                    'odoo_order_id' => $base->odoo_id,
                    'odoo_line_id' => $linea->odoo_id,
                ]);
            }

            PurchaseRequestEvent::create([
                'purchase_request_id' => $solicitud->id,
                'actor_name_snapshot' => 'Odoo',
                'event_type' => PurchaseRequestEvent::ADOPTED_FROM_ODOO,
                'to_status' => PurchaseRequestStatus::APPROVED,
                'revision_number' => $solicitud->revision_number ?? 1,
                'comment' => sprintf('%s la creó en Odoo (%s); Laravel la trajo para recibir aquí las cotizaciones.', $base->created_by_name ?: 'Alguien', $proveedores),
                'metadata' => ['orders' => $todas->pluck('name')->all(), 'odoo_ids' => $todas->pluck('odoo_id')->all(), 'group' => $base->purchase_group_id],
            ]);

            return $solicitud->fresh('items');
        });
    }

    /** A quién le queda: el configurado, o el primer administrador. */
    private function duenio(): ?User
    {
        $id = config('purchase_requests.odoo.adopt_user_id');

        return $id ? User::find($id) : User::where('role', 'admin')->orderBy('id')->first();
    }

    private function unidad(?string $uom): string
    {
        return match (mb_strtolower(trim((string) $uom))) {
            '', 'units', 'unit', 'unidades', 'unidad', 'unit(s)' => 'Unidades',
            default => (string) $uom,
        };
    }
}
