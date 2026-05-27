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
