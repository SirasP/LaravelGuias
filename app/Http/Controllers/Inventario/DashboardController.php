<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // 📅 Últimos 9 días (incluye hoy)
        $from = Carbon::now()->subDays(9)->startOfDay();

        /*
        |--------------------------------------------------------------------------
        | GRÁFICO: kilos reales por día (Frambuesa)
        |--------------------------------------------------------------------------
        */
        $rows = DB::table('excel_out_transfer_lines as l')
            ->join('excel_out_transfers as t', 't.id', '=', 'l.excel_out_transfer_id')
            ->where('l.producto', 'Frambuesa Orgánica WakeField')
            ->whereDate('t.fecha_prevista', '>=', $from)
            ->select(
                DB::raw('DATE(t.fecha_prevista) as fecha'),
                DB::raw("
                    ROUND(
                        SUM(
                            CASE
                                WHEN l.cantidad = FLOOR(l.cantidad)
                                    THEN l.cantidad / 1000
                                ELSE l.cantidad
                            END
                        ),
                        3
                    ) as kilos_reales
                ")
            )
            ->groupBy(DB::raw('DATE(t.fecha_prevista)'))
            ->orderBy('fecha')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | KPI: total últimos 5 días
        |--------------------------------------------------------------------------
        */
        $kpi5Dias = $rows->sum('kilos_reales');

        /*
        |--------------------------------------------------------------------------
        | TABLA: detalle por producto (últimos 5 días)
        |--------------------------------------------------------------------------
        */
        $productos = DB::table('excel_out_transfer_lines as l')
            ->join('excel_out_transfers as t', 't.id', '=', 'l.excel_out_transfer_id')
            ->where('l.producto', 'Frambuesa Orgánica WakeField')
            ->whereDate('t.fecha_prevista', '>=', $from)
            ->select(
                'l.producto',
                DB::raw("
                    ROUND(
                        SUM(
                            CASE
                                WHEN l.cantidad = FLOOR(l.cantidad)
                                    THEN l.cantidad / 1000
                                ELSE l.cantidad
                            END
                        ),
                        3
                    ) as total_kilos
                ")
            )
            ->groupBy('l.producto')
            ->orderByDesc('total_kilos')
            ->get();

        return view('index', [
            'chartLabels' => $rows->map(
                fn($r) => Carbon::parse($r->fecha)->format('d-m')
            ),
            'chartData' => $rows->pluck('kilos_reales'),
            'kpi5Dias' => $kpi5Dias,
            'productos' => $productos,
        ]);
    }
}
