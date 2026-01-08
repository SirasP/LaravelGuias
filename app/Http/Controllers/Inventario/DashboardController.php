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
         * 1️⃣ Normalización base (parseo inteligente del texto)
         */
        $kgNorm = "(
            CASE
                WHEN l.cantidad IS NULL OR l.cantidad = '' THEN 0

                -- 1,234.56  (coma miles + punto decimal)
                WHEN CAST(l.cantidad AS CHAR) REGEXP '^[0-9]{1,3}(,[0-9]{3})+(\\.[0-9]+)?$' THEN
                    CAST(REPLACE(CAST(l.cantidad AS CHAR), ',', '') AS DECIMAL(18,3))

                -- 1.234,56  (punto miles + coma decimal)
                WHEN CAST(l.cantidad AS CHAR) REGEXP '^[0-9]{1,3}(\\.[0-9]{3})+(,[0-9]+)?$' THEN
                    CAST(REPLACE(REPLACE(CAST(l.cantidad AS CHAR), '.', ''), ',', '.') AS DECIMAL(18,3))

                -- 846.000  => 846
                WHEN CAST(l.cantidad AS CHAR) REGEXP '^[0-9]+\\.[0]{3}$' THEN
                    CAST(SUBSTRING_INDEX(CAST(l.cantidad AS CHAR), '.', 1) AS DECIMAL(18,3))

                -- 366,60 => 366.60
                WHEN INSTR(CAST(l.cantidad AS CHAR), ',') > 0
                     AND INSTR(CAST(l.cantidad AS CHAR), '.') = 0 THEN
                    CAST(REPLACE(CAST(l.cantidad AS CHAR), ',', '.') AS DECIMAL(18,3))

                -- default
                ELSE
                    CAST(CAST(l.cantidad AS CHAR) AS DECIMAL(18,3))
            END
        )";

        /**
         * 2️⃣ Regla CLAVE (opción C):
         *    Solo dividir por 1000 cuando claramente es kg * 1000
         */
        $kgNormFixed = "(
    CASE
        -- kg × 1000
        WHEN {$kgNorm} >= 100000
            THEN {$kgNorm} / 1000

        -- kg × 100
        WHEN {$kgNorm} >= 10000
            THEN {$kgNorm} / 100

        -- kg reales
        ELSE {$kgNorm}
    END
)";
        

        // ===== GRÁFICO =====
        $rows = DB::table('excel_out_transfer_lines as l')
            ->join('excel_out_transfers as t', 't.id', '=', 'l.excel_out_transfer_id')
            ->where('l.producto', 'Frambuesa Orgánica WakeField')
            ->whereDate('t.fecha_prevista', '>=', $from)
            ->select(
                DB::raw('DATE(t.fecha_prevista) as fecha'),
                DB::raw("SUM($kgNormFixed) as kilos_reales")
            )
            ->groupBy(DB::raw('DATE(t.fecha_prevista)'))
            ->orderBy('fecha')
            ->get();

        // ===== KPI =====
        $kpi = (float) $rows->sum('kilos_reales');

        // ===== TABLA =====
        $productos = DB::table('excel_out_transfer_lines as l')
            ->join('excel_out_transfers as t', 't.id', '=', 'l.excel_out_transfer_id')
            ->where('l.producto', 'Frambuesa Orgánica WakeField')
            ->whereDate('t.fecha_prevista', '>=', $from)
            ->select(
                'l.producto',
                DB::raw("SUM($kgNormFixed) as total_kilos")
            )
            ->groupBy('l.producto')
            ->get();

        return view('index', [
            'chartLabels' => $rows->map(
                fn($r) =>
                Carbon::parse($r->fecha)->format('d-m')
            ),
            'chartData' => $rows->pluck('kilos_reales')->map(fn($v) => (float) $v),
            'kpi5Dias' => $kpi,
            'productos' => $productos,
        ]);
    }
}
