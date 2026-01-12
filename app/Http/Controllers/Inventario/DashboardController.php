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

        $qtyNorm = "
(
  CASE
    WHEN l.cantidad IS NULL OR l.cantidad = '' THEN 0

    WHEN CAST(l.cantidad AS CHAR) REGEXP '^[0-9]+\\.[0]{3}$'
      THEN CAST(SUBSTRING_INDEX(CAST(l.cantidad AS CHAR), '.', 1) AS UNSIGNED)

    WHEN CAST(l.cantidad AS CHAR) REGEXP '^[0-9]+,[0]{3}$'
      THEN CAST(SUBSTRING_INDEX(CAST(l.cantidad AS CHAR), ',', 1) AS UNSIGNED)

    WHEN UPPER(l.producto) LIKE '%BANDE%'
         AND CAST(l.cantidad AS CHAR) REGEXP '^[0-9]+\\.[0-9]{3}$'
      THEN CAST(REPLACE(CAST(l.cantidad AS CHAR), '.', '') AS UNSIGNED)

    WHEN UPPER(l.producto) LIKE '%BANDE%'
         AND CAST(l.cantidad AS CHAR) REGEXP '^[0-9]+,[0-9]{3}$'
      THEN CAST(REPLACE(CAST(l.cantidad AS CHAR), ',', '') AS UNSIGNED)

    ELSE CAST(l.cantidad AS UNSIGNED)
  END
)
";

        // ======================
        // 📋 TABLA POR PRODUCTO (ODOO)
        // ======================
        $productos = DB::query()
            ->fromSub(
                DB::table('excel_out_transfers as t')
                    ->join('excel_out_transfer_lines as l', 'l.excel_out_transfer_id', '=', 't.id')
                    ->where('t.estado', '<>', 'NULA')
                    ->where('l.producto', 'Frambuesa Orgánica WakeField')
                    ->whereDate('t.fecha_prevista', '>=', $from)
                    ->whereNotNull(DB::raw("JSON_EXTRACT(l.raw, '$.L')"))
                    ->groupBy('t.id', 'l.producto')
                    ->select(
                        'l.producto',
                        DB::raw("MAX($kgFromRaw) as kilos_por_guia")
                    ),
                'x'
            )
            ->select(
                'producto',
                DB::raw('SUM(kilos_por_guia) as total_kilos')
            )
            ->groupBy('producto')
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
                        MAX(
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
        // 📊 KILOS POR CONTACTO (CENTROS)
        // ======================
        $kilosPorContacto = DB::table('excel_out_transfers as t')
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




            // filtros reales
            ->where('t.estado', 'Realizado')
            ->whereNotNull('t.guia_entrega')
            ->whereRaw("TRIM(t.guia_entrega) <> ''")
            ->whereNotNull('t.patente')
            ->whereRaw("TRIM(t.patente) <> ''")
            ->whereNotNull('t.chofer')
            ->whereRaw("TRIM(t.chofer) <> ''")
            ->whereDate('t.fecha_prevista', '>=', $from)
            ->where('t.contacto', '<>', 'Agrícola Epple, Heinrich y Enfield Spa')

            ->groupBy('t.contacto')

            ->select(
                't.contacto',
                DB::raw("COUNT(DISTINCT REGEXP_SUBSTR(t.guia_entrega, '[0-9]+')) AS total_guias"),
                DB::raw("COUNT(DISTINCT centros.guia_no) AS guias_con_match"),
                DB::raw("
            COUNT(DISTINCT REGEXP_SUBSTR(t.guia_entrega, '[0-9]+'))
            -
            COUNT(DISTINCT centros.guia_no)
            AS guias_sin_match
        "),
                DB::raw('SUM(centros.kilos_centro) AS total_kilos')
            )

            ->orderByDesc(DB::raw('SUM(centros.kilos_centro)'))
            ->get();

        $bandejasPorContacto = DB::table('excel_out_transfers as t')
            ->join('excel_out_transfer_lines as l', 'l.excel_out_transfer_id', '=', 't.id')

            // filtros reales (idénticos a kilos)
            ->where('t.estado', 'Realizado')
            ->whereNotNull('t.guia_entrega')
            ->whereRaw("TRIM(t.guia_entrega) <> ''")
            ->whereNotNull('t.patente')
            ->whereRaw("TRIM(t.patente) <> ''")
            ->whereNotNull('t.chofer')
            ->whereRaw("TRIM(t.chofer) <> ''")
            ->whereDate('t.fecha_prevista', '>=', $from)

            // SOLO BANDEJAS
            ->whereRaw("UPPER(l.producto) LIKE '%BANDE%'")

            ->groupBy('t.contacto')

            ->select(
                't.contacto',
                DB::raw("SUM($qtyNorm) AS total_bandejas")
            )
            ->get()
            ->keyBy('contacto');

        $kpiBandejas = DB::table('excel_out_transfers as t')
            ->join('excel_out_transfer_lines as l', 'l.excel_out_transfer_id', '=', 't.id')

            // mismos filtros “reales”
            ->where('t.estado', 'Realizado')
            ->whereNotNull('t.guia_entrega')
            ->whereRaw("TRIM(t.guia_entrega) <> ''")
            ->whereNotNull('t.patente')
            ->whereRaw("TRIM(t.patente) <> ''")
            ->whereNotNull('t.chofer')
            ->whereRaw("TRIM(t.chofer) <> ''")
            ->whereDate('t.fecha_prevista', '>=', $from)

            // solo bandejas
            ->whereRaw("UPPER(l.producto) LIKE '%BANDE%'")

            ->selectRaw("SUM($qtyNorm) as total_bandejas")
            ->value('total_bandejas');



        // ======================
// 📦 KPI BANDEJAS AGRAK (últimos 40 días)
// ======================
        $kpiBandejasAgrak = DB::table('agrak_registros')
            ->whereDate('fecha_registro', '>=', $from)
            ->whereNotNull('numero_bandejas_palet')
            ->selectRaw('SUM(numero_bandejas_palet) as total_bandejas')
            ->value('total_bandejas');
        // ======================
// 🟫 KPI BINS AGRAK (últimos 40 días)
// ======================
        $kpiBinsAgrak = DB::table('agrak_registros')
            ->whereDate('fecha_registro', '>=', $from)
            ->whereNotNull('codigo_bin')
            ->count('codigo_bin');


        $aliasContactos = [
            'Santiago Comercio Exterior Exportaciones S.A.' => 'Santiago Comercio Exterior',
            'Agroindustria Pinochet Fuenzalida Limitada' => 'Agroindustria Pinochet',
            'COMFRUT CHILE SPA' => 'COMFRUT',
            'Rio Futuro Procesos SpA' => 'Rio Futuro',
            'Valle Frio SPA' => 'Valle Frío',
            'Vitafoods Spa' => 'Vitafoods',
        ];

        $kpiCentrosPorContacto = (float) $kilosPorContacto->sum('total_kilos');
        $topEmpresa = $kilosPorContacto->sortByDesc('total_kilos')->first();


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
            'contactosLabels' => $kilosPorContacto->map(function ($row) use ($aliasContactos) {
                return $aliasContactos[$row->contacto] ?? $row->contacto;
            }),
            'contactosKilos' => $kilosPorContacto->pluck('total_kilos')->map(fn($v) => (float) $v),
            'kpiCentrosPorContacto' => $kpiCentrosPorContacto,
            'topEmpresa' => $topEmpresa,
            'kilosPorContacto' => $kilosPorContacto,
            'bandejasPorContacto' => $bandejasPorContacto,
            'kpiBandejas' => (int) $kpiBandejas,
            // 🔥 NUEVO KPI BANDEJAS AGRAK
            'kpiBandejasAgrak' => (int) $kpiBandejasAgrak,
            //'kpiFormatted' => number_format($rows->sum('kilos_odoo'), 3, ',', '.'),
            'kpiBinsAgrak' => (int) $kpiBinsAgrak,

        ]);

    }
}