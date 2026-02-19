<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class GmailDteInventoryService
{
    public function addDocumentToStock(int $documentId, ?int $userId = null): array
    {
        return DB::connection('fuelcontrol')->transaction(function () use ($documentId, $userId) {
            $doc = DB::connection('fuelcontrol')
                ->table('gmail_dte_documents')
                ->where('id', $documentId)
                ->lockForUpdate()
                ->first();

            if (!$doc) {
                throw new RuntimeException("Documento no encontrado: {$documentId}");
            }

            if (($doc->inventory_status ?? null) === 'ingresado' && !empty($doc->stock_movement_id)) {
                return [
                    'already_posted' => true,
                    'movement_id' => (int) $doc->stock_movement_id,
                ];
            }

            $lines = DB::connection('fuelcontrol')
                ->table('gmail_dte_document_lines')
                ->where('document_id', $documentId)
                ->orderBy('nro_linea')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lines->isEmpty()) {
                throw new RuntimeException('El documento no tiene lineas para ingresar a inventario.');
            }

            $movementId = DB::connection('fuelcontrol')
                ->table('gmail_inventory_movements')
                ->insertGetId([
                    'document_id' => $documentId,
                    'tipo' => 'ENTRADA',
                    'estado' => 'CONTABILIZADO',
                    'ocurrio_el' => $doc->fecha_factura ?? now()->toDateString(),
                    'usuario_id' => $userId,
                    'notas' => 'Ingreso desde DTE Gmail',
                    'cantidad_total' => 0,
                    'costo_total' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            $qtyTotal = 0.0;
            $costTotal = 0.0;

            foreach ($lines as $line) {
                $qty = (float) $line->cantidad;
                if ($qty <= 0) {
                    continue;
                }

                $rawUnit = trim((string) ($line->unidad ?? ''));
                $unit = $this->normalizeUnit($rawUnit);
                $name = trim((string) ($line->descripcion ?? 'SIN DESCRIPCION'));
                $code = trim((string) ($line->codigo ?? '')) ?: null;
                $unitCost = $qty > 0 ? ((float) $line->monto_item / $qty) : 0.0;

                $product = DB::connection('fuelcontrol')
                    ->table('gmail_inventory_products')
                    ->where(function ($q) use ($code, $name, $unit) {
                        if ($code) {
                            $q->where('codigo', $code);
                        } else {
                            $q->where('nombre', $name)->where('unidad', $unit);
                        }
                    })
                    ->lockForUpdate()
                    ->first();

                if (!$product) {
                    $productId = DB::connection('fuelcontrol')
                        ->table('gmail_inventory_products')
                        ->insertGetId([
                            'codigo' => $code,
                            'nombre' => $name,
                            'unidad' => $unit,
                            'stock_actual' => 0,
                            'costo_promedio' => 0,
                            'is_active' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    $product = DB::connection('fuelcontrol')
                        ->table('gmail_inventory_products')
                        ->where('id', $productId)
                        ->first();
                }

                $lotId = DB::connection('fuelcontrol')
                    ->table('gmail_inventory_lots')
                    ->insertGetId([
                        'product_id' => $product->id,
                        'document_id' => $documentId,
                        'dte_line_id' => $line->id,
                        'ingresado_el' => $doc->fecha_factura ?? now()->toDateString(),
                        'costo_unitario' => $unitCost,
                        'cantidad_ingresada' => $qty,
                        'cantidad_salida' => 0,
                        'cantidad_disponible' => $qty,
                        'estado' => 'ABIERTO',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                $lineCost = $qty * $unitCost;

                DB::connection('fuelcontrol')
                    ->table('gmail_inventory_movement_lines')
                    ->insert([
                        'movement_id' => $movementId,
                        'lot_id' => $lotId,
                        'product_id' => $product->id,
                        'cantidad' => $qty,
                        'costo_unitario' => $unitCost,
                        'costo_total' => $lineCost,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                $newStock = (float) $product->stock_actual + $qty;
                $newAvg = $newStock > 0
                    ? ((((float) $product->stock_actual * (float) $product->costo_promedio) + $lineCost) / $newStock)
                    : $unitCost;

                DB::connection('fuelcontrol')
                    ->table('gmail_inventory_products')
                    ->where('id', $product->id)
                    ->update([
                        'stock_actual' => $newStock,
                        'costo_promedio' => $newAvg,
                        'updated_at' => now(),
                    ]);

                $qtyTotal += $qty;
                $costTotal += $lineCost;
            }

            DB::connection('fuelcontrol')
                ->table('gmail_inventory_movements')
                ->where('id', $movementId)
                ->update([
                    'cantidad_total' => $qtyTotal,
                    'costo_total' => $costTotal,
                    'updated_at' => now(),
                ]);

            DB::connection('fuelcontrol')
                ->table('gmail_dte_documents')
                ->where('id', $documentId)
                ->update([
                    'inventory_status' => 'ingresado',
                    'stock_posted_at' => now(),
                    'stock_movement_id' => $movementId,
                    'updated_at' => now(),
                ]);

            return [
                'already_posted' => false,
                'movement_id' => $movementId,
            ];
        });
    }

    private function normalizeUnit(?string $unit): string
    {
        $u = strtoupper(trim((string) $unit));
        if ($u === '') {
            return 'UN';
        }

        // Quita puntos/espacios para variantes tipo "K.G." o "MTS ".
        $key = str_replace(['.', ' '], '', $u);

        $map = [
            'UN' => 'UN',
            'UND' => 'UN',
            'UNID' => 'UN',
            'UNIDAD' => 'UN',
            'UNIDADES' => 'UN',
            'U' => 'UN',

            'KG' => 'KG',
            'KGS' => 'KG',
            'KILO' => 'KG',
            'KILO' => 'KG',
            'KILOS' => 'KG',
            'KILOGRAMO' => 'KG',
            'KILOGRAMOS' => 'KG',

            'G' => 'G',
            'GRAMO' => 'G',
            'GRAMOS' => 'G',
            'GR' => 'G',
            'GRS' => 'G',

            'L' => 'L',
            'LT' => 'L',
            'LTS' => 'L',
            'LTR' => 'L',
            'LITRO' => 'L',
            'LITROS' => 'L',

            'ML' => 'ML',
            'ML' => 'ML',
            'CC' => 'ML',
            'CM3' => 'ML',

            'M' => 'M',
            'MT' => 'M',
            'MTS' => 'M',
            'METRO' => 'M',
            'METROS' => 'M',

            'CM' => 'CM',
            'CENTIMETRO' => 'CM',
            'CENTIMETROS' => 'CM',

            'MM' => 'MM',
            'MILIMETRO' => 'MM',
            'MILIMETROS' => 'MM',

            'M2' => 'M2',
            'MT2' => 'M2',
            'MTS2' => 'M2',
            'METRO2' => 'M2',
            'METROS2' => 'M2',

            'M3' => 'M3',
            'MT3' => 'M3',
            'MTS3' => 'M3',
            'METRO3' => 'M3',
            'METROS3' => 'M3',
        ];

        return $map[$key] ?? $u;
    }
}
