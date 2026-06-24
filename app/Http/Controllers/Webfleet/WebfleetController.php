<?php

namespace App\Http\Controllers\Webfleet;

use App\Http\Controllers\Controller;
use App\Services\Webfleet\WebfleetApiService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WebfleetController extends Controller
{
    public function index(WebfleetApiService $webfleet)
    {
        $result = $webfleet->objectReport();

        // Calcular estadísticas básicas para las tarjetas informativas
        $stats = [
            'total' => 0,
            'moving' => 0,
            'ignition_on' => 0,
            'ignition_off' => 0,
            'active_today' => 0,
        ];

        if ($result['ok']) {
            $stats['total'] = count($result['data']);
            $todayStart = Carbon::today();
            foreach ($result['data'] as $object) {
                // Verificar velocidad actual
                $speed = $object['speed'] ?? 0;
                if ($speed > 0) {
                    $stats['moving']++;
                }

                // Verificar estado del motor
                $ignition = $object['ignition'] ?? null;
                if ($ignition === 1) {
                    $stats['ignition_on']++;
                } else {
                    $stats['ignition_off']++;
                }

                // Verificar si se ha reportado el día de hoy
                if (isset($object['pos_time'])) {
                    $posTime = Carbon::parse($object['pos_time']);
                    if ($posTime->greaterThanOrEqualTo($todayStart)) {
                        $stats['active_today']++;
                    }
                }
            }
        }

        return view('webfleet.index', [
            'configured' => $webfleet->configured(),
            'missingConfig' => $webfleet->missingConfig(),
            'result' => $result,
            'stats' => $stats,
        ]);
    }

    public function trips(Request $request, WebfleetApiService $webfleet)
    {
        // Rango de fechas: por defecto hoy
        $dateStr = $request->input('fecha', Carbon::today()->toDateString());
        $from = Carbon::parse($dateStr)->startOfDay()->toIso8601String();
        $to = Carbon::parse($dateStr)->endOfDay()->toIso8601String();

        $selectedObject = $request->input('object_no');

        // Obtener listado de objetos para el filtro desplegable
        $objectsResult = $webfleet->objectReport();
        $objects = $objectsResult['ok'] ? $objectsResult['data'] : [];

        // Consultar el reporte de viajes a la API
        $result = $webfleet->tripReport($from, $to, $selectedObject);

        // Procesar totales y métricas de viajes
        $tripStats = [
            'total_distance_m' => 0,
            'total_duration_s' => 0,
            'total_idle_s' => 0,
            'total_fuel_ml' => 0,
            'total_co2_g' => 0,
            'count' => 0,
        ];

        if ($result['ok']) {
            $tripStats['count'] = count($result['data']);
            foreach ($result['data'] as $trip) {
                $tripStats['total_distance_m'] += $trip['distance'] ?? 0;
                $tripStats['total_duration_s'] += $trip['duration'] ?? 0;
                $tripStats['total_idle_s'] += $trip['idle_time'] ?? 0;
                $tripStats['total_fuel_ml'] += $trip['fuel_consumption'] ?? $trip['fuel_consumed'] ?? $trip['fuel_usage'] ?? 0;
                $tripStats['total_co2_g'] += $trip['co2'] ?? $trip['co2_emission'] ?? $trip['co2_emissions'] ?? 0;
            }
        }

        return view('webfleet.trips', [
            'configured' => $webfleet->configured(),
            'missingConfig' => $webfleet->missingConfig(),
            'result' => $result,
            'objects' => $objects,
            'selectedDate' => $dateStr,
            'selectedObject' => $selectedObject,
            'tripStats' => $tripStats,
        ]);
    }

    public function events(Request $request, WebfleetApiService $webfleet)
    {
        // Rango de fechas: por defecto hoy
        $dateStr = $request->input('fecha', Carbon::today()->toDateString());
        $from = Carbon::parse($dateStr)->startOfDay()->toIso8601String();
        $to = Carbon::parse($dateStr)->endOfDay()->toIso8601String();

        $selectedObject = $request->input('object_no');

        // Obtener listado de objetos para el filtro desplegable
        $objectsResult = $webfleet->objectReport();
        $objects = $objectsResult['ok'] ? $objectsResult['data'] : [];

        // Consultar los eventos de Webfleet
        $result = $webfleet->eventReport($from, $to, $selectedObject);

        return view('webfleet.events', [
            'configured' => $webfleet->configured(),
            'missingConfig' => $webfleet->missingConfig(),
            'result' => $result,
            'objects' => $objects,
            'selectedDate' => $dateStr,
            'selectedObject' => $selectedObject,
        ]);
    }

    public function idleExceptions(Request $request, WebfleetApiService $webfleet)
    {
        // Rango de fechas: por defecto hoy
        $dateStr = $request->input('fecha', Carbon::today()->toDateString());
        $from = Carbon::parse($dateStr)->startOfDay()->toIso8601String();
        $to = Carbon::parse($dateStr)->endOfDay()->toIso8601String();

        $selectedObject = $request->input('object_no');

        // Obtener listado de objetos para el filtro desplegable
        $objectsResult = $webfleet->objectReport();
        $objects = $objectsResult['ok'] ? $objectsResult['data'] : [];

        // Consultar reporte de ralentí (exige un vehículo obligatorio)
        $result = null;
        if (! empty($selectedObject)) {
            $result = $webfleet->idleExceptions($from, $to, $selectedObject);
        }

        return view('webfleet.idle', [
            'configured' => $webfleet->configured(),
            'missingConfig' => $webfleet->missingConfig(),
            'result' => $result,
            'objects' => $objects,
            'selectedDate' => $dateStr,
            'selectedObject' => $selectedObject,
        ]);
    }

    public function diagnostics(Request $request, WebfleetApiService $webfleet)
    {
        $selectedObject = $request->input('object_no');

        // Obtener listado de objetos para el filtro desplegable
        $objectsResult = $webfleet->objectReport();
        $objects = $objectsResult['ok'] ? $objectsResult['data'] : [];

        $canSignals = null;
        $canMalfunctions = null;
        $events = null;

        // Historial de eventos de diagnóstico (últimos 15 días)
        $from = Carbon::now()->subDays(15)->startOfDay()->toIso8601String();
        $to = Carbon::now()->endOfDay()->toIso8601String();

        if (! empty($selectedObject)) {
            $canSignalsResult = $webfleet->objectCanSignals($selectedObject);
            $canSignals = $canSignalsResult['ok'] ? $canSignalsResult['data'] : [];

            $canMalfunctionsResult = $webfleet->objectCanMalfunctions($selectedObject);
            $canMalfunctions = $canMalfunctionsResult['ok'] ? $canMalfunctionsResult['data'] : [];

            // Eventos técnicos/alertas de este vehículo
            $eventsResult = $webfleet->eventReport($from, $to, $selectedObject);
            $events = $eventsResult['ok'] ? $eventsResult['data'] : [];
        } else {
            // Consulta masiva
            $canSignalsResult = $webfleet->objectCanSignals();
            $canSignals = $canSignalsResult['ok'] ? $canSignalsResult['data'] : [];

            $canMalfunctionsResult = $webfleet->objectCanMalfunctions();
            $canMalfunctions = $canMalfunctionsResult['ok'] ? $canMalfunctionsResult['data'] : [];
        }

        return view('webfleet.diagnostics', [
            'configured' => $webfleet->configured(),
            'missingConfig' => $webfleet->missingConfig(),
            'objects' => $objects,
            'selectedObject' => $selectedObject,
            'canSignals' => $canSignals,
            'canMalfunctions' => $canMalfunctions,
            'events' => $events,
        ]);
    }
}
