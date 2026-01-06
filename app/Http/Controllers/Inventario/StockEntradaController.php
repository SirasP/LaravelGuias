<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockEntradaController extends Controller
{
    public function create()
    {
        $productos = DB::table('productos')
            ->where('activo', 1)
            ->orderBy('nombre')
            ->get();

        $bodegas = DB::table('bodegas')
            ->orderByDesc('es_principal')
            ->orderBy('nombre')
            ->get();

        return view('inventario.stock_entrada', compact('productos', 'bodegas'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'bodega_id' => ['required', 'integer', 'exists:bodegas,id'],
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'costo_unitario' => ['required', 'numeric', 'gte:0'],
            'ingresado_el' => ['nullable', 'date'],
            'codigo_lote' => ['nullable', 'string', 'max:255'],
            'vence_el' => ['nullable', 'date'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ]);

        $fecha = $data['ingresado_el'] ?? now();

        DB::transaction(function () use ($data, $fecha) {

            // 1) Crear lote FIFO (entrada)
            $loteId = DB::table('lotes_inventario')->insertGetId([
                'producto_id' => $data['producto_id'],
                'bodega_id' => $data['bodega_id'],
                'codigo_lote' => $data['codigo_lote'] ?? null,
                'ingresado_el' => $fecha,
                'vence_el' => $data['vence_el'] ?? null,

                'costo_unitario' => $data['costo_unitario'],
                'cantidad_ingresada' => $data['cantidad'],
                'cantidad_salida' => 0,
                'cantidad_disponible' => $data['cantidad'],

                // Para trazabilidad (esto es “ajuste manual”)
                'origen_tipo' => 'ajuste_stock',
                'origen_id' => null,

                // Si tu tabla tiene este campo (lo usamos en compras)
                'costo_pendiente' => false,

                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2) Crear movimiento (kardex)
            DB::table('movimientos_inventario')->insert([
                'producto_id' => $data['producto_id'],
                'bodega_id' => $data['bodega_id'],
                'tipo' => 'ENTRADA',
                'ocurrio_el' => $fecha,

                'cantidad' => $data['cantidad'],
                'costo_unitario' => $data['costo_unitario'],
                'costo_total' => bcmul((string) $data['cantidad'], (string) $data['costo_unitario'], 6),

                'documento_tipo' => 'AJUSTE',
                'documento_id' => $loteId,
                'notas' => $data['notas'] ?? 'Entrada manual / stock inicial',

                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('inventario.stock')->with('ok', 'Stock ingresado ✅');
    }
}
