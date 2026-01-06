<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class ServicioPosteoDocumentoCompra
{
    public function contabilizar(int $documentoId): void
    {
        DB::transaction(function () use ($documentoId) {

            $doc = DB::table('documentos_compra')->where('id', $documentoId)->lockForUpdate()->first();
            if (!$doc)
                throw new RuntimeException("Documento no existe: {$documentoId}");
            if ($doc->estado !== 'BORRADOR')
                throw new RuntimeException("Documento {$documentoId} no está en BORRADOR.");

            $lineas = DB::table('lineas_documento_compra')->where('documento_compra_id', $documentoId)->get();
            if ($lineas->isEmpty())
                throw new RuntimeException("Documento {$documentoId} no tiene líneas.");

            $totalNeto = 0;
            $totalEsp = 0;
            $totalIva = 0;
            $totalGen = 0;

            foreach ($lineas as $ln) {
                $cantidadBase = app(ServicioConversionUnidades::class)
                    ->aUnidadBaseProducto((int) $ln->producto_id, (float) $ln->cantidad, (int) $ln->unidad_medida_id);

                $calc = app(ServicioCalculoImpuestosCompra::class)->calcularLinea(
                    (int) $documentoId,
                    (int) $ln->producto_id,
                    (float) $ln->cantidad,
                    (int) $ln->unidad_medida_id,
                    $ln->precio_unitario !== null ? (float) $ln->precio_unitario : null,
                    (float) $ln->descuento
                );

                $costoUnitBase = 0.0;
                if ($cantidadBase > 0 && $calc['costo_inventario_total'] > 0) {
                    $costoUnitBase = $calc['costo_inventario_total'] / $cantidadBase;
                }

                DB::table('lineas_documento_compra')->where('id', $ln->id)->update([
                    'cantidad_base' => $cantidadBase,
                    'monto_neto' => $calc['monto_neto'],
                    'monto_impuesto_especifico' => $calc['monto_impuesto_especifico'],
                    'monto_iva' => $calc['monto_iva'],
                    'monto_total' => $calc['monto_total'],
                    'costo_inventario_total' => $calc['costo_inventario_total'],
                    'costo_unitario_base' => $costoUnitBase,
                    'updated_at' => now(),
                ]);

                $totalNeto += $calc['monto_neto'];
                $totalEsp += $calc['monto_impuesto_especifico'];
                $totalIva += $calc['monto_iva'];
                $totalGen += $calc['monto_total'];
            }

            DB::table('documentos_compra')->where('id', $documentoId)->update([
                'total_neto' => $totalNeto,
                'total_impuesto_especifico' => $totalEsp,
                'total_iva' => $totalIva,
                'total_general' => $totalGen,
                'estado' => 'CONTABILIZADO',
                'fecha_ocurrencia' => $doc->fecha_ocurrencia ?? now(),
                'updated_at' => now(),
            ]);

            // Entrada inventario (lotes FIFO)
            if ($doc->tipo_documento === 'GUIA') {
                $this->crearEntrada($documentoId, costoPendiente: true);
            } else { // FACTURA
                if ($doc->documento_relacionado_id) {
                    // luego hacemos “valorizar guía”; por ahora dejamos simple
                    throw new RuntimeException("Factura vinculada a guía: siguiente paso (valorizar) aún no activado.");
                }
                $this->crearEntrada($documentoId, costoPendiente: false);
            }
        });
    }

    private function crearEntrada(int $documentoId, bool $costoPendiente): void
    {
        $doc = DB::table('documentos_compra')->where('id', $documentoId)->first();
        $lineas = DB::table('lineas_documento_compra')->where('documento_compra_id', $documentoId)->get();

        foreach ($lineas as $ln) {
            $costoUnit = $costoPendiente ? 0.0 : (float) $ln->costo_unitario_base;

            $loteId = DB::table('lotes_inventario')->insertGetId([
                'producto_id' => $ln->producto_id,
                'bodega_id' => $doc->bodega_id,
                'codigo_lote' => $ln->codigo_lote,
                'ingresado_el' => $doc->fecha_ocurrencia ?? now(),
                'vence_el' => $ln->vence_el,
                'costo_unitario' => $costoUnit,
                'cantidad_ingresada' => (float) $ln->cantidad_base,
                'cantidad_salida' => 0,
                'cantidad_disponible' => (float) $ln->cantidad_base,
                'origen_tipo' => 'documentos_compra',
                'origen_id' => $documentoId,
                'costo_pendiente' => $costoPendiente,
                'estado' => 'ABIERTO',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $movId = DB::table('movimientos_inventario')->insertGetId([
                'tipo' => 'ENTRADA',
                'producto_id' => $ln->producto_id,
                'bodega_id' => $doc->bodega_id,
                'cantidad' => (float) $ln->cantidad_base,
                'costo_unitario' => $costoUnit,
                'costo_total' => $costoUnit * (float) $ln->cantidad_base,
                'ocurrio_el' => $doc->fecha_ocurrencia ?? now(),
                'documento_tipo' => 'documentos_compra',
                'documento_id' => $documentoId,
                'usuario_id' => null,
                'notas' => $costoPendiente ? 'Entrada por guía (costo pendiente)' : 'Entrada por factura',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('lineas_movimiento_inventario')->insert([
                'movimiento_id' => $movId,
                'lote_id' => $loteId,
                'cantidad' => (float) $ln->cantidad_base,
                'costo_unitario' => $costoUnit,
                'costo_total' => $costoUnit * (float) $ln->cantidad_base,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
