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
            // Cálculo de km por vehículo usando odómetro (fallback: odómetro bomba)
            $hasOdomCol = Schema::connection('fuelcontrol')->hasColumn('movimientos', 'odometro');
            $hasOdomBombaCol = Schema::connection('fuelcontrol')->hasColumn('movimientos', 'odometro_bomba');
            $hasOdomAny = $hasOdomCol || $hasOdomBombaCol;
            $hasVehiculoId = Schema::connection('fuelcontrol')->hasColumn('movimientos', 'vehiculo_id');

            $topVehiculosLabels = collect();
            $topVehiculosLitros = collect();
            $topVehiculosKmL = collect();
            $usoDiarioLabels = collect();
            $usoDiarioLitros = collect();
            $usoDiarioKmL = collect();
            $vehiculosDebug = [];

            if ($hasVehiculoId && $hasOdomAny) {
                $vehRows = DB::connection('fuelcontrol')
                    ->table('movimientos as m')
                    ->leftJoin('vehiculos as v', 'v.id', '=', 'm.vehiculo_id')
                    ->select(
                        'm.vehiculo_id',
                        'm.fecha_movimiento',
                        'm.cantidad',
                        'm.odometro',
                        'm.odometro_bomba'
                    )
                    ->selectRaw("
                        COALESCE(
                            NULLIF(TRIM(v.patente), ''),
                            CONCAT('Vehículo #', m.vehiculo_id)
                        ) as vehiculo
                    ")
                    ->where('m.fecha_movimiento', '>=', $desde)
                    ->whereNotNull('m.vehiculo_id')
                    ->where(function ($q) {
                        $q->whereNull('m.estado')
                            ->orWhere('m.estado', 'aprobado');
                    })
                    ->orderBy('m.vehiculo_id')
                    ->orderBy('m.fecha_movimiento')
                    ->get();

                $vehiculosCalc = collect();
                $daily = [];

                foreach ($vehRows->groupBy('vehiculo_id') as $vehiculoId => $rows) {
                    $prevOdo = null;
                    $vehLitros = 0.0;
                    $vehKm = 0.0;
                    $vehName = (string) ($rows->first()->vehiculo ?? "Vehículo #{$vehiculoId}");

                    foreach ($rows as $r) {
                        $litros = abs((float) ($r->cantidad ?? 0));
                        $odoPrincipal = (float) ($r->odometro ?? 0);
                        $odoBomba = (float) ($r->odometro_bomba ?? 0);
                        $odo = $odoPrincipal > 0 ? $odoPrincipal : ($odoBomba > 0 ? $odoBomba : 0);
                        $fecha = Carbon::parse($r->fecha_movimiento)->toDateString();

                        $vehLitros += $litros;
                        if (!isset($daily[$fecha])) {
                            $daily[$fecha] = ['litros' => 0.0, 'km' => 0.0];
                        }
                        $daily[$fecha]['litros'] += $litros;

                        if ($odo > 0 && !is_null($prevOdo) && $odo > $prevOdo) {
                            $deltaKm = $odo - $prevOdo;
                            $vehKm += $deltaKm;
                            $daily[$fecha]['km'] += $deltaKm;
                        }
                        if ($odo > 0) {
                            $prevOdo = $odo;
                        }
                    }

                    $vehiculosCalc->push([
                        'vehiculo' => $vehName,
                        'litros' => round($vehLitros, 2),
                        'km' => round($vehKm, 2),
                        'kml' => ($vehLitros > 0 && $vehKm > 0) ? round($vehKm / $vehLitros, 2) : null,
                    ]);
                }

                $top = $vehiculosCalc
                    ->sortByDesc('litros')
                    ->take(8)
                    ->values();

                $topVehiculosLabels = $top
                    ->pluck('vehiculo')
                    ->map(fn($v) => mb_strimwidth((string) $v, 0, 18, '…'))
                    ->values();

                $topVehiculosLitros = $top
                    ->pluck('litros')
                    ->values();

                $topVehiculosKmL = $top
                    ->pluck('kml')
                    ->values();

                ksort($daily);
                $usoDiarioAgg = collect($daily)->map(function ($r) {
                    $litros = (float) ($r['litros'] ?? 0);
                    $km = (float) ($r['km'] ?? 0);
                    return [
                        'litros' => round($litros, 2),
                        'kml' => ($litros > 0 && $km > 0) ? round($km / $litros, 2) : null,
                    ];
                });

                $usoDiarioLabels = $usoDiarioAgg
                    ->keys()
                    ->map(fn($f) => Carbon::parse($f)->format('d-m'))
                    ->values();

                $usoDiarioLitros = $usoDiarioAgg
                    ->pluck('litros')
                    ->values();

                $usoDiarioKmL = $usoDiarioAgg
                    ->pluck('kml')
                    ->values();

                $vehiculosDebug = [
                    'rows_source' => $vehRows->count(),
                    'rows_top' => $top->count(),
                    'rows_daily_grouped' => $usoDiarioAgg->count(),
                    'litros_top' => round($top->sum(fn($r) => (float) ($r['litros'] ?? 0)), 2),
                    'litros_daily' => round($usoDiarioAgg->sum(fn($r) => (float) ($r['litros'] ?? 0)), 2),
                    'labels_top' => $topVehiculosLabels->count(),
                    'labels_daily' => $usoDiarioLabels->count(),
                ];
            }

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
            'vehiculosDebug',
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
