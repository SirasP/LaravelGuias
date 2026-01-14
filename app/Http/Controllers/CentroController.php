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
            ->select(
                'd.nombre_item',
                DB::raw('SUM(d.cantidad) as total_unidades')
            )
            ->groupBy('d.nombre_item')
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
            WHEN UPPER(l.producto) LIKE '%AMARIL%' THEN 'BANDEJON AMARILLO'
            WHEN UPPER(l.producto) LIKE '%AZUL%'   THEN 'BANDEJAS AZUL'
            WHEN UPPER(l.producto) LIKE '%PLOM%'   THEN 'BANDEJA PLOMA'
            WHEN UPPER(l.producto) LIKE '%BANDEJON%' THEN 'BANDEJON'
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

        $guiasPorTipo = DB::table('comfrut_guias as g')
            ->join('comfrut_guia_detalles as d', 'd.comfrut_guia_id', '=', 'g.id')
            ->whereRaw('UPPER(TRIM(g.productor)) = ?', [$contactoNormalizado])
            ->selectRaw("
        CASE
            WHEN UPPER(d.nombre_item) LIKE '%IQF%' THEN 'BANDEJON AMARILLO'
            WHEN UPPER(d.nombre_item) LIKE '%AZUL%' THEN 'BANDEJAS AZUL'
            WHEN UPPER(d.nombre_item) LIKE '%FRUTILLERA%' THEN 'BANDEJON'
            WHEN UPPER(d.nombre_item) LIKE '%FRAMBUESA%' THEN 'BANDEJA PLOMA'
            ELSE NULL
        END AS tipo_bandeja,
        SUM(d.cantidad) AS total_guia
    ")
            ->whereRaw("UPPER(d.nombre_item) LIKE '%BANDE%'")
            ->groupBy('tipo_bandeja')
            ->havingRaw('tipo_bandeja IS NOT NULL')
            ->get();

        $diferenciaPorTipo = [];

        foreach ($guiasPorTipo as $g) {
            $tipo = trim(mb_strtoupper($g->tipo_bandeja));

            $odoo = $bandejasPorTipo
                ->first(fn($o) => trim(mb_strtoupper($o->tipo_bandeja)) === $tipo)
                ->total_bandejas ?? 0;

            $diferenciaPorTipo[] = [
                'tipo' => $tipo,
                'guia' => (int) $g->total_guia,
                'odoo' => (int) $odoo,
                'diff' => (int) $g->total_guia - (int) $odoo,
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

        ]);
    }
}
