<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\ProductVolumeDetector;
use Illuminate\Support\Facades\DB;

class MantencionApiController extends Controller
{
    /** Bodega Taller Mecánico (fijo) */
    private const BODEGA_TALLER = 2;

    // ─────────────────────────────────────────────────────────────────────────
    // REPUESTOS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/mantencion/repuestos
     *
     * Devuelve el stock actual de Taller Mecánico.
     * Si existe una conversión para el producto, incluye factor y unidad_consumo
     * para que la app muestre litros en lugar de tambores.
     */
    public function repuestos(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $products = DB::connection('fuelcontrol')
            ->table('gmail_inventory_products as p')
            ->join(
                DB::connection('fuelcontrol')->raw('(
                    SELECT product_id,
                           COALESCE(SUM(CASE WHEN estado = \'ABIERTO\' THEN cantidad_disponible ELSE 0 END), 0) AS stock_disponible
                    FROM gmail_inventory_lots
                    WHERE bodega_id = ' . self::BODEGA_TALLER . '
                    GROUP BY product_id
                ) AS lotes_taller'),
                'lotes_taller.product_id',
                '=',
                'p.id'
            )
            ->select([
                'p.id',
                'p.nombre',
                'p.codigo',
                'p.unidad',
                'p.stock_minimo',
                'lotes_taller.stock_disponible',
            ])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qb) use ($q) {
                    $qb->where('p.nombre', 'like', "%{$q}%")
                       ->orWhere('p.codigo', 'like', "%{$q}%");
                });
            })
            ->orderBy('p.nombre')
            ->get();

        // ── Conversiones manuales ya guardadas ──────────────────────────────────
        $productIds   = $products->pluck('id');
        $conversiones = DB::table('inventory_conversions')
            ->whereIn('product_id', $productIds)
            ->get()
            ->keyBy('product_id');

        // ── Auto-detectar conversiones que aún no existen ───────────────────
        // Solo intentamos en productos sin conversión y cuya unidad sea "Unidades"
        // (o variantes), ya que los líquidos en Odoo suelen estar como unidades sueltas.
        $sinConversion = $products->filter(
            fn($p) => !$conversiones->has($p->id)
        );

        if ($sinConversion->isNotEmpty()) {
            $autoDetectados = ProductVolumeDetector::detectMany($sinConversion);

            if (!empty($autoDetectados)) {
                $ahora = now();
                foreach ($autoDetectados as $det) {
                    // Solo guardar si no existe ya (evitar sobreescribir manuales)
                    $existe = DB::table('inventory_conversions')
                        ->where('product_id', $det['product_id'])
                        ->exists();

                    if (!$existe) {
                        DB::table('inventory_conversions')->insert([
                            'product_id'     => $det['product_id'],
                            'nombre'         => $det['nombre'],
                            'factor'         => $det['factor'],
                            'unidad_consumo' => $det['unidad_consumo'],
                            'unidad_compra'  => $det['unidad_compra'],
                            'auto_detected'  => true,
                            'created_at'     => $ahora,
                            'updated_at'     => $ahora,
                        ]);
                        $conversiones->put($det['product_id'], (object) $det);
                    }
                }
            }
        }

        // ── Mezclar conversiones en cada producto ────────────────────────────
        $products = $products->map(function ($p) use ($conversiones) {
            $conv = $conversiones->get($p->id);
            $p->factor         = $conv ? (float) $conv->factor        : null;
            $p->unidad_consumo = $conv ? $conv->unidad_consumo        : null;
            $p->unidad_compra  = $conv ? $conv->unidad_compra         : null;
            $p->auto_detected  = $conv ? (bool)  $conv->auto_detected : false;
            return $p;
        });

        return response()->json([
            'ok'           => true,
            'bodega'       => 'Taller Mecánico',
            'bodega_id'    => self::BODEGA_TALLER,
            'total'        => $products->count(),
            'actualizadoEl'=> now()->toIso8601String(),
            'data'         => $products,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CONVERSIONES
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/mantencion/conversiones
     * Lista todas las conversiones configuradas.
     */
    public function conversiones(): JsonResponse
    {
        $rows = DB::table('inventory_conversions')
            ->orderBy('nombre')
            ->get();

        return response()->json(['ok' => true, 'data' => $rows]);
    }

    /**
     * POST /api/mantencion/conversiones
     * Crea o actualiza la conversión de un producto.
     *
     * Body: { product_id, factor, unidad_consumo, unidad_compra?, nombre? }
     * Ejemplo: { product_id: 42, factor: 208, unidad_consumo: "Ltrs", unidad_compra: "tambor" }
     */
    public function upsertConversion(Request $request): JsonResponse
    {
        $v = $request->validate([
            'product_id'    => 'required|integer|min:1',
            'factor'        => 'required|numeric|min:0.0001',
            'unidad_consumo'=> 'required|string|max:20',
            'unidad_compra' => 'nullable|string|max:40',
            'nombre'        => 'nullable|string|max:200',
        ]);

        DB::table('inventory_conversions')->updateOrInsert(
            ['product_id' => $v['product_id']],
            [
                'nombre'        => $v['nombre'] ?? null,
                'factor'        => $v['factor'],
                'unidad_consumo'=> $v['unidad_consumo'],
                'unidad_compra' => $v['unidad_compra'] ?? null,
                'updated_at'    => now(),
                'created_at'    => now(),
            ]
        );

        return response()->json(['ok' => true, 'message' => 'Conversión guardada.']);
    }

    /**
     * DELETE /api/mantencion/conversiones/{product_id}
     * Elimina la conversión de un producto (vuelve a unidad nativa de Odoo).
     */
    public function deleteConversion(int $productId): JsonResponse
    {
        DB::table('inventory_conversions')->where('product_id', $productId)->delete();

        return response()->json(['ok' => true, 'message' => 'Conversión eliminada.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MOVIMIENTOS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/mantencion/repuestos/{id}/movimientos
     * Historial de movimientos de un producto.
     * Incluye factor de conversión si está configurado.
     */
    public function movimientos(int $id): JsonResponse
    {
        $product = DB::connection('fuelcontrol')
            ->table('gmail_inventory_products')
            ->where('id', $id)
            ->first(['id', 'nombre', 'codigo', 'unidad', 'stock_minimo']);

        if (!$product) {
            return response()->json(['ok' => false, 'message' => 'Producto no encontrado.'], 404);
        }

        $movimientos = DB::connection('fuelcontrol')
            ->table('gmail_inventory_movement_lines as ml')
            ->join('gmail_inventory_movements as m', 'ml.movement_id', '=', 'm.id')
            ->join('gmail_inventory_lots as l', 'ml.lot_id', '=', 'l.id')
            ->where('ml.product_id', $id)
            ->where('l.bodega_id', self::BODEGA_TALLER)
            ->select([
                'm.id as movement_id',
                'm.tipo',
                'm.tipo_salida',
                'm.ocurrio_el',
                'm.destinatario',
                'm.notas',
                'ml.cantidad',
                'ml.costo_unitario',
                'ml.costo_total',
            ])
            ->orderByDesc('m.ocurrio_el')
            ->orderByDesc('m.id')
            ->limit(50)
            ->get();

        $stockActual = DB::connection('fuelcontrol')
            ->table('gmail_inventory_lots')
            ->where('product_id', $id)
            ->where('bodega_id', self::BODEGA_TALLER)
            ->where('estado', 'ABIERTO')
            ->sum('cantidad_disponible');

        // Incluir conversión si existe
        $conv = DB::table('inventory_conversions')->where('product_id', $id)->first();

        return response()->json([
            'ok'           => true,
            'producto'     => $product,
            'stock_actual' => (float) $stockActual,
            'factor'       => $conv ? (float) $conv->factor        : null,
            'unidad_consumo'=> $conv ? $conv->unidad_consumo        : null,
            'unidad_compra' => $conv ? $conv->unidad_compra         : null,
            'movimientos'  => $movimientos,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EGRESOS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/mantencion/egresos
     *
     * Registra salida de stock (FIFO).
     * Si el producto tiene conversión, la cantidad viene en unidad de consumo (Ltrs)
     * y se convierte automáticamente a la unidad de Odoo antes de descontar.
     *
     * Body: { equipo?, notas?, items: [{ product_id, cantidad }] }
     */
    public function registrarEgresos(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|min:1',
            'items.*.cantidad'   => 'required|numeric|min:0.001',
            'equipo'             => 'nullable|string|max:255',
            'notas'              => 'nullable|string|max:1000',
        ]);

        $db    = DB::connection('fuelcontrol');
        $notas = trim(($validated['equipo'] ?? '') . ' — ' . ($validated['notas'] ?? 'Mantención'));

        // Cargar conversiones para todos los productos del request
        $productIds = collect($validated['items'])->pluck('product_id')->unique();
        $conversiones = DB::table('inventory_conversions')
            ->whereIn('product_id', $productIds)
            ->get()
            ->keyBy('product_id');

        $errors = [];

        $db->transaction(function () use ($db, $validated, $notas, $conversiones, &$errors) {

            $movementId = $db->table('gmail_inventory_movements')->insertGetId([
                'tipo'           => 'SALIDA',
                'tipo_salida'    => 'MANTENCION',
                'estado'         => 'CONTABILIZADO',
                'notas'          => $notas,
                'ocurrio_el'     => now(),
                'cantidad_total' => 0,
                'costo_total'    => 0,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            $cantidadTotalMovimiento = 0;

            foreach ($validated['items'] as $item) {
                $productId           = (int)   $item['product_id'];
                $cantidadSolicitada  = (float) $item['cantidad']; // en unidad de consumo (Ltrs, etc.)

                // Convertir a unidad de Odoo si hay conversión configurada
                $conv = $conversiones->get($productId);
                $cantidadNecesaria = ($conv && $conv->factor > 0)
                    ? round($cantidadSolicitada / $conv->factor, 6)
                    : $cantidadSolicitada;

                $lotes = $db->table('gmail_inventory_lots')
                    ->where('product_id', $productId)
                    ->where('bodega_id', self::BODEGA_TALLER)
                    ->where('estado', 'ABIERTO')
                    ->where('cantidad_disponible', '>', 0)
                    ->orderBy('id') // FIFO
                    ->get();

                $stockTotal = $lotes->sum('cantidad_disponible');

                if ($stockTotal < $cantidadNecesaria) {
                    // Mostrar mensaje en unidad entendible
                    $stockMostrar = $conv
                        ? round($stockTotal * $conv->factor, 2) . ' ' . $conv->unidad_consumo
                        : "{$stockTotal}";
                    $pedidoMostrar = $conv
                        ? "{$cantidadSolicitada} {$conv->unidad_consumo}"
                        : "{$cantidadSolicitada}";

                    $errors[] = "Producto #{$productId}: stock insuficiente ({$stockMostrar} disponible, se pidieron {$pedidoMostrar})";
                    throw new \Exception('stock_insuficiente');
                }

                $pendiente = $cantidadNecesaria;

                foreach ($lotes as $lote) {
                    if ($pendiente <= 0) break;

                    $deducir = min($pendiente, $lote->cantidad_disponible);
                    $nueva   = $lote->cantidad_disponible - $deducir;

                    $db->table('gmail_inventory_lots')
                        ->where('id', $lote->id)
                        ->update([
                            'cantidad_disponible' => $nueva,
                            'cantidad_salida'     => $lote->cantidad_salida + $deducir,
                            'estado'              => $nueva <= 0 ? 'CERRADO' : 'ABIERTO',
                            'updated_at'          => now(),
                        ]);

                    $costoUnitario = $lote->costo_unitario ?? 0;
                    $db->table('gmail_inventory_movement_lines')->insert([
                        'movement_id'    => $movementId,
                        'lot_id'         => $lote->id,
                        'product_id'     => $productId,
                        'cantidad'       => $deducir,   // en unidad Odoo (tambores)
                        'costo_unitario' => $costoUnitario,
                        'costo_total'    => round($deducir * $costoUnitario, 6),
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ]);

                    $pendiente -= $deducir;
                }

                $cantidadTotalMovimiento += $cantidadNecesaria;
            }

            $db->table('gmail_inventory_movements')
                ->where('id', $movementId)
                ->update(['cantidad_total' => $cantidadTotalMovimiento, 'updated_at' => now()]);
        });

        if (!empty($errors)) {
            return response()->json(['ok' => false, 'message' => implode('; ', $errors)], 422);
        }

        return response()->json(['ok' => true, 'message' => 'Egresos registrados correctamente.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FCM TOKENS
    // ─────────────────────────────────────────────────────────────────────────

    public function registerFcmToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token'   => 'required|string',
            'device_type' => 'required|in:android,ios',
            'device_name' => 'nullable|string|max:255',
        ]);

        DB::connection('fuelcontrol')
            ->table('device_tokens')
            ->updateOrInsert(
                ['fcm_token' => $validated['fcm_token']],
                [
                    'user_id'     => 0,
                    'device_type' => $validated['device_type'],
                    'device_name' => $validated['device_name'] ?? null,
                    'app_type'    => 'mantencion',
                    'active'      => true,
                    'updated_at'  => now(),
                ]
            );

        return response()->json(['ok' => true, 'message' => 'Token FCM registrado para app mantención.']);
    }

    public function deactivateFcmToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => 'required|string',
        ]);

        DB::connection('fuelcontrol')
            ->table('device_tokens')
            ->where('fcm_token', $validated['fcm_token'])
            ->where('app_type', 'mantencion')
            ->update(['active' => false, 'updated_at' => now()]);

        return response()->json(['ok' => true, 'message' => 'Token desactivado.']);
    }
}
