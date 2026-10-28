<?php

namespace App\Services\DtePurchases;

use App\Models\DtePurchaseReference;
use App\Models\OdooAccountMove;
use App\Models\PurchaseProductLink;
use App\Models\PurchaseRequestItem;
use App\Services\PurchaseRequests\Odoo\OdooClient;
use App\Support\Rut;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Reads purchase provenance; never confirms, edits, pays or creates anything in Odoo. */
class OdooPurchaseReferenceSync
{
    public function __construct(private PurchaseReferences $references) {}

    public function sync(int $documentId, bool $dryRun = false): array
    {
        if (! config('dte_purchase_references.odoo_sync_enabled')) {
            return ['linked' => 0, 'reason' => 'La verificación con Odoo está desactivada.'];
        }
        foreach (['url', 'db', 'user', 'password'] as $key) {
            if (! filled(config('odoo.'.$key))) {
                return ['linked' => 0, 'reason' => 'Falta la configuración de lectura de Odoo.'];
            }
        }
        // IDs from QA and production cannot be mixed, even when their numbers coincide.
        if (rtrim((string) config('purchase_requests.odoo.url'), '/') !== rtrim((string) config('odoo.url'), '/')
            || config('purchase_requests.odoo.db') !== config('odoo.db')) {
            return ['linked' => 0, 'reason' => 'Solicitudes y contabilidad apuntan a instancias distintas de Odoo.'];
        }
        if (! $dryRun) {
            // An interrupted verification must not leave a formerly valid relation looking freshly checked.
            foreach ($this->references->active()->where('document_id', $documentId)->where('source', 'odoo')->get() as $existing) {
                $existing->update(['evidence' => array_replace($existing->evidence ?? [], ['verification_status' => 'unverified'])]);
            }
        }
        $document = $this->references->document($documentId);
        if (! $this->references->eligible($document)
            || DB::table('dte_purchase_decisions')->where('document_id', $documentId)->where('classification', 'without_request')->exists()) {
            return ['linked' => 0, 'reason' => 'La clasificación actual no admite una vinculación automática.'];
        }
        $rut = $this->rut($document->proveedor_rut);
        if (! $rut || ! ctype_digit((string) $document->folio)) {
            return ['linked' => 0, 'reason' => 'Falta un RUT o folio inequívoco.'];
        }
        $moves = OdooAccountMove::where('move_type', 'in_invoice')->where('state', 'posted')
            ->where('folio', (int) $document->folio)->whereDate('invoice_date', $document->fecha_factura)->get()
            ->filter(fn ($move): bool => $this->rut($move->partner_vat) === $rut
                && Quantity::of($move->amount_total)->isEqualTo(Quantity::of($document->monto_total)));
        if ($moves->count() !== 1) {
            return ['linked' => 0, 'reason' => 'No hay una factura contable única sincronizada para este proveedor, folio, fecha y total.'];
        }
        $client = new OdooClient((string) config('odoo.url'), (string) config('odoo.db'), (string) config('odoo.user'), (string) config('odoo.password'));
        $move = $this->readOne($client, 'account.move', $moves->first()->odoo_id, [
            'move_type', 'state', 'partner_id', 'company_id', 'currency_id', 'invoice_date', 'amount_total',
            'invoice_line_ids', 'l10n_latam_document_number', 'l10n_latam_document_type_id',
        ]);
        $supplier = $this->readOne($client, 'res.partner', $this->id($move['partner_id']), ['vat']);
        $company = $this->readOne($client, 'res.company', $this->id($move['company_id']), ['vat']);
        $type = $this->readOne($client, 'l10n_latam.document.type', $this->id($move['l10n_latam_document_type_id']), ['code']);
        if (($move['move_type'] ?? '') !== 'in_invoice' || ($move['state'] ?? '') !== 'posted'
            || $this->rut($supplier['vat'] ?? null) !== $rut
            || $this->rut($company['vat'] ?? null) !== $this->rut(config('purchase_requests.company.tax_id'))
            || ($move['currency_id'][1] ?? '') !== 'CLP'
            || (int) ($type['code'] ?? 0) !== (int) $document->tipo_dte
            || ! ctype_digit((string) ($move['l10n_latam_document_number'] ?? ''))
            || (int) $move['l10n_latam_document_number'] !== (int) $document->folio
            || ($move['invoice_date'] ?? '') !== $document->fecha_factura
            || ! Quantity::of($move['amount_total'])->isEqualTo(Quantity::of($document->monto_total))) {
            return ['linked' => 0, 'reason' => 'La factura de Odoo no pasó todas las comprobaciones de identidad.'];
        }

        $billed = $client->execute('account.move.line', 'read', [$move['invoice_line_ids']], ['fields' => [
            'name', 'quantity', 'price_unit', 'product_uom_id', 'purchase_line_id', 'product_id',
        ]]);
        $localLines = $this->references->lines($documentId);
        $edges = [];
        foreach ($billed as $billLine) {
            $edges[$billLine['id']] = $localLines->filter(fn ($line): bool => $this->sameBilledLine($line, $billLine))->pluck('id')->all();
        }
        $source = hash('sha256', rtrim((string) config('odoo.url'), '/').'|'.config('odoo.db'));
        $linked = 0;
        $refreshed = 0;
        $warnings = [];
        $proposals = [];
        foreach ($billed as $billLine) {
            if (! $this->id($billLine['purchase_line_id'] ?? false) || count($edges[$billLine['id']]) !== 1) {
                continue;
            }
            $lineId = $edges[$billLine['id']][0];
            if (count(array_filter($edges, fn ($ids): bool => in_array($lineId, $ids, true))) !== 1) {
                continue;
            }
            $purchaseLine = $this->readOne($client, 'purchase.order.line', $this->id($billLine['purchase_line_id']), ['order_id', 'product_uom', 'product_qty', 'product_id']);
            $orderId = $this->id($purchaseLine['order_id']);
            $order = $this->readOne($client, 'purchase.order', $orderId, ['name', 'origin', 'partner_id', 'company_id', 'state']);
            $items = PurchaseRequestItem::with('purchaseRequest')->where('odoo_line_id', $purchaseLine['id'])
                ->where('odoo_order_id', $orderId)->get();
            if ($items->count() !== 1) {
                continue;
            }
            $item = $items->first();
            $request = $item->purchaseRequest;
            preg_match_all('/\bSC-\d{4}-\d{6}\b/', (string) ($order['origin'] ?? ''), $origins);
            if (! in_array($request->folio, $origins[0], true)
                || ! in_array($request->status->value, ['approved', 'completed'], true)
                || $request->currency !== 'CLP'
                || ! in_array($order['state'] ?? '', ['purchase', 'done'], true)
                || $this->id($order['partner_id']) !== $this->id($move['partner_id'])
                || $this->id($order['company_id']) !== $this->id($move['company_id'])
                || $this->id($billLine['product_id']) !== $this->id($purchaseLine['product_id'])
                || $this->id($billLine['product_uom_id']) !== $this->id($purchaseLine['product_uom'])
                || ! Quantity::sameUnit($item->unit, $purchaseLine['product_uom'][1] ?? '')
                || ! Quantity::of($purchaseLine['product_qty'])->isEqualTo(Quantity::of($item->quantity))) {
                continue;
            }
            $line = $localLines->firstWhere('id', $lineId);
            $key = $source.':'.$billLine['id'];
            $data = [
                'document_line_id' => $lineId, 'purchase_request_item_id' => $item->id,
                'invoice_quantity' => (string) Quantity::of($line->cantidad), 'request_quantity' => (string) Quantity::of($line->cantidad),
                'version' => $this->references->fingerprint($document), 'line_version' => $this->references->lineFingerprint($line),
                'item_version' => $this->references->itemFingerprint($item),
                'note' => 'Factura y línea de compra verificadas en Odoo: '.$order['name'].'.',
            ];
            $evidence = ['source_key' => $key, 'source_instance' => $source, 'move_id' => $move['id'],
                'invoice_line_id' => $billLine['id'], 'purchase_line_id' => $purchaseLine['id'],
                'purchase_order_id' => $orderId, 'order_name' => $order['name'],
                'verification_status' => 'verified', 'verified_at' => now()->toIso8601String()];
            $proposals[] = [$data, $evidence];
        }
        // Finish all upstream reads before persisting anything. A failed RPC cannot leave a half-read invoice.
        if ($dryRun) {
            DB::beginTransaction();
        }
        try {
            foreach ($proposals as [$data, $evidence]) {
                try {
                    $existing = DtePurchaseReference::where('source_key', $evidence['source_key'])->first();
                    if ($existing) {
                        if (! $existing->revoked_at
                            && Quantity::of($existing->invoice_quantity)->isEqualTo(Quantity::of($data['invoice_quantity']))
                            && Quantity::of($existing->request_quantity)->isEqualTo(Quantity::of($data['request_quantity']))
                            && ($existing->snapshot['document_fingerprint'] ?? '') === $data['version']
                            && ($existing->snapshot['line_fingerprint'] ?? '') === $data['line_version']
                            && ($existing->snapshot['item_fingerprint'] ?? '') === $data['item_version']) {
                            $existing->update(['evidence' => $evidence]);
                            $refreshed++;
                        }

                        continue;
                    }
                    $this->references->link($documentId, $data, null, 'odoo', $evidence);
                    $linked++;
                } catch (ValidationException $exception) {
                    $warnings[] = array_values($exception->errors())[0][0];
                }
            }
        } finally {
            if ($dryRun) {
                DB::rollBack();
            }
        }

        return ['linked' => $linked, 'refreshed' => $refreshed, 'reason' => ($linked || $refreshed) ? 'Relaciones verificadas.' : 'No hay partidas con un recorrido completo y único.', 'warnings' => $warnings];
    }

