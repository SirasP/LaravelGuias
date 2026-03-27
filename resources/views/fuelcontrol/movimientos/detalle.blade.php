<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-3">
                <a href="{{ route('fuelcontrol.vehiculos.index') }}" class="w-8 h-8 rounded-xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center hover:bg-gray-200 dark:hover:bg-gray-700 transition">
                    <svg class="w-4 h-4 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Detalle de Máquina</h2>
                    <p class="text-[11px] text-gray-400 mt-0.5">{{ $movimiento->vehiculo_descripcion ?? 'Maquinaria' }} · {{ $movimiento->patente }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="px-2 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 text-[10px] font-bold text-indigo-600 dark:text-indigo-400 uppercase">
                    {{ $movimiento->vehiculo_tipo ?? 'N/A' }}
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            
            {{-- Resumen y Gráfico --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                {{-- Card Informativa --}}
                <div class="lg:col-span-1 space-y-4">
                    <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700/50">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-12 h-12 rounded-2xl bg-orange-600 flex items-center justify-center shrink-0 shadow-lg shadow-orange-200 dark:shadow-orange-900/20">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Última Carga</p>
                                <p class="text-lg font-black text-gray-900 dark:text-gray-100">{{ number_format(abs($movimiento->cantidad), 2, ',', '.') }} L</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-3 rounded-2xl bg-gray-50 dark:bg-gray-900/50">
                                <span class="text-xs text-gray-500 font-medium">Odómetro Bomba</span>
                                <span class="text-sm font-bold text-gray-800 dark:text-gray-200 tabular-nums">{{ number_format($movimiento->odometro_bomba ?? 0, 0, ',', '.') }} <small class="text-gray-400 font-normal">km</small></span>
                            </div>
                            <div class="flex items-center justify-between p-3 rounded-2xl bg-gray-50 dark:bg-gray-900/50">
                                <span class="text-xs text-gray-500 font-medium">Odómetro Vehículo</span>
                                <span class="text-sm font-bold text-gray-800 dark:text-gray-200 tabular-nums">
                                    {{ $movimiento->odometro ? number_format($movimiento->odometro, 0, ',', '.') . ' km' : '—' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between p-3 rounded-2xl bg-indigo-50 dark:bg-indigo-900/20">
                                <span class="text-xs text-indigo-600 dark:text-indigo-400 font-bold">Combustible</span>
                                <span class="text-xs font-black text-indigo-700 dark:text-indigo-300 uppercase">{{ $movimiento->producto_nombre }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Stats Rápidas --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-white dark:bg-gray-800 rounded-3xl p-4 shadow-sm border border-gray-100 dark:border-gray-700/50 text-center flex flex-col justify-center">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Frecuencia</p>
                            @if($avgDays)
                                <p class="text-lg font-black text-indigo-600 dark:text-indigo-400">cada {{ round($avgDays, 1) }} <small class="text-[10px] font-bold">días</small></p>
                            @else
                                <p class="text-lg font-black text-gray-400 italic text-sm">N/A</p>
                            @endif
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-3xl p-4 shadow-sm border border-gray-100 dark:border-gray-700/50 text-center flex flex-col justify-center">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Cargas</p>
                            <p class="text-xl font-black text-gray-900 dark:text-gray-100">{{ $historialTable->count() }}</p>
                        </div>
                    </div>
                </div>

                {{-- Gráfico de Rendimiento --}}
                <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700/50">
                        <div class="flex items-center justify-between mb-6">
                        <div>
                                <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase tracking-tight">{{ $etiquetaRendimiento }} Histórico</h3>
                                <p class="text-[11px] text-gray-400 mt-0.5">{{ $esMaquinaria ? 'Combustible consumido' : 'Rendimiento de combustible' }} en {{ $unidad }} por cada carga</p>
                        </div>
                        <div class="flex items-center gap-2">
                             <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                <span class="text-[10px] font-bold text-gray-400 uppercase">{{ $unidad }}</span>
                             </div>
                        </div>
                    </div>
                    <div class="h-64 relative">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>
            </div>

            {{-- Historial Table --}}
            <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-700/50 overflow-hidden">
                <div class="px-6 py-4 border-bottom border-gray-50 dark:border-gray-700/50 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase tracking-tight">Historial de Cargas</h3>
                    <span class="text-[11px] text-gray-400">Ordenado por fecha descendiente</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-700">
                                <th class="px-6 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Fecha / Hora</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Combustible</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-wider text-right">Carga</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-wider text-right">Odo / Horas</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-wider text-right">Recorrido</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-wider text-right">Frecuencia</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-wider text-right">{{ $etiquetaRendimiento }} ({{ $unidad }})</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                            @foreach($historialTable as $row)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/40 transition-colors">
                                    <td class="px-6 py-4 text-xs text-gray-600 dark:text-gray-400 tabular-nums">
                                        {{ \Carbon\Carbon::parse($row['fecha'])->format('d-m-Y H:i') }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-0.5 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 text-[10px] font-bold text-indigo-600 dark:text-indigo-400 uppercase">
                                            {{ $row['producto'] }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right tabular-nums text-xs font-bold text-gray-900 dark:text-gray-100">
                                        {{ number_format(abs($row['cantidad']), 2) }} L
                                    </td>
                                    <td class="px-6 py-4 text-right tabular-nums text-xs font-bold text-gray-900 dark:text-gray-100">
                                        {{ number_format($row['odo_usado'], 1) }}
                                    </td>
                                    <td class="px-6 py-4 text-right tabular-nums text-xs text-gray-500">
                                        {{ $row['dif'] ? number_format($row['dif'], 1) . ' ' . ($unidad == 'L/h' ? 'h' : 'km') : '—' }}
                                    </td>
                                    <td class="px-6 py-4 text-right text-xs text-gray-500 italic">
                                        {{ $row['frecuencia'] ?? 'Primera' }}
                                    </td>
                                    <td class="px-6 py-4 text-right tabular-nums">
                                        @if($row['rendimiento'])
                                            <span class="px-2.5 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 text-xs font-black text-emerald-600 dark:text-emerald-400">
                                                {{ number_format($row['rendimiento'], 2) }}
                                                <span class="text-[10px] font-bold ml-0.5">{{ $unidad }}</span>
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-300 italic">No disponible</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const ctx = document.getElementById('performanceChart');
            if(!ctx) return;

            const isDark = document.documentElement.classList.contains('dark');
            const gridColor = isDark ? 'rgba(255,255,255,.05)' : 'rgba(0,0,0,.05)';
            const tickColor = isDark ? '#475569' : '#94a3b8';

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: @json($labels),
                    datasets: [
                        {
                            label: '{{ $unidad }}',
                            data: @json($dataRendimiento),
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16,185,129,.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 4,
                            pointBackgroundColor: '#10b981',
                            pointBorderColor: isDark ? '#1e293b' : '#fff',
                            pointBorderWidth: 2,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: isDark ? '#1e293b' : '#fff',
                            titleColor: isDark ? '#f1f5f9' : '#1e293b',
                            bodyColor: tickColor,
                            borderColor: isDark ? '#334155' : '#e2e8f0',
                            borderWidth: 1,
                            padding: 12,
                            cornerRadius: 12,
                            displayColors: false,
                            callbacks: {
                                label: (ctx) => ` Rendimiento: ${ctx.parsed.y.toLocaleString('es-CL', {minimumFractionDigits: 2})} {{ $unidad }}`,
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: tickColor, font: { size: 10 } }
                        },
                        y: {
                            grid: { color: gridColor },
                            ticks: { 
                                color: tickColor, 
                                font: { size: 10 },
                                callback: (v) => v + ' {{ $unidad }}'
                            },
                            border: { display: false }
                        }
                    }
                }
            });
        });
    </script>
</x-app-layout>
