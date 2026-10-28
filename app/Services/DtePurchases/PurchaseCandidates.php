<?php

namespace App\Services\DtePurchases;

use App\Models\PurchaseProductLink;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestIngestion;
use App\Models\PurchaseRequestItem;
use App\Services\PurchaseRequests\Products\ProductSimilarity;
use App\Support\Rut;

class PurchaseCandidates
{
    private array $quotesByRequest = [];

    public function __construct(private ProductSimilarity $similarity) {}

    /** Evidence of a supplier's quotation is a suggestion, never proof of a purchase. */
    public function supplierKnown(PurchaseRequestItem $item, string $rut): bool
    {
        $quotes = $this->quotesByRequest[$item->purchase_request_id] ??= PurchaseRequestIngestion::where('compared_request_id', $item->purchase_request_id)
            ->where('company_code', PurchaseRequest::COMPANY_CODE)->get();

        return $quotes->contains(fn ($quote): bool => in_array($quote->status, [PurchaseRequestIngestion::COMPLETED, PurchaseRequestIngestion::NEEDS_REVIEW], true)
            && $quote->customer_matches_company !== false && Rut::normalize($quote->supplier_tax_id) === $rut
            && $this->quoteContains($quote, $item));
    }

    private function quoteContains(PurchaseRequestIngestion $quote, PurchaseRequestItem $item): bool
    {
        if (in_array($item->id, $quote->parejasConfirmadas(), true)) {
            return true;
        }

        foreach ($quote->extracted['items'] ?? [] as $line) {
            $name = (string) ($line['product_service'] ?? '');
            if ($name !== '' && PurchaseProductLink::normalizar($name) === PurchaseProductLink::normalizar($item->product_service)) {
                return true;
            }
        }

        return false;
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
            $query->where(fn ($q) => $q->where('folio', 'like', $term)->orWhere('reason', 'like', $term));
        } else {
            if (! $rut) {
                return [];
            }
            // A name shared with some unrelated supplier is never a candidate.
            $requestIds = PurchaseRequestIngestion::whereNotNull('compared_request_id')
                ->where('company_code', PurchaseRequest::COMPANY_CODE)->get(['compared_request_id', 'supplier_tax_id'])
                ->filter(fn ($quote): bool => Rut::normalize($quote->supplier_tax_id) === $rut)
                ->pluck('compared_request_id')->unique();
            $query->whereIn('id', $requestIds)->whereDate('request_date', '>=', date('Y-m-d', strtotime($document->fecha_factura.' -180 days')));
        }

        $lines = $references->lines($documentId)->filter(fn ($line): bool => Quantity::of($line->cantidad)->minus($references->usedOnLine($line->id))->isGreaterThan(0));
        $result = [];
        foreach ($query->orderByDesc('request_date')->orderByDesc('id')->limit(25)->get() as $request) {
            foreach ($request->items as $item) {
                $remaining = Quantity::of($item->quantity)->minus($references->usedOnItem($item->id));
                if (! $remaining->isGreaterThan(0)) {
                    continue;
                }
                $supplierKnown = $rut && $this->supplierKnown($item, $rut);
                if (! $explicit && ! $supplierKnown) {
                    continue;
                }
                $best = null;
                $score = 0.0;
                foreach ($lines as $line) {
                    $value = $this->similarity->score((string) $line->descripcion, $item->product_service);
                    if ($value > $score) {
                        $score = $value;
                        $best = $line;
                    }
                }
                if (! $explicit && $score < 0.65) {
                    continue;
                }
                $result[] = [
                    'item_id' => $item->id, 'item_version' => $references->itemFingerprint($item),
                    'folio' => $request->folio, 'reason' => $request->reason,
                    'request_url' => route('purchase_requests.show', $request),
                    'status' => $request->status->label(), 'date' => $request->request_date->toDateString(),
                    'product' => $item->product_service, 'specification' => $item->specification,
                    'unit' => $item->unit, 'quantity' => (string) Quantity::of($item->quantity),
                    'remaining' => (string) $remaining, 'supplier_known' => (bool) $supplierKnown,
                    'packaged' => Quantity::packaged($item->product_service),
                    'line_id' => $score >= 0.65 ? $best?->id : null, 'score' => $score,
                    'explanation' => $supplierKnown ? 'Este proveedor cotizó esta partida; confirma que la factura corresponde a esa compra.' : 'No hay evidencia registrada de este proveedor para esta partida. Requiere confirmación y motivo.',
                ];
            }
        }
        usort($result, fn ($a, $b): int => $b['score'] <=> $a['score']);

        return array_slice($result, 0, 100);
    }
}
