<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NotificacionesApiController extends Controller
{
    /**
     * Obtener notificaciones no leídas para el usuario
     *
     * GET /api/notificaciones?user_id=1&limit=20
     */
    public function index(Request $request)
    {
        $userId = $request->query('user_id');
        $limit = (int) $request->query('limit', 20);

        if (!$userId) {
            return response()->json([
                'ok' => false,
                'message' => 'user_id es requerido'
            ], 400);
        }

        // Obtener notificaciones no leídas del usuario
        $notificaciones = DB::connection('fuelcontrol')
            ->table('notificaciones as n')
            ->join('notificacion_usuarios as nu', 'n.id', '=', 'nu.notificacion_id')
            ->leftJoin('movimientos as m', 'n.movimiento_id', '=', 'm.id')
            ->leftJoin('productos as p', 'm.producto_id', '=', 'p.id')
            ->where('nu.user_id', $userId)
            ->where('nu.leido', 0)
            ->select(
                'n.id',
                'n.tipo',
                'n.titulo',
                'n.mensaje',
                'n.movimiento_id',
                'n.created_at',
                'p.nombre as producto_nombre',
                'm.cantidad as producto_cantidad',
                'm.tipo as movimiento_tipo',
                'm.estado as movimiento_estado'
            )
            ->orderBy('n.created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'ok' => true,
            'data' => $notificaciones,
            'count' => $notificaciones->count()
        ]);
    }

    /**
     * Marcar notificación como leída
     *
     * POST /api/notificaciones/{id}/leer
     */
    public function marcarLeida(Request $request, $id)
    {
        $userId = $request->input('user_id');

        if (!$userId) {
            return response()->json([
                'ok' => false,
                'message' => 'user_id es requerido'
            ], 400);
        }

        $updated = DB::connection('fuelcontrol')
            ->table('notificacion_usuarios')
            ->where('notificacion_id', $id)
            ->where('user_id', $userId)
            ->update([
                'leido' => 1,
                'updated_at' => now()
            ]);

        return response()->json([
            'ok' => true,
            'message' => 'Notificación marcada como leída',
            'updated' => $updated > 0
        ]);
    }

    /**
     * Obtener últimos movimientos de combustible (Diesel/Gasolina)
     *
     * GET /api/combustible/movimientos?limit=10&tipo=entrada
     */
    public function movimientosCombustible(Request $request)
    {
        $limit = (int) $request->query('limit', 10);
        $tipo = $request->query('tipo'); // 'entrada', 'vehiculo', null (todos)

        $query = DB::connection('fuelcontrol')
            ->table('movimientos as m')
            ->join('productos as p', 'm.producto_id', '=', 'p.id')
            ->whereIn('p.nombre', ['Diesel', 'Gasolina'])
            ->select(
                'm.id',
                'p.nombre as producto',
                'm.cantidad',
                'm.tipo',
                'm.origen',
                'm.estado',
                'm.referencia',
                'm.fecha_movimiento',
                'm.created_at'
            )
            ->orderBy('m.created_at', 'desc');

        if ($tipo) {
            $query->where('m.tipo', $tipo);
        }

        $movimientos = $query->limit($limit)->get();

        return response()->json([
            'ok' => true,
            'data' => $movimientos,
            'count' => $movimientos->count()
        ]);
    }

    /**
     * Registrar o actualizar token FCM del dispositivo
     *
     * POST /api/combustible/fcm-token
     * Body: { user_id, fcm_token, device_type, device_name }
     */
    public function registrarFcmToken(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'fcm_token' => 'required|string',
            'device_type' => 'required|in:android,ios',
            'device_name' => 'nullable|string|max:255'
        ]);

        // Insertar o actualizar token
        $token = DB::connection('fuelcontrol')
            ->table('device_tokens')
            ->updateOrInsert(
                [
                    'user_id' => $validated['user_id'],
                    'fcm_token' => $validated['fcm_token']
                ],
                [
                    'device_type' => $validated['device_type'],
                    'device_name' => $validated['device_name'] ?? null,
                    'active' => true,
                    'updated_at' => now()
                ]
            );

        return response()->json([
            'ok' => true,
            'message' => 'Token FCM registrado correctamente',
            'user_id' => $validated['user_id']
        ]);
    }

    /**
     * Desactivar token FCM (logout o desinstalación)
     *
     * POST /api/combustible/fcm-token/deactivate
     * Body: { user_id, fcm_token }
     */
    public function desactivarFcmToken(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'fcm_token' => 'required|string'
        ]);

        DB::connection('fuelcontrol')
            ->table('device_tokens')
            ->where('user_id', $validated['user_id'])
            ->where('fcm_token', $validated['fcm_token'])
            ->update(['active' => false, 'updated_at' => now()]);

        return response()->json([
            'ok' => true,
            'message' => 'Token desactivado correctamente'
        ]);
    }

    /**
     * Obtener stock actual de combustibles
     *
     * GET /api/combustible/stock
     */
    public function stockCombustible()
    {
        $productos = DB::connection('fuelcontrol')
            ->table('productos')
            ->whereIn('nombre', ['Diesel', 'Gasolina'])
            ->select('id', 'nombre', 'cantidad', 'fecha_registro')
            ->get();

        return response()->json([
            'ok' => true,
            'data' => $productos
        ]);
    }
}
