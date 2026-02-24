<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductosController extends Controller
{
    private function db()
    {
        return DB::connection('fuelcontrol');
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $productos = $this->db()->table('gmail_inventory_products')
            ->select('id', 'nombre', 'codigo', 'unidad', 'stock_actual', 'costo_promedio', 'is_active', 'created_at')
            ->when($q !== '', fn($query) => $query->where(function ($qq) use ($q) {
                $qq->where('nombre', 'like', "%{$q}%")
                   ->orWhere('codigo', 'like', "%{$q}%");
            }))
            ->orderBy('nombre', 'asc')
            ->paginate(25)
            ->withQueryString();

        return view('inventario.productos', compact('productos', 'q'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'    => ['required', 'string', 'max:255'],
            'codigo'    => ['nullable', 'string', 'max:64'],
            'unidad'    => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable'],
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
        ]);

        $this->db()->table('gmail_inventory_products')->insert([
            'nombre'     => trim($data['nombre']),
            'codigo'     => $data['codigo'] ? strtoupper(trim($data['codigo'])) : null,
            'unidad'     => strtoupper(trim($data['unidad'] ?? 'UN')),
            'is_active'  => isset($data['is_active']) ? 1 : 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('inventario.productos')
            ->with('ok', 'Producto creado correctamente ✅');
    }

    public function show(int $id)
    {
        $db = $this->db();

        $producto = $db->table('gmail_inventory_products')->where('id', $id)->first();
        abort_if(!$producto, 404);

        // ── Lotes activos FIFO (más antiguos primero = se consumen primero) ──
        $lotes = $db->table('gmail_inventory_lots as l')
            ->leftJoin('gmail_dte_documents as d', 'l.document_id', '=', 'd.id')
            ->where('l.product_id', $id)
            ->where('l.estado', 'ABIERTO')
            ->where('l.cantidad_disponible', '>', 0)
            ->select(
                'l.id', 'l.ingresado_el', 'l.costo_unitario',
                'l.cantidad_ingresada', 'l.cantidad_disponible', 'l.cantidad_salida',
                'l.document_id',
                'd.folio', 'd.proveedor_nombre as proveedor',
                'd.proveedor_rut', 'd.fecha_factura', 'd.tipo_dte'
            )
            ->orderBy('l.ingresado_el', 'asc')
            ->get();

        // ── Historial de precios (todos los lotes con costo, incluidos cerrados) ──
        $historialPrecios = $db->table('gmail_inventory_lots as l')
            ->leftJoin('gmail_dte_documents as d', 'l.document_id', '=', 'd.id')
            ->where('l.product_id', $id)
            ->where('l.costo_unitario', '>', 0)
            ->select(
                'l.ingresado_el', 'l.costo_unitario', 'l.cantidad_ingresada',
                'd.folio', 'd.proveedor_nombre as proveedor', 'd.fecha_factura'
            )
            ->orderBy('l.ingresado_el', 'asc')
            ->limit(60)
            ->get();

        // ── Movimientos recientes (por línea de movimiento) ──
        $movimientos = $db->table('gmail_inventory_movement_lines as ml')
            ->join('gmail_inventory_movements as m', 'ml.movement_id', '=', 'm.id')
            ->leftJoin('gmail_dte_documents as d', 'm.document_id', '=', 'd.id')
            ->where('ml.product_id', $id)
            ->select(
                'ml.cantidad', 'ml.costo_unitario', 'ml.costo_total',
                'm.tipo', 'm.ocurrio_el', 'm.notas', 'm.estado',
                'd.folio', 'd.proveedor_nombre as proveedor'
            )
            ->orderBy('m.ocurrio_el', 'desc')
            ->limit(30)
            ->get();

        // ── KPIs (stock y costo promedio ya calculados en el producto) ──
        $stockTotal    = (float) $producto->stock_actual;
        $costoPromedio = (float) $producto->costo_promedio;
        $valorTotal    = $stockTotal * $costoPromedio;
        $ultimoPrecio  = $historialPrecios->last()?->costo_unitario ?? 0;
        $primerPrecio  = $historialPrecios->first()?->costo_unitario ?? 0;
        $variacion     = ($primerPrecio > 0 && $ultimoPrecio > 0)
            ? (((float)$ultimoPrecio - (float)$primerPrecio) / (float)$primerPrecio) * 100
            : null;

        return view('inventario.producto_detalle', compact(
            'producto', 'lotes', 'historialPrecios',
            'movimientos', 'stockTotal', 'valorTotal', 'costoPromedio',
            'ultimoPrecio', 'variacion'
        ));
    }

    public function toggle($id)
    {
        $producto = $this->db()->table('gmail_inventory_products')->where('id', $id)->first();
        if (!$producto) {
            return response()->json(['success' => false], 404);
        }

        $nuevo = !(bool) $producto->is_active;

        $this->db()->table('gmail_inventory_products')->where('id', $id)->update([
            'is_active'  => $nuevo,
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'activo' => $nuevo]);
    }
}
