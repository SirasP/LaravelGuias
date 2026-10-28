<?php

namespace App\Http\Controllers;

use App\Services\DtePurchases\PurchaseCandidates;
use App\Services\DtePurchases\PurchaseReferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DtePurchaseReferenceController extends Controller
{
    public function show(int $id, PurchaseReferences $references, PurchaseCandidates $candidates): JsonResponse
    {
        // Opening the tab is enough: clear matches link themselves, the rest stays as suggestions.
        $candidates->autoLink($id);

        return response()->json($references->report($id));
    }

    public function candidates(int $id, Request $request, PurchaseCandidates $candidates): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        return response()->json(['candidates' => $candidates->search($id, $data['q'] ?? '')]);
    }

    public function store(int $id, Request $request, PurchaseReferences $references): JsonResponse
    {
        $data = $request->validate([
            'document_line_id' => ['required', 'integer'],
            'purchase_request_item_id' => ['required', 'integer'],
            'invoice_quantity' => ['required', 'numeric', 'decimal:0,6', 'gt:0'],
            'request_quantity' => ['required', 'numeric', 'decimal:0,6', 'gt:0'],
            'version' => ['required', 'string', 'size:64'],
            'line_version' => ['required', 'string', 'size:64'],
            'item_version' => ['required', 'string', 'size:64'],
            'operation_key' => ['required', 'uuid'],
            'acknowledge' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:1500'],
        ]);
        $reference = $references->link($id, $data, $request->user());

        return response()->json(['id' => $reference->id, 'message' => 'Relación guardada.'], 201);
    }

    public function destroy(int $id, int $reference, Request $request, PurchaseReferences $references): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:1500']]);
        $references->revoke($id, $reference, $request->user(), $data['note']);

        return response()->json(['message' => 'Relación retirada. El historial se conserva.']);
    }

    public function classify(int $id, Request $request, PurchaseReferences $references): JsonResponse
    {
        $data = $request->validate([
            'classification' => ['required', 'in:without_request,unidentified'],
            'version' => ['required', 'string', 'size:64'],
            'note' => ['required', 'string', 'min:5', 'max:1500'],
        ]);
        $references->classify($id, $data['classification'], $data['version'], $data['note'], $request->user());

        return response()->json(['message' => 'Clasificación guardada.']);
    }
}
