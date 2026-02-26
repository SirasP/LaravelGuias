<?php

namespace App\Http\Controllers;

use App\Services\DteGeneratorService;
use App\Services\GmailDteInventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GmailInventoryController extends Controller
{
    private function db()
    {
        return DB::connection('fuelcontrol');
    }

    // GET /gmail/inventario/salida
    public function exitCreate()
    {
        return view('gmail.inventory.exit_form');
    }

    // POST /gmail/inventario/salida
    public function exitStore(Request $request, GmailDteInventoryService $service, DteGeneratorService $dteGenerator)
    {
        $validated = $request->validate([
            'destinatario'         => 'required|string|max:200',
            'tipo_salida'          => 'nullable|string|in:Venta,EPP,Salida',
            'notas'                => 'nullable|string|max:1000',
            'enviar_factura'       => 'nullable|boolean',
            'correo_factura'       => 'nullable|string|email|max:200',
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|integer',
            'items.*.quantity'     => 'required|numeric|gt:0',
        ]);

        $productIds  = collect($validated['items'])->pluck('product_id')->unique()->values()->all();
        $existingIds = $this->db()
            ->table('gmail_inventory_products')
            ->whereIn('id', $productIds)
            ->pluck('id')
            ->all();

        if (!empty(array_diff($productIds, $existingIds))) {
            return back()->withInput()->withErrors(['items' => 'Uno o más productos no son válidos.']);
        }

        if (
            ($validated['tipo_salida'] ?? null) === 'Venta'
            && (int) ($validated['enviar_factura'] ?? 0) === 1
            && trim((string) ($validated['correo_factura'] ?? '')) === ''
        ) {
            return back()->withInput()->withErrors(['correo_factura' => 'Debes indicar un correo para enviar la factura.']);
        }

        try {
            $result = $service->processExit(
                $validated['items'],
                auth()->id(),
                $validated['destinatario'],
                $validated['notas'] ?? null,
                $validated['tipo_salida'] ?? null
            );

            $dteXmlPath = null;
            if (($validated['tipo_salida'] ?? null) === 'Venta') {
                $products = $this->db()
                    ->table('gmail_inventory_products')
                    ->whereIn('id', $productIds)
                    ->get(['id', 'nombre', 'costo_promedio'])
                    ->keyBy('id');

                $dteItems = collect($validated['items'])
                    ->map(function (array $item) use ($products): ?array {
                        $productId = (int) $item['product_id'];
                        $product = $products->get($productId);
                        if (!$product) {
                            return null;
                        }

                        return [
                            'product_id' => $productId,
                            'nombre' => (string) $product->nombre,
                            'quantity' => (float) $item['quantity'],
                            'unit_price' => (float) $product->costo_promedio,
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

                $dteXmlPath = $dteGenerator->generateFacturaXmlForExit([
                    'movement_id' => (int) $result['movement_id'],
                    'destinatario' => (string) $validated['destinatario'],
                    'receptor_email' => trim((string) ($validated['correo_factura'] ?? '')),
                    'items' => $dteItems,
                ]);
            }
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['items' => $e->getMessage()]);
        }

        $success = 'Salida registrada correctamente (movimiento #' . $result['movement_id'] . ').';
        if (($validated['tipo_salida'] ?? null) === 'Venta' && !empty($dteXmlPath)) {
            $success .= ' XML DTE generado: ' . $dteXmlPath;
        }

        return redirect()
            ->route('gmail.inventory.exits')
            ->with('success', $success)
            ->with('dte_xml_path', $dteXmlPath ?? null);
    }

    // POST /gmail/inventario/salidas/{id}/venta
    public function exitSell(Request $request, int $id)
    {
        $validated = $request->validate([
            'precio_venta' => 'required|numeric|min:0',
        ]);

        $movement = $this->db()
            ->table('gmail_inventory_movements')
            ->where('id', $id)
            ->where('tipo', 'SALIDA')
            ->first();

        if (! $movement) {
            return response()->json(['error' => 'Movimiento no encontrado.'], 404);
        }

        $this->db()
            ->table('gmail_inventory_movements')
            ->where('id', $id)
            ->update([
                'precio_venta' => $validated['precio_venta'],
                'tipo_salida'  => 'Venta',
            ]);

        $costoTotal  = (float) $movement->costo_total;
        $precioVenta = (float) $validated['precio_venta'];
        $margen      = $costoTotal > 0
            ? round((($precioVenta - $costoTotal) / $costoTotal) * 100, 2)
            : null;

        return response()->json([
            'ok'           => true,
            'precio_venta' => $precioVenta,
            'costo_total'  => $costoTotal,
            'margen'       => $margen,
        ]);
    }

    // GET /gmail/inventario/salidas
    public function exitList(Request $request)
    {
        $q     = trim((string) $request->query('q', ''));
        $desde = trim((string) $request->query('desde', ''));
        $hasta = trim((string) $request->query('hasta', ''));
        $range = trim((string) $request->query('range', ''));
        $flag  = trim((string) $request->query('flag', ''));
        // 'Venta' → ventas view   |   '' (default) → EPP+Salidas view
        $vista = $request->query('tipo', '') === 'Venta' ? 'Venta' : 'ops';

        $query = $this->db()
            ->table('gmail_inventory_movements')
            ->where('tipo', 'SALIDA')
            ->orderByDesc('ocurrio_el')
            ->orderByDesc('id');

        if ($vista === 'Venta') {
            $query->where('tipo_salida', 'Venta');
        } else {
            $query->where(function ($qb) {
                $qb->whereIn('tipo_salida', ['EPP', 'Salida'])
                   ->orWhereNull('tipo_salida');
            });
        }

        if ($q !== '') {
            $query->where('destinatario', 'like', "%{$q}%");
        }
        if ($range !== '') {
            [$rangeDesde, $rangeHasta] = $this->resolveQuickRange($range);
            if ($rangeDesde && $rangeHasta) {
                $query->whereBetween('ocurrio_el', [$rangeDesde, $rangeHasta]);
            }
        }
        if ($desde !== '') {
            $query->where('ocurrio_el', '>=', $desde);
        }
        if ($hasta !== '') {
            $query->where('ocurrio_el', '<=', $hasta);
        }
        if ($vista === 'Venta' && $flag === 'sin_precio') {
            $query->where(function ($qb) {
                $qb->whereNull('precio_venta')->orWhere('precio_venta', '<=', 0);
            });
        }

        $movements = $query->limit(200)->get();
        $ids       = $movements->pluck('id')->all();

        $lines = $ids
            ? $this->db()
                ->table('gmail_inventory_movement_lines as ml')
                ->join('gmail_inventory_products as p', 'p.id', '=', 'ml.product_id')
                ->whereIn('ml.movement_id', $ids)
                ->orderBy('p.nombre')
                ->get([
                    'ml.movement_id',
                    'p.nombre as producto',
                    'p.codigo',
                    'p.unidad',
                    'ml.cantidad',
                    'ml.costo_unitario',
                    'ml.costo_total',
                ])
                ->groupBy('movement_id')
            : collect();

        // Ventas mode: group by cliente name
        $byName = $movements->groupBy(fn($m) => $m->destinatario ?? '—');

        // EPP+Salidas mode: group by tipo_salida → destinatario
        $byTipoName = $movements
            ->groupBy(fn($m) => $m->tipo_salida ?? 'Salida')
            ->map(fn($g) => $g->groupBy(fn($m) => $m->destinatario ?? '—'));

        $countEpp    = $movements->filter(fn($m) => ($m->tipo_salida ?? '') === 'EPP')->count();
        $countSalida = $movements->filter(fn($m) => ($m->tipo_salida ?? 'Salida') === 'Salida')->count();
        $costoVentas = $movements->sum('costo_total');
        $pvVentas    = $movements->sum('precio_venta');

        // KPIs separados por tipo — mes actual
        $mesInicio = now()->startOfMonth()->toDateString();
        $mesFin    = now()->endOfMonth()->toDateString();
        $mesPrevInicio = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $mesPrevFin    = now()->subMonthNoOverflow()->endOfMonth()->toDateString();

        $kpiVentas = $this->db()
            ->table('gmail_inventory_movements')
            ->where('tipo', 'SALIDA')
            ->where('tipo_salida', 'Venta')
            ->whereBetween('ocurrio_el', [$mesInicio, $mesFin])
            ->selectRaw('count(*) as cnt, coalesce(sum(costo_total),0) as costo, coalesce(sum(precio_venta),0) as venta')
            ->first();

        $kpiEpp = $this->db()
            ->table('gmail_inventory_movements')
            ->where('tipo', 'SALIDA')
            ->where('tipo_salida', 'EPP')
            ->whereBetween('ocurrio_el', [$mesInicio, $mesFin])
            ->selectRaw('count(*) as cnt, coalesce(sum(costo_total),0) as costo')
            ->first();

        $kpiSalida = $this->db()
            ->table('gmail_inventory_movements')
            ->where('tipo', 'SALIDA')
            ->where(function ($qb) {
                $qb->where('tipo_salida', 'Salida')->orWhereNull('tipo_salida');
            })
            ->whereBetween('ocurrio_el', [$mesInicio, $mesFin])
            ->selectRaw('count(*) as cnt, coalesce(sum(costo_total),0) as costo')
            ->first();

        $kpiVentasPrev = $this->db()
            ->table('gmail_inventory_movements')
            ->where('tipo', 'SALIDA')
            ->where('tipo_salida', 'Venta')
            ->whereBetween('ocurrio_el', [$mesPrevInicio, $mesPrevFin])
            ->selectRaw('count(*) as cnt, coalesce(sum(costo_total),0) as costo, coalesce(sum(precio_venta),0) as venta')
            ->first();

        $kpiEppPrev = $this->db()
            ->table('gmail_inventory_movements')
            ->where('tipo', 'SALIDA')
            ->where('tipo_salida', 'EPP')
            ->whereBetween('ocurrio_el', [$mesPrevInicio, $mesPrevFin])
            ->selectRaw('count(*) as cnt, coalesce(sum(costo_total),0) as costo')
            ->first();

        $kpiSalidaPrev = $this->db()
            ->table('gmail_inventory_movements')
            ->where('tipo', 'SALIDA')
            ->where(function ($qb) {
                $qb->where('tipo_salida', 'Salida')->orWhereNull('tipo_salida');
            })
            ->whereBetween('ocurrio_el', [$mesPrevInicio, $mesPrevFin])
            ->selectRaw('count(*) as cnt, coalesce(sum(costo_total),0) as costo')
            ->first();

        $topVenta = $this->db()
            ->table('gmail_inventory_movement_lines as ml')
            ->join('gmail_inventory_movements as m', 'm.id', '=', 'ml.movement_id')
            ->join('gmail_inventory_products as p', 'p.id', '=', 'ml.product_id')
            ->where('m.tipo', 'SALIDA')
            ->where('m.tipo_salida', 'Venta')
            ->whereBetween('m.ocurrio_el', [$mesInicio, $mesFin])
            ->selectRaw('p.nombre, sum(ml.cantidad) as total_qty')
            ->groupBy('p.id', 'p.nombre')
            ->orderByDesc('total_qty')
            ->first();

        $topOps = $this->db()
            ->table('gmail_inventory_movement_lines as ml')
            ->join('gmail_inventory_movements as m', 'm.id', '=', 'ml.movement_id')
            ->join('gmail_inventory_products as p', 'p.id', '=', 'ml.product_id')
            ->where('m.tipo', 'SALIDA')
            ->where(function ($qb) {
                $qb->whereIn('m.tipo_salida', ['EPP', 'Salida'])->orWhereNull('m.tipo_salida');
            })
            ->whereBetween('m.ocurrio_el', [$mesInicio, $mesFin])
            ->selectRaw('p.nombre, sum(ml.cantidad) as total_qty')
            ->groupBy('p.id', 'p.nombre')
            ->orderByDesc('total_qty')
            ->first();

        return view('gmail.inventory.exits', compact(
            'movements', 'lines', 'byName', 'byTipoName',
            'vista', 'countEpp', 'countSalida', 'costoVentas', 'pvVentas',
            'q', 'desde', 'hasta', 'range', 'flag',
            'kpiVentas', 'kpiEpp', 'kpiSalida', 'kpiVentasPrev', 'kpiEppPrev', 'kpiSalidaPrev', 'topVenta', 'topOps'
        ));
    }

    // GET /gmail/inventario/salidas/export
    public function exitExport(Request $request)
    {
        $q     = trim((string) $request->query('q', ''));
        $desde = trim((string) $request->query('desde', ''));
        $hasta = trim((string) $request->query('hasta', ''));
        $range = trim((string) $request->query('range', ''));
        $flag  = trim((string) $request->query('flag', ''));
        $tipo  = trim((string) $request->query('tipo', ''));

        $query = $this->db()
            ->table('gmail_inventory_movements as m')
            ->leftJoin('gmail_inventory_movement_lines as ml', 'ml.movement_id', '=', 'm.id')
            ->leftJoin('gmail_inventory_products as p', 'p.id', '=', 'ml.product_id')
            ->where('m.tipo', 'SALIDA')
            ->orderByDesc('m.ocurrio_el')
            ->orderByDesc('m.id')
            ->select([
                'm.id as movimiento_id',
                'm.ocurrio_el as fecha',
                'm.destinatario',
                'm.tipo_salida',
                'm.notas',
                'm.costo_total as costo_total_movimiento',
                'm.precio_venta',
                'p.nombre as producto',
                'p.codigo as codigo',
                'p.unidad as unidad',
                'ml.cantidad',
                'ml.costo_unitario',
                'ml.costo_total as costo_linea',
            ]);

        if ($q !== '') {
            $query->where('m.destinatario', 'like', "%{$q}%");
        }
        if ($tipo === 'Venta') {
            $query->where('m.tipo_salida', 'Venta');
        } elseif ($tipo !== '') {
            $query->where('m.tipo_salida', $tipo);
        }
        if ($range !== '') {
            [$rangeDesde, $rangeHasta] = $this->resolveQuickRange($range);
            if ($rangeDesde && $rangeHasta) {
                $query->whereBetween('m.ocurrio_el', [$rangeDesde, $rangeHasta]);
            }
        }
        if ($desde !== '') {
            $query->where('m.ocurrio_el', '>=', $desde);
        }
        if ($hasta !== '') {
            $query->where('m.ocurrio_el', '<=', $hasta);
        }
        if ($tipo === 'Venta' && $flag === 'sin_precio') {
            $query->where(function ($qb) {
                $qb->whereNull('m.precio_venta')->orWhere('m.precio_venta', '<=', 0);
            });
        }

        $rows = $query->get();

        $filename = 'salidas_inventario_' . now()->format('Ymd_His') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($rows) {
            $fh = fopen('php://output', 'w');
            fprintf($fh, \chr(0xEF) . \chr(0xBB) . \chr(0xBF)); // BOM UTF-8
            fputcsv($fh, [
                'ID Movimiento', 'Fecha', 'Destinatario', 'Tipo Salida', 'Notas',
                'Costo Total Mov.', 'Precio Venta', 'Producto', 'Código', 'Unidad',
                'Cantidad', 'Costo Unit.', 'Costo Línea',
            ], ';');
            foreach ($rows as $r) {
                fputcsv($fh, [
                    $r->movimiento_id,
                    $r->fecha,
                    $r->destinatario,
                    $r->tipo_salida ?? '',
                    $r->notas,
                    number_format((float) $r->costo_total_movimiento, 2, ',', '.'),
                    $r->precio_venta !== null ? number_format((float) $r->precio_venta, 2, ',', '.') : '',
                    $r->producto,
                    $r->codigo,
                    $r->unidad,
                    number_format((float) $r->cantidad, 4, ',', '.'),
                    number_format((float) $r->costo_unitario, 2, ',', '.'),
                    number_format((float) $r->costo_linea, 2, ',', '.'),
                ], ';');
            }
            fclose($fh);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function resolveQuickRange(string $range): array
    {
        return match ($range) {
            'today' => [now()->toDateString(), now()->toDateString()],
            '7d' => [now()->subDays(6)->toDateString(), now()->toDateString()],
            '30d' => [now()->subDays(29)->toDateString(), now()->toDateString()],
            default => [null, null],
        };
    }

    // GET /gmail/inventario/api/productos
    public function productsApi(Request $request)
    {
        $q     = trim((string) $request->query('q', ''));
        $limit = min(50, max(1, (int) $request->query('limit', 6)));

        $query = $this->db()
            ->table('gmail_inventory_products')
            ->where('is_active', 1)
            ->where('stock_actual', '>', 0)
            ->orderBy('nombre')
            ->limit($limit);

        if ($q !== '') {
            $query->where(function ($qb) use ($q) {
                $qb->where('nombre', 'like', "%{$q}%")
                   ->orWhere('codigo', 'like', "%{$q}%");
            });
        }

        $products = $query->get(['id', 'nombre', 'codigo', 'unidad', 'stock_actual', 'costo_promedio']);

        return response()->json($products);
    }

    // GET /gmail/inventario/api/destinatarios
    public function destinatariosApi(Request $request)
    {
        $q    = trim((string) $request->query('q', ''));
        $tipo = trim((string) $request->query('tipo', ''));

        $query = $this->db()
            ->table('gmail_inventory_movements')
            ->where('tipo', 'SALIDA')
            ->whereNotNull('destinatario')
            ->where('destinatario', '!=', '')
            ->selectRaw('destinatario, max(created_at) as last_used')
            ->groupBy('destinatario')
            ->orderByDesc('last_used')
            ->limit(6);

        if ($tipo !== '') {
            $query->where('tipo_salida', $tipo);
        }
        if ($q !== '') {
            $query->where('destinatario', 'like', "%{$q}%");
        }

        return response()->json($query->pluck('destinatario'));
    }

    // GET /gmail/inventario/api/lotes/{productId}
    public function lotsApi(int $productId)
    {
        $lots = $this->db()
            ->table('gmail_inventory_lots')
            ->where('product_id', $productId)
            ->where('estado', 'ABIERTO')
            ->where('cantidad_disponible', '>', 0)
            ->orderBy('ingresado_el')
            ->orderBy('id')
            ->get(['id', 'ingresado_el', 'costo_unitario', 'cantidad_disponible']);

        return response()->json($lots);
    }

    // GET /gmail/inventario/api/salida/{id}/lineas
    public function exitDetail(int $id)
    {
        $lines = $this->db()
            ->table('gmail_inventory_movement_lines as ml')
            ->join('gmail_inventory_products as p', 'p.id', '=', 'ml.product_id')
            ->join('gmail_inventory_lots as l', 'l.id', '=', 'ml.lot_id')
            ->where('ml.movement_id', $id)
            ->orderBy('p.nombre')
            ->get([
                'p.nombre as producto',
                'p.codigo',
                'p.unidad',
                'ml.cantidad',
                'ml.costo_unitario',
                'ml.costo_total',
                'l.ingresado_el as lote_fecha',
            ]);

        return response()->json($lines);
    }

    // GET /gmail/inventario/salidas/{id}
    public function exitShow(int $id)
    {
        $movement = $this->db()
            ->table('gmail_inventory_movements')
            ->where('id', $id)
            ->where('tipo', 'SALIDA')
            ->first();

        abort_if(!$movement, 404);

        $lines = $this->db()
            ->table('gmail_inventory_movement_lines as ml')
            ->join('gmail_inventory_products as p', 'p.id', '=', 'ml.product_id')
            ->leftJoin('gmail_inventory_lots as l', 'l.id', '=', 'ml.lot_id')
            ->where('ml.movement_id', $id)
            ->orderBy('p.nombre')
            ->get([
                'p.nombre as producto',
                'p.codigo',
                'p.unidad',
                'ml.cantidad',
                'ml.costo_unitario',
                'ml.costo_total',
                'l.ingresado_el as lote_fecha',
            ]);

        return view('gmail.inventory.exit_show', compact('movement', 'lines'));
    }

    // GET /gmail/inventario/salidas-resumen?destinatario=...&tipo=...
    public function exitGroupShow(Request $request)
    {
        $destinatario = trim((string) $request->query('destinatario', ''));
        $tipo = trim((string) $request->query('tipo', ''));

        abort_if($destinatario === '', 404);

        $query = $this->db()
            ->table('gmail_inventory_movements')
            ->where('tipo', 'SALIDA')
            ->where('destinatario', $destinatario)
            ->orderByDesc('ocurrio_el')
            ->orderByDesc('id');

        if ($tipo === 'Venta') {
            $query->where('tipo_salida', 'Venta');
        } elseif ($tipo === 'EPP') {
            $query->where('tipo_salida', 'EPP');
        } elseif ($tipo === 'Salida') {
            $query->where(function ($qb) {
                $qb->where('tipo_salida', 'Salida')->orWhereNull('tipo_salida');
            });
        }

        $movements = $query->get();
        abort_if($movements->isEmpty(), 404);

        $ids = $movements->pluck('id')->all();

        $lines = $this->db()
            ->table('gmail_inventory_movement_lines as ml')
            ->join('gmail_inventory_products as p', 'p.id', '=', 'ml.product_id')
            ->leftJoin('gmail_inventory_lots as l', 'l.id', '=', 'ml.lot_id')
            ->whereIn('ml.movement_id', $ids)
            ->orderByDesc('ml.movement_id')
            ->orderBy('p.nombre')
            ->get([
                'ml.movement_id',
                'p.nombre as producto',
                'p.codigo',
                'p.unidad',
                'ml.cantidad',
                'ml.costo_unitario',
                'ml.costo_total',
                'l.ingresado_el as lote_fecha',
            ])
            ->groupBy('movement_id');

        $summary = (object) [
            'movimientos' => (int) $movements->count(),
            'cantidad_total' => (float) $lines->flatten(1)->sum('cantidad'),
            'costo_total' => (float) $movements->sum('costo_total'),
            'venta_total' => (float) $movements->sum(fn($m) => (float) ($m->precio_venta ?? 0)),
            'sin_precio' => (int) $movements->filter(fn($m) => ((float) ($m->precio_venta ?? 0)) <= 0)->count(),
            'ultimo_movimiento' => $movements->first(),
        ];

        return view('gmail.inventory.exit_group_show', compact('destinatario', 'tipo', 'movements', 'lines', 'summary'));
    }

    // GET /gmail/inventario/api/contactos
    public function contactsApi(Request $request)
    {
        $q    = trim((string) $request->query('q', ''));
        $tipo = trim((string) $request->query('tipo', ''));

        $query = $this->db()
            ->table('gmail_inventory_contacts')
            ->orderByDesc('updated_at')
            ->limit(8);

        if ($tipo !== '') {
            $query->where('tipo', $tipo);
        }
        if ($q !== '') {
            $query->where(function ($qb) use ($q) {
                $qb->where('nombre', 'like', "%{$q}%")
                   ->orWhere('rut', 'like', "%{$q}%")
                   ->orWhere('empresa', 'like', "%{$q}%");
            });
        }

        return response()->json(
            $query->get(['id', 'tipo', 'nombre', 'rut', 'empresa', 'cargo', 'area', 'telefono', 'email'])
        );
    }

    // POST /gmail/inventario/api/contactos
    public function contactStore(Request $request)
    {
        $validated = $request->validate([
            'tipo'     => 'required|string|in:cliente,trabajador,destinatario',
            'nombre'   => 'required|string|max:200',
            'rut'      => 'nullable|string|max:30',
            'empresa'  => 'nullable|string|max:200',
            'cargo'    => 'nullable|string|max:100',
            'area'     => 'nullable|string|max:100',
            'telefono' => 'nullable|string|max:50',
            'email'    => 'nullable|email|max:200',
            'notas'    => 'nullable|string|max:1000',
        ]);

        $id = $this->db()->table('gmail_inventory_contacts')->insertGetId([
            ...$validated,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(
            $this->db()->table('gmail_inventory_contacts')->find($id)
        );
    }
}
