<x-app-layout>
    <!-- Leaflet CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <style>
        #map {
            height: 500px;
            width: 100%;
            border-radius: 0.5rem;
            z-index: 10;
        }
        /* Custom markers styling */
        .custom-pin {
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
        .pin-green { background-color: #10B981; }  /* En movimiento */
        .pin-orange { background-color: #F59E0B; } /* Contacto ON, detenido */
        .pin-red { background-color: #EF4444; }    /* Contacto OFF */
        .pin-gray { background-color: #6B7280; }   /* Desconectado/Antiguo */
    </style>

    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between w-full gap-3">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 tracking-tight">Monitoreo de Flota</h2>
                <x-breadcrumbs :items="[
                    ['label' => 'Integraciones'],
                    ['label' => 'Webfleet', 'route' => 'webfleet.index'],
                    ['label' => 'Dashboard'],
                ]" />
            </div>

            <!-- Navegacion de Vistas -->
            <div class="flex items-center gap-2">
                <a href="{{ route('webfleet.index') }}"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 text-xs font-bold rounded-lg bg-sky-600 text-white shadow-sm">
                    Dashboard
                </a>
                <a href="{{ route('webfleet.trips') }}"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 text-xs font-bold rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
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
        <!-- Estado de configuracion y alertas -->
        @if(!$configured)
            <div class="bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/50 p-4 rounded-lg flex flex-col gap-2">
                <div class="flex items-center gap-2 text-amber-800 dark:text-amber-300 font-semibold text-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    Faltan credenciales en el archivo .env
                </div>
                <div class="text-xs text-amber-700 dark:text-amber-400">
                    Faltan las siguientes variables de entorno para establecer conexion:
                    <div class="flex flex-wrap gap-1 mt-2">
                        @foreach($missingConfig as $key)
                            <code class="px-2 py-1 rounded bg-amber-100 dark:bg-amber-900/40 font-mono text-[10px]">{{ $key }}</code>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        @if(($result['ok'] ?? null) === false)
            <div class="bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/50 p-4 rounded-lg">
                <h3 class="text-sm font-bold text-red-800 dark:text-red-300">Error al conectar con la API de Webfleet</h3>
                <p class="mt-1 text-xs text-red-700 dark:text-red-400">{{ $result['error'] }}</p>
            </div>
        @endif

        <!-- Tarjetas de Estadisticas -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-4 rounded-xl shadow-sm flex items-center gap-4">
                <div class="p-3 bg-sky-50 dark:bg-sky-950/30 text-sky-600 dark:text-sky-400 rounded-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </div>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400 block font-semibold uppercase">Total Equipos</span>
                    <span id="stat-total" class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['total'] }}</span>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-4 rounded-xl shadow-sm flex items-center gap-4">
                <div class="p-3 bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400 rounded-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400 block font-semibold uppercase">En Movimiento</span>
                    <span id="stat-moving" class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['moving'] }}</span>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-4 rounded-xl shadow-sm flex items-center gap-4">
                <div class="p-3 bg-amber-50 dark:bg-amber-950/30 text-amber-600 dark:text-amber-400 rounded-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400 block font-semibold uppercase">Contacto ON</span>
                    <span id="stat-ignition-on" class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['ignition_on'] }}</span>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-4 rounded-xl shadow-sm flex items-center gap-4">
                <div class="p-3 bg-purple-50 dark:bg-purple-950/30 text-purple-600 dark:text-purple-400 rounded-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400 block font-semibold uppercase">Activos Hoy</span>
                    <span id="stat-active-today" class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $stats['active_today'] }}</span>
                </div>
            </div>
        </div>

        <!-- Seccion Principal: Mapa e Lista -->
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <!-- Mapa (2/3 de ancho en pantallas grandes) -->
            <div class="xl:col-span-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Ubicaciones en Tiempo Real</h3>
                    <span class="text-xs text-gray-400 dark:text-gray-500 flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Actualizado en vivo
                    </span>
                </div>
                <div id="map"></div>
            </div>

            <!-- Listado Lateral (1/3 de ancho) -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm flex flex-col h-[560px]">
                <div class="pb-3 border-b border-gray-100 dark:border-gray-800 space-y-2">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Listado de Equipos</h3>
                    <!-- Buscador -->
                    <input type="text" id="search-input" placeholder="Buscar tractor, patente, etc..."
                        class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-sky-500 focus:border-sky-500" />
                </div>

                <!-- Lista Scrolleable -->
                <div class="flex-1 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800/80 pr-1 mt-2 text-xs" id="vehicles-list">
                    @forelse($result['data'] ?? [] as $vehicle)
                        @php
                            $speed = $vehicle['speed'] ?? 0;
                            $ignition = $vehicle['ignition'] ?? 0;
                            $isToday = false;
                            if (isset($vehicle['pos_time'])) {
                                $isToday = \Carbon\Carbon::parse($vehicle['pos_time'])->isToday();
                            }
                            
                            $statusColor = 'text-gray-500';
                            $statusBg = 'bg-gray-100';
                            $statusText = 'Desconectado';
                            
                            if ($isToday) {
                                if ($speed > 0) {
                                    $statusColor = 'text-emerald-700 dark:text-emerald-400';
                                    $statusBg = 'bg-emerald-50 dark:bg-emerald-950/20';
                                    $statusText = $speed . ' km/h';
                                } elseif ($ignition === 1) {
                                    $statusColor = 'text-amber-700 dark:text-amber-400';
                                    $statusBg = 'bg-amber-50 dark:bg-amber-950/20';
                                    $statusText = 'Contacto ON';
                                } else {
                                    $statusColor = 'text-red-700 dark:text-red-400';
                                    $statusBg = 'bg-red-50 dark:bg-red-950/20';
                                    $statusText = 'Contacto OFF';
                                }
                            }
                        @endphp
                        <button type="button" 
                            onclick="focusVehicle({{ $vehicle['latitude_mdeg'] ?? 0 }}, {{ $vehicle['longitude_mdeg'] ?? 0 }}, '{{ $vehicle['objectno'] }}')"
                            class="w-full text-left p-3 hover:bg-gray-50 dark:hover:bg-gray-850 flex items-center justify-between rounded-lg transition border border-transparent hover:border-gray-100 dark:hover:border-gray-800 vehicle-item"
                            data-name="{{ strtolower($vehicle['objectname']) }}"
                            data-no="{{ $vehicle['objectno'] }}">
                            <div class="space-y-1">
                                <div class="font-bold text-gray-900 dark:text-gray-100 flex items-center gap-1.5 vehicle-name-text">
                                    <span class="w-1.5 h-1.5 rounded-full vehicle-dot @if($speed > 0) bg-emerald-500 @elseif($ignition === 1) bg-amber-500 @else bg-red-500 @endif"></span>
                                    <span class="vehicle-name-label">{{ $vehicle['objectname'] }}</span>
                                </div>
                                <div class="text-[10px] text-gray-500 dark:text-gray-400 font-semibold flex items-center gap-2">
                                    <span>N° {{ $vehicle['objectno'] }}</span>
                                    <span>•</span>
                                    <span class="vehicle-pos-text">{{ $vehicle['postext_short'] ?? 'Sin ubicacion' }}</span>
                                </div>
                            </div>
                            <span class="px-2 py-1 rounded text-[10px] font-bold vehicle-status-badge {{ $statusColor }} {{ $statusBg }}">
                                {{ $statusText }}
                            </span>
                        </button>
                    @empty
                        <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                            No se encontraron vehículos.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Script del Mapa -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Inicializar datos de vehículos
            let vehicles = @json($result['data'] ?? []);
            
            // Filtrar vehículos que tengan coordenadas válidas
            const getValidVehicles = (list) => list.filter(v => v.latitude_mdeg && v.longitude_mdeg);
            let validVehicles = getValidVehicles(vehicles);
            
            // Centrar mapa por defecto en las coordenadas del primer vehiculo o en Rio Bueno por defecto
            let mapCenter = [-40.3628, -72.9450]; // Rio Bueno
            if (validVehicles.length > 0) {
                mapCenter = [validVehicles[0].latitude_mdeg / 1000000, validVehicles[0].longitude_mdeg / 1000000];
            }
            
            // Inicializar Leaflet Map
            const map = L.map('map').setView(mapCenter, 11);
            
            // Añadir capa de OpenStreetMap
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            // Guardar marcadores creados para hacer zoom en ellos posteriormente
            const markers = {};
            const markerGroup = L.featureGroup();

            // Función para generar popup HTML
            function getPopupContent(v) {
                const speed = v.speed ?? 0;
                const ignition = v.ignition ?? 0;
                return `
                    <div class="p-1 space-y-1.5 text-xs">
                        <div class="font-bold text-gray-900 border-b pb-1 flex items-center justify-between gap-4">
                            <span>${v.objectname}</span>
                            <span class="px-1.5 py-0.5 rounded text-[9px] text-white bg-slate-600">N° ${v.objectno}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-x-3 gap-y-1">
                            <span class="text-gray-500">Velocidad:</span>
                            <span class="font-semibold text-right">${speed} km/h</span>
                            <span class="text-gray-500">Motor:</span>
                            <span class="font-semibold text-right">${ignition === 1 ? 'ENCENDIDO' : 'APAGADO'}</span>
                            <span class="text-gray-500">Odómetro:</span>
                            <span class="font-semibold text-right">${v.odometer ? v.odometer.toLocaleString() : 0} km</span>
                        </div>
                        <div class="text-[10px] text-gray-400 pt-1 border-t">
                            Dirección: ${v.postext ?? 'No especificado'}<br>
                            Último reporte: ${v.pos_time ? new Date(v.pos_time).toLocaleString() : 'Nunca'}
                        </div>
                    </div>
                `;
            }

            // Dibujar marcadores para todos los vehículos válidos
            function renderMarkers(list) {
                list.forEach(v => {
                    const lat = v.latitude_mdeg / 1000000;
                    const lng = v.longitude_mdeg / 1000000;
                    
                    // Determinar color de pin
                    let pinClass = 'pin-gray';
                    const speed = v.speed ?? 0;
                    const ignition = v.ignition ?? 0;
                    
                    let isToday = false;
                    if (v.pos_time) {
                        const reportDate = new Date(v.pos_time);
                        const today = new Date();
                        isToday = reportDate.toDateString() === today.toDateString();
                    }

                    if (isToday) {
                        if (speed > 0) {
                            pinClass = 'pin-green';
                        } else if (ignition === 1) {
                            pinClass = 'pin-orange';
                        } else {
                            pinClass = 'pin-red';
                        }
                    }
                    
                    const icon = L.divIcon({
                        className: '',
                        html: `<div class="custom-pin ${pinClass}">${v.objectno}</div>`,
                        iconSize: [32, 32],
                        iconAnchor: [16, 16]
                    });
                    
                    if (markers[v.objectno]) {
                        // Actualizar marcador existente
                        markers[v.objectno].setLatLng([lat, lng]);
                        markers[v.objectno].setIcon(icon);
                        markers[v.objectno].setPopupContent(getPopupContent(v));
                    } else {
                        // Crear Marcador nuevo
                        const marker = L.marker([lat, lng], { icon: icon }).addTo(map);
                        marker.bindPopup(getPopupContent(v));
                        markers[v.objectno] = marker;
                        markerGroup.addLayer(marker);
                    }
                });
            }

            // Renderizar inicial
            renderMarkers(validVehicles);
            
            // Si hay marcadores, ajustar el zoom para encuadrarlos a todos en pantalla automáticamente
            if (validVehicles.length > 0) {
                map.fitBounds(markerGroup.getBounds().pad(0.1));
            }
            
            // Función para centrar y abrir popup de un vehiculo desde el panel lateral
            window.focusVehicle = function(latMdeg, lngMdeg, objectNo) {
                if (latMdeg && lngMdeg) {
                    const lat = latMdeg / 1000000;
                    const lng = lngMdeg / 1000000;
                    map.setView([lat, lng], 15);
                    if (markers[objectNo]) {
                        markers[objectNo].openPopup();
                    }
                } else {
                    alert('Este vehículo no tiene coordenadas de posición válidas reportadas.');
                }
            };
            
            // Filtro del Buscador
            const searchInput = document.getElementById('search-input');
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                const items = document.querySelectorAll('.vehicle-item');
                
                items.forEach(item => {
                    const name = item.dataset.name;
                    const no = item.dataset.no;
                    if (name.includes(query) || no.includes(query)) {
                        item.style.display = 'flex';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });

            // Función para refrescar datos desde Laravel (AJAX)
            function refreshData() {
                fetch('{{ route('webfleet.index') }}', {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.ok) {
                        // 1. Actualizar Tarjetas de Estadísticas
                        if (data.stats) {
                            document.getElementById('stat-total').textContent = data.stats.total ?? 0;
                            document.getElementById('stat-moving').textContent = data.stats.moving ?? 0;
                            document.getElementById('stat-ignition-on').textContent = data.stats.ignition_on ?? 0;
                            document.getElementById('stat-active-today').textContent = data.stats.active_today ?? 0;
                        }
                        
                        // 2. Actualizar Listado de Equipos Lateral
                        if (data.result && data.result.data) {
                            const newVehicles = data.result.data;
                            newVehicles.forEach(v => {
                                const button = document.querySelector(`.vehicle-item[data-no="${v.objectno}"]`);
                                if (button) {
                                    const speed = v.speed ?? 0;
                                    const ignition = v.ignition ?? 0;
                                    
                                    let isToday = false;
                                    if (v.pos_time) {
                                        const reportDate = new Date(v.pos_time);
                                        const today = new Date();
                                        isToday = reportDate.toDateString() === today.toDateString();
                                    }

                                    // Actualizar data-name por si cambió
                                    button.dataset.name = v.objectname.toLowerCase();

                                    // Actualizar punto de color
                                    const dot = button.querySelector('.vehicle-dot');
                                    if (dot) {
                                        dot.className = 'w-1.5 h-1.5 rounded-full vehicle-dot';
                                        if (speed > 0) {
                                            dot.classList.add('bg-emerald-500');
                                        } else if (ignition === 1) {
                                            dot.classList.add('bg-amber-500');
                                        } else {
                                            dot.classList.add('bg-red-500');
                                        }
                                    }

                                    // Actualizar nombre
                                    const nameLabel = button.querySelector('.vehicle-name-label');
                                    if (nameLabel) {
                                        nameLabel.textContent = v.objectname;
                                    }

                                    // Actualizar posición corta
                                    const posLabel = button.querySelector('.vehicle-pos-text');
                                    if (posLabel) {
                                        posLabel.textContent = v.postext_short ?? 'Sin ubicación';
                                    }

                                    // Actualizar placa de estado
                                    const badge = button.querySelector('.vehicle-status-badge');
                                    if (badge) {
                                        badge.className = 'px-2 py-1 rounded text-[10px] font-bold vehicle-status-badge';
                                        if (isToday) {
                                            if (speed > 0) {
                                                badge.classList.add('text-emerald-700', 'dark:text-emerald-400', 'bg-emerald-50', 'dark:bg-emerald-950/20');
                                                badge.textContent = `${speed} km/h`;
                                            } else if (ignition === 1) {
                                                badge.classList.add('text-amber-700', 'dark:text-amber-400', 'bg-amber-50', 'dark:bg-amber-950/20');
                                                badge.textContent = 'Contacto ON';
                                            } else {
                                                badge.classList.add('text-red-700', 'dark:text-red-400', 'bg-red-50', 'dark:bg-red-950/20');
                                                badge.textContent = 'Contacto OFF';
                                            }
                                        } else {
                                            badge.classList.add('text-gray-500', 'bg-gray-100');
                                            badge.textContent = 'Desconectado';
                                        }
                                    }

                                    // Actualizar onclick
                                    button.setAttribute('onclick', `focusVehicle(${v.latitude_mdeg ?? 0}, ${v.longitude_mdeg ?? 0}, '${v.objectno}')`);
                                }
                            });

                            // 3. Actualizar Marcadores en el Mapa
                            const newValidVehicles = getValidVehicles(newVehicles);
                            renderMarkers(newValidVehicles);
                        }
                    }
                })
                .catch(error => console.error('Error refreshing Webfleet data:', error));
            }

            // Programar refresco automático cada 20 segundos
            setInterval(refreshData, 20000);
        });
    </script>
</x-app-layout>
