<?php

namespace App\Http\Controllers;

use App\Models\OdooPurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Las órdenes que se hicieron directo en Odoo, sin pasar por una solicitud.
 * Sólo consulta: la copia la hace `odoo:sync-purchase-orders` cada 5 minutos.
 */
class OdooPurchaseOrderController extends Controller
{
    private const FILTERS = ['cotizaciones', 'abiertas', 'confirmadas', 'canceladas', 'todas'];

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
            'cotizaciones' => (clone $base)->pendingQuotations()->count(),
            'abiertas' => (clone $base)->leftOpen()->count(),
            'confirmadas' => (clone $base)->confirmed()->count(),
            'canceladas' => (clone $base)->where('state', 'cancel')->count(),
            'todas' => (clone $base)->count(),
        ];

        // Una compra cotizada a varios proveedores va junta: el grupo aparece una
        // vez, con todas sus órdenes, si alguna calza con el filtro.
        $matching = (clone $base)
            ->when($filter === 'cotizaciones', fn ($query) => $query->pendingQuotations())
            ->when($filter === 'abiertas', fn ($query) => $query->leftOpen())
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
            'bought' => OdooPurchaseOrder::boughtGroups()->distinct()->pluck('purchase_group_id')->map(fn ($id) => (int) $id)->flip()->all(),
            'invoices' => app(\App\Services\DtePurchases\OdooOrderLinks::class)->invoicesFor($loaded->pluck('odoo_id')->all()), 'filter' => $filter, 'search' => $search, 'creator' => $creator,
            'creators' => OdooPurchaseOrder::whereNotNull('created_by_name')->distinct()->orderBy('created_by_name')->pluck('created_by_name'),
            'syncedAt' => OdooPurchaseOrder::max('synced_at'),
            // Las cotizaciones que el jefe hace en Odoo a partir de una solicitud
            // llevan su folio en el origen: el enlace lleva a esa solicitud.
            'requestLinks' => $this->requestLinks($loaded),
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

        $requestLinks = $this->requestLinks($group);
        // La solicitud que Laravel armó con este grupo, si nació en Odoo.
        $adopted = \App\Models\PurchaseRequest::whereIn('odoo_order_id', $group->pluck('odoo_id'))->first();
        $solicitud = $adopted ?? \App\Models\PurchaseRequest::with('items')
            ->where('company_code', \App\Models\PurchaseRequest::COMPANY_CODE)
            ->whereIn('folio', $group->map->requestFolio()->filter()->unique())->first();

        $keyOf = fn ($line): string => $line->product_odoo_id ? 'p'.$line->product_odoo_id : 'n'.\App\Models\PurchaseProductLink::normalizar((string) $line->name);
        $lineas = $group->flatMap(fn (OdooPurchaseOrder $m) => $m->lines->reject->isNote()->map(fn ($l) => [$m, $l]));

        // Las filas son lo que se pidió: las partidas de la solicitud, con su
        // nombre, y no el texto de un proveedor («CODO PVC 200MM x 90░»).
        $pedidas = [];
        $partidaDe = [];
        foreach ($solicitud?->items ?? [] as $item) {
            $base = $lineas->first(fn ($par) => (int) $par[1]->odoo_id === (int) $item->odoo_line_id);
            if ($base !== null) {
                $pedidas[$keyOf($base[1])] = (string) $item->product_service;
                $partidaDe[(int) $item->id] = $keyOf($base[1]);
            }
        }
        if ($pedidas === []) {
            // Sin solicitud: lo que cotizó más de uno; con una sola orden, todo.
            $porProducto = $lineas->groupBy(fn ($par) => $keyOf($par[1]))->map(fn ($pares) => $pares->map(fn ($par) => $par[0]->odoo_id)->unique()->count());
            foreach ($porProducto as $key => $cuantas) {
                if ($cuantas > 1 || $group->count() === 1) {
                    $pedidas[$key] = null;
                }
            }
        }

        // Lo que un proveedor cotizó aparte como pieza de una partida (la
        // flange volante de Climent, de la válvula de la SC-2026-000114) va
        // dentro de esa partida, como en la solicitud.
        $piezas = [];
        foreach (\App\Models\PurchaseRequestIngestion::where('compared_request_id', $solicitud?->id ?? 0)->get() as $lectura) {
            foreach ($lectura->piezasConfirmadas() as $renglon => $itemId) {
                $nombre = $lectura->extracted['items'][$renglon]['product_service'] ?? null;
                if ($nombre !== null && isset($partidaDe[$itemId])) {
                    $piezas[\App\Models\PurchaseProductLink::normalizar((string) $nombre)] = $partidaDe[$itemId];
                }
            }
        }

        // Una fila por producto pedido y una columna por proveedor; lo demás, aparte.
        $products = [];
        $extras = [];
        foreach ($lineas as [$member, $line]) {
            $key = $keyOf($line);
            $pieza = $piezas[\App\Models\PurchaseProductLink::normalizar((string) $line->name)] ?? null;
            if (! array_key_exists($key, $pedidas) && $pieza !== null) {
                $products[$pieza]['cells'][$member->odoo_id]['pieces'] = ($products[$pieza]['cells'][$member->odoo_id]['pieces'] ?? 0.0) + $line->price_subtotal;
                continue;
            }
            if (! array_key_exists($key, $pedidas)) {
                $extras[] = ['order' => $member, 'name' => (string) $line->name, 'qty' => $line->product_qty, 'uom' => $line->uom_name, 'price' => $line->price_unit, 'subtotal' => $line->price_subtotal];
                continue;
            }
            $products[$key]['name'] ??= $pedidas[$key] ?? (string) $line->name;
            $cell = $products[$key]['cells'][$member->odoo_id] ?? [];
            $cell = ['qty' => ($cell['qty'] ?? 0.0) + $line->product_qty, 'subtotal' => ($cell['subtotal'] ?? 0.0) + $line->price_subtotal, 'uom' => $line->uom_name, 'pieces' => $cell['pieces'] ?? 0.0];
            $products[$key]['cells'][$member->odoo_id] = $cell;
        }
        // En el orden de la solicitud.
        $products = collect(array_keys($pedidas))->filter(fn ($k) => isset($products[$k]['name']))->mapWithKeys(fn ($k) => [$k => $products[$k]])->all();

        // El más barato de cada producto, con sus piezas, entre las que no se cancelaron y en una misma moneda.
        $sameCurrency = $group->pluck('currency')->unique()->count() === 1;
        foreach ($products as &$product) {
            $prices = [];
            foreach ($product['cells'] as $id => &$cell) {
                $cell += ['qty' => 0.0, 'subtotal' => 0.0, 'uom' => null, 'pieces' => 0.0];
                $cell['base_price'] = $cell['qty'] > 0 ? $cell['subtotal'] / $cell['qty'] : 0.0;
                $cell['price'] = $cell['qty'] > 0 ? ($cell['subtotal'] + $cell['pieces']) / $cell['qty'] : 0.0;
                if ($cell['price'] > 0 && $group->firstWhere('odoo_id', $id)?->state !== 'cancel') {
                    $prices[$id] = $cell['price'];
                }
            }
            unset($cell);
            $product['best'] = $sameCurrency && count($prices) > 1 ? array_keys($prices, min($prices))[0] : null;
        }
        unset($product);

        $boughtWith = $order->isQuotation() ? $group->filter(fn (OdooPurchaseOrder $o): bool => in_array($o->state, OdooPurchaseOrder::CONFIRMED_STATES, true)) : collect();

        return response()->view('purchase_requests.odoo_show', [
            'requestLinks' => $requestLinks, 'adopted' => $adopted,
            'order' => $order, 'group' => $group, 'products' => $products, 'extras' => $extras, 'boughtWith' => $boughtWith,
            'invoices' => app(\App\Services\DtePurchases\OdooOrderLinks::class)->invoicesFor($group->pluck('odoo_id')->all()),
        ]);
    }

    /**
     * Folio de solicitud => su página, para las órdenes que lo llevan en el origen.
     *
     * @return array<string, string>
     */
    private function requestLinks(\Illuminate\Support\Collection $orders): array
    {
        $folios = $orders->map(fn (OdooPurchaseOrder $order): ?string => $order->requestFolio())->filter()->unique()->values();

        return \App\Models\PurchaseRequest::query()->where('company_code', \App\Models\PurchaseRequest::COMPANY_CODE)
            ->whereIn('folio', $folios)->get()
            ->mapWithKeys(fn (\App\Models\PurchaseRequest $request): array => [$request->folio => route('purchase_requests.show', $request)])
            ->all();
    }
}
