<?php

namespace App\Http\Controllers;

use App\Services\DtePurchases\OdooOrderLinks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** La orden de Odoo que pagó cada factura. Sólo escribe en Laravel. */
class DteOdooOrderController extends Controller
{
    public function show(int $id, OdooOrderLinks $links): JsonResponse
    {
        // Como con las solicitudes: abrir la pestaña basta para que lo seguro se vincule solo.
        $links->autoLink($id);

        return response()->json($links->report($id));
    }

    public function candidates(int $id, Request $request, OdooOrderLinks $links): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        return response()->json(['candidates' => $links->candidates($id, $data['q'] ?? '')]);
    }

    public function store(int $id, Request $request, OdooOrderLinks $links): JsonResponse
    {
        $data = $request->validate([
            'odoo_order_id' => ['required', 'integer'],
            'amount' => ['nullable', 'numeric', 'gt:0'],
        ]);
        $link = $links->link($id, (int) $data['odoo_order_id'], isset($data['amount']) ? (float) $data['amount'] : null, $request->user());

        return response()->json(['id' => $link->id, 'message' => 'Orden vinculada.'], 201);
    }

    public function destroy(int $id, int $link, Request $request, OdooOrderLinks $links): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:1500']]);
        $links->revoke($id, $link, $request->user(), $data['note']);

        return response()->json(['message' => 'Vínculo retirado. El historial se conserva.']);
    }
}
