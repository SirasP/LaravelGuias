<?php

namespace App\Http\Controllers\FuelControl;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {

        try {
            /* =========================
             * PRODUCTOS 
             * ========================= */
            $productos = DB::connection('fuelcontrol')
                ->table('productos')
                ->select('id', 'nombre', 'cantidad')
                ->orderBy('nombre', 'asc')
                ->get();

            /* =========================
             * ÚLTIMOS MOVIMIENTOS
             * ========================= */
            $movimientos = DB::connection('fuelcontrol')
                ->table('movimientos as m')
                ->leftJoin('productos as p', 'p.id', '=', 'm.producto_id')
                ->select(
                    'm.*',
                    'p.nombre as producto_nombre'
                )
                ->orderByDesc('m.fecha_movimiento')
                ->limit(5)

                ->get();




            /* =========================
             * NOTIFICACIONES (SOLO ADMIN)
             * ========================= */
            $notificaciones = collect();

            $notificaciones = DB::connection('fuelcontrol')
                ->table('notificaciones as n')
                ->join('notificacion_usuarios as nu', 'nu.notificacion_id', '=', 'n.id')
                ->where('nu.user_id', auth()->id())
                ->where('nu.leido', 0)
                ->orderByDesc('n.created_at')
                ->limit(5)
                ->get([
                    'n.id',
                    'n.titulo',
                    'n.mensaje',
                    'n.created_at'
                ]);

            $users = DB::table('users')->pluck('id');

            foreach ($users as $userId) {
                DB::connection('fuelcontrol')
                    ->table('notificacion_usuarios')
                    ->insert([
                        'notificacion_id' => $notificacionId,
                        'user_id' => $userId,
                        'leido' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
            }
            /* =========================
             * RESUMEN
             * ========================= */
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

            /* =========================
             * GRÁFICOS: CONSUMO ÚLTIMOS 30 DÍAS
             * ========================= */
            $desde = now()->subDays(30)->startOfDay();

            $consumo = DB::connection('fuelcontrol')
                ->table('movimientos')
                ->join('productos', 'productos.id', '=', 'movimientos.producto_id')
                ->selectRaw('
        DATE(fecha_movimiento) as fecha,
        LOWER(productos.nombre) as producto,
        SUM(ABS(movimientos.cantidad)) as total
    ')
                ->where('tipo', 'salida')
                ->where('fecha_movimiento', '>=', $desde)
                ->groupBy('fecha', 'producto')
                ->orderBy('fecha')
                ->get();

            /* =========================
             * GASOLINA
             * ========================= */

            $gasolina = $consumo->filter(fn($r) => str_contains($r->producto, 'gas'));

            $labelsGasolina = $gasolina
                ->pluck('fecha')
                ->map(fn($f) => Carbon::parse($f)->format('d-m'))
                ->values();

            $dataGasolina = $gasolina
                ->pluck('total')
                ->values();

            /* =========================
             * DIESEL
             * ========================= */

            $diesel = $consumo->filter(fn($r) => str_contains($r->producto, 'die'));

            $labelsDiesel = $diesel
                ->pluck('fecha')
                ->map(fn($f) => Carbon::parse($f)->format('d-m'))
                ->values();

            $dataDiesel = $diesel
                ->pluck('total')
                ->values();

            /* =========================
             * COMBINAR DATOS PARA GRÁFICO
             * ========================= */

        } catch (\Throwable $e) {
            dd([
                'error' => true,
                'mensaje' => $e->getMessage(),
                'conexion' => 'fuelcontrol',
            ]);
        }

        return view('fuelcontrol.index', compact(
            'productos',
            'movimientos',
            'resumen',
            'labelsGasolina',
            'dataGasolina',
            'labelsDiesel',
            'dataDiesel',
            'notificaciones'
        ));
    }
}
