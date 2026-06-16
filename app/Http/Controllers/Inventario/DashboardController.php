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
            ->whereDate('fecha_registro', '>=', $from)
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
            ->whereDate('fecha_registro', '>=', $from)
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
        // 📉 CÁLCULOS PERÍODO ANTERIOR (% CAMBIO)
        // ======================
        $fromPrev = Carbon::now()->subDays(240)->startOfDay();
        $toPrev   = Carbon::now()->subDays(120)->endOfDay();

        // Odoo (Kilos) prev
        $kpiOdooPrev = DB::table('excel_out_transfers as t')
            ->join('excel_out_transfer_lines as l', 'l.excel_out_transfer_id', '=', 't.id')
            ->where('t.estado', 'Realizado')
            ->whereNotNull('t.guia_entrega')
            ->whereRaw("TRIM(t.guia_entrega) <> ''")
            ->whereNotNull('t.patente')
            ->whereRaw("TRIM(t.patente) <> ''")
            ->whereNotNull('t.chofer')
            ->whereRaw("TRIM(t.chofer) <> ''")
            ->whereBetween('t.fecha_prevista', [$fromPrev, $toPrev])
            ->whereNotNull(DB::raw("JSON_EXTRACT(l.raw, '$.L')"))
            ->selectRaw("SUM($kgFromRaw) as kilos_odoo_prev")
            ->value('kilos_odoo_prev') ?? 0;

        // Odoo (Bandejas) prev
        $kpiBandejasPrev = DB::table('excel_out_transfers as t')
            ->join('excel_out_transfer_lines as l', 'l.excel_out_transfer_id', '=', 't.id')
            ->where('t.estado', 'Realizado')
            ->whereNotNull('t.guia_entrega')
            ->whereRaw("TRIM(t.guia_entrega) <> ''")
            ->whereBetween('t.fecha_prevista', [$fromPrev, $toPrev])
            ->whereRaw("UPPER(l.producto) LIKE '%BANDE%'")
            ->selectRaw("SUM($qtyNorm) as total_bandejas")
            ->value('total_bandejas') ?? 0;

        // Centros prev
        $kpiCentrosPrev = DB::table('excel_out_transfers as t')
            ->leftJoin(DB::raw("
                (SELECT p.guia_no, MAX(CAST(JSON_UNQUOTE(COALESCE(JSON_EXTRACT(JSON_UNQUOTE(p.meta), '$.total_kgs'), JSON_EXTRACT(JSON_UNQUOTE(p.meta), '$.recepcion.total_kgs'), JSON_EXTRACT(JSON_UNQUOTE(p.meta), '$.kgs_recibido'), JSON_EXTRACT(JSON_UNQUOTE(p.meta), '$.total.kgs'), JSON_EXTRACT(JSON_UNQUOTE(p.meta), '$.subtotal.kgs'))) AS DECIMAL(18,3))) AS kilos_centro FROM pdf_imports p GROUP BY p.guia_no) centros
            "), DB::raw('CAST(centros.guia_no AS CHAR)'), '=', DB::raw("REGEXP_SUBSTR(t.guia_entrega, '[0-9]+')"))
            ->where('t.estado', 'Realizado')
            ->whereNotNull('t.guia_entrega')
            ->whereRaw("TRIM(t.guia_entrega) <> ''")
            ->whereBetween('t.fecha_prevista', [$fromPrev, $toPrev])
            ->selectRaw('SUM(centros.kilos_centro) as total_centros')
            ->value('total_centros') ?? 0;

        // Agrak (Bandejas) prev
        $kpiBandejasAgrakPrev = DB::table('agrak_registros')
            ->whereBetween('fecha_registro', [$fromPrev, $toPrev])
            ->whereNotNull('numero_bandejas_palet')
            ->selectRaw('SUM(numero_bandejas_palet) as total_bandejas')
            ->value('total_bandejas') ?? 0;

        // Agrak (Bins) prev
        $kpiBinsAgrakPrev = DB::table('agrak_registros')
            ->whereBetween('fecha_registro', [$fromPrev, $toPrev])
            ->whereNotNull('codigo_bin')
            ->count('codigo_bin');

        $calcPct = function($current, $prev) {
            if ($prev == 0) return $current > 0 ? 100 : 0;
            return (($current - $prev) / abs($prev)) * 100;
        };

        $pctOdoo = $calcPct((float) $rows->sum('kilos_odoo'), (float) $kpiOdooPrev);
        $pctBandejas = $calcPct((float) $kpiBandejas, (float) $kpiBandejasPrev);
        $pctCentros = $calcPct((float) $rows->sum('kilos_centros'), (float) $kpiCentrosPrev);
        $pctBandejasAgrak = $calcPct((float) $kpiBandejasAgrak, (float) $kpiBandejasAgrakPrev);
        $pctBinsAgrak = $calcPct((float) $kpiBinsAgrak, (float) $kpiBinsAgrakPrev);


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
            'pctOdoo' => $pctOdoo,
            'pctBandejas' => $pctBandejas,
            'pctCentros' => $pctCentros,
            'pctBandejasAgrak' => $pctBandejasAgrak,
            'pctBinsAgrak' => $pctBinsAgrak,

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

    public function comparacion(Request $request)
    {
        $availableSeasons = $this->getAvailableSeasons();

        // Detect the latest season that actually contains records in the DB
        $maxAgrak = DB::table('agrak_registros')->max('fecha_registro');
        $maxOdoo = DB::table('excel_out_transfers')->max('fecha_prevista');

        $latestSeasonWithData = '2025/2026'; // Fallback default
        if ($maxAgrak || $maxOdoo) {
            $maxDateStr = $maxAgrak ?: Carbon::parse($maxOdoo)->format('Y-m-d');
            if ($maxAgrak && $maxOdoo) {
                $maxDateStr = max($maxAgrak, Carbon::parse($maxOdoo)->format('Y-m-d'));
            }
            $carbon = Carbon::parse($maxDateStr);
            $year = $carbon->year;
            if ($carbon->month >= 10) {
                $latestSeasonWithData = $year . '/' . ($year + 1);
            } else {
                $latestSeasonWithData = ($year - 1) . '/' . $year;
            }
        }

        // Season A defaults to the latest season that has data in the DB
        $seasonA = $request->input('season_a', $latestSeasonWithData);

        // Season B defaults to the NEXT (upcoming) season
        list($yL, $yL2) = explode('/', $latestSeasonWithData);
        $nextSeason = ($yL + 1) . '/' . ($yL + 2);
        $seasonB = $request->input('season_b', $nextSeason);

        $seriesA = [];
        $seriesB = [];

        if ($seasonA) {
            $seriesA = $this->getSeasonData($seasonA);
        }
        if ($seasonB) {
            $seriesB = $this->getSeasonData($seasonB);
        }

        $maxDays = max(count($seriesA), count($seriesB));
        $chartLabels = [];
        for ($i = 1; $i <= $maxDays; $i++) {
            $chartLabels[] = "Día {$i}";
        }

        $binsA_cum = collect($seriesA)->pluck('cum_bins')->toArray();
        $binsB_cum = collect($seriesB)->pluck('cum_bins')->toArray();

        $kilosA_cum = collect($seriesA)->pluck('cum_kilos')->toArray();
        $kilosB_cum = collect($seriesB)->pluck('cum_kilos')->toArray();

        $kilosCentrosA_cum = collect($seriesA)->pluck('cum_kilos_centros')->toArray();
        $kilosCentrosB_cum = collect($seriesB)->pluck('cum_kilos_centros')->toArray();

        $litrosA_cum = collect($seriesA)->pluck('cum_litros')->toArray();
        $litrosB_cum = collect($seriesB)->pluck('cum_litros')->toArray();

        $binsA_daily = collect($seriesA)->pluck('bins')->toArray();
        $binsB_daily = collect($seriesB)->pluck('bins')->toArray();

        $kilosA_daily = collect($seriesA)->pluck('kilos')->toArray();
        $kilosB_daily = collect($seriesB)->pluck('kilos')->toArray();

        $kilosCentrosA_daily = collect($seriesA)->pluck('kilos_centros')->toArray();
        $kilosCentrosB_daily = collect($seriesB)->pluck('kilos_centros')->toArray();

        $litrosA_daily = collect($seriesA)->pluck('litros')->toArray();
        $litrosB_daily = collect($seriesB)->pluck('litros')->toArray();

        // KPIs
        $totalBinsA = count($seriesA) > 0 ? end($seriesA)['cum_bins'] : 0;
        $totalBinsB = count($seriesB) > 0 ? end($seriesB)['cum_bins'] : 0;

        $totalKilosA = count($seriesA) > 0 ? end($seriesA)['cum_kilos'] : 0;
        $totalKilosB = count($seriesB) > 0 ? end($seriesB)['cum_kilos'] : 0;

        $totalKilosCentrosA = count($seriesA) > 0 ? end($seriesA)['cum_kilos_centros'] : 0;
        $totalKilosCentrosB = count($seriesB) > 0 ? end($seriesB)['cum_kilos_centros'] : 0;

        $totalLitrosA = count($seriesA) > 0 ? end($seriesA)['cum_litros'] : 0;
        $totalLitrosB = count($seriesB) > 0 ? end($seriesB)['cum_litros'] : 0;

        $totalBandejasA = count($seriesA) > 0 ? end($seriesA)['cum_bandejas'] : 0;
        $totalBandejasB = count($seriesB) > 0 ? end($seriesB)['cum_bandejas'] : 0;

        $activeDaysA = count($seriesA);
        $activeDaysB = count($seriesB);

        $tableRows = [];
        for ($i = 0; $i < $maxDays; $i++) {
            $rowA = $seriesA[$i] ?? null;
            $rowB = $seriesB[$i] ?? null;
            $tableRows[] = [
                'day' => $i + 1,
                // Season A
                'dateA' => $rowA ? Carbon::parse($rowA['date'])->format('d-m-Y') : '—',
                'binsA' => $rowA ? $rowA['bins'] : null,
                'cumBinsA' => $rowA ? $rowA['cum_bins'] : null,
                'kilosA' => $rowA ? $rowA['kilos'] : null,
                'cumKilosA' => $rowA ? $rowA['cum_kilos'] : null,
                'kilosCentrosA' => $rowA ? $rowA['kilos_centros'] : null,
                'cumKilosCentrosA' => $rowA ? $rowA['cum_kilos_centros'] : null,
                'litrosA' => $rowA ? $rowA['litros'] : null,
                'cumLitrosA' => $rowA ? $rowA['cum_litros'] : null,
                // Season B
                'dateB' => $rowB ? Carbon::parse($rowB['date'])->format('d-m-Y') : '—',
                'binsB' => $rowB ? $rowB['bins'] : null,
                'cumBinsB' => $rowB ? $rowB['cum_bins'] : null,
                'kilosB' => $rowB ? $rowB['kilos'] : null,
                'cumKilosB' => $rowB ? $rowB['cum_kilos'] : null,
                'kilosCentrosB' => $rowB ? $rowB['kilos_centros'] : null,
                'cumKilosCentrosB' => $rowB ? $rowB['cum_kilos_centros'] : null,
                'litrosB' => $rowB ? $rowB['litros'] : null,
                'cumLitrosB' => $rowB ? $rowB['cum_litros'] : null,
            ];
        }

        // --- BINS POR CUARTEL & COSECHADORA AGRAK ---
        // Season A Dates
        list($yStartA, $yEndA) = explode('/', $seasonA);
        $startA = "{$yStartA}-10-01 00:00:00";
        $endA = "{$yEndA}-04-30 23:59:59";

        $cuartelA = DB::table('agrak_registros')
            ->whereBetween('fecha_registro', [$startA, $endA])
            ->whereNotNull('cuartel')
            ->whereRaw("TRIM(cuartel) <> ''")
            ->whereNotNull('codigo_bin')
            ->select('cuartel', DB::raw('COUNT(DISTINCT codigo_bin) as bins'))
            ->groupBy('cuartel')
            ->pluck('bins', 'cuartel')
            ->toArray();

        $maquinaA = DB::table('agrak_registros')
            ->whereBetween('fecha_registro', [$startA, $endA])
            ->whereNotNull('maquina')
            ->whereRaw("TRIM(maquina) <> ''")
            ->whereNotNull('codigo_bin')
            ->select('maquina', DB::raw('COUNT(DISTINCT codigo_bin) as bins'))
            ->groupBy('maquina')
            ->pluck('bins', 'maquina')
            ->toArray();

        // Season B Dates
        list($yStartB, $yEndB) = explode('/', $seasonB);
        $startB = "{$yStartB}-10-01 00:00:00";
        $endB = "{$yEndB}-04-30 23:59:59";

        $cuartelB = DB::table('agrak_registros')
            ->whereBetween('fecha_registro', [$startB, $endB])
            ->whereNotNull('cuartel')
            ->whereRaw("TRIM(cuartel) <> ''")
            ->whereNotNull('codigo_bin')
            ->select('cuartel', DB::raw('COUNT(DISTINCT codigo_bin) as bins'))
            ->groupBy('cuartel')
            ->pluck('bins', 'cuartel')
            ->toArray();

        $maquinaB = DB::table('agrak_registros')
            ->whereBetween('fecha_registro', [$startB, $endB])
            ->whereNotNull('maquina')
            ->whereRaw("TRIM(maquina) <> ''")
            ->whereNotNull('codigo_bin')
            ->select('maquina', DB::raw('COUNT(DISTINCT codigo_bin) as bins'))
            ->groupBy('maquina')
            ->pluck('bins', 'maquina')
            ->toArray();

        // Align Cuarteles
        $cuartelLabels = array_unique(array_merge(array_keys($cuartelA), array_keys($cuartelB)));
        sort($cuartelLabels);
        $cuartelBinsA = [];
        $cuartelBinsB = [];
        foreach ($cuartelLabels as $c) {
            $cuartelBinsA[] = $cuartelA[$c] ?? 0;
            $cuartelBinsB[] = $cuartelB[$c] ?? 0;
        }

        // Align Maquinas
        $maquinaLabels = array_unique(array_merge(array_keys($maquinaA), array_keys($maquinaB)));
        sort($maquinaLabels);
        $maquinaBinsA = [];
        $maquinaBinsB = [];
        foreach ($maquinaLabels as $m) {
            $maquinaBinsA[] = $maquinaA[$m] ?? 0;
            $maquinaBinsB[] = $maquinaB[$m] ?? 0;
        }

        return view('inventario.comparacion', [
            'seasons' => $availableSeasons,
            'seasonA' => $seasonA,
            'seasonB' => $seasonB,
            'chartLabels' => $chartLabels,
            'binsA_cum' => $binsA_cum,
            'binsB_cum' => $binsB_cum,
            'kilosA_cum' => $kilosA_cum,
            'kilosB_cum' => $kilosB_cum,
            'kilosCentrosA_cum' => $kilosCentrosA_cum,
            'kilosCentrosB_cum' => $kilosCentrosB_cum,
            'litrosA_cum' => $litrosA_cum,
            'litrosB_cum' => $litrosB_cum,
            'binsA_daily' => $binsA_daily,
            'binsB_daily' => $binsB_daily,
            'kilosA_daily' => $kilosA_daily,
            'kilosB_daily' => $kilosB_daily,
            'kilosCentrosA_daily' => $kilosCentrosA_daily,
            'kilosCentrosB_daily' => $kilosCentrosB_daily,
            'litrosA_daily' => $litrosA_daily,
            'litrosB_daily' => $litrosB_daily,
            'totalBinsA' => $totalBinsA,
            'totalBinsB' => $totalBinsB,
            'totalKilosA' => $totalKilosA,
            'totalKilosB' => $totalKilosB,
            'totalKilosCentrosA' => $totalKilosCentrosA,
            'totalKilosCentrosB' => $totalKilosCentrosB,
            'totalLitrosA' => $totalLitrosA,
            'totalLitrosB' => $totalLitrosB,
            'totalBandejasA' => $totalBandejasA,
            'totalBandejasB' => $totalBandejasB,
            'activeDaysA' => $activeDaysA,
            'activeDaysB' => $activeDaysB,
            'tableRows' => $tableRows,
            'cuartelLabels' => $cuartelLabels,
            'cuartelBinsA' => $cuartelBinsA,
            'cuartelBinsB' => $cuartelBinsB,
            'maquinaLabels' => $maquinaLabels,
            'maquinaBinsA' => $maquinaBinsA,
            'maquinaBinsB' => $maquinaBinsB,
        ]);
    }

    private function getAvailableSeasons()
    {
        $startYear = 2024;
        $currentYear = Carbon::now()->year;
        // Include up to next year in case we are in transition or starting early
        $endYear = $currentYear + 1;
        $seasons = [];
        for ($y = $startYear; $y <= $endYear; $y++) {
            $seasons[] = $y . '/' . ($y + 1);
        }
        return array_reverse($seasons);
    }

    private function getSeasonData($season)
    {
        list($startYear, $endYear) = explode('/', $season);
        $seasonStart = "{$startYear}-10-01 00:00:00";
        $seasonEnd = "{$endYear}-04-30 23:59:59";

        $agrakDates = DB::table('agrak_registros')
            ->whereBetween('fecha_registro', [$seasonStart, $seasonEnd])
            ->whereNotNull('codigo_bin')
            ->selectRaw('fecha_registro as fecha, COUNT(DISTINCT codigo_bin) as bins, SUM(numero_bandejas_palet) as bandejas')
            ->groupBy('fecha_registro')
            ->orderBy('fecha_registro', 'ASC')
            ->get()
            ->keyBy(fn($r) => Carbon::parse($r->fecha)->format('Y-m-d'));

        $odooDates = DB::table('excel_out_transfers as t')
            ->leftJoin(DB::raw("
                (
                    SELECT
                        excel_out_transfer_id,
                        MAX(CAST(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(raw, '$.L')), ',', '') AS DECIMAL(18,3))) AS kilos_odoo
                    FROM excel_out_transfer_lines
                    WHERE JSON_EXTRACT(raw, '$.L') IS NOT NULL
                    GROUP BY excel_out_transfer_id
                ) odoo
            "), 'odoo.excel_out_transfer_id', '=', 't.id')
            ->leftJoin(DB::raw("
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
            "), DB::raw('CAST(centros.guia_no AS CHAR)'), '=', DB::raw("REGEXP_SUBSTR(t.guia_entrega, '[0-9]+')"))
            ->where('t.estado', 'Realizado')
            ->whereNotNull('t.guia_entrega')
            ->whereRaw("TRIM(t.guia_entrega) <> ''")
            ->whereNotNull('t.patente')
            ->whereRaw("TRIM(t.patente) <> ''")
            ->whereNotNull('t.chofer')
            ->whereRaw("TRIM(t.chofer) <> ''")
            ->whereBetween('t.fecha_prevista', [$seasonStart, $seasonEnd])
            ->selectRaw('DATE(t.fecha_prevista) as fecha, SUM(odoo.kilos_odoo) as kilos, SUM(COALESCE(centros.kilos_centro, 0)) as kilos_centros')
            ->groupBy(DB::raw('DATE(t.fecha_prevista)'))
            ->orderBy('fecha', 'ASC')
            ->get()
            ->keyBy(fn($r) => Carbon::parse($r->fecha)->format('Y-m-d'));

        // Query harvester fuel control daily litres
        $fuelDates = DB::connection('fuelcontrol')
            ->table('movimientos as m')
            ->join('vehiculos as v', 'v.id', '=', 'm.vehiculo_id')
            ->where('v.patente', 'Cosechadora')
            ->where('m.tipo', 'salida')
            ->where(function ($q) {
                $q->whereNull('m.estado')
                  ->orWhere('m.estado', 'aprobado');
            })
            ->whereBetween('m.fecha_movimiento', [$seasonStart, $seasonEnd])
            ->selectRaw('DATE(m.fecha_movimiento) as fecha, SUM(ABS(m.cantidad)) as litros')
            ->groupBy(DB::raw('DATE(m.fecha_movimiento)'))
            ->orderBy('fecha', 'ASC')
            ->get()
            ->keyBy(fn($r) => Carbon::parse($r->fecha)->format('Y-m-d'));

        $allDates = collect(array_merge(
            $agrakDates->keys()->toArray(),
            $odooDates->keys()->toArray(),
            $fuelDates->keys()->toArray()
        ))->unique()->sort()->values();

        $series = [];
        $cumBins = 0;
        $cumKilos = 0;
        $cumKilosCentros = 0;
        $cumBandejas = 0;
        $cumLitros = 0;

        foreach ($allDates as $index => $date) {
            $dayNum = $index + 1;
            $agrak = $agrakDates->get($date);
            $odoo = $odooDates->get($date);
            $fuel = $fuelDates->get($date);

            $bins = $agrak ? (int) $agrak->bins : 0;
            $bandejas = $agrak ? (int) $agrak->bandejas : 0;
            $kilos = $odoo ? (float) $odoo->kilos : 0.0;
            $kilosCentros = $odoo ? (float) $odoo->kilos_centros : 0.0;
            $litros = $fuel ? (float) $fuel->litros : 0.0;

            $cumBins += $bins;
            $cumBandejas += $bandejas;
            $cumKilos += $kilos;
            $cumKilosCentros += $kilosCentros;
            $cumLitros += $litros;

            $series[] = [
                'day' => $dayNum,
                'date' => $date,
                'bins' => $bins,
                'bandejas' => $bandejas,
                'kilos' => $kilos,
                'kilos_centros' => $kilosCentros,
                'litros' => $litros,
                'cum_bins' => $cumBins,
                'cum_bandejas' => $cumBandejas,
                'cum_kilos' => $cumKilos,
                'cum_kilos_centros' => $cumKilosCentros,
                'cum_litros' => $cumLitros,
            ];
        }

        return $series;
    }
}