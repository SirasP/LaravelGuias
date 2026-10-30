<?php

namespace App\Console\Commands;

use App\Models\OdooPurchaseOrder;
use App\Models\OdooPurchaseOrderLine;
use App\Models\PurchaseRequest;
use App\Services\PurchaseRequests\Odoo\OdooClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Trae las órdenes de compra que se hicieron directo en Odoo, sin solicitud.
 *
 * Una orden es «de Laravel» sólo si Laravel la creó: la solicitud o una de sus
 * partidas guarda su id (`odoo_order_id`). Las demás se copian, aunque lleven
 * el folio de una solicitud en su origen: las cotizaciones que el jefe manda a
 * otros proveedores desde Odoo a partir de la SC-2026-000106 (P00371 a P00374)
 * no estaban en ninguna parte de Laravel, y la pestaña no las mostraba.
 *
 * Lee. No escribe nada en Odoo, nunca.
 */
class OdooSyncPurchaseOrders extends Command
{
    protected $signature = 'odoo:sync-purchase-orders
                            {--dry-run : Muestra lo que traería sin guardar nada}';

    protected $description = 'Copia las órdenes de compra de Odoo que no salieron de una solicitud';

    private const ORDER_FIELDS = [
        'id', 'name', 'state', 'partner_id', 'partner_ref', 'origin', 'purchase_group_id', 'currency_id', 'amount_untaxed', 'amount_total',
        'create_uid', 'user_id', 'date_order', 'invoice_status', 'receipt_status', 'notes', 'create_date', 'write_date',
    ];

    private const LINE_FIELDS = [
        'id', 'order_id', 'sequence', 'display_type', 'product_id', 'name', 'product_qty', 'product_uom',
        'price_unit', 'discount', 'price_subtotal', 'qty_received', 'qty_invoiced',
    ];

    private ?\Illuminate\Support\Collection $exported = null;

    public function handle(): int
    {
        $this->exported = null;
        // La misma instancia a la que exportan las solicitudes: los ids que
        // separan lo de Laravel de lo de Odoo sólo valen dentro de ella.
        $url = (string) config('purchase_requests.odoo.url');
        $db = (string) config('purchase_requests.odoo.db');
        if ($url === '' || $db === '') {
            $this->error('Falta la configuración de Odoo. Revisa PURCHASE_REQUESTS_ODOO_URL y _DB (o las ODOO_* compartidas).');

            return self::FAILURE;
        }
        $this->line('Odoo: <fg=cyan>'.$db.'</>');

        $client = new OdooClient($url, $db, (string) config('purchase_requests.odoo.user'),
            (string) config('purchase_requests.odoo.password'), (int) config('purchase_requests.odoo.timeout', 30));

        try {
            $orders = $this->read($client, 'purchase.order', [], self::ORDER_FIELDS);
            $orders = array_values(array_filter($orders, fn (array $order): bool => ! $this->fromLaravel($order)));
            $lines = $this->linesOf($client, array_column($orders, 'id'));
            $vats = $this->vatsOf($client, array_filter(array_map(fn (array $order): ?int => $this->id($order['partner_id']), $orders)));
        } catch (Throwable $e) {
            $this->error('Odoo devolvió un error: '.$e->getMessage());

            return self::FAILURE;
        }

        $states = array_count_values(array_column($orders, 'state'));
        $this->line(sprintf('  órdenes sin solicitud: %d (%s)', count($orders),
            implode(', ', array_map(fn ($state, $count) => (OdooPurchaseOrder::STATE_LABELS[$state] ?? $state).' '.$count, array_keys($states), $states))));
        $known = OdooPurchaseOrder::pluck('odoo_id')->flip();
        $new = array_filter($orders, fn (array $order): bool => ! $known->has($order['id']));
        $this->line('  nuevas desde la última vez: '.count($new));

        if ($this->option('dry-run')) {
            foreach ($new as $order) {
                $this->line(sprintf('    %s · %s · %s', $order['name'], $this->name($order['partner_id']) ?? '—', $order['state']));
            }
            $this->warn('Simulación: no se guardó nada.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($orders, $lines, $vats): void {
            $now = now();
            $ids = [];
            foreach ($orders as $order) {
                $local = OdooPurchaseOrder::updateOrCreate(['odoo_id' => $order['id']], [
                    'name' => $order['name'], 'state' => $order['state'],
                    'partner_odoo_id' => $this->id($order['partner_id']), 'partner_name' => $this->name($order['partner_id']),
                    'partner_vat' => $vats[$this->id($order['partner_id'])] ?? null,
                    'partner_ref' => $order['partner_ref'] ?: null, 'origin' => $order['origin'] ?: null,
                    'purchase_group_id' => $this->id($order['purchase_group_id'] ?? false),
                    'currency' => $this->name($order['currency_id']),
                    'amount_untaxed' => (float) $order['amount_untaxed'], 'amount_total' => (float) $order['amount_total'],
                    'created_by_name' => $this->name($order['create_uid']), 'buyer_name' => $this->name($order['user_id']),
                    'date_order' => $this->date($order['date_order']), 'invoice_status' => $order['invoice_status'] ?: null,
                    'receipt_status' => $order['receipt_status'] ?: null, 'notes' => $this->plain($order['notes']),
                    'odoo_created_at' => $this->date($order['create_date']), 'odoo_updated_at' => $this->date($order['write_date']),
                    'synced_at' => $now,
                ]);
                $ids[$order['id']] = $local->id;
            }

            $lineIds = [];
            foreach ($lines as $line) {
                $orderId = $ids[$this->id($line['order_id'])] ?? null;
                if ($orderId === null) {
                    continue;
                }
                OdooPurchaseOrderLine::updateOrCreate(['odoo_id' => $line['id']], [
                    'odoo_purchase_order_id' => $orderId, 'sequence' => (int) $line['sequence'],
                    'display_type' => $line['display_type'] ?: null, 'product_odoo_id' => $this->id($line['product_id']),
                    'name' => $line['name'] ?: null, 'product_qty' => (float) $line['product_qty'],
                    'uom_name' => $this->name($line['product_uom']), 'price_unit' => (float) $line['price_unit'],
                    'discount' => (float) $line['discount'], 'price_subtotal' => (float) $line['price_subtotal'],
                    'qty_received' => (float) $line['qty_received'], 'qty_invoiced' => (float) $line['qty_invoiced'],
                ]);
                $lineIds[] = $line['id'];
            }

            // Lo que ya no está: se borró en Odoo, o resultó ser de una solicitud.
            // Una lectura vacía no borra nada; es más probable un fallo que un Odoo sin compras.
            if ($ids !== []) {
                OdooPurchaseOrder::whereNotIn('odoo_id', array_keys($ids))->delete();
                OdooPurchaseOrderLine::whereIn('odoo_purchase_order_id', $ids)->whereNotIn('odoo_id', $lineIds)->delete();
            }
        });

        $this->info(sprintf('✓ %d órdenes y %d líneas al día.', OdooPurchaseOrder::count(), OdooPurchaseOrderLine::count()));
        // Con las órdenes al día, las facturas recientes que sin duda son de una de ellas.
        $linked = app(\App\Services\DtePurchases\OdooOrderLinks::class)->linkRecent();
        $this->info("✓ {$linked} facturas vinculadas con su orden.");

        return self::SUCCESS;
    }

