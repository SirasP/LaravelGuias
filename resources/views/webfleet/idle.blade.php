<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between w-full gap-3">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 tracking-tight">Reporte de Ralentí Innecesario</h2>
                <x-breadcrumbs :items="[
                    ['label' => 'Integraciones'],
                    ['label' => 'Webfleet', 'route' => 'webfleet.index'],
                    ['label' => 'Ralentí'],
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
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 text-xs font-bold rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    Eventos
                </a>
                <a href="{{ route('webfleet.idle') }}"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 text-xs font-bold rounded-lg bg-sky-600 text-white shadow-sm">
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
            <form method="GET" action="{{ route('webfleet.idle') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <!-- Selector de Fecha -->
                <div class="space-y-1">
                    <label for="fecha" class="text-xs font-bold text-gray-700 dark:text-gray-300">Fecha del Reporte</label>
                    <input type="date" id="fecha" name="fecha" value="{{ $selectedDate }}"
                        class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-sky-500 focus:border-sky-500" />
                </div>

                <!-- Selector de Vehiculo -->
                <div class="space-y-1">
                    <label for="object_no" class="text-xs font-bold text-gray-700 dark:text-gray-300">Vehículo (Obligatorio)</label>
                    <select id="object_no" name="object_no" required
                        class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-sky-500 focus:border-sky-500">
                        <option value="">-- Selecciona un vehículo --</option>
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
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        Ver Excepciones
                    </button>
                </div>
            </form>
        </section>

        @if(empty($selectedObject))
            <!-- Advertencia inicial de seleccion -->
            <div class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-8 text-center text-sm text-gray-500 dark:text-gray-400">
                <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-700 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Por favor, selecciona un vehículo en el filtro superior y haz clic en "Ver Excepciones" para cargar el reporte de ralentí de ese equipo.
            </div>
        @else
            @if(($result['ok'] ?? null) === false)
                <div class="bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/50 p-4 rounded-lg">
                    <h3 class="text-sm font-bold text-red-800 dark:text-red-300">Error al consultar el reporte de ralentí</h3>
                    <p class="mt-1 text-xs text-red-700 dark:text-red-400">{{ $result['error'] }}</p>
                </div>
            @endif

            @php
                $idleEvents = $result['data'] ?? [];
                $totalIdleTimeSeconds = 0;
                foreach($idleEvents as $evt) {
                    $totalIdleTimeSeconds += $evt['duration'] ?? 0;
                }
                
                // Consumo estimado en Ralentí (Tractor/Cosechadora Diésel consume aprox 3 litros por hora en ralentí)
                $litrosPerdidos = ($totalIdleTimeSeconds / 3600) * 3.0;
                $costoEstimadoCLP = $litrosPerdidos * 1000; // Asumiendo $1.000 CLP el litro
            @endphp

            <!-- Tarjetas de Estadisticas de Ineficiencia -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-4 rounded-xl shadow-sm">
                    <span class="text-xs text-gray-500 dark:text-gray-400 block font-semibold uppercase">Eventos de Ralentí</span>
                    <span class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-1 block">
                        {{ count($idleEvents) }}
                    </span>
                </div>

                <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-4 rounded-xl shadow-sm">
                    <span class="text-xs text-gray-500 dark:text-gray-400 block font-semibold uppercase">Tiempo Total Perdido</span>
                    <span class="text-xl font-bold text-amber-600 dark:text-amber-400 mt-1 block">
                        {{ floor($totalIdleTimeSeconds / 60) }} min
                    </span>
                </div>

                <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-4 rounded-xl shadow-sm">
                    <span class="text-xs text-gray-500 dark:text-gray-400 block font-semibold uppercase">Combustible Desperdiciado (Est.)</span>
                    <span class="text-xl font-bold text-red-600 dark:text-red-400 mt-1 block">
                        {{ number_format($litrosPerdidos, 1, ',', '.') }} Ltrs
                    </span>
                </div>

                <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-4 rounded-xl shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-xs text-gray-500 dark:text-gray-400 block font-semibold uppercase">Dinero Perdido (Est.)</span>
                        <span class="text-xl font-bold text-red-700 dark:text-red-400 mt-1 block">
                            ${{ number_format($costoEstimadoCLP, 0, ',', '.') }} CLP
                        </span>
                    </div>
                    <span class="text-[9px] text-gray-400 font-semibold uppercase bg-gray-100 dark:bg-gray-800 px-1.5 py-1 rounded">Basado en $1.000/L</span>
                </div>
            </div>

            <!-- Tabla de Excepciones -->
            <section class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-100 dark:border-gray-800">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Bitácora de Paradas con Motor Encendido</h3>
                </div>

                <div class="overflow-x-auto text-xs font-semibold">
                    <table class="min-w-full text-left">
                        <thead class="bg-gray-50 dark:bg-gray-800/70 text-gray-500 dark:text-gray-400 font-bold uppercase">
                            <tr>
                                <th class="px-4 py-3">Inicio</th>
                                <th class="px-4 py-3">Término</th>
                                <th class="px-4 py-3 text-red-500">Duración Ralentí</th>
                                <th class="px-4 py-3">Kilometraje</th>
                                <th class="px-4 py-3">Ubicación Parada</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800/80">
                            @forelse($idleEvents as $evt)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                    <td class="px-4 py-3">
                                        {{ \Carbon\Carbon::parse($evt['start_time'])->format('d/m/Y H:i') }} hrs
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ \Carbon\Carbon::parse($evt['end_time'])->format('d/m/Y H:i') }} hrs
                                    </td>
                                    <td class="px-4 py-3 text-red-600 dark:text-red-400 font-bold">
                                        {{ floor(($evt['duration'] ?? 0) / 60) }} min {{ ($evt['duration'] ?? 0) % 60 }} seg
                                    </td>
                                    <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                        {{ isset($evt['odometer']) ? number_format($evt['odometer'], 0, ',', '.') : '-' }} km
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                        {{ $evt['postext'] ?? 'Dirección no disponible' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-8 text-center text-gray-500 dark:text-gray-400">
                                        Excelente: No se registraron excesos de ralentí innecesario para este vehículo en este día.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
