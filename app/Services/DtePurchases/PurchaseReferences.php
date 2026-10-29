<?php

namespace App\Services\DtePurchases;

use App\Models\DtePurchaseReference;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use App\Services\PurchaseRequests\Products\ProductSimilarity;
use App\Support\Rut;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReferences
{
    public const REQUEST_STATES = ['submitted', 'resubmitted', 'changes_requested', 'approved', 'completed'];

    private const SYSTEM = ['odoo' => 'Sincronización de Odoo', 'auto' => 'Vínculo automático'];

    /** A price within this many percent of the request's is the same price. */
    public const PRICE_TOLERANCE = 5.0;

    public function document(int $id): object
    {
        return DB::connection('fuelcontrol')->table('gmail_dte_documents')->where('id', $id)->firstOrFail();
    }

    public function lines(int $id): Collection
    {
        return DB::connection('fuelcontrol')->table('gmail_dte_document_lines')
            ->where('document_id', $id)->orderBy('nro_linea')->orderBy('id')->get();
    }

    public function eligible(object $document): bool
    {
        return in_array((int) $document->tipo_dte, [33, 34, 46], true)
            && ! in_array(mb_strtolower($document->workflow_status ?? ''), ['anulado', 'rechazado'], true);
    }

    public function fingerprint(object $document): string
    {
        return hash('sha256', json_encode([
            $document->tipo_dte, $this->folio($document), Rut::normalize($document->proveedor_rut),
            $document->fecha_factura, (string) Quantity::of($document->monto_total),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function identityKey(object $document): string
    {
        // An issuer cannot issue two documents of the same type and folio.
        // Date or total disagreements mean an import needs review, not a new invoice.
        return hash('sha256', PurchaseRequest::COMPANY_CODE.'|'.Rut::normalize($document->proveedor_rut).'|'.$document->tipo_dte.'|'.$this->folio($document));
    }

    private function folio(object $document): string
    {
        $folio = trim((string) $document->folio);

        return ctype_digit($folio) ? (ltrim($folio, '0') ?: '0') : mb_strtoupper($folio);
    }

    public function lineFingerprint(object $line): string
    {
        return hash('sha256', json_encode([
            $line->id, $line->document_id, $line->codigo, $line->descripcion, $line->unidad,
            (string) Quantity::of($line->cantidad), (string) Quantity::of($line->precio_unitario),
            (string) Quantity::of($line->monto_item),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function itemFingerprint(PurchaseRequestItem $item): string
    {
        return hash('sha256', json_encode([
            $item->id, $item->purchase_request_id, $item->product_service, $item->specification,
            $item->unit, (string) Quantity::of($item->quantity), $item->odoo_order_id, $item->odoo_line_id,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function active(): \Illuminate\Database\Eloquent\Builder
    {
        return DtePurchaseReference::query()->whereNull('revoked_at');
    }

    public function usedOnLine(int $lineId): string
    {
        return (string) Quantity::of($this->active()->where('document_line_id', $lineId)->sum('invoice_quantity'));
    }

    public function usedOnItem(int $itemId): string
    {
        // Stale references still reserve their quantities until somebody reviews them;
        // what a credit note gave back is pending again.
        return (string) Quantity::of($this->active()->where('purchase_request_item_id', $itemId)->sum('request_quantity'))
            ->minus(Quantity::of($this->returnedByItem([$itemId])[$itemId] ?? 0));
    }

    /**
     * What credit notes gave back of each item, in request units: Implementos
     * invoiced 17 sprays in 7100520 and took 2 back with NC 361827.
     *
     * @return array<int, string> item id → quantity
     */
    public function returnedByItem(array $itemIds): array
    {
        $returned = [];
        $references = $this->active()->whereIn('purchase_request_item_id', $itemIds)->get();
        foreach ($this->returnedByReference($references) as $id => $quantity) {
            $itemId = $references->firstWhere('id', $id)->purchase_request_item_id;
            $returned[$itemId] = (string) Quantity::of($returned[$itemId] ?? 0)->plus($quantity);
        }

        return $returned;
    }

    /**
     * The same per reference. When several references share an invoice line,
     * the units go back in the order they were linked.
     *
     * @return array<int, \Brick\Math\BigDecimal> reference id → request units
     */
    public function returnedByReference(Collection $references): array
    {
        $references = $references->filter(fn ($ref): bool => (bool) $ref->document_line_id);
        if ($references->isEmpty()) {
            return [];
        }
        $fuel = DB::connection('fuelcontrol');
        $documents = $fuel->table('gmail_dte_documents')->whereIn('id', $references->pluck('document_id')->unique())
            ->get(['id', 'folio', 'tipo_dte', 'proveedor_rut']);
        $notes = app(CreditNotes::class)->forDocuments($documents);
        if ($notes === []) {
            return [];
        }
        // Every line of each invoice: a note's line goes to the one it resembles most.
        $invoiceLines = $fuel->table('gmail_dte_document_lines')->whereIn('document_id', array_keys($notes))->get();
        $lines = $invoiceLines->keyBy('id');
        $byDocument = $invoiceLines->groupBy('document_id');
        $returned = [];
        foreach ($references->sortBy('id')->groupBy('document_line_id') as $lineId => $group) {
            $line = $lines->get($lineId);
            if (! $line || ! isset($notes[$line->document_id])) {
                continue;
            }
            $left = Quantity::of(number_format(app(CreditNotes::class)->returned($notes[$line->document_id], $line, $byDocument->get($line->document_id)), 6, '.', ''));
            foreach ($group as $ref) {
                $invoiced = Quantity::of($ref->invoice_quantity);
                if (! $left->isGreaterThan(0) || $invoiced->isZero()) {
                    break;
                }
                $take = $left->isLessThan($invoiced) ? $left : $invoiced;
                $returned[$ref->id] = $take->multipliedBy(Quantity::of($ref->request_quantity))
                    ->dividedBy($invoiced, 6, \Brick\Math\RoundingMode::HALF_UP);
                $left = $left->minus($take);
            }
        }

        return $returned;
    }

    public function report(int $id): array
    {
        $document = $this->document($id);
        $lines = $this->lines($id)->keyBy('id');
        $references = $this->active()->with(['requestItem', 'purchaseRequest'])->where('document_id', $id)->get();
        $net = $this->netFactor($document, $lines);
        $returned = $this->returnedByReference($references);
        $notes = app(CreditNotes::class)->forDocuments([$document])[$id] ?? [];
        $rows = $references->map(function (DtePurchaseReference $ref) use ($document, $lines, $net, $returned): array {
            $line = $lines->get($ref->document_line_id);
            $stale = ! $line || ! $ref->requestItem || ! $ref->purchaseRequest
                || ! $this->eligible($document)
                || ! in_array($ref->purchaseRequest?->status->value, self::REQUEST_STATES, true)
                || ($ref->purchaseRequest && ($ref->purchaseRequest->company_code !== PurchaseRequest::COMPANY_CODE
                    || $ref->purchaseRequest->folio !== $ref->snapshot['request_folio']
                    || $ref->purchaseRequest->request_date->toDateString() > (string) $document->fecha_factura))
                || ($ref->snapshot['document_fingerprint'] ?? '') !== $this->fingerprint($document)
                || ($line && ($ref->snapshot['line_fingerprint'] ?? '') !== $this->lineFingerprint($line))
                || ($ref->requestItem && ($ref->snapshot['item_fingerprint'] ?? '') !== $this->itemFingerprint($ref->requestItem));
            if ($ref->source === 'odoo') {
                $stale = $stale || ($ref->evidence['verification_status'] ?? '') !== 'verified'
                    || ! in_array($ref->purchaseRequest?->status->value, ['approved', 'completed'], true)
                    || \Illuminate\Support\Carbon::parse($ref->evidence['verified_at'] ?? '1970-01-01')->lt(now()->subDays(7));
            }

            return [
                'id' => $ref->id, 'request_id' => $ref->purchase_request_id, 'source' => $ref->source, 'stale' => $stale,
                'folio' => $ref->snapshot['request_folio'],
                'request_url' => $ref->purchaseRequest ? route('purchase_requests.show', $ref->purchaseRequest) : null,
                'invoice_product' => self::text($ref->snapshot['invoice_product']), 'invoice_unit' => $ref->snapshot['invoice_unit'],
                'request_product' => $ref->snapshot['request_product'], 'request_unit' => $ref->snapshot['request_unit'],
                'invoice_quantity' => $ref->invoice_quantity, 'request_quantity' => $ref->request_quantity,
                'requested_quantity' => $ref->snapshot['requested_quantity'],
                'remaining_request' => $ref->requestItem ? (string) Quantity::of($ref->requestItem->quantity)->minus($this->usedOnItem($ref->requestItem->id)) : null,
                'actor' => $ref->actor_name ?: self::SYSTEM[$ref->source] ?? 'Sistema', 'date' => $ref->created_at->toIso8601String(),
                'note' => $ref->note,
                'price' => $this->price($line, $ref, $net),
                'returned' => isset($returned[$ref->id]) ? (string) $returned[$ref->id] : null,
                'measures' => $this->measureWarning((string) $ref->snapshot['invoice_product'], (string) $ref->snapshot['request_product']),
            ];
        })->values();
        $priced = $rows->filter(fn (array $row): bool => ! $row['stale'] && $row['price'] !== null);
        $requested = $priced->sum(fn (array $row): float => $row['price']['requested']);
        $invoiced = $priced->sum(fn (array $row): float => $row['price']['invoiced']);
        $billedItems = $references->pluck('purchase_request_item_id')->all();
        $decision = DB::table('dte_purchase_decisions')->where('document_id', $id)->first();
        $withoutRequest = ($decision?->classification === 'without_request');
        $decisionStale = $withoutRequest && $decision->document_fingerprint !== $this->fingerprint($document);
        $lineRows = $lines->map(fn ($line): array => [
            'id' => $line->id, 'description' => self::text($line->descripcion), 'unit' => $line->unidad,
            'quantity' => (string) Quantity::of($line->cantidad),
            'version' => $this->lineFingerprint($line),
            'remaining' => (string) Quantity::of($line->cantidad)->minus($this->usedOnLine($line->id)),
        ])->values();
        $complete = $lineRows->isNotEmpty() && $lineRows->every(fn ($line): bool => Quantity::of($line['remaining'])->isZero())
            && $rows->every(fn ($row): bool => ! $row['stale']);

        return [
            'document_id' => $id, 'eligible' => $this->eligible($document),
            'classification' => $withoutRequest ? 'without_request' : ($rows->isEmpty() ? 'unidentified' : ($complete ? 'linked' : 'partial')),
            'decision_stale' => $decisionStale, 'decision_note' => $decision?->note,
            'version' => $this->fingerprint($document), 'lines' => $lineRows, 'references' => $rows,
            'prices' => $priced->isEmpty() ? null : [
                'requested' => $requested, 'invoiced' => $invoiced, 'difference' => $invoiced - $requested,
                'change' => $requested > 0 ? round(($invoiced - $requested) * 100 / $requested, 1) : null,
                'without_price' => $rows->filter(fn (array $row): bool => ! $row['stale'] && $row['price'] === null)->count(),
            ],
            'price_tolerance' => self::PRICE_TOLERANCE,
            'credit_notes' => array_map(fn (array $note): array => ['folio' => $note['folio'], 'annuls' => $note['annuls'],
                'net' => $note['net'], 'reason' => $note['reason']], $notes),
            'unbilled' => $references->pluck('purchaseRequest')->filter()->unique('id')
                ->mapWithKeys(fn (PurchaseRequest $request): array => [$request->id => $this->unbilled($request, $billedItems)]),
            'history' => DB::table('dte_purchase_events')->where('document_id', $id)->orderByDesc('id')->limit(15)
                ->get(['event', 'actor_name', 'details', 'created_at'])->map(function ($event) {
                    $event->details = json_decode($event->details, true);
                    $event->created_at = \Illuminate\Support\Carbon::parse($event->created_at)->toIso8601String();

                    return $event;
                }),
        ];
    }

    public function link(int $documentId, array $data, ?User $actor, string $source = 'manual', array $evidence = []): DtePurchaseReference
    {
        return DB::transaction(function () use ($documentId, $data, $actor, $source, $evidence): DtePurchaseReference {
            $state = $this->lockDocument($documentId);
            $document = $this->document($documentId);
            $this->require($this->eligible($document), 'Este documento no admite vínculos de compra.');
            $this->require($state->classification !== 'without_request', 'Primero vuelve a revisar la clasificación «Compra sin solicitud».');
            $this->require(hash_equals($this->fingerprint($document), $data['version'] ?? ''), 'La factura cambió. Recarga antes de confirmar.');
            $line = $this->lines($documentId)->firstWhere('id', (int) $data['document_line_id']);
            $this->require($line !== null, 'La partida no pertenece a esta factura.');
            $item = PurchaseRequestItem::with('purchaseRequest')->lockForUpdate()->findOrFail($data['purchase_request_item_id']);
            $request = $item->purchaseRequest;
            $this->require($request->company_code === PurchaseRequest::COMPANY_CODE
                && in_array($request->status->value, self::REQUEST_STATES, true), 'La solicitud está en borrador, anulada o rechazada.');
            $this->require($request->request_date->toDateString() <= (string) $document->fecha_factura, 'La solicitud es posterior a la factura. Revisa las fechas.');
            $rut = Rut::normalize($document->proveedor_rut);
            $this->require($rut !== null, 'La factura no tiene un RUT válido de proveedor.');
            $this->claimIdentity($documentId, $this->identityKey($document));
            $changedIdentity = $this->active()->where('document_id', $documentId)->get(['snapshot'])
                ->contains(fn ($ref): bool => ($ref->snapshot['document_fingerprint'] ?? '') !== $this->fingerprint($document));
            $this->require(! $changedIdentity, 'La identidad de la factura cambió. Revisa y desvincula las relaciones anteriores antes de confirmar otras.');
            $this->require(! $this->active()->where('purchase_request_id', $request->id)->whereNull('purchase_request_item_id')->exists(),
                'Hay relaciones con partidas eliminadas de esta solicitud. Revísalas y desvincúlalas antes de confirmar otras.');
            $duplicate = $this->active()->where('supplier_rut', $rut)->where('document_id', '<>', $documentId)
                ->get(['snapshot'])->contains(fn ($ref): bool => ($ref->snapshot['document_fingerprint'] ?? '') === $this->fingerprint($document));
            $this->require(! $duplicate, 'La misma factura ya está vinculada desde otro registro DTE. Revisa el duplicado antes de continuar.');
            $invoiceQty = Quantity::of($data['invoice_quantity']);
            $requestQty = Quantity::of($data['request_quantity']);
            if ($source === 'manual' && filled($data['operation_key'] ?? null)) {
                $previous = DtePurchaseReference::where('operation_key', $data['operation_key'])->first();
                if ($previous) {
                    $this->require($previous->document_id === $documentId
                        && $previous->document_line_id === $line->id
                        && $previous->purchase_request_item_id === $item->id
                        && Quantity::of($previous->invoice_quantity)->isEqualTo($invoiceQty)
                        && Quantity::of($previous->request_quantity)->isEqualTo($requestQty), 'Este envío ya se usó para otra relación. Recarga la pantalla.');

                    return $previous;
                }
            }
            $this->require($invoiceQty->isGreaterThan(0) && $requestQty->isGreaterThan(0), 'Las cantidades deben ser mayores que cero.');
            $this->require($invoiceQty->isLessThanOrEqualTo('999999999999.999999') && $requestQty->isLessThanOrEqualTo('999999999999.999999'), 'La cantidad excede el límite permitido.');
            $this->require(($data['line_version'] ?? '') === $this->lineFingerprint($line)
                && ($data['item_version'] ?? '') === $this->itemFingerprint($item), 'Una partida cambió. Vuelve a buscarla antes de confirmar.');

            if ($source !== 'manual') {
                $existing = DtePurchaseReference::where('source_key', $evidence['source_key'])->first();
                if ($existing) {
                    // Includes revoked decisions: an automatic sync never undoes a human correction.
                    return $existing;
                }
            }

            $this->require($invoiceQty->plus($this->usedOnLine($line->id))->isLessThanOrEqualTo(Quantity::of($line->cantidad)), 'Esa cantidad de la factura ya está vinculada o supera lo facturado.');
            $this->require($requestQty->plus($this->usedOnItem($item->id))->isLessThanOrEqualTo(Quantity::of($item->quantity)), 'La cantidad supera lo pendiente de vincular en la solicitud.');
            $conversion = ! Quantity::sameUnit($line->unidad, $item->unit)
                || Quantity::packaged($line->descripcion ?? '') || Quantity::packaged($item->product_service)
                || ! $invoiceQty->isEqualTo($requestQty);
            // The click confirms the supplier; only an equivalence between different
            // presentations needs to be explained.
            if ($source === 'manual' && $conversion) {
                $this->require(($data['acknowledge'] ?? false) && mb_strlen(trim($data['note'] ?? '')) >= 10,
                    'Confirma la equivalencia de cantidades y explica la relación en al menos 10 caracteres.');
            }
            $this->require($source === 'manual' || ! $conversion, 'La presentación o unidad requiere confirmación manual.');
            $ref = DtePurchaseReference::create([
                'document_id' => $documentId, 'document_line_id' => $line->id,
                'purchase_request_id' => $request->id, 'purchase_request_item_id' => $item->id,
                'invoice_quantity' => (string) $invoiceQty, 'request_quantity' => (string) $requestQty,
                'supplier_rut' => $rut, 'source' => $source, 'source_key' => $evidence['source_key'] ?? null,
                'operation_key' => $data['operation_key'] ?? null,
                'evidence' => $evidence ?: null, 'note' => trim($data['note'] ?? '') ?: 'Vínculo confirmado.',
                'actor_id' => $actor?->id, 'actor_name' => $actor?->name,
                'snapshot' => [
                    'document_fingerprint' => $this->fingerprint($document), 'line_fingerprint' => $this->lineFingerprint($line),
                    'item_fingerprint' => $this->itemFingerprint($item), 'request_folio' => $request->folio,
                    'invoice_product' => $line->descripcion, 'invoice_unit' => $line->unidad,
                    'request_product' => $item->product_service, 'request_unit' => $item->unit,
                    'requested_quantity' => (string) Quantity::of($item->quantity),
                ],
            ]);
            $this->event($documentId, 'linked', $actor, ['reference_id' => $ref->id, 'folio' => $request->folio, 'source' => $source, 'note' => $ref->note]);

            return $ref;
        }, 3);
    }

    public function revoke(int $documentId, int $referenceId, User $actor, string $note): void
    {
        DB::transaction(function () use ($documentId, $referenceId, $actor, $note): void {
            $this->lockDocument($documentId);
            $ref = DtePurchaseReference::where('document_id', $documentId)->lockForUpdate()->findOrFail($referenceId);
            if ($ref->revoked_at) {
                return;
            }
            if ($ref->purchase_request_item_id) {
                PurchaseRequestItem::lockForUpdate()->find($ref->purchase_request_item_id);
            }
            $ref->update(['revoked_at' => now(), 'revoked_by' => $actor->id, 'revocation_note' => $note]);
            if (! $this->active()->where('document_id', $documentId)->exists()) {
                DB::table('dte_purchase_decisions')->where('document_id', $documentId)->update(['invoice_key' => null]);
            }
            $this->event($documentId, 'revoked', $actor, ['reference_id' => $ref->id, 'folio' => $ref->snapshot['request_folio'], 'note' => $note]);
        }, 3);
    }

    public function classify(int $documentId, string $classification, string $version, string $note, User $actor): void
    {
        DB::transaction(function () use ($documentId, $classification, $version, $note, $actor): void {
            $this->lockDocument($documentId);
            $document = $this->document($documentId);
            $this->require($this->eligible($document), 'Este documento no admite clasificación de compra.');
            $this->require($this->fingerprint($document) === $version, 'La factura cambió. Recarga antes de confirmar.');
            $this->require($classification !== 'without_request' || (Rut::normalize($document->proveedor_rut) !== null && $this->folio($document) !== ''),
                'Revisa el RUT y el folio de la factura antes de clasificarla como compra sin solicitud.');
            $hasReferences = $this->active()->where('document_id', $documentId)->exists();
            $this->require(! $hasReferences, 'Desvincula primero las partidas de solicitudes existentes.');
            $this->claimIdentity($documentId, $classification === 'without_request' ? $this->identityKey($document) : null);
            DB::table('dte_purchase_decisions')->where('document_id', $documentId)->update([
                'classification' => $classification, 'document_fingerprint' => $version,
                'note' => $note, 'actor_id' => $actor->id, 'updated_at' => now(),
            ]);
            $this->event($documentId, $classification, $actor, ['note' => $note]);
        }, 3);
    }

    public function forRequest(PurchaseRequest $request): array
    {
        $ids = $this->active()->where('purchase_request_id', $request->id)->distinct()->pluck('document_id');

        return $ids->map(function ($id) use ($request): ?array {
            $document = DB::connection('fuelcontrol')->table('gmail_dte_documents')->where('id', $id)->first();
            if (! $document) {
                return ['folio' => 'Documento eliminado', 'supplier' => '', 'date' => null, 'url' => null, 'references' => []];
            }

            return [
                'folio' => $document->folio, 'supplier' => $document->proveedor_nombre, 'date' => $document->fecha_factura,
                'url' => route('gmail.dtes.show', $id).'#referencias-cruzadas',
                'references' => array_values(array_filter($this->report($id)['references']->all(), fn ($row): bool => $row['request_id'] === $request->id)),
            ];
        })->all();
    }

    /**
     * Items of the request still waiting for an invoice, leaving out `$except`
     * (those this invoice already covers, which show their own remainder).
     *
     * @return list<array{product: string, specification: ?string, quantity: string, remaining: string, unit: ?string}>
     */
    public function unbilled(PurchaseRequest $request, array $except = []): array
    {
        $used = $this->active()->whereIn('purchase_request_item_id', $request->items->pluck('id'))
            ->groupBy('purchase_request_item_id')->selectRaw('purchase_request_item_id, sum(request_quantity) as total')
            ->pluck('total', 'purchase_request_item_id');
        $returned = $this->returnedByItem($request->items->pluck('id')->all());

        return $request->items->reject(fn (PurchaseRequestItem $item): bool => in_array($item->id, $except, true))
            ->map(fn (PurchaseRequestItem $item): array => [
                'product' => $item->product_service, 'specification' => $item->specification,
                'quantity' => (string) Quantity::of($item->quantity), 'unit' => $item->unit,
                'remaining' => (string) Quantity::of($item->quantity)->minus(Quantity::of($used[$item->id] ?? 0))
                    ->plus(Quantity::of($returned[$item->id] ?? 0)),
            ])
            ->filter(fn (array $item): bool => Quantity::of($item['remaining'])->isGreaterThan(0))->values()->all();
    }

    /**
     * What the invoice charges for this link against what the request expected,
     * both net and in request units, so a box of 12 compares with 12 sprays.
     */
    private function price(?object $line, DtePurchaseReference $ref, float $net): ?array
    {
        $item = $ref->requestItem;
        $request = $ref->purchaseRequest;
        if (! $line || ! $item || ! $request || (float) $item->unit_price <= 0 || (float) $line->cantidad <= 0
            || (float) $ref->request_quantity <= 0 || strtoupper($request->currency ?: 'CLP') !== 'CLP') {
            return null;
        }
        $lineNet = ((float) $line->monto_item > 0 ? (float) $line->monto_item : (float) $line->precio_unitario * (float) $line->cantidad) * $net;
        $invoiced = $lineNet / (float) $line->cantidad * (float) $ref->invoice_quantity;
        $unit = (float) $item->unit_price;
        if ($request->prices_include_tax && empty($line->es_exento)) {
            $unit /= 1 + ((float) ($line->impuesto_tasa ?? 0) ?: 19.0) / 100;
        }
        $requested = $unit * (float) $ref->request_quantity;

        return [
            'requested_unit' => round($unit, 2), 'invoiced_unit' => round($invoiced / (float) $ref->request_quantity, 2),
            'requested' => round($requested), 'invoiced' => round($invoiced), 'difference' => round($invoiced) - round($requested),
            'change' => round(($invoiced - $requested) * 100 / $requested, 1),
        ];
    }

    /**
     * What turns the line amounts into net ones. Some suppliers write the lines
     * with VAT and never say so: Ferretería Bulnes' 82827 adds up to the total,
     * not the net, and every product looked 19 % dearer than requested.
     */
    private function netFactor(object $document, Collection $lines): float
    {
        $sum = $lines->sum(fn ($line): float => (float) $line->monto_item);

        return (float) $document->monto_iva > 0 && $sum > 0
            && abs($sum - (float) $document->monto_total) <= 2 && abs($sum - (float) $document->monto_neto) > 2
            ? (float) $document->monto_neto / $sum : 1.0;
    }

    /**
     * Names that read alike once the numbers are set aside, but whose numbers
     * disagree: «HUINCHA 3/4X10MTS» for «3/4X20MTS». Unlike names are left
     * alone, since their numbers are models and codes («SOLDADURA 6011»).
     *
     * @return array{invoice: list<string>, request: list<string>}|null
     */
    public function measureWarning(string $invoice, string $request): ?array
    {
        $difference = Measures::difference($invoice, $request);
        $words = fn (string $name): string => preg_replace('/\d+(?:\s*[.,\/]\s*\d+)*/', ' ', self::text($name));

        return $difference && app(ProductSimilarity::class)->score($words($invoice), $words($request)) >= PurchaseCandidates::SUGGEST
            ? $difference : null;
    }

    /** XML descriptions arrive with entities: «1 1&#47;2&quot;» is «1 1/2"». */
    public static function text(?string $value): string
    {
        return html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function lockDocument(int $id): object
    {
        DB::table('dte_purchase_decisions')->insertOrIgnore([
            'document_id' => $id, 'classification' => 'unidentified', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('dte_purchase_decisions')->where('document_id', $id)->lockForUpdate()->first();
    }

    private function claimIdentity(int $id, ?string $key): void
    {
        try {
            DB::table('dte_purchase_decisions')->where('document_id', $id)->update(['invoice_key' => $key]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['reference' => 'La misma factura ya tiene una decisión en otro registro DTE. Revisa el duplicado antes de continuar.']);
        }
    }

    private function event(int $id, string $event, ?User $actor, array $details): void
    {
        DB::table('dte_purchase_events')->insert([
            'document_id' => $id, 'event' => $event, 'actor_id' => $actor?->id,
            'actor_name' => $actor?->name ?: self::SYSTEM[$details['source'] ?? 'odoo'] ?? 'Sistema',
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['reference' => $message]);
        }
    }
}
