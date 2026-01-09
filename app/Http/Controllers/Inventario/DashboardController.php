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
        // 📋 TABLA POR PRODUCTO (ODOO)
        // ======================
        $productos = DB::table('excel_out_transfer_lines as l')
            ->join('excel_out_transfers as t', 't.id', '=', 'l.excel_out_transfer_id')
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
        // 📊 GRÁFICO DIARIO (ODOO vs CENTROS)
        // ======================
        $rows = DB::table('excel_out_transfers as t')

            // ===============================
            // ODOO → kilos desde líneas
            // ===============================
            ->leftJoin(DB::raw("
                (
                    SELECT
                        l.excel_out_transfer_id,
                        SUM(
                            CAST(
                                REPLACE(
                                    JSON_UNQUOTE(JSON_EXTRACT(l.raw, '$.L')),
                                    ',',
                                    ''
                                ) AS DECIMAL(18,3)
                            )
                        ) AS kilos_odoo
                    FROM excel_out_transfer_lines l
                    WHERE JSON_EXTRACT(l.raw, '$.L') IS NOT NULL
                    GROUP BY l.excel_out_transfer_id
                ) odoo
            "), 'odoo.excel_out_transfer_id', '=', 't.id')

            // ===============================
            // CENTROS → PDF / XML / EXCEL
            // ===============================
            ->leftJoin(
                DB::raw("
        (
            SELECT
                p.guia_no,
                MAX(
                    CAST(
                        JSON_UNQUOTE(
                            COALESCE(
                                JSON_EXTRACT(JSON_UNQUOTE(p.meta), '$.total_kgs'),
                                JSON_EXTRACT(JSON_UNQUOTE(p.meta), '$.recepcion.total_kgs'),
                                JSON_EXTRACT(JSON_UNQUOTE(p.meta), '$.kgs_recibido'),
                                JSON_EXTRACT(JSON_UNQUOTE(p.meta), '$.total.kgs'),
                                JSON_EXTRACT(JSON_UNQUOTE(p.meta), '$.subtotal.kgs')
                            )
                        ) AS DECIMAL(18,3)
                    )
                ) AS kilos_centro
            FROM pdf_imports p
            GROUP BY p.guia_no
        ) centros
    "),
                DB::raw('CAST(centros.guia_no AS CHAR)'),
                '=',
                DB::raw("REGEXP_SUBSTR(t.guia_entrega, '[0-9]+')")
            )




            // ===============================
            // FILTROS REALES (IGUAL QUE LARAVEL)
            // ===============================
            ->where('t.estado', 'Realizado')
            ->whereNotNull('t.guia_entrega')
            ->whereRaw("TRIM(t.guia_entrega) <> ''")
            ->whereNotNull('t.patente')
            ->whereRaw("TRIM(t.patente) <> ''")
            ->whereNotNull('t.chofer')
            ->whereRaw("TRIM(t.chofer) <> ''")
            ->whereDate('t.fecha_prevista', '>=', $from)

            // ===============================
            // AGRUPACIÓN DIARIA
            // ===============================
            ->select(
                DB::raw('DATE(t.fecha_prevista) as fecha'),
                DB::raw('SUM(odoo.kilos_odoo) as kilos_odoo'),
                DB::raw('SUM(COALESCE(centros.kilos_centro, 0)) as kilos_centros'),
                DB::raw('SUM(odoo.kilos_odoo) - SUM(COALESCE(centros.kilos_centro, 0)) as diferencia')
            )
            ->groupBy(DB::raw('DATE(t.fecha_prevista)'))
            ->orderBy('fecha')
            ->get();


        // ======================
        // 📤 VISTA
        // ======================
        return view('index', [
            'chartLabels' => $rows->map(fn($r) => Carbon::parse($r->fecha)->format('d-m')),
            'chartData' => $rows->pluck('kilos_odoo')->map(fn($v) => (float) $v),
            'centrosData' => $rows->pluck('kilos_centros')->map(fn($v) => (float) $v),

            'productos' => $productos,

            'kpi5Dias' => (float) $rows->sum('kilos_odoo'),
            'kpiCentros' => (float) $rows->sum('kilos_centros'),
        ]);

    }
}