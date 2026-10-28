<?php

namespace App\Services\DtePurchases;

use App\Models\DtePurchaseReference;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use App\Support\Rut;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReferences
{
    public const REQUEST_STATES = ['submitted', 'resubmitted', 'changes_requested', 'approved', 'completed'];

    private const SYSTEM = ['odoo' => 'Sincronización de Odoo', 'auto' => 'Vínculo automático'];

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
        // Stale references still reserve their quantities until somebody reviews them.
        return (string) Quantity::of($this->active()->where('purchase_request_item_id', $itemId)->sum('request_quantity'));
    }

    public function report(int $id): array
    {
        $document = $this->document($id);
        $lines = $this->lines($id)->keyBy('id');
        $references = $this->active()->with(['requestItem', 'purchaseRequest'])->where('document_id', $id)->get();
        $rows = $references->map(function (DtePurchaseReference $ref) use ($document, $lines): array {
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
            ];
        })->values();
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