    /** La creó Laravel: la solicitud o una de sus partidas apunta a ella. */
    private function fromLaravel(array $order): bool
    {
        $this->exported ??= PurchaseRequest::whereNotNull('odoo_order_id')->pluck('odoo_order_id')
            ->merge(\App\Models\PurchaseRequestItem::whereNotNull('odoo_order_id')->pluck('odoo_order_id'))
            ->map(fn ($id) => (int) $id)->unique()->flip();

        return $this->exported->has((int) $order['id']);
    }

    /** @return list<array<string, mixed>> */
    private function read(OdooClient $client, string $model, array $domain, array $fields): array
    {
        $records = [];
        for ($offset = 0; ; $offset += 200) {
            $page = $client->execute($model, 'search_read', [$domain],
                ['fields' => $fields, 'limit' => 200, 'offset' => $offset, 'order' => 'id asc']);
            array_push($records, ...$page);
            if (count($page) < 200) {
                return $records;
            }
        }
    }

    /**
     * El RUT de cada proveedor. Un contacto («Coagra S.A., Catalina Zuñiga»)
     * no suele tener RUT propio: vale el de su empresa.
     *
     * @return array<int, string>
     */
    private function vatsOf(OdooClient $client, array $partnerIds): array
    {
        $partnerIds = array_values(array_unique($partnerIds));
        if ($partnerIds === []) {
            return [];
        }
        $partners = $client->execute('res.partner', 'read', [$partnerIds], ['fields' => ['vat', 'commercial_partner_id']]);
        $companies = array_values(array_unique(array_filter(array_map(
            fn (array $partner): ?int => $partner['vat'] ? null : $this->id($partner['commercial_partner_id']), $partners))));
        $companyVats = $companies === [] ? [] : array_column(
            $client->execute('res.partner', 'read', [$companies], ['fields' => ['vat']]), 'vat', 'id');
        $vats = [];
        foreach ($partners as $partner) {
            $vat = $partner['vat'] ?: ($companyVats[$this->id($partner['commercial_partner_id'])] ?? null);
            if ($vat) {
                $vats[(int) $partner['id']] = (string) $vat;
            }
        }

        return $vats;
    }

    /** @return list<array<string, mixed>> */
    private function linesOf(OdooClient $client, array $orderIds): array
    {
        $lines = [];
        foreach (array_chunk($orderIds, 200) as $chunk) {
            array_push($lines, ...$this->read($client, 'purchase.order.line', [['order_id', 'in', $chunk]], self::LINE_FIELDS));
        }

        return $lines;
    }

    /** Odoo manda `[id, "nombre"]` o `false`. */
    private function id(mixed $value): ?int
    {
        return is_array($value) ? (int) $value[0] : null;
    }

    private function name(mixed $value): ?string
    {
        return is_array($value) ? (string) $value[1] : null;
    }

    /** Odoo guarda en UTC. */
    private function date(mixed $value): ?Carbon
    {
        return $value ? Carbon::parse($value, 'UTC')->setTimezone(config('app.timezone')) : null;
    }

    private function plain(mixed $html): ?string
    {
        $text = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '</p>', '</div>'], "\n", (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $html && $text !== '' ? preg_replace("/\n{2,}/", "\n", $text) : null;
    }
}
