<?php

namespace App\Http\Controllers;

use App\Models\OdooPurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;

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

        // Una compra cotizada a varios proveedores va junta: el grupo aparece una
        // vez, con todas sus órdenes, si alguna calza con el filtro.
        $matching = (clone $base)
            ->when($filter === 'cotizaciones', fn ($query) => $query->quotations())
            ->when($filter === 'confirmadas', fn ($query) => $query->confirmed())
            ->when($filter === 'canceladas', fn ($query) => $query->where('state', 'cancel'))
            ->orderByDesc('odoo_created_at')->orderByDesc('odoo_id')
            ->get(['odoo_id', 'purchase_group_id']);
        $units = $matching->map(fn (OdooPurchaseOrder $order): string => $order->purchase_group_id ? 'g'.$order->purchase_group_id : 'o'.$order->odoo_id)
            ->unique()->values();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $pages = new LengthAwarePaginator($units->forPage($page, 25)->values(), $units->count(), 25, $page,
            ['path' => $request->url(), 'query' => $request->query()]);
        $groups = $pages->getCollection()->filter(fn ($unit) => $unit[0] === 'g')->map(fn ($unit) => (int) substr($unit, 1))->values();
        $singles = $pages->getCollection()->filter(fn ($unit) => $unit[0] === 'o')->map(fn ($unit) => (int) substr($unit, 1))->values();
        $loaded = OdooPurchaseOrder::with('lines')
            ->where(fn ($query) => $query->whereIn('odoo_id', $singles)->orWhereIn('purchase_group_id', $groups))->get();
        $rank = fn (OdooPurchaseOrder $order): int => in_array($order->state, OdooPurchaseOrder::CONFIRMED_STATES, true) ? 0 : ($order->state === 'cancel' ? 2 : 1);
        $items = $pages->getCollection()->map(fn (string $unit): array => [
            'group' => $unit[0] === 'g' ? (int) substr($unit, 1) : null,
            'orders' => ($unit[0] === 'g'
                ? $loaded->where('purchase_group_id', (int) substr($unit, 1))
                : $loaded->where('odoo_id', (int) substr($unit, 1)))
                ->sortBy(fn (OdooPurchaseOrder $order): array => [$rank($order), $order->name])->values(),
        ])->filter(fn (array $item): bool => $item['orders']->isNotEmpty())->values();

        return response()->view('purchase_requests.odoo', [
            'items' => $items, 'pages' => $pages, 'counts' => $counts,
            'invoices' => app(\App\Services\DtePurchases\OdooOrderLinks::class)->invoicesFor($loaded->pluck('odoo_id')->all()), 'filter' => $filter, 'search' => $search, 'creator' => $creator,
            'creators' => OdooPurchaseOrder::whereNotNull('created_by_name')->distinct()->orderBy('created_by_name')->pluck('created_by_name'),
            'syncedAt' => OdooPurchaseOrder::max('synced_at'),
        ]);
    }

    /**
     * Una orden con sus líneas y facturas y, si se cotizó a varios proveedores,
     * los precios de todas lado a lado.
     */
    public function show(Request $request, int $odooId): Response
    {
        abort_unless($request->user()?->isPurchaseReviewer(), 403);

        $order = OdooPurchaseOrder::with('lines')->where('odoo_id', $odooId)->firstOrFail();
        $rank = fn (OdooPurchaseOrder $o): int => in_array($o->state, OdooPurchaseOrder::CONFIRMED_STATES, true) ? 0 : ($o->state === 'cancel' ? 2 : 1);
        $group = ($order->purchase_group_id
            ? OdooPurchaseOrder::with('lines')->where('purchase_group_id', $order->purchase_group_id)->get()
            : collect([$order]))->sortBy(fn (OdooPurchaseOrder $o): array => [$rank($o), $o->name])->values();

        // Una fila por producto (el de Odoo, o el nombre si la línea no tiene) y una columna por proveedor.
        $products = [];
        foreach ($group as $member) {
            foreach ($member->lines->reject->isNote() as $line) {
                $key = $line->product_odoo_id ? 'p'.$line->product_odoo_id : 'n'.\App\Models\PurchaseProductLink::normalizar((string) $line->name);
                $products[$key]['name'] ??= (string) $line->name;
                $cell = $products[$key]['cells'][$member->odoo_id] ?? ['qty' => 0.0, 'subtotal' => 0.0, 'uom' => $line->uom_name];
                $cell['qty'] += $line->product_qty;
                $cell['subtotal'] += $line->price_subtotal;
                $products[$key]['cells'][$member->odoo_id] = $cell;
            }
        }
        // El más barato de cada producto, entre las que no se cancelaron y en una misma moneda.
        $sameCurrency = $group->pluck('currency')->unique()->count() === 1;
        foreach ($products as &$product) {
            $prices = [];
            foreach ($product['cells'] as $id => &$cell) {
                $cell['price'] = $cell['qty'] > 0 ? $cell['subtotal'] / $cell['qty'] : 0.0;
                if ($cell['price'] > 0 && $group->firstWhere('odoo_id', $id)?->state !== 'cancel') {
                    $prices[$id] = $cell['price'];
                }
            }
            unset($cell);
            $product['best'] = $sameCurrency && count($prices) > 1 ? array_keys($prices, min($prices))[0] : null;
        }
        unset($product);

        return response()->view('purchase_requests.odoo_show', [
            'order' => $order, 'group' => $group, 'products' => $products,
            'invoices' => app(\App\Services\DtePurchases\OdooOrderLinks::class)->invoicesFor($group->pluck('odoo_id')->all()),
        ]);
    }
}
