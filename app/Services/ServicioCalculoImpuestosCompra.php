<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class ServicioCalculoImpuestosCompra
{
    public function calcularLinea(int $documentoId, int $productoId, float $cantidad, int $unidadLineaId, ?float $precioUnitario, float $descuento = 0.0): array
    {
        $doc = DB::table('documentos_compra')->where('id', $documentoId)->first();
        if (!$doc)
            throw new RuntimeException("Documento no existe: {$documentoId}");

        $prod = DB::table('productos')->where('id', $productoId)->first();
        if (!$prod)
            throw new RuntimeException("Producto no existe: {$productoId}");

        $perfil = $prod->perfil_impuesto_id
            ? DB::table('perfiles_impuestos')->where('id', $prod->perfil_impuesto_id)->first()
            : null;

        // guía sin precio => no calculamos impuestos
        if ($precioUnitario === null) {
            return [
                'monto_neto' => 0,
                'monto_impuesto_especifico' => 0,
                'monto_iva' => 0,
                'monto_total' => 0,
                'costo_inventario_total' => 0,
            ];
        }

        $tasaIva = (float) $doc->tasa_iva;
        $ivaRecuperable = (bool) $doc->iva_recuperable;
        $preciosIncluyenIva = (bool) $doc->precios_incluyen_iva;

        $aplicaEsp = $perfil ? (bool) $perfil->aplica_impuesto_especifico : false;
        $tipoEsp = $perfil?->tipo_impuesto_especifico;
        $tasaEsp = $perfil ? (float) $perfil->tasa_impuesto_especifico : 0.0;

        $inclIvaCosto = $perfil ? (bool) $perfil->incluir_iva_en_costo_inventario : false;
        $inclEspCosto = $perfil ? (bool) $perfil->incluir_especifico_en_costo_inventario : true;
        $baseIvaIncluyeEsp = $perfil ? (bool) $perfil->base_iva_incluye_especifico : true;

        // 1) Impuesto específico
        $montoEsp = 0.0;
        if ($aplicaEsp) {
            if ($tipoEsp === 'POR_LITRO') {
                $cantidadBase = app(ServicioConversionUnidades::class)->aUnidadBaseProducto($productoId, $cantidad, $unidadLineaId);
                $montoEsp = $cantidadBase * $tasaEsp;
            } elseif ($tipoEsp === 'POR_UNIDAD') {
                $montoEsp = $cantidad * $tasaEsp;
            } elseif ($tipoEsp === 'PORCENTAJE') {
                // se calcula después del neto
            }
        }

        // 2) Neto / IVA / Total
        if (!$preciosIncluyenIva) {
            $montoNeto = max(0.0, ($cantidad * $precioUnitario) - $descuento);

            if ($aplicaEsp && $tipoEsp === 'PORCENTAJE') {
                $montoEsp = $montoNeto * $tasaEsp;
            }

            $baseIva = $baseIvaIncluyeEsp ? ($montoNeto + $montoEsp) : $montoNeto;
            $montoIva = $baseIva * $tasaIva;
            $montoTotal = $montoNeto + $montoEsp + $montoIva;
        } else {
            $montoTotal = max(0.0, ($cantidad * $precioUnitario) - $descuento);

            if ($baseIvaIncluyeEsp) {
                $base = $montoTotal / (1.0 + $tasaIva);
                $montoNeto = max(0.0, $base - $montoEsp);
                $montoIva = $montoTotal - ($montoNeto + $montoEsp);
            } else {
                $montoNeto = $montoTotal / (1.0 + $tasaIva);
                $montoIva = $montoTotal - $montoNeto;
            }
        }

        // 3) Costo FIFO
        $costoInv = $montoNeto;
        if ($inclEspCosto)
            $costoInv += $montoEsp;
        if ($inclIvaCosto || !$ivaRecuperable)
            $costoInv += $montoIva;

        return [
            'monto_neto' => round($montoNeto, 6),
            'monto_impuesto_especifico' => round($montoEsp, 6),
            'monto_iva' => round($montoIva, 6),
            'monto_total' => round($montoTotal, 6),
            'costo_inventario_total' => round($costoInv, 6),
        ];
    }
}
