<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class ServicioConversionUnidades
{
    public function convertir(float $cantidad, int $desdeUnidadId, int $haciaUnidadId): float
    {
        if ($desdeUnidadId === $haciaUnidadId)
            return $cantidad;

        $conv = DB::table('conversiones_unidad_medida')
            ->where('desde_unidad_id', $desdeUnidadId)
            ->where('hacia_unidad_id', $haciaUnidadId)
            ->first();

        if ($conv)
            return $cantidad * (float) $conv->factor;

        $inv = DB::table('conversiones_unidad_medida')
            ->where('desde_unidad_id', $haciaUnidadId)
            ->where('hacia_unidad_id', $desdeUnidadId)
            ->first();

        if ($inv && (float) $inv->factor != 0.0)
            return $cantidad / (float) $inv->factor;

        throw new RuntimeException("No existe conversión (desde={$desdeUnidadId}, hacia={$haciaUnidadId}).");
    }

    public function aUnidadBaseProducto(int $productoId, float $cantidad, int $unidadLineaId): float
    {
        $p = DB::table('productos')->where('id', $productoId)->first();
        if (!$p)
            throw new RuntimeException("Producto no existe: {$productoId}");
        if (!$p->unidad_stock_id)
            throw new RuntimeException("Producto {$productoId} no tiene unidad_stock_id.");

        return $this->convertir($cantidad, $unidadLineaId, (int) $p->unidad_stock_id);
    }
}
