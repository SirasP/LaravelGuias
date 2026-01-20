<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CentroController extends Controller
{
    public function show(Request $request)
    {
        $contacto = $request->query('contacto');

        if (!$contacto) {
            abort(404, 'Centro no especificado');
        }
        $qtyNorm = "(
  CASE
    WHEN l.cantidad IS NULL OR l.cantidad = '' THEN 0

    -- 622.000 => 622
    WHEN CAST(l.cantidad AS CHAR) REGEXP '^[0-9]+\\.[0]{3}$' THEN
      CAST(SUBSTRING_INDEX(CAST(l.cantidad AS CHAR), '.', 1) AS UNSIGNED)

    -- 622,000 => 622
    WHEN CAST(l.cantidad AS CHAR) REGEXP '^[0-9]+,[0]{3}$' THEN
      CAST(SUBSTRING_INDEX(CAST(l.cantidad AS CHAR), ',', 1) AS UNSIGNED)

    -- SOLO BANDEJAS: 3.510 => 3510
    WHEN UPPER(COALESCE(l.producto,'')) LIKE '%BANDE%'
         AND CAST(l.cantidad AS CHAR) REGEXP '^[0-9]+\\.[0-9]{3}$'
    THEN
      CAST(REPLACE(CAST(l.cantidad AS CHAR), '.', '') AS UNSIGNED)

    -- SOLO BANDEJAS con coma miles: 3,510 => 3510
    WHEN UPPER(COALESCE(l.producto,'')) LIKE '%BANDE%'
         AND CAST(l.cantidad AS CHAR) REGEXP '^[0-9]+,[0-9]{3}$'
    THEN
      CAST(REPLACE(CAST(l.cantidad AS CHAR), ',', '') AS UNSIGNED)

    -- default seguro
    ELSE
      CAST(CAST(l.cantidad AS CHAR) AS UNSIGNED)
  END
)";


        // 🔒 Normalizamos el centro (CLAVE)
        $contactoNormalizado = trim(mb_strtoupper($contacto));

        /**
         * ============================================================
         * 📄 GUÍAS POR CENTRO (DOCUMENTAL)
         * ============================================================
         */
        $guias = DB::table('comfrut_guias as g')
            ->leftJoin('comfrut_guia_detalles as d', 'd.comfrut_guia_id', '=', 'g.id')
            ->whereRaw('UPPER(TRIM(g.productor)) = ?', [$contactoNormalizado])
            ->select(
                'g.id',
                'g.guia_numero',
                'g.fecha_guia',
                'g.tipo_dte',
                'g.productor',
                DB::raw('SUM(d.cantidad) as total_unidades'),
                DB::raw('SUM(d.monto) as monto_total')
            )
            ->groupBy(
                'g.id',
                'g.guia_numero',
                'g.fecha_guia',
                'g.tipo_dte',
                'g.productor'
            )
            ->orderBy('g.fecha_guia', 'desc')
            ->get();

        /**
         * ============================================================
         * 📦 PRODUCTOS EN GUÍAS
         * ============================================================
         */
        $productos = DB::table('comfrut_guias as g')
            ->join('comfrut_guia_detalles as d', 'd.comfrut_guia_id', '=', 'g.id')
            ->whereRaw('UPPER(TRIM(g.productor)) = ?', [$contactoNormalizado])
            ->selectRaw("
        d.nombre_item AS nombre_original,

        CASE
-- ✅ SANTIAGO COMERCIO EXTERIOR
WHEN UPPER(TRIM(g.productor)) LIKE 'SANTIAGO COMERCIO EXTERIOR%'
     AND UPPER(d.nombre_item) LIKE '%BERRIES%'
     AND UPPER(d.nombre_item) LIKE '%AZUL%'
THEN 'BANDEJAS AZUL'

WHEN UPPER(TRIM(g.productor)) LIKE 'SANTIAGO COMERCIO EXTERIOR%'
     AND UPPER(d.nombre_item) LIKE '%IQF%'
     AND UPPER(d.nombre_item) LIKE '%BLANC%'
THEN 'BANDEJA BLANCA'

WHEN UPPER(TRIM(g.productor)) LIKE 'SANTIAGO COMERCIO EXTERIOR%'
     AND UPPER(d.nombre_item) LIKE '%IQF%'
     AND UPPER(d.nombre_item) LIKE '%AMARIL%'
THEN 'BANDEJON AMARILLO'

-- ✅ RIO FUTURO
WHEN UPPER(TRIM(g.productor)) LIKE 'RIO FUTURO%'
     AND UPPER(d.nombre_item) LIKE '%IQF%'
THEN 'BANDEJON'

WHEN UPPER(TRIM(g.productor)) LIKE 'RIO FUTURO%'
     AND UPPER(d.nombre_item) LIKE '%COSECHA MECANICA%'
THEN 'AMARILLA'

WHEN UPPER(TRIM(g.productor)) LIKE 'RIO FUTURO%'
     AND (
        UPPER(d.nombre_item) LIKE '%ARANDANERA%'
        OR UPPER(d.nombre_item) LIKE '%BLANCA 1/8%'
     )
THEN 'ARANDANERA'

WHEN UPPER(TRIM(g.productor)) LIKE 'RIO FUTURO%'
     AND (
        UPPER(d.nombre_item) LIKE '%FRUTILLERA%'
        OR UPPER(d.nombre_item) LIKE '%ESPARRAGUERA%'
     )
THEN 'FRUTILLERA'


-- ✅ COMFRUT
WHEN UPPER(TRIM(g.productor)) LIKE 'COMFRUT%'
     AND UPPER(d.nombre_item) LIKE '%BDJA%'
     AND UPPER(d.nombre_item) LIKE '%COSECHA%'
THEN 'BANDEJAS AZUL'

WHEN UPPER(TRIM(g.productor)) LIKE 'COMFRUT%'
     AND (
        UPPER(d.nombre_item) LIKE '%BAJO PESO%'
        OR UPPER(d.nombre_item) LIKE '%595X40X10%'
        OR UPPER(d.nombre_item) LIKE '%BLANCA%'
     )
THEN 'BANDEJON'



        -- Vitafoods 
            WHEN UPPER(TRIM(g.productor)) LIKE 'VITAFOODS%'
                 AND UPPER(d.nombre_item) LIKE '%IQF%'
                THEN 'BANDEJON AMARILLO'

            WHEN UPPER(TRIM(g.productor)) LIKE 'VITAFOODS%'
                 AND UPPER(d.nombre_item) LIKE '%AZUL%'
                THEN 'BANDEJAS AZUL'

            WHEN UPPER(TRIM(g.productor)) LIKE '%VITAFOODS%'
                 AND UPPER(d.nombre_item) LIKE '%FRUTILLERA%'
                THEN 'BANDEJON'

            WHEN UPPER(TRIM(g.productor)) LIKE '%VITAFOODS%'
                 AND UPPER(d.nombre_item) LIKE '%FRAMBUESA%'
                THEN 'BANDEJA PLOMA'

            ELSE NULL
        END AS tipo_bandeja,

        SUM(d.cantidad) AS total_unidades
    ")
            ->groupBy('nombre_original', 'tipo_bandeja')
            ->orderByDesc('total_unidades')
            ->get();


        /**
         * ============================================================
         * 🧮 TOTALES GUÍAS (BANDEJAS / PALLETS)
         * ============================================================
         */
        $totales = DB::table('comfrut_guias as g')
            ->join('comfrut_guia_detalles as d', 'd.comfrut_guia_id', '=', 'g.id')
            ->whereRaw('UPPER(TRIM(g.productor)) = ?', [$contactoNormalizado])
            ->selectRaw("
                SUM(
                    CASE 
                        WHEN UPPER(d.nombre_item) LIKE '%PALLET%' 
                        THEN d.cantidad 
                        ELSE 0 
                    END
                ) AS total_pallets,
                SUM(
                    CASE 
                        WHEN UPPER(d.nombre_item) NOT LIKE '%PALLET%' 
                        THEN d.cantidad 
                        ELSE 0 
                    END
                ) AS total_bandejas
            ")
            ->first();

        /**
         * ============================================================
         * 🚚 SALIDAS REALES (excel_out_transfers)
         * 👉 USANDO RAW->L (NO cantidad)
         * ============================================================
         */


        $bandejasPorTipo = DB::table('excel_out_transfer_lines as l')
            ->join('excel_out_transfers as t', 't.id', '=', 'l.excel_out_transfer_id')

            // 🔒 MISMAS REGLAS DEL EXPORT
            ->whereRaw('UPPER(TRIM(t.contacto)) = ?', [$contactoNormalizado])
            ->where('t.estado', 'Realizado')

            ->whereNotNull('t.guia_entrega')
            ->whereRaw("TRIM(t.guia_entrega) <> ''")

            ->whereNotNull('t.patente')
            ->whereRaw("TRIM(t.patente) <> ''")

            ->whereNotNull('t.chofer')
            ->whereRaw("TRIM(t.chofer) <> ''")

            // 🔒 SOLO ENVASES
            ->whereRaw("UPPER(l.producto) LIKE '%BANDE%'")

            ->selectRaw("
    CASE
        WHEN UPPER(l.producto) LIKE '%FRUTILL%' THEN 'FRUTILLERA'
        WHEN UPPER(l.producto) LIKE '%ARANDAN%' THEN 'ARANDANERA'
        WHEN UPPER(l.producto) LIKE '%BLANC%'   THEN 'BANDEJA BLANCA'
        WHEN UPPER(l.producto) LIKE '%PLOM%'    THEN 'BANDEJA PLOMA'
        WHEN UPPER(l.producto) LIKE '%AMARIL%'  THEN 'BANDEJON AMARILLO'
        WHEN UPPER(l.producto) LIKE '%AZUL%'    THEN 'BANDEJAS AZUL'
        WHEN UPPER(l.producto) LIKE '%BANDEJ%'  THEN 'BANDEJON'
        ELSE NULL
    END AS tipo_bandeja,
    SUM($qtyNorm) AS total_bandejas
")
            ->groupBy('tipo_bandeja')
            ->havingRaw('tipo_bandeja IS NOT NULL')
            ->orderByDesc('total_bandejas')
            ->get();



        /**
         * ============================================================
         * 🔥 TOTAL GENERAL SALIDAS (MISMO CÁLCULO, MISMO CENTRO)
         * ============================================================
         */


        $totalBandejasOut = DB::table('excel_out_transfer_lines as l')
            ->join('excel_out_transfers as t', 't.id', '=', 'l.excel_out_transfer_id')

            ->whereRaw('UPPER(TRIM(t.contacto)) = ?', [$contactoNormalizado])
            ->where('t.estado', 'Realizado')

            ->whereNotNull('t.guia_entrega')
            ->whereRaw("TRIM(t.guia_entrega) <> ''")

            ->whereNotNull('t.patente')
            ->whereRaw("TRIM(t.patente) <> ''")

            ->whereNotNull('t.chofer')
            ->whereRaw("TRIM(t.chofer) <> ''")

            ->whereRaw("UPPER(l.producto) LIKE '%BANDE%'")

            ->selectRaw("SUM($qtyNorm) as total")
            ->value('total');

        $guiasPorTipo = collect();

        if (str_contains($contactoNormalizado, 'VITAFOODS')) {

            $guiasPorTipo = DB::table('comfrut_guias as g')
                ->join('comfrut_guia_detalles as d', 'd.comfrut_guia_id', '=', 'g.id')
                ->whereRaw('UPPER(TRIM(g.productor)) = ?', [$contactoNormalizado])
                ->whereRaw("UPPER(d.nombre_item) LIKE '%BANDE%'")
                ->selectRaw("
            d.nombre_item AS nombre_original,
            CASE
                WHEN UPPER(d.nombre_item) LIKE '%IQF%' THEN 'BANDEJON AMARILLO'
                WHEN UPPER(d.nombre_item) LIKE '%AZUL%' THEN 'BANDEJAS AZUL'
                WHEN UPPER(d.nombre_item) LIKE '%FRUTILLERA%' THEN 'BANDEJON'
                WHEN UPPER(d.nombre_item) LIKE '%FRAMBUESA%' THEN 'BANDEJA PLOMA'
                ELSE NULL
            END AS tipo_bandeja,
            SUM(d.cantidad) AS total_guia
        ")
                ->groupBy('nombre_original', 'tipo_bandeja')
                ->havingRaw('tipo_bandeja IS NOT NULL')
                ->get();

        } elseif (str_contains($contactoNormalizado, 'COMFRUT')) {

            $guiasPorTipo = DB::table('comfrut_guias as g')
                ->join('comfrut_guia_detalles as d', 'd.comfrut_guia_id', '=', 'g.id')
                ->whereRaw('UPPER(TRIM(g.productor)) = ?', [$contactoNormalizado])
                ->whereRaw("
            UPPER(d.nombre_item) LIKE '%BANDE%'
            OR UPPER(d.nombre_item) LIKE '%BDJA%'
        ")
                ->selectRaw("
            d.nombre_item AS nombre_original,
            CASE
                WHEN UPPER(d.nombre_item) LIKE '%BDJA%' AND UPPER(d.nombre_item) LIKE '%COSECHA%'
                    THEN 'BANDEJAS AZUL'

                WHEN UPPER(d.nombre_item) LIKE '%BAJO PESO%'
                     OR UPPER(d.nombre_item) LIKE '%595X40X10%'
                     OR UPPER(d.nombre_item) LIKE '%BLANCA%'
                    THEN 'BANDEJON'

                ELSE NULL
            END AS tipo_bandeja,
            SUM(d.cantidad) AS total_guia
        ")
                ->groupBy('nombre_original', 'tipo_bandeja')
                ->havingRaw('tipo_bandeja IS NOT NULL')
                ->get();

        } elseif (str_contains($contactoNormalizado, 'RIO FUTURO')) {

            $guiasPorTipo = DB::table('comfrut_guias as g')
                ->join('comfrut_guia_detalles as d', 'd.comfrut_guia_id', '=', 'g.id')
                ->whereRaw('UPPER(TRIM(g.productor)) = ?', [$contactoNormalizado])
                ->whereRaw("
            UPPER(d.nombre_item) LIKE '%IQF%'
            OR (UPPER(d.nombre_item) LIKE '%COSECHA%' AND UPPER(d.nombre_item) LIKE '%MECANICA%')
            OR UPPER(d.nombre_item) LIKE '%ARANDANERA%'
            OR UPPER(d.nombre_item) LIKE '%BLANCA 1/8%'
            OR UPPER(d.nombre_item) LIKE '%FRUTILLERA%'
            OR UPPER(d.nombre_item) LIKE '%ESPARRAGUERA%'
        ")
                ->selectRaw("
            d.nombre_item AS nombre_original,
            CASE
                WHEN UPPER(d.nombre_item) LIKE '%IQF%' THEN 'BANDEJON'

                WHEN UPPER(d.nombre_item) LIKE '%COSECHA%'
                 AND UPPER(d.nombre_item) LIKE '%MECANICA%'
                THEN 'BANDEJON AMARILLO'

                WHEN UPPER(d.nombre_item) LIKE '%ARANDANERA%'
                  OR UPPER(d.nombre_item) LIKE '%BLANCA 1/8%'
                THEN 'ARANDANERA'

                WHEN UPPER(d.nombre_item) LIKE '%FRUTILLERA%'
                  OR UPPER(d.nombre_item) LIKE '%ESPARRAGUERA%'
                THEN 'FRUTILLERA'

                ELSE NULL
            END AS tipo_bandeja,
            SUM(d.cantidad) AS total_guia
        ")
                ->groupBy('nombre_original', 'tipo_bandeja')
                ->havingRaw('tipo_bandeja IS NOT NULL')
                ->get();
        } elseif (str_contains($contactoNormalizado, 'SANTIAGO COMERCIO EXTERIOR')) {

            $guiasPorTipo = DB::table('comfrut_guias as g')
                ->join('comfrut_guia_detalles as d', 'd.comfrut_guia_id', '=', 'g.id')
                ->whereRaw('UPPER(TRIM(g.productor)) = ?', [$contactoNormalizado])

                // ✅ FILTRO QUIRÚRGICO SOLO A ESOS 3 ITEMS
                ->whereRaw("
            UPPER(d.nombre_item) LIKE '%BERRIES%'
            OR (UPPER(d.nombre_item) LIKE '%IQF%' AND UPPER(d.nombre_item) LIKE '%BLANC%')
            OR (UPPER(d.nombre_item) LIKE '%IQF%' AND UPPER(d.nombre_item) LIKE '%AMARIL%')
        ")

                ->selectRaw("
            d.nombre_item AS nombre_original,
            CASE
                WHEN UPPER(d.nombre_item) LIKE '%BERRIES%'
                 AND UPPER(d.nombre_item) LIKE '%AZUL%'
                    THEN 'BANDEJAS AZUL'

                WHEN UPPER(d.nombre_item) LIKE '%IQF%'
                 AND UPPER(d.nombre_item) LIKE '%BLANC%'
                    THEN 'BANDEJON'

                WHEN UPPER(d.nombre_item) LIKE '%IQF%'
                 AND UPPER(d.nombre_item) LIKE '%AMARIL%'
                    THEN 'BANDEJON AMARILLO'

                ELSE NULL
            END AS tipo_bandeja,
            SUM(d.cantidad) AS total_guia
        ")
                ->groupBy('nombre_original', 'tipo_bandeja')
                ->havingRaw('tipo_bandeja IS NOT NULL')
                ->get();
        }







        $diferenciaPorTipo = [];

        foreach ($guiasPorTipo as $g) {
            $tipo = trim(mb_strtoupper($g->tipo_bandeja));

            $odoo = (int) $bandejasPorTipo
                ->filter(fn($o) => trim(mb_strtoupper($o->tipo_bandeja)) === $tipo)
                ->sum('total_bandejas');

            $diferenciaPorTipo[] = [
                'tipo' => $tipo,
                'guia' => (int) $g->total_guia,
                'odoo' => (int) $odoo,
                'diff' => (int) $g->total_guia - (int) $odoo,
            ];
        }

        // ✅ AGREGAR TIPOS QUE EXISTEN EN ODOO PERO NO EN GUÍAS (para que no "desaparezcan")
        $tiposEnGuias = collect($guiasPorTipo)
            ->pluck('tipo_bandeja')
            ->map(fn($t) => trim(mb_strtoupper($t)))
            ->unique()
            ->values();

        foreach ($bandejasPorTipo as $o) {

            $tipoOdoo = trim(mb_strtoupper($o->tipo_bandeja));

            // si ya estaba en guías, no lo duplicamos
            if ($tiposEnGuias->contains($tipoOdoo)) {
                continue;
            }

            $diferenciaPorTipo[] = [
                'tipo' => $tipoOdoo,
                'guia' => 0,
                'odoo' => (int) $o->total_bandejas,
                'diff' => 0 - (int) $o->total_bandejas,
            ];
        }


        $totalBandejasGuia = (int) ($totales->total_bandejas ?? 0);
        $diferenciaBandejas = ($totalBandejasGuia ?? 0) - ($totalBandejasOut ?? 0);

        /**
         * ============================================================
         * 📤 VIEW
         * ============================================================
         */
        return view('centros.detalle', [
            'contacto' => $contacto,
            'guias' => $guias,
            'productos' => $productos,
            'totalPallets' => (int) ($totales->total_pallets ?? 0),
            'totalBandejas' => (int) ($totales->total_bandejas ?? 0),

            // 🔥 SALIDAS REALES
            'bandejasPorTipo' => $bandejasPorTipo,
            'totalBandejasOut' => $totalBandejasOut,
            'diferenciaBandejas' => $diferenciaBandejas,
            'diferenciaPorTipo' => $diferenciaPorTipo,
            'guiasPorTipo' => $guiasPorTipo,

        ]);
    }
}
