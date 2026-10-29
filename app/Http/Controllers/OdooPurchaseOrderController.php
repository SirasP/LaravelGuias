<?php

namespace App\Http\Controllers;

use App\Models\OdooPurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Las órdenes que se hicieron directo en Odoo, sin pasar por una solicitud.
 * Sólo consulta: la copia la hace `odoo:sync-purchase-orders` cada noche.
 */
class OdooPurchaseOrderController extends Controller
{
    private const FILTERS = ['cotizaciones', 'confirmadas', 'canceladas', 'todas'];

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isPurchaseReviewer(), 403);

        $filter = in_array($request->query('estado'), self::FILTERS, true) ? $request->query('estado') : 'cotizaciones';
        $search = trim((string) $request->query('q'));
        $creator = trim((string) $request->query('creada_por'));

        $base = OdooPurchaseOrder::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.addcslashes($search, '%_\\').'%')
                ->orWhere('partner_name', 'like', '%'.addcslashes($search, '%_\\').'%')))
            ->when($creator !== '', fn ($query) => $query->where('created_by_name', $creator));

        // Los contadores respetan la búsqueda, para que cada pestaña diga cuánto hay en ella.
        $counts = [
            'cotizaciones' => (clone $base)->quotations()->count(),
            'confirmadas' => (clone $base)->confirmed()->count(),
            'canceladas' => (clone $base)->where('state', 'cancel')->count(),
            'todas' => (clone $base)->count(),
        ];

        $orders = (clone $base)
            ->when($filter === 'cotizaciones', fn ($query) => $query->quotations())
            ->when($filter === 'confirmadas', fn ($query) => $query->confirmed())
            ->when($filter === 'canceladas', fn ($query) => $query->where('state', 'cancel'))
            ->with('lines')
            ->orderByDesc('odoo_created_at')->orderByDesc('odoo_id')
            ->paginate(25)->withQueryString();

        return response()->view('purchase_requests.odoo', [
            'orders' => $orders, 'counts' => $counts,
            'invoices' => app(\App\Services\DtePurchases\OdooOrderLinks::class)->invoicesFor($orders->pluck('odoo_id')->all()), 'filter' => $filter, 'search' => $search, 'creator' => $creator,
            'creators' => OdooPurchaseOrder::whereNotNull('created_by_name')->distinct()->orderBy('created_by_name')->pluck('created_by_name'),
            'syncedAt' => OdooPurchaseOrder::max('synced_at'),
        ]);
    }
}
