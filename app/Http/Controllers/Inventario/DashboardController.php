<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $from = Carbon::now()->subDays(120)->startOfDay();

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

        // Obtener el kg promedio de bandejas Agrak
        $kgPromedioAgrak = DB::table('agrak_bandeja_promedios')
            ->orderByDesc('id')
            ->value('kg_promedio') ?? 0;
        //CHART BANDEJAS AGRAK POR DÍA
        $bandejasAgrakPorDia = DB::table('agrak_registros')
            ->whereDate('fecha_registro', '>=', $from)
            ->whereNotNull('numero_bandejas_palet')
            ->selectRaw('
        DATE(fecha_registro) as fecha,
        SUM(numero_bandejas_palet) as total_bandejas
    ')
            ->groupBy(DB::raw('DATE(fecha_registro)'))
            ->orderBy('fecha')
            ->get();

        $bandejasAgrakLabels = $bandejasAgrakPorDia
            ->map(fn($r) => Carbon::parse($r->fecha)->format('d-m'));

        $bandejasAgrakData = $bandejasAgrakPorDia
            ->pluck('total_bandejas')
            ->map(fn($v) => (int) $v);

        //CHART BINS AGRAK POR DÍA
        $binsAgrakPorDia = DB::table('agrak_registros')
            ->whereDate('fecha_registro', '>=', $from)
            ->whereNotNull('codigo_bin')
            ->selectRaw('
        DATE(fecha_registro) as fecha,
        COUNT(DISTINCT codigo_bin) as total_bins
    ')
            ->groupBy(DB::raw('DATE(fecha_registro)'))
            ->orderBy('fecha')
            ->get();

        $binsAgrakLabels = $binsAgrakPorDia
            ->map(fn($r) => Carbon::parse($r->fecha)->format('d-m'));

        $binsAgrakData = $binsAgrakPorDia
            ->pluck('total_bins')
            ->map(fn($v) => (int) $v);

        $maquinasAgrak = DB::table('agrak_registros')
            ->whereNotNull('maquina')
            ->whereNotNull('codigo_bin')
            ->whereRaw("TRIM(codigo_bin) <> ''")
            ->selectRaw("
        REGEXP_REPLACE(
            UPPER(TRIM(maquina)),
            '[^A-Z0-9 ]',
            ''
        ) AS maquina_norm,
        COUNT(DISTINCT codigo_bin) AS total_bins
    ")
            ->groupBy('maquina_norm')
            ->havingRaw('COUNT(DISTINCT codigo_bin) > 0')
            ->orderByDesc('total_bins')
            ->get();

        // CHART BINS POR CUARTEL AGRAK
        $binsPorCuartel = DB::table('agrak_registros')
            ->select(
                'etiquetas_cuartel',
                DB::raw('COUNT(DISTINCT codigo_bin) as total_bins')
            )
            ->whereNotNull('etiquetas_cuartel')
            ->whereNotNull('codigo_bin')
            ->groupBy('etiquetas_cuartel')
            ->orderByDesc('total_bins')
            ->get();




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

        $maquinasLabels = $maquinasAgrak->pluck('maquina_norm');
        $maquinasTotales = $maquinasAgrak->pluck('total_bins');



        $notificaciones = collect();

        if (auth()->check() && auth()->id() === 1) {
            $notificaciones = DB::connection('fuelcontrol')
                ->table('notificaciones as n')
                ->join('notificacion_usuarios as nu', 'nu.notificacion_id', '=', 'n.id')
                ->leftJoin('movimientos as m', 'm.id', '=', 'n.movimiento_id') // 🔥 ESTA LÍNEA FALTA
                ->where('nu.user_id', auth()->id())
                ->where('nu.leido', 0)
                ->orderByDesc('n.created_at')
                ->limit(5)
                ->get([
                    'n.id',
                    'n.titulo',
                    'n.tipo',
                    'n.movimiento_id',
                    'n.mensaje',
                    'n.created_at',
                    'm.estado', // 🔥 IMPORTANTE

                ]);
        }


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
            'kgPromedioAgrak' => (float) $kgPromedioAgrak,
            //chart bandejas agrak
            'bandejasAgrakLabels' => $bandejasAgrakLabels,
            'bandejasAgrakData' => $bandejasAgrakData,
            //chart bins agrak
            'binsAgrakLabels' => $binsAgrakLabels,
            'binsAgrakData' => $binsAgrakData,
            //chart maquinas agrak
            'maquinasLabels' => $maquinasLabels,
            'maquinasTotales' => $maquinasTotales,
            //chart bins por cuartel agrak
            'binsPorCuartelLabels' => $binsPorCuartel->pluck('etiquetas_cuartel')->values(),
            'binsPorCuartelData' => $binsPorCuartel->pluck('total_bins')->values(),
            'notificaciones' => $notificaciones,

        ]);

    }

    public function updateKgPromedio(Request $request)
    {
        $request->validate([
            'kg_promedio' => 'required|numeric|min:0'
        ]);

        DB::table('agrak_bandeja_promedios')->updateOrInsert(
            ['id' => 1],
            [
                'kg_promedio' => $request->kg_promedio,
                'updated_at' => now(),
            ]
        );

        return response()->json([
            'ok' => true,
            'kg_promedio' => (float) $request->kg_promedio,
        ]);
    }



}