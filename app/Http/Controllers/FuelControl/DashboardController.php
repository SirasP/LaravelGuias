<?php

namespace App\Http\Controllers\FuelControl;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index()
    {

        try {
            /* =========================
             * PRODUCTOS 
             * ========================= */
            $productos = DB::connection('fuelcontrol')
                ->table('productos')
                ->select('id', 'nombre', 'cantidad')
                ->orderBy('nombre', 'asc')
                ->get();

            /* =========================
             * ÚLTIMOS MOVIMIENTOS
             * ========================= */
            $movimientos = DB::connection('fuelcontrol')
                ->table('movimientos as m')
                ->leftJoin('productos as p', 'p.id', '=', 'm.producto_id')
                ->select(
                    'm.*',
                    'p.nombre as producto_nombre'
                )
                ->orderByDesc('m.fecha_movimiento')
                ->limit(5)

                ->get();


            /* =========================
             * NOTIFICACIONES (SOLO ADMIN)
             * ========================= */
            $notificaciones = collect();

            $notificaciones = DB::connection('fuelcontrol')
                ->table('notificaciones as n')
                ->join('notificacion_usuarios as nu', 'nu.notificacion_id', '=', 'n.id')
                ->leftJoin('movimientos as m', 'm.id', '=', 'n.movimiento_id') // 🔥 ESTA LÍNEA FALTA
                ->where('nu.user_id', auth()->id())
                ->where('nu.leido', 0)
                ->orderByDesc('n.created_at')
                ->limit(5)
                ->get([
                    'n.id',
                    'n.titulo',
                    'n.tipo',
                    'n.movimiento_id',
                    'n.mensaje',
                    'n.created_at',
                    'm.estado' // 🔥 IMPORTANTE

                ]);


            /* =========================
             * RESUMEN
             * ========================= */
            $resumen = [
                'total_productos' => $productos->count(),

                'total_vehiculos' => DB::connection('fuelcontrol')
                    ->table('vehiculos')
                    ->count(),

                'movimientos_hoy' => DB::connection('fuelcontrol')
                    ->table('movimientos')
                    ->whereDate('fecha_movimiento', now()->toDateString())
                    ->count(),
            ];

            /* =========================
             * GRÁFICOS: CONSUMO ÚLTIMOS 30 DÍAS
             * ========================= */
            $desde = now()->subDays(30)->startOfDay();

            $consumo = DB::connection('fuelcontrol')
                ->table('movimientos')
                ->join('productos', 'productos.id', '=', 'movimientos.producto_id')
                ->selectRaw('
        DATE(fecha_movimiento) as fecha,
        LOWER(productos.nombre) as producto,
        SUM(ABS(movimientos.cantidad)) as total
    ')
                ->where('tipo', 'salida')
                ->where('fecha_movimiento', '>=', $desde)
                ->groupBy('fecha', 'producto')
                ->orderBy('fecha')
                ->get();

            /* =========================
             * GASOLINA
             * ========================= */

            $gasolina = $consumo->filter(fn($r) => str_contains($r->producto, 'gas'));

            $labelsGasolina = $gasolina
                ->pluck('fecha')
                ->map(fn($f) => Carbon::parse($f)->format('d-m'))
                ->values();

            $dataGasolina = $gasolina
                ->pluck('total')
                ->values();

            /* =========================
             * DIESEL
             * ========================= */

            $diesel = $consumo->filter(fn($r) => str_contains($r->producto, 'die'));

            $labelsDiesel = $diesel
                ->pluck('fecha')
                ->map(fn($f) => Carbon::parse($f)->format('d-m'))
                ->values();

            $dataDiesel = $diesel
                ->pluck('total')
                ->values();

            /* =========================
             * CHARTS VEHÍCULOS (30 DÍAS)
             * ========================= */
            $hasOdomCol = Schema::connection('fuelcontrol')->hasColumn('movimientos', 'odometro');
            $hasOdomBombaCol = Schema::connection('fuelcontrol')->hasColumn('movimientos', 'odometro_bomba');
            $hasOdomAny = $hasOdomCol || $hasOdomBombaCol;
            $hasVehiculoId = Schema::connection('fuelcontrol')->hasColumn('movimientos', 'vehiculo_id');
            $odoExpr = match (true) {
                $hasOdomCol && $hasOdomBombaCol => 'COALESCE(NULLIF(m.odometro,0), NULLIF(m.odometro_bomba,0))',
                $hasOdomCol => 'NULLIF(m.odometro,0)',
                $hasOdomBombaCol => 'NULLIF(m.odometro_bomba,0)',
                default => null,
            };

            $topVehiculosRaw = collect();
            $usoDiarioVehiculosRaw = collect();

            if ($hasVehiculoId) {
                $topVehiculosQuery = DB::connection('fuelcontrol')
                    ->table('movimientos as m')
                    ->leftJoin('vehiculos as v', 'v.id', '=', 'm.vehiculo_id')
                    ->selectRaw("
                        COALESCE(
                            NULLIF(TRIM(v.patente), ''),
                            CONCAT('Vehículo #', m.vehiculo_id),
                            'Sin vehículo'
                        ) as vehiculo
                    ")
                    ->selectRaw('SUM(ABS(COALESCE(m.cantidad, 0))) as litros')
                    ->selectRaw('COUNT(*) as cargas')
                    ->where('m.fecha_movimiento', '>=', $desde)
                    ->where(function ($q) {
                        $q->whereNotNull('m.vehiculo_id')
                            ->orWhereRaw("LOWER(m.tipo) = 'vehiculo'");
                    })
                    ->where(function ($q) {
                        $q->whereNull('m.estado')
                            ->orWhere('m.estado', 'aprobado');
                    })
                    ->groupByRaw("
                        COALESCE(
                            NULLIF(TRIM(v.patente), ''),
                            CONCAT('Vehículo #', m.vehiculo_id),
                            'Sin vehículo'
                        )
                    ")
                    ->orderByDesc('litros')
                    ->limit(8);

                if ($hasOdomAny && $odoExpr) {
                    $topVehiculosQuery
                        ->selectRaw("MIN({$odoExpr}) as odo_min")
                        ->selectRaw("MAX({$odoExpr}) as odo_max");
                }

                $topVehiculosRaw = $topVehiculosQuery->get();

                $usoDiarioVehiculosQuery = DB::connection('fuelcontrol')
                    ->table('movimientos as m')
                    ->selectRaw('DATE(m.fecha_movimiento) as fecha')
                    ->selectRaw('SUM(ABS(COALESCE(m.cantidad, 0))) as litros')
                    ->where('m.fecha_movimiento', '>=', $desde)
                    ->where(function ($q) {
                        $q->whereNotNull('m.vehiculo_id')
                            ->orWhereRaw("LOWER(m.tipo) = 'vehiculo'");
                    })
                    ->where(function ($q) {
                        $q->whereNull('m.estado')
                            ->orWhere('m.estado', 'aprobado');
                    })
                    ->groupByRaw('DATE(m.fecha_movimiento)')
                    ->orderBy('fecha');

                if ($hasOdomAny && $odoExpr) {
                    $usoDiarioVehiculosQuery->selectRaw("MAX({$odoExpr}) - MIN({$odoExpr}) as km_dia");
                }

                $usoDiarioVehiculosRaw = $usoDiarioVehiculosQuery->get();
            }

            $topVehiculosLabels = $topVehiculosRaw
                ->pluck('vehiculo')
                ->map(fn($v) => mb_strimwidth((string) $v, 0, 18, '…'))
                ->values();

            $topVehiculosLitros = $topVehiculosRaw
                ->pluck('litros')
                ->map(fn($v) => round((float) $v, 2))
                ->values();

            $topVehiculosKmL = $topVehiculosRaw
                ->map(function ($r) use ($hasOdomAny) {
                    if (!$hasOdomAny) {
                        return null;
                    }
                    $litros = (float) ($r->litros ?? 0);
                    $km = (float) (($r->odo_max ?? 0) - ($r->odo_min ?? 0));
                    if ($litros <= 0 || $km <= 0) {
                        return null;
                    }
                    return round($km / $litros, 2);
                })
                ->values();

            $usoDiarioLabels = $usoDiarioVehiculosRaw
                ->pluck('fecha')
                ->map(fn($f) => Carbon::parse($f)->format('d-m'))
                ->values();

            $usoDiarioLitros = $usoDiarioVehiculosRaw
                ->pluck('litros')
                ->map(fn($v) => round((float) $v, 2))
                ->values();

            $usoDiarioKmL = $usoDiarioVehiculosRaw
                ->map(function ($r) use ($hasOdomAny) {
                    if (!$hasOdomAny) {
                        return null;
                    }
                    $litros = (float) ($r->litros ?? 0);
                    $km = (float) ($r->km_dia ?? 0);
                    if ($litros <= 0 || $km <= 0) {
                        return null;
                    }
                    return round($km / $litros, 2);
                })
                ->values();

        } catch (\Throwable $e) {
            dd([
                'error' => true,
                'mensaje' => $e->getMessage(),
                'conexion' => 'fuelcontrol',
            ]);
        }

        return view('fuelcontrol.index', compact(
            'productos',
            'movimientos',
            'resumen',
            'labelsGasolina',
            'dataGasolina',
            'labelsDiesel',
            'dataDiesel',
            'topVehiculosLabels',
            'topVehiculosLitros',
            'topVehiculosKmL',
            'usoDiarioLabels',
            'usoDiarioLitros',
            'usoDiarioKmL',
            'hasOdomAny',
            'notificaciones'
        ));
    }

    /* =========================
     * VER XML (MODAL)
     * ========================= */


    public function show($movimientoId)
    {
        $movimiento = DB::connection('fuelcontrol')
            ->table('movimientos')
            ->where('id', $movimientoId)
            ->firstOrFail();

        $ruta = 'xml/' . $movimiento->xml_path;

        if (!Storage::disk('local')->exists($ruta)) {
            abort(404, 'Archivo XML no encontrado');
        }

        $contenidoXml = Storage::disk('local')->get($ruta);

        return view('fuelcontrol.xml.modal', [
            'xml' => $contenidoXml,
            'movimiento' => $movimiento
        ]);
    }

    public function aprobar($movimientoId)
    {
        $db = DB::connection('fuelcontrol');

        $movimiento = $db->table('movimientos')
            ->where('id', $movimientoId)
            ->first();

        if (!$movimiento) {
            return response()->json(['error' => 'Movimiento no encontrado'], 404);
        }

        // 🔒 Evitar doble proceso
        if ($movimiento->estado !== 'pendiente') {
            return response()->json([
                'error' => 'Este documento ya fue procesado'
            ], 400);
        }

        $db->beginTransaction();

        try {

            // 🔥 SOLO VEHICULO debe entrar acá
            if ($movimiento->tipo === 'vehiculo') {

                $db->table('productos')
                    ->where('id', $movimiento->producto_id)
                    ->increment('cantidad', $movimiento->cantidad);
            }

            $db->table('movimientos')
                ->where('id', $movimientoId)
                ->update([
                    'estado' => 'aprobado',
                    'updated_at' => now()
                ]);

            $db->commit();

            return response()->json(['ok' => true]);

        } catch (\Throwable $e) {

            $db->rollBack();

            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }

    }

    public function rechazar($movimientoId)
    {
        $db = DB::connection('fuelcontrol');

        $movimiento = $db->table('movimientos')
            ->where('id', $movimientoId)
            ->first();

        if (!$movimiento) {
            return response()->json(['error' => 'Movimiento no encontrado'], 404);
        }

        // 🔒 Evitar doble proceso
        if ($movimiento->estado !== 'pendiente') {
            return response()->json([
                'error' => 'Este documento ya fue procesado'
            ], 400);
        }

        try {

            $db->table('movimientos')
                ->where('id', $movimientoId)
                ->update([
                    'estado' => 'rechazado',
                    'updated_at' => now()
                ]);

            return response()->json(['ok' => true]);

        } catch (\Throwable $e) {

            return response()->json([
                'error' => 'Error interno al rechazar'
            ], 500);
        }
    }





}
