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
        $contactoNormalizado = trim(mb_strtoupper($contacto));

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

            ->select(
                'l.producto as tipo_bandeja',
                DB::raw("SUM($qtyNorm) as total_bandejas")
            )
            ->groupBy('l.producto')
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
        ]);
    }
}
