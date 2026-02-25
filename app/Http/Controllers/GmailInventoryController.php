<?php

namespace App\Http\Controllers;

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
    public function exitStore(Request $request, GmailDteInventoryService $service)
    {
        $validated = $request->validate([
            'destinatario'         => 'required|string|max:200',
            'tipo_salida'          => 'nullable|string|in:Venta,EPP,Salida',
            'notas'                => 'nullable|string|max:1000',
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

        try {
            $result = $service->processExit(
                $validated['items'],
                auth()->id(),
                $validated['destinatario'],
                $validated['notas'] ?? null,
                $validated['tipo_salida'] ?? null
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['items' => $e->getMessage()]);
        }

        return redirect()
            ->route('gmail.inventory.exits')
            ->with('success', 'Salida registrada correctamente (movimiento #' . $result['movement_id'] . ').');
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

        $query = $this->db()
            ->table('gmail_inventory_movements')
            ->where('tipo', 'SALIDA')
            ->orderByDesc('ocurrio_el')
            ->orderByDesc('id');

        if ($q !== '') {
            $query->where('destinatario', 'like', "%{$q}%");
        }
        if ($desde !== '') {
            $query->where('ocurrio_el', '>=', $desde);
        }
        if ($hasta !== '') {
            $query->where('ocurrio_el', '<=', $hasta);
        }

        $movements = $query->paginate(24)->withQueryString();

        $ids = $movements->pluck('id')->all();

        // Lines grouped by movement for card detail
        $lines = $this->db()
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
            ->groupBy('movement_id');

        // KPI del mes actual
        $mesInicio = now()->startOfMonth()->toDateString();
        $mesFin    = now()->endOfMonth()->toDateString();

        $kpiMes = $this->db()
            ->table('gmail_inventory_movements')
            ->where('tipo', 'SALIDA')
            ->whereBetween('ocurrio_el', [$mesInicio, $mesFin])
            ->selectRaw('count(*) as total_salidas, coalesce(sum(costo_total), 0) as costo_total, coalesce(sum(precio_venta), 0) as precio_venta_total')
            ->first();

        // Producto más retirado del mes
        $topProducto = $this->db()
            ->table('gmail_inventory_movement_lines as ml')
            ->join('gmail_inventory_movements as m', 'm.id', '=', 'ml.movement_id')
            ->join('gmail_inventory_products as p', 'p.id', '=', 'ml.product_id')
            ->where('m.tipo', 'SALIDA')
            ->whereBetween('m.ocurrio_el', [$mesInicio, $mesFin])
            ->selectRaw('p.nombre, sum(ml.cantidad) as total_qty')
            ->groupBy('p.id', 'p.nombre')
            ->orderByDesc('total_qty')
            ->first();

        return view('gmail.inventory.exits', compact(
            'movements', 'lines', 'q', 'desde', 'hasta',
            'kpiMes', 'topProducto'
        ));
    }

    // GET /gmail/inventario/salidas/export
    public function exitExport(Request $request)
    {
        $q     = trim((string) $request->query('q', ''));
        $desde = trim((string) $request->query('desde', ''));
        $hasta = trim((string) $request->query('hasta', ''));

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
        if ($desde !== '') {
            $query->where('m.ocurrio_el', '>=', $desde);
        }
        if ($hasta !== '') {
            $query->where('m.ocurrio_el', '<=', $hasta);
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
}
