<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between w-full gap-3">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 tracking-tight">Diagnóstico y Batería</h2>
                <x-breadcrumbs :items="[
                    ['label' => 'Integraciones'],
                    ['label' => 'Webfleet', 'route' => 'webfleet.index'],
                    ['label' => 'Diagnóstico'],
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
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 text-xs font-bold rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    Ralentí
                </a>
                <a href="{{ route('webfleet.diagnostics') }}"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 text-xs font-bold rounded-lg bg-sky-600 text-white shadow-sm">
                    Diagnóstico
                </a>
            </div>
        </div>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6">
        <!-- Formulario de Filtros -->
        <section class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm">
            <form method="GET" action="{{ route('webfleet.diagnostics') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <!-- Selector de Vehiculo -->
                <div class="space-y-1 md:col-span-3">
                    <label for="object_no" class="text-xs font-bold text-gray-700 dark:text-gray-300">Seleccionar Vehículo para Diagnóstico Detallado</label>
                    <select id="object_no" name="object_no"
                        class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-sky-500 focus:border-sky-500">
                        <option value="">-- Ver resumen global de flota --</option>
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
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                        Analizar Estado
                    </button>
                </div>
            </form>
        </section>

        @if(! empty($selectedObject))
            <!-- MODO INDIVIDUAL: Detalle del vehículo seleccionado -->
            @php
                $signals = collect($canSignals)->firstWhere('objectno', $selectedObject) ?? [];
                $malfunctions = collect($canMalfunctions)->filter(fn($m) => ($m['objectno'] ?? '') == $selectedObject);
                
                // Intentar recuperar voltaje de batería
                $volt = $signals['ext_power'] ?? $signals['voltage'] ?? null;
                // Si el voltaje viene multiplicado por 1000 en milivoltios
                if ($volt > 100) {
                    $volt = $volt / 1000;
                }
                
                $battStatus = 'Normal';
                $battColor = 'text-emerald-500';
                $battBg = 'bg-emerald-50 dark:bg-emerald-950/20';
                
                if ($volt !== null) {
                    if ($volt < 11.5 || ($volt > 20 && $volt < 23.0)) {
                        $battStatus = 'Bajo (Alerta)';
                        $battColor = 'text-red-500';
                        $battBg = 'bg-red-50 dark:bg-red-950/20';
                    } elseif ($volt < 12.0 || ($volt > 20 && $volt < 24.0)) {
                        $battStatus = 'Precaución';
                        $battColor = 'text-amber-500';
                        $battBg = 'bg-amber-50 dark:bg-amber-950/20';
                    }
                }
            @endphp

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Columna Izquierda: Batería y DTC -->
                <div class="space-y-6">
                    <!-- Tarjeta de Estado de Batería -->
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-5 shadow-sm space-y-4">
                        <div class="flex items-center justify-between border-b pb-3 border-gray-100 dark:border-gray-800">
                            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Batería del Vehículo</h3>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $battColor }} {{ $battBg }}">
                                {{ $battStatus }}
                            </span>
                        </div>

                        <div class="text-center py-4 space-y-2">
                            @if($volt !== null)
                                <div class="text-4xl font-extrabold tracking-tight {{ $volt < 12.0 || ($volt > 20 && $volt < 24.0) ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                    {{ number_format($volt, 1) }} V
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 font-semibold">Tensión de alimentación eléctrica CAN</p>
                                
                                <!-- Simulación de Barra de Voltaje -->
                                <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-2 mt-4 overflow-hidden">
                                    @php
                                        // Mapeo simple de porcentaje para barra de progreso
                                        $percent = 0;
                                        if ($volt > 9 && $volt < 16) {
                                            $percent = (($volt - 9) / 6) * 100;
                                        } elseif ($volt >= 16 && $volt < 30) {
                                            $percent = (($volt - 18) / 10) * 100;
                                        }
                                        $percent = min(100, max(0, $percent));
                                    @endphp
                                    <div class="h-full rounded-full @if($volt < 12.0 || ($volt > 20 && $volt < 24.0)) bg-amber-500 @else bg-emerald-500 @endif" style="width: {{ $percent }}%"></div>
                                </div>
                            @else
                                <div class="text-3xl font-extrabold text-gray-400 dark:text-gray-600">
                                    -- V
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 font-semibold">No se reporta tensión de batería actualmente</p>
                            @endif
                        </div>
                    </div>

                    <!-- Códigos de Falla CAN (DTC) -->
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-5 shadow-sm space-y-3">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 border-b pb-3 border-gray-100 dark:border-gray-800">
                            Códigos de Falla Activos (DTC)
                        </h3>

                        @forelse($malfunctions as $malf)
                            <div class="p-3 bg-red-50 dark:bg-red-950/20 border border-red-200/50 dark:border-red-900/50 rounded-lg space-y-1">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-red-800 dark:text-red-300">DTC: {{ $malf['dtc_code'] ?? 'Desconocido' }}</span>
                                    <span class="px-1.5 py-0.5 rounded bg-red-100 text-red-700 font-mono text-[9px] dark:bg-red-900/40 dark:text-red-400">Activo</span>
                                </div>
                                <p class="text-[11px] text-red-700 dark:text-red-400 font-medium">
                                    {{ $malf['dtc_description'] ?? 'Mal funcionamiento reportado en bus de datos.' }}
                                </p>
                            </div>
                        @empty
                            <div class="text-center py-6 space-y-2">
                                <div class="mx-auto flex items-center justify-center h-10 w-10 rounded-full bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <p class="text-xs text-gray-900 dark:text-gray-100 font-bold">Sin fallas activas en la ECU</p>
                                <p class="text-[10px] text-gray-500 dark:text-gray-400">El bus CAN no reporta Códigos de Diagnóstico (DTC) vigentes.</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- Columna Central: Métricas CAN -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Dashboard de Telemetría CAN Real-Time -->
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-5 shadow-sm space-y-4">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 border-b pb-3 border-gray-100 dark:border-gray-800">
                            Indicadores CAN Bus Recientes
                        </h3>

                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            <!-- Nivel Combustible -->
                            <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                                <span class="text-[10px] text-gray-500 dark:text-gray-400 block font-semibold">Nivel Combustible</span>
                                <span class="text-lg font-bold text-gray-900 dark:text-gray-100 mt-1 block">
                                    {{ isset($signals['fuel_level']) ? number_format($signals['fuel_level'], 0) . ' %' : '--' }}
                                </span>
                            </div>

                            <!-- Temp. Refrigerante -->
                            <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                                <span class="text-[10px] text-gray-500 dark:text-gray-400 block font-semibold">Temp. Refrigerante</span>
                                <span class="text-lg font-bold text-gray-900 dark:text-gray-100 mt-1 block">
                                    {{ isset($signals['coolant_temp']) ? number_format($signals['coolant_temp'], 0) . ' °C' : '--' }}
                                </span>
                            </div>

                            <!-- RPM Motor -->
                            <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                                <span class="text-[10px] text-gray-500 dark:text-gray-400 block font-semibold">RPM Motor</span>
                                <span class="text-lg font-bold text-gray-900 dark:text-gray-100 mt-1 block">
                                    {{ isset($signals['engine_speed']) ? number_format($signals['engine_speed'], 0) . ' RPM' : '--' }}
                                </span>
                            </div>

                            <!-- Nivel AdBlue -->
                            <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                                <span class="text-[10px] text-gray-500 dark:text-gray-400 block font-semibold">Nivel AdBlue</span>
                                <span class="text-lg font-bold text-gray-900 dark:text-gray-100 mt-1 block">
                                    {{ isset($signals['adblue_level']) ? number_format($signals['adblue_level'], 0) . ' %' : '--' }}
                                </span>
                            </div>

                            <!-- Odómetro CAN -->
                            <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                                <span class="text-[10px] text-gray-500 dark:text-gray-400 block font-semibold">Odómetro CAN</span>
                                <span class="text-lg font-bold text-gray-900 dark:text-gray-100 mt-1 block">
                                    {{ isset($signals['odometer']) ? number_format($sig['odometer'], 0, ',', '.') . ' km' : (isset($signals['odometer']) ? number_format($signals['odometer'], 0, ',', '.') . ' km' : '--') }}
                                </span>
                            </div>

                            <!-- Horas Motor (Horómetro) -->
                            <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                                <span class="text-[10px] text-gray-500 dark:text-gray-400 block font-semibold">Horómetro Engine</span>
                                <span class="text-lg font-bold text-gray-900 dark:text-gray-100 mt-1 block">
                                    {{ isset($signals['engine_hours']) ? number_format($signals['engine_hours'], 1, ',', '.') . ' h' : '--' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Bitácora de Alertas de Diagnóstico / Timeline -->
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-5 shadow-sm space-y-4">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 border-b pb-3 border-gray-100 dark:border-gray-800">
                            Historial Reciente de Alertas Técnicas
                        </h3>

                        <div class="space-y-4 max-h-[300px] overflow-y-auto pr-1">
                            @php
                                // Filtrar eventos que pertenezcan al nivel de Alarma ("A") o contengan vocabulario técnico.
                                $diagEvents = collect($events)->filter(function ($e) {
                                    $msg = strtolower($e['msgtext'] ?? '');
                                    return ($e['eventlevel'] ?? '') === 'A' 
                                        || str_contains($msg, 'batería') 
                                        || str_contains($msg, 'voltaje') 
                                        || str_contains($msg, 'motor')
                                        || str_contains($msg, 'falla')
                                        || str_contains($msg, 'alerta')
                                        || str_contains($msg, 'temperatura')
                                        || str_contains($msg, 'mantenimiento')
                                        || str_contains($msg, 'dtc');
                                });
                            @endphp

                            @forelse($diagEvents as $evt)
                                <div class="relative pl-5 border-l-2 border-red-500 text-xs">
                                    <span class="absolute -left-[5px] top-1.5 w-2 h-2 rounded-full bg-red-500"></span>
                                    <div class="flex items-center justify-between font-bold text-gray-900 dark:text-gray-100">
                                        <span>Alerta de Sistema</span>
                                        <span class="text-gray-400 text-[10px] font-normal">
                                            {{ \Carbon\Carbon::parse($evt['eventtime'])->format('d/m H:i') }}
                                        </span>
                                    </div>
                                    <p class="text-gray-700 dark:text-gray-300 font-semibold mt-0.5">{{ $evt['msgtext'] }}</p>
                                    <span class="text-[10px] text-gray-400 block mt-0.5">{{ $evt['postext'] ?? 'Sin dirección' }}</span>
                                </div>
                            @empty
                                <div class="text-center py-8 text-gray-500 dark:text-gray-400 text-xs">
                                    No se reportaron alertas de diagnóstico en los últimos 15 días.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

        @else
            <!-- MODO GLOBAL: Tabla resumen de todos los vehículos -->
            <section class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-100 dark:border-gray-800">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Diagnóstico General de la Flota</h3>
                </div>

                <div class="overflow-x-auto text-xs">
                    <table class="min-w-full text-left">
                        <thead class="bg-gray-50 dark:bg-gray-800/70 text-gray-500 dark:text-gray-400 font-bold uppercase">
                            <tr>
                                <th class="px-6 py-3">Vehículo</th>
                                <th class="px-6 py-3">Voltaje Batería</th>
                                <th class="px-6 py-3">Fallas ECU (DTC)</th>
                                <th class="px-6 py-3">Odo / Horómetro CAN</th>
                                <th class="px-6 py-3">Último Reporte CAN</th>
                                <th class="px-6 py-3 text-center">Detalle</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800/80">
                            @foreach($objects as $obj)
                                @php
                                    $objNo = $obj['objectno'];
                                    
                                    // Buscar señal CAN de este vehículo
                                    $sig = collect($canSignals)->firstWhere('objectno', $objNo) ?? [];
                                    
                                    // Buscar fallas de este vehículo
                                    $hasMalf = collect($canMalfunctions)->contains(fn($m) => ($m['objectno'] ?? '') == $objNo);
                                    
                                    $volt = $sig['ext_power'] ?? $sig['voltage'] ?? null;
                                    if ($volt > 100) {
                                        $volt = $volt / 1000;
                                    }
                                    
                                    $voltColor = 'text-gray-500 bg-gray-100 dark:bg-gray-800 dark:text-gray-400';
                                    $voltLabel = '-- V';
                                    
                                    if ($volt !== null) {
                                        $voltLabel = number_format($volt, 1) . ' V';
                                        if ($volt < 12.0 || ($volt > 20 && $volt < 24.0)) {
                                            $voltColor = 'text-red-700 bg-red-50 dark:bg-red-950/20 dark:text-red-400 font-bold';
                                        } else {
                                            $voltColor = 'text-emerald-700 bg-emerald-50 dark:bg-emerald-950/20 dark:text-emerald-400';
                                        }
                                    }
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                    <td class="px-6 py-4 font-bold text-gray-900 dark:text-gray-100">
                                        {{ $obj['objectname'] }} <span class="text-gray-400 block font-normal text-[10px]">N° {{ $obj['objectno'] }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 rounded text-[10px] font-bold {{ $voltColor }}">
                                            {{ $voltLabel }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($hasMalf)
                                            <span class="px-2 py-1 rounded text-[10px] font-bold text-red-700 bg-red-50 dark:bg-red-950/20 dark:text-red-400">
                                                ⚠ Falla Activa
                                            </span>
                                        @else
                                            <span class="px-2 py-1 rounded text-[10px] font-semibold text-emerald-700 bg-emerald-50 dark:bg-emerald-950/20 dark:text-emerald-400">
                                                ✓ Sin Fallas
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400 font-medium">
                                        <div class="space-y-0.5">
                                            <div>{{ isset($sig['odometer']) ? number_format($sig['odometer'], 0, ',', '.') . ' km' : '--' }}</div>
                                            <div class="text-[10px] text-gray-400">{{ isset($sig['engine_hours']) ? number_format($sig['engine_hours'], 1, ',', '.') . ' h motor' : '--' }}</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400">
                                        {{ isset($sig['pos_time']) ? \Carbon\Carbon::parse($sig['pos_time'])->format('d/m/Y H:i') : '--' }}
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <a href="{{ route('webfleet.diagnostics', ['object_no' => $objNo]) }}"
                                            class="inline-flex items-center px-2 py-1 bg-sky-50 dark:bg-sky-950/20 text-sky-600 dark:text-sky-400 hover:bg-sky-100 rounded text-[10px] font-bold border border-sky-200/50">
                                            Analizar
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
