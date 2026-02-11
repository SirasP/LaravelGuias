<?php

namespace App\Http\Controllers\FuelControl;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MovimientoController extends Controller
{
    /**
     * Listado general
     */
    public function index()
    {
        $movimientos = DB::connection('fuelcontrol')
            ->table('movimientos as m')
            ->join('productos as p', 'p.id', '=', 'm.producto_id')
            ->select(
                'm.*',
                'p.nombre as producto_nombre'
            )
            ->orderByDesc('m.id')
            ->paginate(20);


        return view('fuelcontrol.movimientos.index', compact('movimientos'));
    }

    /**
     * Mostrar detalle XML
     */
    public function show($id)
    {
        $conexion = DB::connection('fuelcontrol');

        $movimiento = $conexion->table('movimientos')
            ->where('id', $id)
            ->first();

        if (!$movimiento) {
            abort(404);
        }

        if (empty($movimiento->xml_path)) {
            return response()->json([
                'error' => true,
                'message' => 'Este movimiento no tiene XML'
            ], 404);
        }

        $ruta = storage_path('app/' . $movimiento->xml_path);

        if (!file_exists($ruta)) {
            return response()->json([
                'error' => true,
                'message' => 'Archivo XML no encontrado'
            ], 404);
        }

        $xml = file_get_contents($ruta);

        return view('fuelcontrol.movimientos.xml', compact('movimiento', 'xml'));
    }

    /**
     * Aprobar XML
     */
    public function aprobar($id)
    {
        $conexion = DB::connection('fuelcontrol');

        $movimiento = $conexion->table('movimientos')
            ->where('id', $id)
            ->first();

        if (!$movimiento) {
            abort(404);
        }

        if ($movimiento->estado !== 'pendiente') {
            return response()->json([
                'error' => true,
                'message' => 'Ya fue procesado'
            ], 400);
        }

        $conexion->transaction(function () use ($conexion, $movimiento) {

            $producto = $conexion->table('productos')
                ->where('id', $movimiento->producto_id)
                ->first();

            if (!$producto) {
                throw new \Exception('Producto no encontrado');
            }

            $conexion->table('productos')
                ->where('id', $producto->id)
                ->update([
                    'cantidad' => $producto->cantidad + $movimiento->cantidad
                ]);

            $conexion->table('movimientos')
                ->where('id', $movimiento->id)
                ->update([
                    'estado' => 'aprobado'
                ]);
        });

        return response()->json([
            'success' => true
        ]);
    }

    /**
     * Rechazar XML
     */
    public function rechazar($id)
    {
        $conexion = DB::connection('fuelcontrol');

        $movimiento = $conexion->table('movimientos')
            ->where('id', $id)
            ->first();

        if (!$movimiento) {
            abort(404);
        }

        if ($movimiento->estado !== 'pendiente') {
            return response()->json([
                'error' => true,
                'message' => 'Ya fue procesado'
            ], 400);
        }

        $conexion->table('movimientos')
            ->where('id', $movimiento->id)
            ->update([
                'estado' => 'rechazado'
            ]);

        return response()->json([
            'success' => true
        ]);
    }

    /**
     * Crear movimiento manual
     */
    public function store(Request $request)
    {
        $request->validate([
            'producto_id' => 'required',
            'cantidad' => 'required|numeric',
            'tipo' => 'required|in:ingreso,salida',
        ]);

        $conexion = DB::connection('fuelcontrol');

        $conexion->transaction(function () use ($conexion, $request) {

            $producto = $conexion->table('productos')
                ->where('id', $request->producto_id)
                ->first();

            if (!$producto) {
                throw new \Exception('Producto no encontrado');
            }

            $nuevaCantidad = $request->tipo === 'ingreso'
                ? $producto->cantidad + $request->cantidad
                : $producto->cantidad - $request->cantidad;

            $conexion->table('movimientos')->insert([
                'producto_id' => $request->producto_id,
                'cantidad' => $request->cantidad,
                'tipo' => $request->tipo,
                'estado' => 'aprobado',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $conexion->table('productos')
                ->where('id', $producto->id)
                ->update([
                    'cantidad' => $nuevaCantidad
                ]);
        });

        return back()->with('success', 'Movimiento registrado correctamente');
    }
}
