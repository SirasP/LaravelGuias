<?php

namespace App\Http\Controllers\FuelControl;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Stock actual por producto
        $productos = DB::table('productos')
            ->select('id', 'nombre', 'cantidad')
            ->orderBy('nombre')
            ->get();

        // Últimos movimientos
        $movimientos = DB::table('movimientos')
            ->orderByDesc('fecha_movimiento')
            ->limit(10)
            ->get();

        // Resumen rápido
        $resumen = [
            'total_productos' => $productos->count(),
            'total_vehiculos' => DB::table('vehiculos')->count(),
            'movimientos_hoy' => DB::table('movimientos')
                ->whereDate('fecha_movimiento', now()->toDateString())
                ->count(),
        ];

        return view('fuelcontrol.index', compact(
            'productos',
            'movimientos',
            'resumen'
        ));
    }
}
