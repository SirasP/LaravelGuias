<x-app-layout>
    <!-- Leaflet CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <style>
        #events-map {
            height: 400px;
            width: 100%;
            border-radius: 0.5rem;
            z-index: 10;
        }
        /* Custom markers styling */
        .event-pin {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            color: white;
            font-weight: bold;
            font-size: 11px;
            border: 2px solid white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
        }
        .evt-green { background-color: #10B981; }  /* Entrada a Geocerca */
        .evt-orange { background-color: #F59E0B; } /* Salida de Geocerca */
        .evt-red { background-color: #EF4444; }    /* Alerta/Alarma */
    </style>

    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between w-full gap-3">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 tracking-tight">Historial de Eventos</h2>
                <x-breadcrumbs :items="[
                    ['label' => 'Integraciones'],
                    ['label' => 'Webfleet', 'route' => 'webfleet.index'],
                    ['label' => 'Eventos'],
                ]" />
            </div>

            <!-- Navegacion de Vistas -->
            <div class="flex items-center gap-2">
                <a href="{{ route('webfleet.index') }}"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 text-xs font-bold rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    Dashboard
                </a>
                <a href="{{ route('webfleet.trips') }}"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 text-xs font-bold rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    Viajes
                </a>
                <a href="{{ route('webfleet.events') }}"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 text-xs font-bold rounded-lg bg-sky-600 text-white shadow-sm">
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
            <form method="GET" action="{{ route('webfleet.events') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <!-- Selector de Fecha -->
                <div class="space-y-1">
                    <label for="fecha" class="text-xs font-bold text-gray-700 dark:text-gray-300">Fecha del Reporte</label>
                    <input type="date" id="fecha" name="fecha" value="{{ $selectedDate }}"
                        class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-sky-500 focus:border-sky-500" />
                </div>

                <!-- Selector de Vehiculo -->
                <div class="space-y-1">
                    <label for="object_no" class="text-xs font-bold text-gray-700 dark:text-gray-300">Filtrar por Vehículo</label>
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
                        Consultar Eventos
                    </button>
                </div>
            </form>
        </section>

        @if(($result['ok'] ?? null) === false)
            <div class="bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/50 p-4 rounded-lg">
                <h3 class="text-sm font-bold text-red-800 dark:text-red-300">Error al consultar los eventos</h3>
                <p class="mt-1 text-xs text-red-700 dark:text-red-400">{{ $result['error'] }}</p>
            </div>
        @endif

        @if(is_array($result['data'] ?? null) && count($result['data']) > 0)
            <!-- Seccion Principal: Mapa e Historial -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <!-- Mapa de Eventos (2/3) -->
                <div class="xl:col-span-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm space-y-3">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Ubicación de Alertas y Eventos</h3>
                    <div id="events-map"></div>
                </div>

                <!-- Timeline de Eventos (1/3) -->
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm flex flex-col h-[460px]">
                    <div class="pb-3 border-b border-gray-100 dark:border-gray-800">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Registro Cronológico</h3>
                    </div>

                    <div class="flex-1 overflow-y-auto pr-1 mt-3 space-y-4 text-xs" id="events-list">
                        @foreach($result['data'] as $index => $event)
                            @php
                                $msg = $event['msgtext'] ?? '';
                                $isEntry = str_contains(strtolower($msg), 'entrando') || str_contains(strtolower($msg), 'entering');
                                $isExit = str_contains(strtolower($msg), 'saliendo') || str_contains(strtolower($msg), 'leaving');
                                
                                $colorClass = 'border-gray-200 dark:border-gray-700';
                                $badgeClass = 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
                                
                                if ($isEntry) {
                                    $colorClass = 'border-emerald-500';
                                    $badgeClass = 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400';
                                } elseif ($isExit) {
                                    $colorClass = 'border-amber-500';
                                    $badgeClass = 'bg-amber-50 text-amber-700 dark:bg-amber-950/20 dark:text-amber-400';
                                } elseif (($event['eventlevel'] ?? '') === 'A') {
                                    $colorClass = 'border-red-500';
                                    $badgeClass = 'bg-red-50 text-red-700 dark:bg-red-950/20 dark:text-red-400';
                                }
                            @endphp
                            
                            <div class="relative pl-6 border-l-2 {{ $colorClass }}">
                                <!-- Dot indicador en la linea del timeline -->
                                <span class="absolute -left-[5px] top-1.5 w-2 h-2 rounded-full @if($isEntry) bg-emerald-500 @elseif($isExit) bg-amber-500 @else bg-red-500 @endif"></span>
                                
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-bold text-gray-900 dark:text-gray-100">{{ $event['objectname'] }}</span>
                                        <span class="font-semibold text-gray-400 dark:text-gray-500 text-[10px]">
                                            {{ \Carbon\Carbon::parse($event['eventtime'])->format('H:i') }} hrs
                                        </span>
                                    </div>
                                    <p class="text-gray-700 dark:text-gray-300 font-semibold">{{ $msg }}</p>
                                    <div class="text-[10px] text-gray-400 dark:text-gray-500 flex items-center justify-between gap-3 pt-0.5">
                                        <span class="truncate max-w-[150px]">{{ $event['postext'] }}</span>
                                        @if(isset($event['latitude_mdeg']) && isset($event['longitude_mdeg']))
                                            <button type="button"
                                                onclick="focusEvent({{ $event['latitude_mdeg'] }}, {{ $event['longitude_mdeg'] }}, {{ $index }})"
                                                class="text-sky-600 dark:text-sky-400 font-bold hover:underline">
                                                Ver Mapa
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-8 text-center text-gray-500 dark:text-gray-400">
                No se registraron eventos o alertas para los filtros seleccionados en este día.
            </div>
        @endif
    </div>

    <!-- Script del Mapa de Eventos -->
    @if(is_array($result['data'] ?? null) && count($result['data']) > 0)
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const events = @json($result['data']);
                
                // Filtrar eventos que tengan coordenadas válidas
                const validEvents = events.filter(e => e.latitude_mdeg && e.longitude_mdeg);
                
                let mapCenter = [-40.3628, -72.9450]; // Rio Bueno por defecto
                if (validEvents.length > 0) {
                    mapCenter = [validEvents[0].latitude_mdeg / 1000000, validEvents[0].longitude_mdeg / 1000000];
                }

                // Inicializar mapa Leaflet
                const map = L.map('events-map').setView(mapCenter, 12);
                
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);

                const markers = {};
                const markerGroup = L.featureGroup();

                validEvents.forEach((e, idx) => {
                    const lat = e.latitude_mdeg / 1000000;
                    const lng = e.longitude_mdeg / 1000000;
                    
                    const msg = e.msgtext || '';
                    const isEntry = msg.toLowerCase().includes('entrando') || msg.toLowerCase().includes('entering');
                    const isExit = msg.toLowerCase().includes('saliendo') || msg.toLowerCase().includes('leaving');

                    let pinClass = 'evt-green';
                    if (isExit) {
                        pinClass = 'evt-orange';
                    } else if (!isEntry && e.eventlevel === 'A') {
                        pinClass = 'evt-red';
                    }

                    // Marcador de punto personalizado
                    const icon = L.divIcon({
                        className: '',
                        html: `<div class="event-pin ${pinClass}">${e.objectno}</div>`,
                        iconSize: [32, 32],
                        iconAnchor: [16, 16]
                    });

                    const marker = L.marker([lat, lng], { icon: icon }).addTo(map);

                    const timeStr = new Date(e.eventtime).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    const popupContent = `
                        <div class="text-xs p-1 space-y-1.5">
                            <div class="font-bold text-gray-900 border-b pb-1 flex items-center justify-between gap-4">
                                <span>${e.objectname}</span>
                                <span class="text-gray-400 font-normal">${timeStr} hrs</span>
                            </div>
                            <div class="font-semibold text-gray-800">${msg}</div>
                            <div class="text-[10px] text-gray-400">
                                Dirección: ${e.postext}<br>
                                Alarma: ${e.alarmlevel ?? 'N/A'} (Nivel ${e.eventlevel ?? 'A'})
                            </div>
                        </div>
                    `;

                    marker.bindPopup(popupContent);
                    markers[idx] = marker;
                    markerGroup.addLayer(marker);
                });

                if (validEvents.length > 0) {
                    map.fitBounds(markerGroup.getBounds().pad(0.1));
                }

                // Centrar evento desde la lista lateral
                window.focusEvent = function (latMdeg, lngMdeg, idx) {
                    if (latMdeg && lngMdeg) {
                        const lat = latMdeg / 1000000;
                        const lng = lngMdeg / 1000000;
                        map.setView([lat, lng], 15);
                        if (markers[idx]) {
                            markers[idx].openPopup();
                        }
                    }
                };
            });
        </script>
    @endif
</x-app-layout>