    private function sameBilledLine(object $line, array $billLine): bool
    {
        $name = preg_replace('/^\[[^\]]+\]\s*/', '', (string) ($billLine['name'] ?? ''));

        return PurchaseProductLink::normalizar($line->descripcion ?? '') !== ''
            && PurchaseProductLink::normalizar($line->descripcion ?? '') === PurchaseProductLink::normalizar($name)
            && Quantity::of($line->cantidad)->isGreaterThan(0)
            && Quantity::of($line->cantidad)->isEqualTo(Quantity::of($billLine['quantity'] ?? 0))
            && Quantity::of($line->precio_unitario)->isEqualTo(Quantity::of($billLine['price_unit'] ?? 0))
            && Quantity::sameUnit($line->unidad, $billLine['product_uom_id'][1] ?? '');
    }

    private function rut(?string $value): ?string
    {
        return Rut::normalize(preg_replace('/^CL/i', '', trim((string) $value)));
    }

    private function id(mixed $value): int
    {
        return is_array($value) ? (int) ($value[0] ?? 0) : 0;
    }

    private function readOne(OdooClient $client, string $model, int $id, array $fields): array
    {
        if ($id <= 0) {
            throw new RuntimeException('Falta un identificador de origen en Odoo.');
        }
        $rows = $client->execute($model, 'read', [[$id]], ['fields' => $fields]);
        if (! is_array($rows) || count($rows) !== 1 || (int) $rows[0]['id'] !== $id) {
            throw new RuntimeException('Odoo no devolvió un registro único.');
        }

        return $rows[0];
    }
}
