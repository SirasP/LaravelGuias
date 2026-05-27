<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MantencionApiController extends Controller
{
    /** Bodega Taller Mecánico (fijo) */
    private const BODEGA_TALLER = 2;

    /**
     * GET /api/mantencion/repuestos
     *
     * Devuelve el stock actual de Taller Mecánico:
     * - Todos los productos que tienen al menos un lote en bodega_id=2
     * - stock_disponible = suma de cantidad_disponible en lotes ABIERTO de esa bodega
     * - Ordenados por nombre
     */
    public function repuestos(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        // Obtenemos los productos que tienen lotes en Taller Mecánico
        // junto con su stock disponible (suma de lotes ABIERTO en esa bodega)
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

        return response()->json([
            'ok'           => true,
            'bodega'       => 'Taller Mecánico',
            'bodega_id'    => self::BODEGA_TALLER,
            'total'        => $products->count(),
            'actualizadoEl'=> now()->toIso8601String(),
            'data'         => $products,
        ]);
    }

    /**
     * POST /api/mantencion/fcm-token
     *
     * Registra o actualiza el token FCM del dispositivo para la app de mantención.
     * Body: { fcm_token, device_type, device_name? }
     *
     * No requiere user_id — todos los tokens de mantencion reciben el mismo push.
     */
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
                    'user_id'     => 0,          // No aplica para esta app
                    'device_type' => $validated['device_type'],
                    'device_name' => $validated['device_name'] ?? null,
                    'app_type'    => 'mantencion',
                    'active'      => true,
                    'updated_at'  => now(),
                ]
            );

        return response()->json([
            'ok'      => true,
            'message' => 'Token FCM registrado para app mantención.',
        ]);
    }

    /**
     * GET /api/mantencion/repuestos/{id}/movimientos
     *
     * Historial de movimientos de un producto en Taller Mecánico.
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

        // Stock actual en Taller Mecánico
        $stockActual = DB::connection('fuelcontrol')
            ->table('gmail_inventory_lots')
            ->where('product_id', $id)
            ->where('bodega_id', self::BODEGA_TALLER)
            ->where('estado', 'ABIERTO')
            ->sum('cantidad_disponible');

        return response()->json([
            'ok'          => true,
            'producto'    => $product,
            'stock_actual'=> (float) $stockActual,
            'movimientos' => $movimientos,
        ]);
    }

    /**
     * POST /api/mantencion/egresos
     *
     * Registra una salida de stock (FIFO) para cada insumo usado en una mantención.
     *
     * Body JSON:
     * {
     *   "equipo":   "Tractor John Deere",   // opcional
     *   "notas":    "Mantención preventiva", // opcional
     *   "items": [
     *     { "product_id": 5, "cantidad": 2 },
     *     { "product_id": 9, "cantidad": 1 }
     *   ]
     * }
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

        $db     = DB::connection('fuelcontrol');
        $notas  = trim(($validated['equipo'] ?? '') . ' — ' . ($validated['notas'] ?? 'Mantención'));
        $errors = [];

        $db->transaction(function () use ($db, $validated, $notas, &$errors) {

            // Crear cabecera del movimiento (una por llamada)
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
                $productId     = (int) $item['product_id'];
                $cantidadNecesaria = (float) $item['cantidad'];

                // Lotes ABIERTO en Taller Mecánico, orden FIFO
                $lotes = $db->table('gmail_inventory_lots')
                    ->where('product_id', $productId)
                    ->where('bodega_id', self::BODEGA_TALLER)
                    ->where('estado', 'ABIERTO')
                    ->where('cantidad_disponible', '>', 0)
                    ->orderBy('id')           // FIFO
                    ->get();

                $stockTotal = $lotes->sum('cantidad_disponible');

                if ($stockTotal < $cantidadNecesaria) {
                    $errors[] = "Producto #{$productId}: stock insuficiente ({$stockTotal} disponible, se pidieron {$cantidadNecesaria})";
                    throw new \Exception('stock_insuficiente');
                }

                $pendiente = $cantidadNecesaria;

                foreach ($lotes as $lote) {
                    if ($pendiente <= 0) break;

                    $deducir = min($pendiente, $lote->cantidad_disponible);
                    $nueva   = $lote->cantidad_disponible - $deducir;

                    // Actualizar lote
                    $db->table('gmail_inventory_lots')
                        ->where('id', $lote->id)
                        ->update([
                            'cantidad_disponible' => $nueva,
                            'cantidad_salida'     => $lote->cantidad_salida + $deducir,
                            'estado'              => $nueva <= 0 ? 'CERRADO' : 'ABIERTO',
                            'updated_at'          => now(),
                        ]);

                    // Línea del movimiento
                    $costoUnitario = $lote->costo_unitario ?? 0;
                    $db->table('gmail_inventory_movement_lines')->insert([
                        'movement_id'   => $movementId,
                        'lot_id'        => $lote->id,
                        'product_id'    => $productId,
                        'cantidad'      => $deducir,
                        'costo_unitario'=> $costoUnitario,
                        'costo_total'   => round($deducir * $costoUnitario, 6),
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);

                    $pendiente -= $deducir;
                }

                $cantidadTotalMovimiento += $cantidadNecesaria;
            }

            // Actualizar totales del movimiento
            $db->table('gmail_inventory_movements')
                ->where('id', $movementId)
                ->update(['cantidad_total' => $cantidadTotalMovimiento, 'updated_at' => now()]);
        });

        if (!empty($errors)) {
            return response()->json(['ok' => false, 'message' => implode('; ', $errors)], 422);
        }

        return response()->json(['ok' => true, 'message' => 'Egresos registrados correctamente.']);
    }

    /**
     * DELETE /api/mantencion/fcm-token
     *
     * Desactiva el token (logout / desinstalación).
     * Body: { fcm_token }
     */
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
