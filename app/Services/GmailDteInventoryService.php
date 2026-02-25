<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
                $code = trim((string) ($line->codigo ?? ''));
                $code = $code !== '' ? mb_strtoupper($code, 'UTF-8') : null;
                $unitCost = $qty > 0 ? ((float) $line->monto_item / $qty) : 0.0;

                $product = $this->resolveProductForIncomingLine($code, $name, $unit);

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

    private function resolveProductForIncomingLine(?string $code, string $name, string $unit): ?object
    {
        $products = DB::connection('fuelcontrol')->table('gmail_inventory_products');

        if ($code) {
            $byCode = (clone $products)
                ->where('codigo', $code)
                ->lockForUpdate()
                ->first();
            if ($byCode) {
                return $byCode;
            }
        }

        $byExact = (clone $products)
            ->whereRaw('UPPER(TRIM(nombre)) = ?', [mb_strtoupper(trim($name), 'UTF-8')])
            ->where('unidad', $unit)
            ->lockForUpdate()
            ->first();
        if ($byExact) {
            return $byExact;
        }

        return $this->findBestFuzzyMatch($name, $unit);
    }

    private function findBestFuzzyMatch(string $name, string $unit): ?object
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

        return DB::connection('fuelcontrol')
            ->table('gmail_inventory_products')
            ->where('id', $best->id)
            ->lockForUpdate()
            ->first();
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
}
