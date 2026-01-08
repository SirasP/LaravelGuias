<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $from = Carbon::now()->subDays(40)->startOfDay();

        /**
         * 🔥 KILOS REALES DESDE RAW (columna L del Excel)
         * Formato Excel / Odoo:
         * - separador miles: ,
         * - decimal: .
         * Ej: "1,170.70" → 1170.70 kg
         */
        $kgFromRaw = "
            CAST(
                REPLACE(
                    JSON_UNQUOTE(JSON_EXTRACT(l.raw, '$.L')),
                    ',',
                    ''
                ) AS DECIMAL(18,3)
            )
        ";

        // ======================
        // 📊 GRÁFICO POR DÍA
        // ======================
        $rows = DB::table('excel_out_transfer_lines as l')
            ->join('excel_out_transfers as t', 't.id', '=', 'l.excel_out_transfer_id')

            // 🔒 NO mostrar guías NULAS
            ->where('t.estado', '<>', 'NULA')

            ->where('l.producto', 'Frambuesa Orgánica WakeField')
            ->whereNotNull(DB::raw("JSON_EXTRACT(l.raw, '$.L')"))
            ->whereDate('t.fecha_prevista', '>=', $from)
            ->select(
                DB::raw('DATE(t.fecha_prevista) as fecha'),
                DB::raw("SUM($kgFromRaw) as kilos_reales")
            )
            ->groupBy(DB::raw('DATE(t.fecha_prevista)'))
            ->orderBy('fecha')
            ->get();

        // ======================
        // 📋 TABLA POR PRODUCTO
        // ======================
        $productos = DB::table('excel_out_transfer_lines as l')
            ->join('excel_out_transfers as t', 't.id', '=', 'l.excel_out_transfer_id')

            // 🔒 misma regla
            ->where('t.estado', '<>', 'NULA')

            ->where('l.producto', 'Frambuesa Orgánica WakeField')
            ->whereNotNull(DB::raw("JSON_EXTRACT(l.raw, '$.L')"))
            ->whereDate('t.fecha_prevista', '>=', $from)
            ->select(
                'l.producto',
                DB::raw("SUM($kgFromRaw) as total_kilos")
            )
            ->groupBy('l.producto')
            ->get();

        // ======================
        // 📤 VISTA
        // ======================
        return view('index', [
            'chartLabels' => $rows->map(
                fn($r) => Carbon::parse($r->fecha)->format('d-m')
            ),
            'chartData' => $rows->pluck('kilos_reales')->map(fn($v) => (float) $v),
            'kpi5Dias' => (float) $rows->sum('kilos_reales'),
            'productos' => $productos,
        ]);
    }
}
