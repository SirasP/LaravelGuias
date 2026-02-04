<?php

namespace App\Http\Controllers\FuelControl;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        try {
            // 🔌 TEST CONEXIÓN: productos
            $productos = DB::connection('fuelcontrol')
                ->table('productos')
                ->select('id', 'nombre', 'cantidad')
                ->orderBy('nombre', 'asc')
                ->get();

            // 🔌 TEST CONEXIÓN: últimos movimientos
            $movimientos = DB::connection('fuelcontrol')
                ->table('movimientos')
                ->orderByDesc('fecha_movimiento')
                ->limit(10)
                ->get();

            // 🔌 TEST CONEXIÓN: resumen
            $resumen = [
                'total_productos' => $productos->count(),
                'total_vehiculos' => DB::connection('fuelcontrol')
                    ->table('vehiculos')
                    ->count(),

                'movimientos_hoy' => DB::connection('fuelcontrol')
                    ->table('movimientos')
                    ->whereDate('fecha_movimiento', now()->toDateString())
                    ->count(),
            ];

        } catch (\Throwable $e) {
            // 🔥 Si algo falla, lo mostramos CLARO
            dd([
                'error' => true,
                'mensaje' => $e->getMessage(),
                'conexion' => 'fuelcontrol',
            ]);
        }

        return view('fuelcontrol.index', compact(
            'productos',
            'movimientos',
            'resumen'
        ));
    }
}
