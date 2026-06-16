<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Recepción de mercadería como evento propio: el stock entra ACÁ (no en la factura).
 *
 * Aditivo sobre fuelcontrol: escribe en las tablas nuevas `recepciones`/`recepcion_lineas`
 * y genera el movimiento ENTRADA + lotes FIFO en gmail_inventory_* exactamente como lo hace
 * GmailDteInventoryService::addDocumentToStock(), pero alimentado por la recepción.
 * No modifica el servicio existente.
 */
class ReceptionService
{
    private function db()
    {
        return DB::connection('fuelcontrol');
    }

    /**
     * Confirma una recepción BORRADOR: crea el movimiento ENTRADA, los lotes FIFO,
     * actualiza stock + costo promedio, y recalcula el estado de recepción de la OC.
     *
     * @return array{movement_id:int, already_posted:bool}
     */
    public function confirmReception(int $recepcionId, ?int $userId = null): array
    {
        return $this->db()->transaction(function () use ($recepcionId, $userId) {
            $rec = $this->db()->table('recepciones')->where('id', $recepcionId)->lockForUpdate()->first();

            if (!$rec) {
                throw new RuntimeException("Recepción no encontrada: {$recepcionId}");
            }

            if ($rec->estado === 'CONFIRMADA' && !empty($rec->stock_movement_id)) {
                return ['movement_id' => (int) $rec->stock_movement_id, 'already_posted' => true];
            }

            if ($rec->estado === 'ANULADA') {
                throw new RuntimeException('La recepción está anulada.');
            }

            $lines = $this->db()->table('recepcion_lineas')
                ->where('recepcion_id', $recepcionId)
                ->lockForUpdate()
                ->get();

            if ($lines->isEmpty()) {
                throw new RuntimeException('La recepción no tiene líneas.');
            }

            $ocurrioEl = $rec->fecha_recepcion ?? now()->toDateString();

            $movementId = $this->db()->table('gmail_inventory_movements')->insertGetId([
                'document_id'    => null,
                'recepcion_id'   => $recepcionId,
                'bodega_id'      => $rec->bodega_id,
                'tipo'           => 'ENTRADA',
                'estado'         => 'CONTABILIZADO',
                'ocurrio_el'     => $ocurrioEl,
                'usuario_id'     => $userId,
                'notas'          => 'Ingreso desde Recepción #' . $recepcionId
                    . ($rec->purchase_order_id ? ' (OC #' . $rec->purchase_order_id . ')' : ''),
                'cantidad_total' => 0,
                'costo_total'    => 0,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            $qtyTotal = 0.0;
            $costTotal = 0.0;

            foreach ($lines as $line) {
                $qty = (float) $line->cantidad_recibida;
                if ($qty <= 0) {
                    continue;
                }

                $unitCost = (float) ($line->costo_unitario ?? 0);
                $productId = $this->resolveProductId($line);

                $product = $this->db()->table('gmail_inventory_products')
                    ->where('id', $productId)
                    ->lockForUpdate()
                    ->first();

                $lotId = $this->db()->table('gmail_inventory_lots')->insertGetId([
                    'product_id'          => $product->id,
                    'document_id'         => null,
                    'bodega_id'           => $rec->bodega_id,
                    'dte_line_id'         => null,
                    'ingresado_el'        => $ocurrioEl,
                    'costo_unitario'      => $unitCost,
                    'cantidad_ingresada'  => $qty,
                    'cantidad_salida'     => 0,
                    'cantidad_disponible' => $qty,
                    'estado'              => 'ABIERTO',
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);

                $lineCost = $qty * $unitCost;

                $this->db()->table('gmail_inventory_movement_lines')->insert([
                    'movement_id'    => $movementId,
                    'lot_id'         => $lotId,
                    'product_id'     => $product->id,
                    'cantidad'       => $qty,
                    'costo_unitario' => $unitCost,
                    'costo_total'    => $lineCost,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);

                $newStock = (float) $product->stock_actual + $qty;
                $newAvg = $newStock > 0
                    ? ((((float) $product->stock_actual * (float) $product->costo_promedio) + $lineCost) / $newStock)
                    : $unitCost;

                $this->db()->table('gmail_inventory_products')
                    ->where('id', $product->id)
                    ->update([
                        'stock_actual'   => $newStock,
                        'costo_promedio' => $newAvg,
                        'updated_at'     => now(),
                    ]);

                // Persistir el producto resuelto en la línea (si vino sin él)
                if ((int) ($line->inventory_product_id ?? 0) !== (int) $product->id) {
                    $this->db()->table('recepcion_lineas')
                        ->where('id', $line->id)
                        ->update(['inventory_product_id' => $product->id, 'updated_at' => now()]);
                }

                $qtyTotal += $qty;
                $costTotal += $lineCost;
            }

            $this->db()->table('gmail_inventory_movements')->where('id', $movementId)->update([
                'cantidad_total' => $qtyTotal,
                'costo_total'    => $costTotal,
                'updated_at'     => now(),
            ]);

            $this->db()->table('recepciones')->where('id', $recepcionId)->update([
                'estado'            => 'CONFIRMADA',
                'stock_movement_id' => $movementId,
                'fecha_recepcion'   => $rec->fecha_recepcion ?? now(),
                'updated_at'        => now(),
            ]);

            if ($rec->purchase_order_id) {
                $this->recomputePoReceptionStatus((int) $rec->purchase_order_id);
            }

            return ['movement_id' => $movementId, 'already_posted' => false];
        });
    }

    /**
     * Resuelve el producto de inventario de una línea de recepción.
     * Si no trae inventory_product_id, crea el producto (paridad con addDocumentToStock).
     */
    private function resolveProductId(object $line): int
    {
        $productId = (int) ($line->inventory_product_id ?? 0);
        if ($productId > 0) {
            $exists = $this->db()->table('gmail_inventory_products')->where('id', $productId)->exists();
            if ($exists) {
                return $productId;
            }
        }

        $name = trim((string) ($line->product_name ?? 'SIN DESCRIPCION'));
        $unit = trim((string) ($line->unidad ?? 'UN')) ?: 'UN';

        // Reutilizar producto por nombre+unidad si ya existe
        $existing = $this->db()->table('gmail_inventory_products')
            ->where('nombre', $name)
            ->where('unidad', $unit)
            ->first();
        if ($existing) {
            return (int) $existing->id;
        }

        return (int) $this->db()->table('gmail_inventory_products')->insertGetId([
            'codigo'         => null,
            'nombre'         => $name,
            'unidad'         => $unit,
            'stock_actual'   => 0,
            'costo_promedio' => 0,
            'is_active'      => 1,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    /**
     * Recalcula purchase_orders.reception_status comparando pedido vs recibido por ítem.
     * NO toca el campo `status` existente.
     */
    public function recomputePoReceptionStatus(int $purchaseOrderId): void
    {
        $items = $this->db()->table('purchase_order_items')
            ->where('purchase_order_id', $purchaseOrderId)
            ->get();

        if ($items->isEmpty()) {
            return;
        }

        $receivedByItem = $this->db()->table('recepcion_lineas as rl')
            ->join('recepciones as r', 'r.id', '=', 'rl.recepcion_id')
            ->where('r.purchase_order_id', $purchaseOrderId)
            ->where('r.estado', 'CONFIRMADA')
            ->whereNotNull('rl.purchase_order_item_id')
            ->groupBy('rl.purchase_order_item_id')
            ->select('rl.purchase_order_item_id', DB::raw('SUM(rl.cantidad_recibida) as recibido'))
            ->pluck('recibido', 'rl.purchase_order_item_id');

        $algoRecibido = false;
        $todoCompleto = true;

        foreach ($items as $item) {
            $pedido = (float) $item->quantity;
            $recibido = (float) ($receivedByItem[$item->id] ?? 0);

            if ($recibido > 0) {
                $algoRecibido = true;
            }
            if ($recibido + 1e-6 < $pedido) {
                $todoCompleto = false;
            }
        }

        $status = $todoCompleto ? 'recibida' : ($algoRecibido ? 'parcial' : null);

        $this->db()->table('purchase_orders')->where('id', $purchaseOrderId)->update([
            'reception_status' => $status,
            'updated_at'       => now(),
        ]);
    }

    /**
     * Conciliación 3 vías: pedido (OC) vs recibido (recepción) vs facturado (DTE).
     * Informativo, no bloquea.
     *
     * @return array{totales:array, lineas:array}
     */
    public function reconcile(int $recepcionId): array
    {
        $rec = $this->db()->table('recepciones')->where('id', $recepcionId)->first();
        if (!$rec) {
            throw new RuntimeException("Recepción no encontrada: {$recepcionId}");
        }

        $recLines = $this->db()->table('recepcion_lineas')->where('recepcion_id', $recepcionId)->get();

        $totalPedido = 0.0;
        $totalRecibido = 0.0;
        foreach ($recLines as $l) {
            $totalRecibido += (float) $l->cantidad_recibida * (float) ($l->costo_unitario ?? 0);
            $totalPedido += (float) ($l->cantidad_pedida ?? 0) * (float) ($l->costo_unitario ?? 0);
        }

        $factura = null;
        $totalFacturado = 0.0;
        if (!empty($rec->gmail_document_id)) {
            $factura = $this->db()->table('gmail_dte_documents')->where('id', $rec->gmail_document_id)->first();
            $totalFacturado = $factura ? (float) $factura->monto_neto : 0.0;
        }

        return [
            'totales' => [
                'pedido'    => $totalPedido,
                'recibido'  => $totalRecibido,
                'facturado' => $totalFacturado,
                'factura'   => $factura,
            ],
            'lineas' => $recLines->map(function ($l) {
                $pedida = $l->cantidad_pedida !== null ? (float) $l->cantidad_pedida : null;
                $recibida = (float) $l->cantidad_recibida;
                return [
                    'product_name'   => $l->product_name,
                    'unidad'         => $l->unidad,
                    'cantidad_pedida'=> $pedida,
                    'cantidad_recibida' => $recibida,
                    'diferencia'     => $pedida !== null ? round($recibida - $pedida, 6) : null,
                    'costo_unitario' => $l->costo_unitario !== null ? (float) $l->costo_unitario : null,
                ];
            })->all(),
        ];
    }
}
