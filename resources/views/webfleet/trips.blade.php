<x-app-layout>
    <!-- Leaflet CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <style>
        #trips-map {
            height: 400px;
            width: 100%;
            border-radius: 0.5rem;
            z-index: 10;
        }
    </style>

    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between w-full gap-3">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 tracking-tight">Historial de Viajes</h2>
                <x-breadcrumbs :items="[
                    ['label' => 'Integraciones'],
                    ['label' => 'Webfleet', 'route' => 'webfleet.index'],
                    ['label' => 'Viajes'],
                ]" />
            </div>

            <!-- Navegacion de Vistas -->
            <div class="flex items-center gap-2">
                <a href="{{ route('webfleet.index') }}"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 text-xs font-bold rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    Dashboard
                </a>
                <a href="{{ route('webfleet.trips') }}"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 text-xs font-bold rounded-lg bg-sky-600 text-white shadow-sm">
                    Viajes
                </a>
                <a href="{{ route('webfleet.events') }}"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 text-xs font-bold rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    Eventos
                </a>
                <a href="{{ route('webfleet.idle') }}"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 text-xs font-bold rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    Ralentí
                </a>
                <a href="{{ route('webfleet.diagnostics') }}"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 text-xs font-bold rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    Diagnóstico
                </a>
            </div>
        </div>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6">
        <!-- Formulario de Filtros -->
        <section class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm">
            <form method="GET" action="{{ route('webfleet.trips') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <!-- Selector de Fecha -->
                <div class="space-y-1">
                    <label for="fecha" class="text-xs font-bold text-gray-700 dark:text-gray-300">Fecha del Reporte</label>
                    <input type="date" id="fecha" name="fecha" value="{{ $selectedDate }}"
                        class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-sky-500 focus:border-sky-500" />
                </div>

                <!-- Selector de Vehiculo -->
                <div class="space-y-1">
                    <label for="object_no" class="text-xs font-bold text-gray-700 dark:text-gray-300">Seleccionar Vehículo</label>
                    <select id="object_no" name="object_no"
                        class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-sky-500 focus:border-sky-500">
                        <option value="">-- Todos los vehículos --</option>
                        @foreach($objects as $obj)
                            <option value="{{ $obj['objectno'] }}" @selected($selectedObject == $obj['objectno'])>
                                {{ $obj['objectname'] }} (N° {{ $obj['objectno'] }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Boton Buscar -->
                <div>
                    <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-bold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition shadow-sm active:scale-95">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Filtrar Reporte
                    </button>
                </div>
            </form>
        </section>

        @if(($result['ok'] ?? null) === false)
            <div class="bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/50 p-4 rounded-lg">
                <h3 class="text-sm font-bold text-red-800 dark:text-red-300">Error al consultar el reporte de viajes</h3>
                <p class="mt-1 text-xs text-red-700 dark:text-red-400">{{ $result['error'] }}</p>
            </div>
        @endif

        <!-- Tarjetas Informativas / Totales -->
        <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">
            <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-4 rounded-xl shadow-sm">
                <span class="text-xs text-gray-500 dark:text-gray-400 block font-semibold uppercase">Total Viajes</span>
                <span class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-1 block">
                    {{ $tripStats['count'] }}
                </span>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-4 rounded-xl shadow-sm">
                <span class="text-xs text-gray-500 dark:text-gray-400 block font-semibold uppercase">Distancia Total</span>
                <span class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-1 block">
                    {{ number_format($tripStats['total_distance_m'] / 1000, 2, ',', '.') }} km
                </span>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-4 rounded-xl shadow-sm">
                <span class="text-xs text-gray-500 dark:text-gray-400 block font-semibold uppercase">Tiempo Conducción</span>
                <span class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-1 block">
                    @php
                        $hours = floor($tripStats['total_duration_s'] / 3600);
                        $mins = floor(($tripStats['total_duration_s'] / 60) % 60);
                    @endphp
                    {{ $hours }}h {{ $mins }}m
                </span>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-4 rounded-xl shadow-sm">
                <span class="text-xs text-gray-500 dark:text-gray-400 block font-semibold uppercase">Tiempo Ralentí</span>
                <span class="text-xl font-bold text-amber-600 dark:text-amber-400 mt-1 block">
                    {{ floor($tripStats['total_idle_s'] / 60) }} min
                </span>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-4 rounded-xl shadow-sm">
                <span class="text-xs text-gray-500 dark:text-gray-400 block font-semibold uppercase">Combustible Usado</span>
                <span class="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-1 block">
                    {{ $tripStats['total_fuel_ml'] > 0 ? number_format($tripStats['total_fuel_ml'] / 1000, 1, ',', '.') . ' L' : '0 L' }}
                </span>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-4 rounded-xl shadow-sm">
                <span class="text-xs text-gray-500 dark:text-gray-400 block font-semibold uppercase">Emisiones CO₂</span>
                <span class="text-xl font-bold text-blue-600 dark:text-blue-400 mt-1 block">
                    {{ $tripStats['total_co2_g'] > 0 ? number_format($tripStats['total_co2_g'] / 1000, 1, ',', '.') . ' kg' : '0 kg' }}
                </span>
            </div>
        </div>

        @if(is_array($result['data'] ?? null) && count($result['data']) > 0)
            <!-- Mapa de Rutas de los Viajes -->
            <section class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm space-y-3">
                <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Recorridos de los Viajes</h3>
                <div id="trips-map"></div>
            </section>

            <!-- Tabla de Detalle -->
            <section class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-100 dark:border-gray-800">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Bitácora Detallada de Viajes</h3>
                </div>

                <div class="overflow-x-auto text-xs">
                    <table class="min-w-full text-left">
                        <thead class="bg-gray-50 dark:bg-gray-800/70 text-gray-500 dark:text-gray-400 font-bold uppercase">
                            <tr>
                                <th class="px-4 py-3">Vehículo</th>
                                <th class="px-4 py-3">Inicio</th>
                                <th class="px-4 py-3">Término</th>
                                <th class="px-4 py-3">Distancia</th>
                                <th class="px-4 py-3">Duración</th>
                                <th class="px-4 py-3">Ralentí (Idle)</th>
                                <th class="px-4 py-3">Vel. Prom / Max</th>
                                <th class="px-4 py-3">Combustible / Prom / CO₂</th>
                                <th class="px-4 py-3">OptiDrive / CAN</th>
                                <th class="px-4 py-3">Ruta</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800/80">
                            @foreach($result['data'] as $index => $trip)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                    <td class="px-4 py-3 font-bold text-gray-900 dark:text-gray-100">
                                        {{ $trip['objectname'] }} <span class="text-gray-400 block font-normal">N° {{ $trip['objectno'] }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold">{{ \Carbon\Carbon::parse($trip['start_time'])->format('H:i') }} hrs</div>
                                        <div class="text-[10px] text-gray-400 max-w-[200px] truncate" title="{{ $trip['start_postext'] }}">{{ $trip['start_postext'] }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold">{{ \Carbon\Carbon::parse($trip['end_time'])->format('H:i') }} hrs</div>
                                        <div class="text-[10px] text-gray-400 max-w-[200px] truncate" title="{{ $trip['end_postext'] }}">{{ $trip['end_postext'] }}</div>
                                    </td>
                                    <td class="px-4 py-3 font-bold text-gray-900 dark:text-gray-100">
                                        {{ number_format(($trip['distance'] ?? 0) / 1000, 2, ',', '.') }} km
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ floor(($trip['duration'] ?? 0) / 60) }} min
                                    </td>
                                    <td class="px-4 py-3 text-amber-600 dark:text-amber-400 font-semibold">
                                        {{ floor(($trip['idle_time'] ?? 0) / 60) }} min
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400 font-semibold">
                                        {{ $trip['avg_speed'] ?? 0 }} / {{ $trip['max_speed'] ?? 0 }} km/h
                                    </td>
                                    <td class="px-4 py-3">
                                        @php
                                            $fuelLtr = isset($trip['fuel_consumption']) ? ($trip['fuel_consumption'] / 1000) : (isset($trip['fuel_consumed']) ? ($trip['fuel_consumed'] / 1000) : (isset($trip['fuel_usage']) ? ($trip['fuel_usage'] / 1000) : null));
                                            $avgFuel = $trip['fuelconsump'] ?? $trip['fuel_average'] ?? null;
                                            $co2Kg = isset($trip['co2']) ? ($trip['co2'] / 1000) : (isset($trip['co2_emission']) ? ($trip['co2_emission'] / 1000) : (isset($trip['co2_emissions']) ? ($trip['co2_emissions'] / 1000) : null));
                                        @endphp
                                        <div class="space-y-0.5">
                                            @if($fuelLtr !== null && $fuelLtr > 0)
                                                <div class="font-bold text-emerald-700 dark:text-emerald-400 flex items-center gap-1">
                                                    <span>⛽ {{ number_format($fuelLtr, 1, ',', '.') }} L</span>
                                                </div>
                                            @else
                                                <span class="text-gray-400">⛽ -</span>
                                            @endif
                                            
                                            <div class="text-[10px] text-gray-500 dark:text-gray-400 font-semibold flex items-center gap-1.5">
                                                <span>{{ $avgFuel !== null ? number_format($avgFuel, 1, ',', '.') . ' L/100k' : '- L/100k' }}</span>
                                                <span>•</span>
                                                <span>{{ $co2Kg !== null ? number_format($co2Kg, 1, ',', '.') . ' kg' : '- kg' }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col gap-1 text-[10px] min-w-[120px]">
                                            @if(isset($trip['optidrive_indicator']) && $trip['optidrive_indicator'] > 0)
                                                <div class="flex items-center gap-1.5">
                                                    <span class="text-gray-500 dark:text-gray-400 font-semibold">OptiDrive:</span>
                                                    <span class="px-1.5 py-0.5 rounded bg-sky-50 text-sky-700 font-bold dark:bg-sky-950/20 dark:text-sky-400">
                                                        {{ number_format($trip['optidrive_indicator'] * 5.0, 1) }} / 5
                                                    </span>
                                                </div>
                                            @endif
                                            <div class="flex flex-wrap gap-1 mt-0.5">
                                                @if(isset($trip['speeding_indicator']) && $trip['speeding_indicator'] < 1)
                                                    <span class="px-1.5 py-0.5 rounded bg-red-50 text-red-700 font-bold dark:bg-red-950/20 dark:text-red-400" title="Exceso de velocidad registrado">Velocidad</span>
                                                @endif
                                                @if(isset($trip['idling_indicator']) && $trip['idling_indicator'] < 1)
                                                    <span class="px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 font-bold dark:bg-amber-950/20 dark:text-amber-400" title="Ralentí excesivo registrado">Ralentí</span>
                                                @endif
                                                @if(isset($trip['drivingevents_indicator']) && $trip['drivingevents_indicator'] > 0.05)
                                                    <span class="px-1.5 py-0.5 rounded bg-red-50 text-red-700 font-bold dark:bg-red-950/20 dark:text-red-400" title="Maniobras bruscas detectadas">Maniobras</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if(isset($trip['start_latitude']) && isset($trip['end_latitude']))
                                            <button type="button"
                                                onclick="focusTrip({{ $index }})"
                                                class="px-2 py-1 bg-sky-50 dark:bg-sky-950/20 text-sky-600 dark:text-sky-400 hover:bg-sky-100 rounded text-[10px] font-bold border border-sky-200/50">
                                                Ver Mapa
                                            </button>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @else
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-8 text-center text-gray-500 dark:text-gray-400">
                No se registraron viajes para los filtros seleccionados en este día.
            </div>
        @endif
    </div>

    <!-- Script del Mapa de Viajes -->
    @if(is_array($result['data'] ?? null) && count($result['data']) > 0)
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const trips = @json($result['data']);
                
                // Centrar en el primer viaje
                let mapCenter = [-40.3628, -72.9450]; // Rio Bueno
                const validTrips = trips.filter(t => t.start_latitude && t.end_latitude);
                if (validTrips.length > 0) {
                    mapCenter = [validTrips[0].start_latitude / 1000000, validTrips[0].start_longitude / 1000000];
                }

                // Inicializar mapa Leaflet
                const map = L.map('trips-map').setView(mapCenter, 12);
                
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);

                const polyGroup = L.featureGroup();
                const paths = {};

                validTrips.forEach((t, idx) => {
                    const startLat = t.start_latitude / 1000000;
                    const startLng = t.start_longitude / 1000000;
                    const endLat = t.end_latitude / 1000000;
                    const endLng = t.end_longitude / 1000000;
                    
                    // Crear marcadores para partida (Verde) y llegada (Rojo)
                    const startMarker = L.circleMarker([startLat, startLng], {
                        radius: 6,
                        fillColor: "#10B981", // Verde
                        color: "#ffffff",
                        weight: 2,
                        fillOpacity: 1
                    }).addTo(map);

                    const endMarker = L.circleMarker([endLat, endLng], {
                        radius: 6,
                        fillColor: "#EF4444", // Rojo
                        color: "#ffffff",
                        weight: 2,
                        fillOpacity: 1
                    }).addTo(map);

                    // Polyline conectando partida y llegada
                    // Para representar viajes de diferente indice, cambiamos el color levemente
                    const colors = ['#2563EB', '#7C3AED', '#DB2777', '#059669', '#D97706'];
                    const pathColor = colors[idx % colors.length];

                    const polyline = L.polyline([[startLat, startLng], [endLat, endLng]], {
                        color: pathColor,
                        weight: 4,
                        opacity: 0.8,
                        dashArray: '5, 10'
                    }).addTo(map);

                    // Informacion de viaje
                    const startTime = new Date(t.start_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    const endTime = new Date(t.end_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    const distKm = ((t.distance ?? 0) / 1000).toFixed(2);
                    
                    const popupContent = `
                        <div class="text-xs p-1 space-y-1">
                            <div class="font-bold text-gray-900 border-b pb-1">${t.objectname}</div>
                            <div><b>Horario:</b> ${startTime} - ${endTime}</div>
                            <div><b>Distancia:</b> ${distKm} km</div>
                            <div><b>Origen:</b> ${t.start_postext_short ?? t.start_postext}</div>
                            <div><b>Destino:</b> ${t.end_postext_short ?? t.end_postext}</div>
                        </div>
                    `;

                    polyline.bindPopup(popupContent);
                    startMarker.bindPopup(popupContent);
                    endMarker.bindPopup(popupContent);

                    polyGroup.addLayer(polyline);
                    polyGroup.addLayer(startMarker);
                    polyGroup.addLayer(endMarker);

                    paths[idx] = {
                        polyline: polyline,
                        bounds: L.latLngBounds([[startLat, startLng], [endLat, endLng]])
                    };
                });

                if (validTrips.length > 0) {
                    map.fitBounds(polyGroup.getBounds().pad(0.1));
                }

                // Funcion para enfocar un viaje desde la tabla
                window.focusTrip = function(index) {
                    if (paths[index]) {
                        map.fitBounds(paths[index].bounds.pad(0.2));
                        paths[index].polyline.openPopup(paths[index].bounds.getCenter());
                    }
                };
            });
        </script>
    @endif
</x-app-layout>
