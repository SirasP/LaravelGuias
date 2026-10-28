<?php

namespace App\Services\DtePurchases;

use App\Models\DtePurchaseReference;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestIngestion;
use App\Models\PurchaseRequestItem;
use App\Models\PurchaseSupplier;
use App\Services\PurchaseRequests\Products\ProductSimilarity;
use App\Services\PurchaseRequests\Quotes\QuotationComparison;
use App\Support\Rut;
use Illuminate\Support\Facades\DB;

class PurchaseCandidates
{
    /** Below this a product is only worth a look, never a link without reading it. */
    public const SUGGEST = 0.65;

    public const STRONG = 0.85;

    /**
     * Invoices arrive 0 to 14 days after the request. Later than this, a link is
     * only a suggestion: S&M's sprays of 28-10 were taken for SC-024 of 02-09.
     */
    public const AUTO_DAYS = 30;

    private array $quotesByRequest = [];

    private array $quotedByQuote = [];

    public function __construct(private ProductSimilarity $similarity, private QuotationComparison $comparison) {}

    /** Evidence of a supplier's quotation is a suggestion, never proof of a purchase. */
    public function supplierKnown(PurchaseRequestItem $item, string $rut): bool
    {
        return $this->quotedNames($item, $rut) !== [];
    }

    /**
     * How this supplier called the item in its quotations. The invoice repeats the
     * supplier's words, not the request's: «uniones estanques pvc 50mm» was quoted
     * and invoiced as «UNION AMERICANA PVC 50MM CEMENTAR».
     *
     * @return list<string>
     */
    public function quotedNames(PurchaseRequestItem $item, string $rut): array
    {
        $quotes = $this->quotesByRequest[$item->purchase_request_id] ??= PurchaseRequestIngestion::where('compared_request_id', $item->purchase_request_id)
            ->where('company_code', PurchaseRequest::COMPANY_CODE)->get();
        $names = [];
        foreach ($quotes as $quote) {
            if (in_array($quote->status, [PurchaseRequestIngestion::COMPLETED, PurchaseRequestIngestion::NEEDS_REVIEW], true)
                && $quote->customer_matches_company !== false && Rut::normalize($quote->supplier_tax_id) === $rut) {
                array_push($names, ...(($this->quotedByQuote[$quote->id] ??= $this->pairedNames($quote))[$item->id] ?? []));
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * The same pairing the quotation screen shows, so it survives a request whose
     * items were edited and recreated after somebody confirmed the pairs.
     *
     * @return array<int, list<string>> item id => quoted names
     */
    private function pairedNames(PurchaseRequestIngestion $quote): array
    {
        $lines = array_values($quote->extracted['items'] ?? []);
        $names = [];
        if ($quote->comparedRequest && $lines !== []) {
            $partner = PurchaseSupplier::query()->forCompany()->where('tax_id', Rut::normalize($quote->supplier_tax_id))->value('odoo_partner_id');
            $result = $this->comparison->comparar($quote->comparedRequest, $lines, $partner === null ? null : (int) $partner,
                $quote->parejasConfirmadas(), $quote->parejasRechazadas());
            foreach ($result->filas as $row) {
                if ($row->cruzo() && ! $row->esPropuesta() && filled($row->cotizada['product_service'] ?? null)) {
                    $names[$row->pedida->id][] = (string) $row->cotizada['product_service'];
                }
            }
        }
        foreach ($quote->parejasConfirmadas() as $index => $itemId) {
            if (filled($lines[$index]['product_service'] ?? null)) {
                $names[$itemId][] = (string) $lines[$index]['product_service'];
            }
        }

        return $names;
    }

    /** XML descriptions arrive with entities: «1 1&#47;2&quot;» is «1 1/2"». */
    private function description(object $line): string
    {
        return html_entity_decode((string) $line->descripcion, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** Same unit and no boxes or packs on either side: the quantities compare as they are. */
    private function plainUnits(object $line, PurchaseRequestItem $item): bool
    {
        return Quantity::sameUnit($line->unidad, $item->unit)
            && ! Quantity::packaged($this->description($line)) && ! Quantity::packaged($item->product_service);
    }

    public function search(int $documentId, string $search = ''): array
    {
        $references = app(PurchaseReferences::class);
        $document = $references->document($documentId);
        if (! $references->eligible($document)) {
            return [];
        }
        $rut = Rut::normalize($document->proveedor_rut);
        $query = PurchaseRequest::where('company_code', PurchaseRequest::COMPANY_CODE)
            ->whereIn('status', PurchaseReferences::REQUEST_STATES)
            ->whereDate('request_date', '<=', $document->fecha_factura)->with('items');
        $explicit = trim($search) !== '';
        if ($explicit) {
            $term = '%'.addcslashes(trim($search), '%_\\').'%';
            $query->where(fn ($q) => $q->where('folio', 'like', $term)->orWhere('reason', 'like', $term))->limit(25);
        } else {
            if (! $rut) {
                return [];
            }
            $query->whereDate('request_date', '>=', date('Y-m-d', strtotime($document->fecha_factura.' -180 days')))->limit(300);
        }

        $lines = $references->lines($documentId)->filter(fn ($line): bool => Quantity::of($line->cantidad)->minus($references->usedOnLine($line->id))->isGreaterThan(0));
        $lineRemaining = $lines->mapWithKeys(fn ($line) => [$line->id => Quantity::of($line->cantidad)->minus($references->usedOnLine($line->id))]);
        $result = [];
        foreach ($query->orderByDesc('request_date')->orderByDesc('id')->get() as $request) {
            foreach ($request->items as $item) {
                $remaining = Quantity::of($item->quantity)->minus($references->usedOnItem($item->id));
                if (! $remaining->isGreaterThan(0)) {
                    continue;
                }
                $quoted = $rut ? $this->quotedNames($item, $rut) : [];
                $supplierKnown = $quoted !== [];
                $best = null;
                $score = 0.0;
                $strong = 0;
                foreach ($lines as $line) {
                    $value = max(array_map(fn (string $name): float => $this->similarity->score($this->description($line), $name), [$item->product_service, ...$quoted]));
                    $strong += $value >= self::STRONG ? 1 : 0;
                    if ($value > $score) {
                        $score = $value;
                        $best = $line;
                    }
                }
                if (! $explicit && $score < self::SUGGEST) {
                    continue;
                }
                $plain = $best && $score >= self::SUGGEST && $this->plainUnits($best, $item);
                if (! $explicit && ! $supplierKnown) {
                    // Without this supplier's quotation only the product and an identical
                    // quantity speak for the match; a shared word like «spray» is not enough.
                    if ($score < self::STRONG || ! $plain || ! $lineRemaining[$best->id]->isEqualTo($remaining)) {
                        continue;
                    }
                }
                $quick = $plain ? (string) ($lineRemaining[$best->id]->isLessThan($remaining) ? $lineRemaining[$best->id] : $remaining) : null;
                $result[] = [
                    'item_id' => $item->id, 'item_version' => $references->itemFingerprint($item),
                    'folio' => $request->folio, 'reason' => $request->reason,
                    'request_url' => route('purchase_requests.show', $request),
                    'status' => $request->status->label(), 'date' => $request->request_date->toDateString(),
                    'product' => $item->product_service, 'specification' => $item->specification,
                    'unit' => $item->unit, 'quantity' => (string) Quantity::of($item->quantity),
                    'remaining' => (string) $remaining, 'supplier_known' => (bool) $supplierKnown,
                    'packaged' => Quantity::packaged($item->product_service),
                    'line_id' => $score >= self::SUGGEST ? $best?->id : null, 'score' => $score,
                    'line_version' => $score >= self::SUGGEST && $best ? $references->lineFingerprint($best) : null,
                    'quick_quantity' => $quick, 'strong_lines' => $strong,
                    'explanation' => $supplierKnown ? 'Este proveedor cotizó esta partida.'
                        : ($explicit ? 'No hay cotización de este proveedor para esta partida.' : 'Mismo producto y misma cantidad, aunque no hay cotización de este proveedor adjunta.'),
                ];
            }
        }
        if (! $explicit) {
            // Two requests that fit the same invoice line equally well are a question for a person.
            $perLine = array_count_values(array_filter(array_column($result, 'line_id')));
            $result = array_values(array_filter($result, fn ($row): bool => $row['supplier_known'] || ($perLine[$row['line_id']] ?? 0) === 1));
        }
        usort($result, fn ($a, $b): int => [$b['supplier_known'], $b['score']] <=> [$a['supplier_known'], $a['score']]);

        return array_slice($result, 0, 100);
    }

    /**
     * An older invoice from the same supplier, still unlinked, that also fits the
     * item comes first: SC-067's thirty elbows were invoiced on 22106, so the
     * thirty on 22288 cannot be taken for them just because 22106 is unlinked.
     */
    private function claimedEarlier(object $document, ?PurchaseRequestItem $item): bool
    {
        $rut = Rut::normalize($document->proveedor_rut);
        if (! $item || ! $rut) {
            return true;
        }
        $references = app(PurchaseReferences::class);
        $names = [$item->product_service, ...$this->quotedNames($item, $rut)];
        $earlier = DB::connection('fuelcontrol')->table('gmail_dte_documents')
            ->whereIn('tipo_dte', [33, 34, 46])->where('id', '<>', $document->id)
            ->whereIn('proveedor_rut', array_unique([$document->proveedor_rut, $rut]))
            ->whereDate('fecha_factura', '>=', $item->purchaseRequest->request_date->toDateString())
            ->where(fn ($q) => $q->whereDate('fecha_factura', '<', $document->fecha_factura)
                ->orWhere(fn ($q) => $q->whereDate('fecha_factura', $document->fecha_factura)->where('id', '<', $document->id)))
            ->get();
        foreach ($earlier as $other) {
            if (! $references->eligible($other)
                || DB::table('dte_purchase_decisions')->where('document_id', $other->id)->value('classification') === 'without_request') {
                continue;
            }
            foreach ($references->lines($other->id) as $line) {
                if (Quantity::of($line->cantidad)->minus($references->usedOnLine($line->id))->isGreaterThan(0)
                    && max(array_map(fn (string $name): float => $this->similarity->score($this->description($line), $name), $names)) >= self::STRONG) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Links what leaves no doubt: units are plain, the whole invoice line fits in
     * what is pending, neither side has another candidate, and either this supplier
     * quoted the item and the product clearly matches, or the name and quantity are
     * identical to the request's. Everything else stays a suggestion.
     *
     * @return int relationships created
     */
    public function autoLink(int $documentId): int
    {
        $references = app(PurchaseReferences::class);
        $document = $references->document($documentId);
        $decision = DB::table('dte_purchase_decisions')->where('document_id', $documentId)->value('classification');
        if (! $references->eligible($document) || $decision === 'without_request') {
            return 0;
        }
        $candidates = collect($this->search($documentId));
        $perLine = $candidates->countBy('line_id');
        $lines = $references->lines($documentId)->keyBy('id');
        $created = 0;
        foreach ($candidates as $candidate) {
            $line = $lines->get($candidate['line_id']);
            $evidence = $candidate['supplier_known'] ? $candidate['score'] >= self::STRONG : $candidate['score'] >= 1.0;
            if (! $line || ! $evidence || $candidate['strong_lines'] !== 1
                || $perLine[$candidate['line_id']] !== 1 || $candidate['quick_quantity'] === null) {
                continue;
            }
            $lineRemaining = Quantity::of($line->cantidad)->minus($references->usedOnLine($line->id));
            if (! $lineRemaining->isEqualTo($candidate['quick_quantity'])) {
                continue;
            }
            if ($candidate['date'] < date('Y-m-d', strtotime($document->fecha_factura.' -'.self::AUTO_DAYS.' days'))
                || $this->claimedEarlier($document, PurchaseRequestItem::find($candidate['item_id']))) {
                continue;
            }
            $key = 'auto:'.$documentId.':'.$line->id.':'.$candidate['item_id'];
            if (DtePurchaseReference::where('source_key', $key)->exists()) {
                // Includes revoked ones: a person's correction is never undone.
                continue;
            }
            try {
                $references->link($documentId, [
                    'document_line_id' => $line->id, 'purchase_request_item_id' => $candidate['item_id'],
                    'invoice_quantity' => $candidate['quick_quantity'], 'request_quantity' => $candidate['quick_quantity'],
                    'version' => $references->fingerprint($document), 'line_version' => $candidate['line_version'],
                    'item_version' => $candidate['item_version'],
                    'note' => $candidate['supplier_known'] ? 'Vinculada automáticamente: el proveedor cotizó esta partida y producto, unidad y cantidad coinciden.'
                        : 'Vinculada automáticamente: mismo nombre, unidad y cantidad que la partida solicitada.',
                ], null, 'auto', ['source_key' => $key, 'score' => round($candidate['score'], 3)]);
                $created++;
            } catch (\Illuminate\Validation\ValidationException) {
                // A rule the search does not see (dates, duplicates); it stays a suggestion.
            }
        }

        return $created;
    }
}
