<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class GmailDteInventoryService
{
    public function rollbackDocumentStock(int $documentId): array
    {
        return DB::connection('fuelcontrol')->transaction(function () use ($documentId) {
            $doc = DB::connection('fuelcontrol')
                ->table('gmail_dte_documents')
                ->where('id', $documentId)
                ->lockForUpdate()
                ->first();

            if (!$doc) {
                throw new RuntimeException("Documento no encontrado: {$documentId}");
            }

            $movementId = (int) ($doc->stock_movement_id ?? 0);
            if (($doc->inventory_status ?? null) !== 'ingresado' || $movementId <= 0) {
                throw new RuntimeException('El documento no tiene un ingreso de stock activo para anular.');
            }

            $movement = DB::connection('fuelcontrol')
                ->table('gmail_inventory_movements')
                ->where('id', $movementId)
                ->where('tipo', 'ENTRADA')
                ->lockForUpdate()
                ->first();

            if (!$movement) {
                throw new RuntimeException('No se encontró el movimiento de entrada asociado.');
            }

            $lots = DB::connection('fuelcontrol')
                ->table('gmail_inventory_lots')
                ->where('document_id', $documentId)
                ->lockForUpdate()
                ->get(['id', 'product_id', 'cantidad_salida']);

            if ($lots->isEmpty()) {
                throw new RuntimeException('No se encontraron lotes para este documento.');
            }

            $consumedCount = $lots->filter(fn ($lot) => (float) $lot->cantidad_salida > 0)->count();
            if ($consumedCount > 0) {
                throw new RuntimeException('No se puede anular: este ingreso ya tiene salidas FIFO asociadas.');
            }

            $productIds = $lots->pluck('product_id')->filter()->unique()->values()->all();

            DB::connection('fuelcontrol')
                ->table('gmail_inventory_movement_lines')
                ->where('movement_id', $movementId)
                ->delete();

            DB::connection('fuelcontrol')
                ->table('gmail_inventory_lots')
                ->where('document_id', $documentId)
                ->delete();

            DB::connection('fuelcontrol')
                ->table('gmail_inventory_movements')
                ->where('id', $movementId)
                ->where('tipo', 'ENTRADA')
                ->delete();

            DB::connection('fuelcontrol')
                ->table('gmail_dte_documents')
                ->where('id', $documentId)
                ->update([
                    'inventory_status' => 'pendiente',
                    'stock_posted_at' => null,
                    'stock_movement_id' => null,
                    'updated_at' => now(),
                ]);

            foreach ($productIds as $productId) {
                $totals = DB::connection('fuelcontrol')
                    ->table('gmail_inventory_lots')
                    ->where('product_id', $productId)
                    ->selectRaw('COALESCE(SUM(cantidad_disponible),0) as stock_total, COALESCE(SUM(cantidad_disponible * costo_unitario),0) as costo_total')
                    ->first();

                $stockTotal = (float) ($totals->stock_total ?? 0);
                $costTotal = (float) ($totals->costo_total ?? 0);
                $avgCost = $stockTotal > 0 ? ($costTotal / $stockTotal) : 0.0;

                DB::connection('fuelcontrol')
                    ->table('gmail_inventory_products')
                    ->where('id', $productId)
                    ->update([
                        'stock_actual' => $stockTotal,
                        'costo_promedio' => $avgCost,
                        'updated_at' => now(),
                    ]);
            }

            return [
                'ok' => true,
                'movement_id' => $movementId,
                'products_updated' => count($productIds),
            ];
        });
    }

    public function addDocumentToStock(
        int $documentId,
        ?int $userId = null,
        array $manualLineProductMap = [],
        bool $learnFromManualMap = true
    ): array
    {
        return DB::connection('fuelcontrol')->transaction(function () use ($documentId, $userId, $manualLineProductMap, $learnFromManualMap) {
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
                $code = trim((string) ($line->codigo ?? ''));
                $code = $code !== '' ? mb_strtoupper($code, 'UTF-8') : null;
                $unitCost = $qty > 0 ? ((float) $line->monto_item / $qty) : 0.0;
                $manualProductId = (int) ($manualLineProductMap[(int) $line->id] ?? 0);
                if ($manualProductId > 0) {
                    $product = DB::connection('fuelcontrol')
                        ->table('gmail_inventory_products')
                        ->where('id', $manualProductId)
                        ->lockForUpdate()
                        ->first();

                    if (!$product) {
                        throw new RuntimeException("No se encontró el producto asignado manualmente para la línea {$line->id}.");
                    }
                } else {
                    $product = $this->resolveProductForIncomingLine($code, $name, $unit, true);
                }

                if (!$product) {
                    $resolvedCode = $code ?: $this->buildAutomaticProductCode($name);

                    $productId = DB::connection('fuelcontrol')
                        ->table('gmail_inventory_products')
                        ->insertGetId([
                            'codigo' => $resolvedCode,
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
                } elseif (empty($product->codigo)) {
                    $resolvedCode = $code ?: $this->buildAutomaticProductCode($name);
                    DB::connection('fuelcontrol')
                        ->table('gmail_inventory_products')
                        ->where('id', $product->id)
                        ->update([
                            'codigo' => $resolvedCode,
                            'updated_at' => now(),
                        ]);
                    $product->codigo = $resolvedCode;
                }

                // Si el producto proviene de inventario inicial (codigo AUTO/vacio)
                // y esta linea trae codigo real del DTE, promovemos nombre+codigo una sola vez.
                if ($this->shouldPromoteInitialProductFromDte($product, $code, $name)) {
                    DB::connection('fuelcontrol')
                        ->table('gmail_inventory_products')
                        ->where('id', $product->id)
                        ->update([
                            'codigo' => $code,
                            'nombre' => $name,
                            'updated_at' => now(),
                        ]);
                    $product->codigo = $code;
                    $product->nombre = $name;
                }

                if ($manualProductId > 0 && $learnFromManualMap) {
                    $this->saveAliasMapping($name, $unit, (int) $product->id);
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

    public function reviewDocumentStockMatching(int $documentId): array
    {
        $doc = DB::connection('fuelcontrol')
            ->table('gmail_dte_documents')
            ->where('id', $documentId)
            ->first();

        if (!$doc) {
            throw new RuntimeException("Documento no encontrado: {$documentId}");
        }

        if (($doc->inventory_status ?? null) === 'ingresado' && !empty($doc->stock_movement_id)) {
            return [
                'already_posted' => true,
                'movement_id' => (int) $doc->stock_movement_id,
                'unresolved' => [],
            ];
        }

        $lines = DB::connection('fuelcontrol')
            ->table('gmail_dte_document_lines')
            ->where('document_id', $documentId)
            ->orderBy('nro_linea')
            ->orderBy('id')
            ->get();

        $unresolved = [];
        foreach ($lines as $line) {
            $qty = (float) $line->cantidad;
            if ($qty <= 0) {
                continue;
            }

            $rawUnit = trim((string) ($line->unidad ?? ''));
            $unit = $this->normalizeUnit($rawUnit);
            $name = trim((string) ($line->descripcion ?? 'SIN DESCRIPCION'));
            $code = trim((string) ($line->codigo ?? ''));
            $code = $code !== '' ? mb_strtoupper($code, 'UTF-8') : null;

            $product = $this->resolveProductForIncomingLine($code, $name, $unit, false);
            if ($product) {
                continue;
            }

            $unresolved[] = [
                'line_id' => (int) $line->id,
                'descripcion' => $name,
                'codigo' => $code,
                'unidad' => $unit,
                'cantidad' => $qty,
            ];
        }

        return [
            'already_posted' => false,
            'movement_id' => null,
            'unresolved' => $unresolved,
        ];
    }

    /**
     * Registra una salida FIFO multi-producto.
     *
     * @param  array<int, array{product_id: int, quantity: float}>  $items
     */
    public function processExit(array $items, ?int $userId, string $destinatario, ?string $notas = null, ?string $tipoSalida = null): array
    {
        return DB::connection('fuelcontrol')->transaction(function () use ($items, $userId, $destinatario, $notas, $tipoSalida) {
            // Validar stock suficiente antes de crear nada
            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $needed    = (float) $item['quantity'];

                $available = DB::connection('fuelcontrol')
                    ->table('gmail_inventory_lots')
                    ->where('product_id', $productId)
                    ->where('estado', 'ABIERTO')
                    ->sum('cantidad_disponible');

                if ($available < $needed) {
                    $product = DB::connection('fuelcontrol')
                        ->table('gmail_inventory_products')
                        ->where('id', $productId)
                        ->value('nombre');
                    throw new RuntimeException(
                        "Stock insuficiente para '{$product}': disponible {$available}, solicitado {$needed}."
                    );
                }
            }

            // Crear movimiento cabecera
            $movementId = DB::connection('fuelcontrol')
                ->table('gmail_inventory_movements')
                ->insertGetId([
                    'document_id'    => null,
                    'tipo'           => 'SALIDA',
                    'estado'         => 'CONTABILIZADO',
                    'ocurrio_el'     => now()->toDateString(),
                    'usuario_id'     => $userId,
                    'notas'          => $notas,
                    'destinatario'   => $destinatario,
                    'tipo_salida'    => $tipoSalida,
                    'cantidad_total' => 0,
                    'costo_total'    => 0,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);

            $qtyTotal  = 0.0;
            $costTotal = 0.0;

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $needed    = (float) $item['quantity'];
                $pending   = $needed;

                // FIFO: lotes más antiguos primero
                $lots = DB::connection('fuelcontrol')
                    ->table('gmail_inventory_lots')
                    ->where('product_id', $productId)
                    ->where('estado', 'ABIERTO')
                    ->where('cantidad_disponible', '>', 0)
                    ->orderBy('ingresado_el')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($lots as $lot) {
                    if ($pending <= 0) {
                        break;
                    }

                    $take = min((float) $lot->cantidad_disponible, $pending);
                    $lineCost = $take * (float) $lot->costo_unitario;

                    // Línea del movimiento por este lote
                    DB::connection('fuelcontrol')
                        ->table('gmail_inventory_movement_lines')
                        ->insert([
                            'movement_id'    => $movementId,
                            'lot_id'         => $lot->id,
                            'product_id'     => $productId,
                            'cantidad'       => $take,
                            'costo_unitario' => $lot->costo_unitario,
                            'costo_total'    => $lineCost,
                            'created_at'     => now(),
                            'updated_at'     => now(),
                        ]);

                    // Actualizar lote
                    $newDisponible = (float) $lot->cantidad_disponible - $take;
                    DB::connection('fuelcontrol')
                        ->table('gmail_inventory_lots')
                        ->where('id', $lot->id)
                        ->update([
                            'cantidad_disponible' => $newDisponible,
                            'cantidad_salida'     => (float) $lot->cantidad_salida + $take,
                            'estado'              => $newDisponible <= 0 ? 'CERRADO' : 'ABIERTO',
                            'updated_at'          => now(),
                        ]);

                    $pending   -= $take;
                    $costTotal += $lineCost;
                }

                // Reducir stock_actual del producto (costo_promedio no cambia en salidas)
                DB::connection('fuelcontrol')
                    ->table('gmail_inventory_products')
                    ->where('id', $productId)
                    ->decrement('stock_actual', $needed, ['updated_at' => now()]);

                $qtyTotal += $needed;
            }

            // Actualizar totales del movimiento
            DB::connection('fuelcontrol')
                ->table('gmail_inventory_movements')
                ->where('id', $movementId)
                ->update([
                    'cantidad_total' => $qtyTotal,
                    'costo_total'    => $costTotal,
                    'updated_at'     => now(),
                ]);

            return ['movement_id' => $movementId];
        });
    }

    /**
     * Ajuste de inventario: positivo (incrementa stock) o negativo (consume FIFO).
     * $direccion: 'POSITIVO' | 'NEGATIVO'
     */
    public function processAdjustment(int $productId, float $quantity, string $direccion, string $motivo, ?int $userId, ?string $notas): array
    {
        return DB::connection('fuelcontrol')->transaction(function () use ($productId, $quantity, $direccion, $motivo, $userId, $notas) {
            $product = DB::connection('fuelcontrol')
                ->table('gmail_inventory_products')
                ->where('id', $productId)
                ->lockForUpdate()
                ->first();

            if (!$product) {
                throw new RuntimeException('Producto no encontrado.');
            }

            if ($direccion === 'NEGATIVO') {
                $available = DB::connection('fuelcontrol')
                    ->table('gmail_inventory_lots')
                    ->where('product_id', $productId)
                    ->where('estado', 'ABIERTO')
                    ->sum('cantidad_disponible');

                if ($available < $quantity) {
                    throw new RuntimeException(
                        "Stock insuficiente para '{$product->nombre}': disponible {$available}, solicitado {$quantity}."
                    );
                }
            }

            $movementId = DB::connection('fuelcontrol')
                ->table('gmail_inventory_movements')
                ->insertGetId([
                    'document_id'    => null,
                    'tipo'           => 'AJUSTE',
                    'estado'         => 'CONTABILIZADO',
                    'ocurrio_el'     => now()->toDateTimeString(),
                    'usuario_id'     => $userId,
                    'destinatario'   => $motivo,
                    'tipo_salida'    => $direccion === 'POSITIVO' ? 'AJUSTE+' : 'AJUSTE-',
                    'notas'          => $notas,
                    'cantidad_total' => 0,
                    'costo_total'    => 0,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);

            $costTotal = 0.0;

            if ($direccion === 'NEGATIVO') {
                // Consumir FIFO
                $pending = $quantity;
                $lots = DB::connection('fuelcontrol')
                    ->table('gmail_inventory_lots')
                    ->where('product_id', $productId)
                    ->where('estado', 'ABIERTO')
                    ->where('cantidad_disponible', '>', 0)
                    ->orderBy('ingresado_el')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($lots as $lot) {
                    if ($pending <= 0) break;
                    $take = min((float) $lot->cantidad_disponible, $pending);
                    $lineCost = $take * (float) $lot->costo_unitario;

                    DB::connection('fuelcontrol')->table('gmail_inventory_movement_lines')->insert([
                        'movement_id'    => $movementId,
                        'lot_id'         => $lot->id,
                        'product_id'     => $productId,
                        'cantidad'       => $take,
                        'costo_unitario' => $lot->costo_unitario,
                        'costo_total'    => $lineCost,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ]);

                    $newDisponible = (float) $lot->cantidad_disponible - $take;
                    DB::connection('fuelcontrol')->table('gmail_inventory_lots')->where('id', $lot->id)->update([
                        'cantidad_disponible' => $newDisponible,
                        'cantidad_salida'     => (float) $lot->cantidad_salida + $take,
                        'estado'              => $newDisponible <= 0 ? 'CERRADO' : 'ABIERTO',
                        'updated_at'          => now(),
                    ]);

                    $pending    -= $take;
                    $costTotal  += $lineCost;
                }

                DB::connection('fuelcontrol')->table('gmail_inventory_products')->where('id', $productId)
                    ->decrement('stock_actual', $quantity, ['updated_at' => now()]);
            } else {
                // Positivo: crear lote con costo promedio actual
                $costoUnitario = (float) $product->costo_promedio;
                $lotId = DB::connection('fuelcontrol')->table('gmail_inventory_lots')->insertGetId([
                    'product_id'          => $productId,
                    'document_id'         => null,
                    'ingresado_el'        => now()->toDateTimeString(),
                    'costo_unitario'      => $costoUnitario,
                    'cantidad_ingresada'  => $quantity,
                    'cantidad_disponible' => $quantity,
                    'cantidad_salida'     => 0,
                    'estado'              => 'ABIERTO',
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);

                $lineCost = $quantity * $costoUnitario;
                DB::connection('fuelcontrol')->table('gmail_inventory_movement_lines')->insert([
                    'movement_id'    => $movementId,
                    'lot_id'         => $lotId,
                    'product_id'     => $productId,
                    'cantidad'       => $quantity,
                    'costo_unitario' => $costoUnitario,
                    'costo_total'    => $lineCost,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);

                $costTotal = $lineCost;

                DB::connection('fuelcontrol')->table('gmail_inventory_products')->where('id', $productId)
                    ->increment('stock_actual', $quantity, ['updated_at' => now()]);
            }

            DB::connection('fuelcontrol')->table('gmail_inventory_movements')->where('id', $movementId)->update([
                'cantidad_total' => $quantity,
                'costo_total'    => $costTotal,
                'updated_at'     => now(),
            ]);

            return ['movement_id' => $movementId];
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

    private function resolveProductForIncomingLine(?string $code, string $name, string $unit, bool $forUpdate = true): ?object
    {
        $products = DB::connection('fuelcontrol')->table('gmail_inventory_products');

        if ($code) {
            $byCodeQuery = (clone $products)->where('codigo', $code);
            if ($forUpdate) {
                $byCodeQuery->lockForUpdate();
            }
            $byCode = $byCodeQuery->first();
            if ($byCode) {
                return $byCode;
            }
        }

        $aliasMatch = $this->resolveAliasProduct($name, $unit, $forUpdate);
        if ($aliasMatch) {
            return $aliasMatch;
        }

        $byExactQuery = (clone $products)
            ->whereRaw('UPPER(TRIM(nombre)) = ?', [mb_strtoupper(trim($name), 'UTF-8')])
            ->where('unidad', $unit);
        if ($forUpdate) {
            $byExactQuery->lockForUpdate();
        }
        $byExact = $byExactQuery->first();
        if ($byExact) {
            return $byExact;
        }

        return $this->findBestFuzzyMatch($name, $unit, $forUpdate);
    }

    private function findBestFuzzyMatch(string $name, string $unit, bool $forUpdate = true): ?object
    {
        $target = $this->normalizeForSimilarity($name);
        if ($target === '') {
            return null;
        }

        $candidates = DB::connection('fuelcontrol')
            ->table('gmail_inventory_products')
            ->where('unidad', $unit)
            ->where('is_active', 1)
            ->whereNotNull('nombre')
            ->select('id', 'codigo', 'nombre', 'unidad', 'stock_actual', 'costo_promedio', 'is_active')
            ->get();

        $best = null;
        $bestScore = 0.0;

        foreach ($candidates as $candidate) {
            $candidateName = $this->normalizeForSimilarity((string) $candidate->nombre);
            if ($candidateName === '') {
                continue;
            }

            $score = $this->similarityScore($target, $candidateName);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        if ($best === null || $bestScore < 0.88) {
            return null;
        }

        $bestQuery = DB::connection('fuelcontrol')
            ->table('gmail_inventory_products')
            ->where('id', $best->id);
        if ($forUpdate) {
            $bestQuery->lockForUpdate();
        }

        return $bestQuery->first();
    }

    private function similarityScore(string $left, string $right): float
    {
        if ($left === $right) {
            return 1.0;
        }

        similar_text($left, $right, $percent);
        $similarTextScore = $percent / 100;

        $maxLen = max(strlen($left), strlen($right), 1);
        $levScore = 1 - (levenshtein($left, $right) / $maxLen);

        $leftTokens = array_values(array_unique(array_filter(explode(' ', $left))));
        $rightTokens = array_values(array_unique(array_filter(explode(' ', $right))));
        $intersection = array_intersect($leftTokens, $rightTokens);
        $unionCount = count(array_unique(array_merge($leftTokens, $rightTokens)));
        $tokenScore = $unionCount > 0 ? count($intersection) / $unionCount : 0.0;

        return ($similarTextScore * 0.45) + ($levScore * 0.35) + ($tokenScore * 0.20);
    }

    private function normalizeForSimilarity(string $value): string
    {
        $value = Str::of($value)->ascii()->lower()->value();
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        if ($value === '') {
            return '';
        }

        $stopwords = [
            'de', 'del', 'la', 'el', 'los', 'las', 'y', 'con', 'para', 'por', 'en', 'sin',
        ];

        $normalizedTokens = [];
        foreach (explode(' ', $value) as $token) {
            $token = trim($token);
            if ($token === '' || in_array($token, $stopwords, true)) {
                continue;
            }

            // Ignora capacidades/medidas para evitar ruido en la similitud.
            if (preg_match('/^\d+([.,]\d+)?$/', $token)) {
                continue;
            }

            // Canoniza unidades frecuentes.
            if (in_array($token, ['l', 'lt', 'lts', 'ltrs', 'litro', 'litros', 'ml', 'cc'], true)) {
                continue;
            }

            // Sinónimos y variantes comunes en inventario.
            $mapped = match ($token) {
                'bidon', 'bidones' => 'bidon',
                // Botellón suele referirse a agua en envase grande.
                'botellon', 'botellones' => 'agua bidon',
                default => $token,
            };

            foreach (explode(' ', $mapped) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $normalizedTokens[] = $part;
                }
            }
        }

        if (empty($normalizedTokens)) {
            return '';
        }

        $normalizedTokens = array_values(array_unique($normalizedTokens));
        sort($normalizedTokens, SORT_STRING);

        return implode(' ', $normalizedTokens);
    }

    private function buildAutomaticProductCode(string $name): string
    {
        $normalized = $this->normalizeForSimilarity($name);
        $seed = $normalized !== '' ? $normalized : 'producto';
        $hash = strtoupper(substr(md5($seed), 0, 6));
        $base = "AUTO-{$hash}";
        $candidate = $base;
        $counter = 1;

        while (
            DB::connection('fuelcontrol')
                ->table('gmail_inventory_products')
                ->where('codigo', $candidate)
                ->exists()
        ) {
            $candidate = "{$base}-{$counter}";
            $counter++;
        }

        return $candidate;
    }

    private function shouldPromoteInitialProductFromDte(object $product, ?string $incomingCode, string $incomingName): bool
    {
        $incomingCode = trim((string) $incomingCode);
        $incomingName = trim((string) $incomingName);
        if ($incomingCode === '' || $incomingName === '') {
            return false;
        }

        $currentCode = mb_strtoupper(trim((string) ($product->codigo ?? '')), 'UTF-8');
        if ($currentCode === '') {
            return true;
        }

        // Solo una vez: cuando aun es "inicial" (codigo automatico).
        return str_starts_with($currentCode, 'AUTO-');
    }

    private function resolveAliasProduct(string $name, string $unit, bool $forUpdate = true): ?object
    {
        $aliasKey = $this->buildAliasKey($name, $unit);
        if ($aliasKey === null) {
            return null;
        }

        $aliases = $this->loadAliasMap();
        $productId = (int) ($aliases[$aliasKey] ?? 0);
        if ($productId <= 0) {
            return null;
        }

        $query = DB::connection('fuelcontrol')
            ->table('gmail_inventory_products')
            ->where('id', $productId);
        if ($forUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function saveAliasMapping(string $name, string $unit, int $productId): void
    {
        $aliasKey = $this->buildAliasKey($name, $unit);
        if ($aliasKey === null || $productId <= 0) {
            return;
        }

        $aliases = $this->loadAliasMap();
        $aliases[$aliasKey] = $productId;
        $this->storeAliasMap($aliases);
    }

    private function buildAliasKey(string $name, string $unit): ?string
    {
        $normalizedName = $this->normalizeForSimilarity($name);
        $normalizedUnit = $this->normalizeUnit($unit);
        if ($normalizedName === '' || $normalizedUnit === '') {
            return null;
        }

        return $normalizedUnit . '|' . $normalizedName;
    }

    private function loadAliasMap(): array
    {
        $path = storage_path('app/gmail/product_aliases.json');
        if (!is_file($path)) {
            return [];
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    private function storeAliasMap(array $aliases): void
    {
        $path = storage_path('app/gmail/product_aliases.json');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents(
            $path,
            json_encode($aliases, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
