<?php

namespace App\Services\PurchaseRequests;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestRevision;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Congela una solicitud al enviarse y materializa su PDF una sola vez.
 */
class PurchaseRequestSnapshotService
{
    public const DISK = 'local';

    /**
     * Crea la revisión correspondiente al envío actual.
     *
     * Es idempotente por (solicitud, número de revisión): un doble envío
     * devuelve la revisión existente en vez de duplicarla.
     */
    public function capture(PurchaseRequest $purchaseRequest, User $actor): PurchaseRequestRevision
    {
        $existing = PurchaseRequestRevision::query()
            ->where('purchase_request_id', $purchaseRequest->getKey())
            ->where('revision_number', $purchaseRequest->revision_number)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $purchaseRequest->loadMissing('items');

        return PurchaseRequestRevision::query()->create([
            'purchase_request_id' => $purchaseRequest->getKey(),
            'revision_number' => $purchaseRequest->revision_number,
            'status' => $purchaseRequest->status,
            'submitted_by' => $actor->getKey(),
            'submitted_by_name_snapshot' => $actor->name,
            'submitted_at' => $purchaseRequest->submitted_at ?? now(),
            'header_snapshot' => $this->headerSnapshot($purchaseRequest),
            'items_snapshot' => $this->itemsSnapshot($purchaseRequest),
            'item_count' => $purchaseRequest->items->count(),
        ]);
    }

    /**
     * Devuelve el PDF de la revisión, generándolo la primera vez.
     *
     * Una vez en disco jamás se regenera: es la garantía de que un histórico
     * no se reimprime con datos nuevos.
     */
    public function pdfContents(PurchaseRequestRevision $revision): string
    {
        $disk = Storage::disk(self::DISK);

        if ($revision->hasPdf() && $disk->exists($revision->pdf_path)) {
            return (string) $disk->get($revision->pdf_path);
        }

        $contents = $this->renderPdf($revision);
        $path = sprintf(
            'purchase-requests/%d/revisions/%d.pdf',
            $revision->purchase_request_id,
            $revision->revision_number,
        );

        $disk->put($path, $contents);
        $revision->forceFill([
            'pdf_disk' => self::DISK,
            'pdf_path' => $path,
            'pdf_sha256' => hash('sha256', $contents),
        ])->save();

        return $contents;
    }

    private function renderPdf(PurchaseRequestRevision $revision): string
    {
        return Pdf::loadView('purchase_requests.pdf', [
            'purchaseRequest' => $revision->purchaseRequest,
            'revision' => $revision,
            'header' => $revision->header_snapshot,
            'items' => $revision->items_snapshot,
        ])->setPaper('letter')->output();
    }

    /** @return array<string, mixed> */
    private function headerSnapshot(PurchaseRequest $purchaseRequest): array
    {
        return [
            'folio' => $purchaseRequest->folio,
            'company_name' => $purchaseRequest->company_name_snapshot,
            'request_date' => $purchaseRequest->request_date?->toDateString(),
            'required_date' => $purchaseRequest->required_date?->toDateString(),
            'department' => $purchaseRequest->department,
            'requester_name' => $purchaseRequest->requester_name_snapshot,
            'requested_for_name' => $purchaseRequest->requested_for_name,
            'reason' => $purchaseRequest->reason,
            'priority' => $purchaseRequest->priority,
            'currency' => $purchaseRequest->currency,
            'total' => filled($purchaseRequest->total()) ? (string) $purchaseRequest->total() : null,
            'urgent_reason' => $purchaseRequest->urgent_reason,
            'cost_center' => $purchaseRequest->cost_center,
            'delivery_location' => $purchaseRequest->delivery_location,
            'internal_notes' => $purchaseRequest->internal_notes,
            'suggested_suppliers' => $purchaseRequest->suggested_suppliers ?? [],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function itemsSnapshot(PurchaseRequest $purchaseRequest): array
    {
        return $purchaseRequest->items
            ->map(fn ($item): array => [
                'sort_order' => $item->sort_order,
                'product_service' => $item->product_service,
                'specification' => $item->specification,
                // Se guarda como texto para no perder ni un decimal al
                // serializar a JSON y volver.
                'quantity' => (string) $item->quantity,
                'unit' => $item->unit,
                'unit_price' => filled($item->unit_price) ? (string) $item->unit_price : null,
                'line_total' => filled($item->unit_price) ? (string) $item->lineTotal() : null,
                'quantity_note' => $item->quantity_note,
                'destination' => $item->destination,
            ])
            ->values()
            ->all();
    }
}
