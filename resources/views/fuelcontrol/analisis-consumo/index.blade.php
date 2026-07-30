<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4 w-full">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-orange-600 to-amber-500 flex items-center justify-center shrink-0 shadow-md shadow-orange-500/20">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Análisis de Consumo</h2>
                    <p class="text-xs text-gray-400 mt-0.5 hidden sm:block">Comparación mensual y tendencias de combustible</p>
                </div>
            </div>
            <div>
                <a href="{{ route('fuelcontrol.index') }}"
                   class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-white hover:bg-slate-50 text-slate-700 dark:bg-gray-800 dark:text-gray-200 text-xs font-semibold border border-slate-200 dark:border-gray-700 transition-all shadow-2xs">
                    &larr; Volver al Dashboard
                </a>
            </div>
        </div>
    </x-slot>

    @php
        $nombreActual = ucfirst($periodoActual->copy()->locale('es')->translatedFormat('F Y'));
        $nombreAnterior = ucfirst($periodoAnterior->copy()->locale('es')->translatedFormat('F Y'));
        $mesAnterior = $periodoActual->copy()->subMonthNoOverflow();
        $mesSiguiente = $periodoActual->copy()->addMonthNoOverflow();
        $puedeAvanzar = $mesSiguiente->lessThanOrEqualTo(now()->startOfMonth());
        $mesesDisponibles = collect(range(0, 17))->map(fn ($mesesAtras) => now()->startOfMonth()->subMonthsNoOverflow($mesesAtras));
        $mesesComparacion = $mesesDisponibles->reject(fn ($mesDisponible) => $mesDisponible->isSameMonth($periodoActual));
    @endphp

    <div class="max-w-9xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        <section class="bg-white dark:bg-gray-900 border border-slate-200/80 dark:border-gray-800 rounded-2xl shadow-xs p-5 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2.5 py-0.5 rounded-full bg-orange-50 dark:bg-orange-950/40 border border-orange-200 dark:border-orange-900 text-orange-700 dark:text-orange-400 text-xs font-extrabold uppercase tracking-wider">Período Analizado</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-gray-100">{{ $nombreActual }}</h1>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-gray-400 mt-1">
                    Comparado frente a <strong class="text-slate-800 dark:text-gray-200">{{ mb_strtolower($nombreAnterior) }}</strong>.
                </p>
            </div>

            <form method="GET" class="flex flex-wrap items-center gap-3 bg-slate-50/80 dark:bg-gray-800/80 p-2 rounded-2xl border border-slate-200/80 dark:border-gray-700">
                <a href="{{ route('fuelcontrol.analisis-consumo', ['mes' => $mesAnterior->format('Y-m'), 'comparar' => $periodoAnterior->format('Y-m')]) }}"
                   class="p-2.5 rounded-xl bg-white dark:bg-gray-900 text-slate-600 dark:text-gray-300 hover:text-orange-600 border border-slate-200/80 dark:border-gray-700 shadow-2xs hover:shadow-xs transition-all"
                   title="Mes anterior" aria-label="Mes anterior">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="m15 18-6-6 6-6" /></svg>
                </a>

                <div class="flex flex-col">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-gray-500 px-1 mb-0.5">Mes a analizar</span>
                    <select name="mes" onchange="this.form.submit()" class="px-3.5 py-1.5 rounded-xl bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-700 text-slate-900 dark:text-gray-100 text-xs font-bold focus:ring-2 focus:ring-orange-500/20 outline-none cursor-pointer">
                        @foreach($mesesDisponibles as $mesDisponible)
                            <option value="{{ $mesDisponible->format('Y-m') }}" @selected($mesDisponible->isSameMonth($periodoActual))>
                                {{ ucfirst($mesDisponible->copy()->locale('es')->translatedFormat('F Y')) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <span class="text-xs font-bold text-slate-400 dark:text-gray-500 px-1 self-end mb-2">vs.</span>

                <div class="flex flex-col">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-gray-500 px-1 mb-0.5">Mes comparación</span>
                    <select name="comparar" onchange="this.form.submit()" class="px-3.5 py-1.5 rounded-xl bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-700 text-slate-700 dark:text-gray-300 text-xs font-bold focus:ring-2 focus:ring-orange-500/20 outline-none cursor-pointer">
                        @foreach($mesesComparacion as $mesDisponible)
                            <option value="{{ $mesDisponible->format('Y-m') }}" @selected($mesDisponible->isSameMonth($periodoAnterior))>
                                {{ ucfirst($mesDisponible->copy()->locale('es')->translatedFormat('F Y')) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @if($puedeAvanzar)
                    <a href="{{ route('fuelcontrol.analisis-consumo', ['mes' => $mesSiguiente->format('Y-m'), 'comparar' => $periodoAnterior->format('Y-m')]) }}"
                       class="p-2.5 rounded-xl bg-white dark:bg-gray-900 text-slate-600 dark:text-gray-300 hover:text-orange-600 border border-slate-200/80 dark:border-gray-700 shadow-2xs hover:shadow-xs transition-all self-end"
                       title="Mes siguiente" aria-label="Mes siguiente">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="m9 18 6-6-6-6" /></svg>
                    </a>
                @else
                    <span class="p-2.5 rounded-xl bg-slate-100 dark:bg-gray-800 text-slate-300 dark:text-gray-600 border border-slate-200/50 dark:border-gray-700 self-end opacity-60 cursor-not-allowed">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="m9 18 6-6-6-6" /></svg>
                    </span>
                @endif
            </form>
        </section>

        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($metricas as $metrica)
                @php
                    $esPositivo = $metrica['diferencia'] >= 0;
                    $signo = $metrica['diferencia'] > 0 ? '+' : '';
                    $badgeBg = $metrica['color'] === 'amber'
                        ? 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-900'
                        : ($metrica['color'] === 'sky'
                            ? 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/40 dark:text-sky-400 dark:border-sky-900'
                            : 'bg-violet-50 text-violet-700 border-violet-200 dark:bg-violet-950/40 dark:text-violet-400 dark:border-violet-900');
                    $dotBg = $metrica['color'] === 'amber' ? 'bg-amber-500' : ($metrica['color'] === 'sky' ? 'bg-sky-500' : 'bg-violet-500');
                @endphp
                <article class="p-6 rounded-2xl bg-white dark:bg-gray-900 border border-slate-200/80 dark:border-gray-800 shadow-xs hover:shadow-md transition-all flex flex-col justify-between group">
                    <div>
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold uppercase tracking-wider border {{ $badgeBg }}">{{ $metrica['label'] }}</span>
                            <span class="w-2.5 h-2.5 rounded-full {{ $dotBg }}"></span>
                        </div>
                        <div class="text-4xl font-black tracking-tight text-slate-900 dark:text-gray-100 group-hover:scale-[1.02] transition-transform">
                            {{ number_format($metrica['actual'], 0, ',', '.') }} <span class="text-base font-bold text-slate-400">L</span>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t border-slate-100 dark:border-gray-800 flex items-center justify-between text-xs">
                        <span class="text-slate-500 dark:text-gray-400 font-medium">Prev: {{ number_format($metrica['anterior'], 0, ',', '.') }} L</span>
                        @if($metrica['porcentaje'] !== null)
                            @if($esPositivo)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg font-extrabold bg-rose-50 text-rose-700 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-400 dark:border-rose-900">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7" /></svg>
                                    {{ $signo }}{{ number_format($metrica['porcentaje'], 1, ',', '.') }}%
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-900">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" /></svg>
                                    {{ number_format($metrica['porcentaje'], 1, ',', '.') }}%
                                </span>
                            @endif
                        @elseif($metrica['actual'] > 0)
                            <span class="px-2.5 py-1 rounded-lg font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-400">Nuevo consumo</span>
                        @else
                            <span class="px-2.5 py-1 rounded-lg font-semibold bg-slate-100 text-slate-500 dark:bg-gray-800 dark:text-gray-400">Sin variación</span>
                        @endif
                    </div>
                </article>
            @endforeach

            <article class="p-6 rounded-2xl bg-white dark:bg-gray-900 border border-slate-200/80 dark:border-gray-800 shadow-xs hover:shadow-md transition-all flex flex-col justify-between group">
                <div>
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold uppercase tracking-wider border bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-950/40 dark:text-orange-400 dark:border-orange-900">Salidas Registradas</span>
                        <span class="w-2.5 h-2.5 rounded-full bg-orange-500"></span>
                    </div>
                    <div class="text-4xl font-black tracking-tight text-slate-900 dark:text-gray-100 group-hover:scale-[1.02] transition-transform">{{ number_format($movimientosActuales, 0, ',', '.') }}</div>
                </div>
                <div class="mt-4 pt-4 border-t border-slate-100 dark:border-gray-800 flex items-center justify-between text-xs text-slate-500 dark:text-gray-400">
                    <span class="font-medium">Cargas realizadas</span>
                    <span class="font-bold text-slate-700 dark:text-gray-300">{{ number_format($movimientosAnteriores, 0, ',', '.') }} en {{ mb_strtolower($nombreAnterior) }}</span>
                </div>
            </article>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-5 gap-8">
            <article class="lg:col-span-3 bg-white dark:bg-gray-900 border border-slate-200/80 dark:border-gray-800 rounded-2xl shadow-xs p-6 flex flex-col justify-between">
                <div class="flex items-center justify-between gap-4 mb-6 pb-3 border-b border-slate-100 dark:border-gray-800">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-gray-100 flex items-center gap-2">
                            <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            Tendencia de Consumo (Últimos 6 Meses)
                        </h3>
                        <p class="text-xs text-slate-400 dark:text-gray-500 mt-0.5">Volumen mensual acumulado de salidas aprobadas</p>
                    </div>
                    <span class="px-2.5 py-1 rounded-md bg-slate-100 dark:bg-gray-800 text-slate-600 dark:text-gray-300 text-xs font-bold">Litros</span>
                </div>
                <div class="relative h-72 w-full"><canvas id="consumoTrend"></canvas></div>
            </article>

            <article class="lg:col-span-2 bg-white dark:bg-gray-900 border border-slate-200/80 dark:border-gray-800 rounded-2xl shadow-xs p-6 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-4 mb-6 pb-3 border-b border-slate-100 dark:border-gray-800">
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-gray-100 flex items-center gap-2">
                                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 17h8m-4-4v4M3 11h18M3 11l2-5h14l2 5M3 11v6a1 1 0 001 1h1m12 0h1a1 1 0 001-1v-6" /></svg>
                                Mayor Consumo por Vehículo
                            </h3>
                            <p class="text-xs text-slate-400 dark:text-gray-500 mt-0.5">Top 8 vehículos en {{ $nombreActual }}</p>
                        </div>
                    </div>
                    <div class="space-y-4">
                        @forelse($vehiculos as $index => $vehiculo)
                            <div>
                                <div class="flex items-center justify-between gap-3 text-xs mb-1.5">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="w-5 h-5 rounded-md bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 font-mono font-bold flex items-center justify-center text-[10px] shrink-0">#{{ $index + 1 }}</span>
                                        <span class="font-bold text-slate-900 dark:text-gray-100 truncate">{{ $vehiculo->nombre }}</span>
                                    </div>
                                    <span class="font-extrabold text-slate-900 dark:text-gray-100 font-mono shrink-0">{{ number_format($vehiculo->litros, 0, ',', '.') }} L</span>
                                </div>
                                <div class="h-2 rounded-full bg-slate-100 dark:bg-gray-800 overflow-hidden">
                                    <div class="h-full rounded-full bg-gradient-to-r from-orange-500 to-amber-500 transition-all duration-500" style="width: {{ min(100, ($vehiculo->litros / max(1, $vehiculos->first()->litros)) * 100) }}%"></div>
                                </div>
                            </div>
                        @empty
                            <div class="py-12 text-center text-slate-400 text-xs italic">No hay salidas registradas para este período.</div>
                        @endforelse
                    </div>
                </div>
            </article>
        </section>

        <section class="bg-white dark:bg-gray-900 border border-slate-200/80 dark:border-gray-800 rounded-2xl shadow-xs overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-gray-800 flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-900 dark:text-gray-100 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    Tabla de Comparación Detallada
                </h3>
                <span class="text-xs text-slate-400 font-medium">Volúmenes netos en litros</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50/80 dark:bg-gray-800/80 border-b border-slate-200/80 dark:border-gray-800 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-4">Combustible / Métrica</th>
                            <th class="px-6 py-4 text-right">{{ $nombreActual }}</th>
                            <th class="px-6 py-4 text-right">{{ $nombreAnterior }}</th>
                            <th class="px-6 py-4 text-right">Diferencia</th>
                            <th class="px-6 py-4 text-right">Variación %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-gray-800">
                        @foreach($metricas as $metrica)
                            @php
                                $diff = $metrica['diferencia'];
                                $esPos = $diff >= 0;
                            @endphp
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-gray-800/60 transition-colors">
                                <td class="px-6 py-4 font-bold text-slate-900 dark:text-gray-100">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full {{ $metrica['color'] === 'amber' ? 'bg-amber-500' : ($metrica['color'] === 'sky' ? 'bg-sky-500' : 'bg-violet-500') }}"></span>
                                        {{ $metrica['label'] }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right font-mono font-extrabold text-slate-900 dark:text-gray-100">{{ number_format($metrica['actual'], 0, ',', '.') }} L</td>
                                <td class="px-6 py-4 text-right font-mono font-medium text-slate-500 dark:text-gray-400">{{ number_format($metrica['anterior'], 0, ',', '.') }} L</td>
                                <td class="px-6 py-4 text-right font-mono font-bold whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-xs {{ $esPos ? 'bg-rose-50 text-rose-700 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-400' : 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400' }}">{{ $diff > 0 ? '+' : '' }}{{ number_format($diff, 0, ',', '.') }} L</span>
                                </td>
                                <td class="px-6 py-4 text-right font-mono font-bold text-xs whitespace-nowrap">
                                    @if($metrica['porcentaje'] !== null)
                                        <span class="{{ $esPos ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">{{ $diff > 0 ? '+' : '' }}{{ number_format($metrica['porcentaje'], 1, ',', '.') }}%</span>
                                    @else
                                        <span class="text-slate-400 italic">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const trend = document.getElementById('consumoTrend');
            if (trend) {
                const isDark = document.documentElement.classList.contains('dark');
                const gridColor = isDark ? 'rgba(255, 255, 255, 0.06)' : 'rgba(226, 232, 240, 0.8)';
                const textColor = isDark ? '#94a3b8' : '#64748b';

                new Chart(trend, {
                    type: 'bar',
                    data: {
                        labels: @json($tendencia->pluck('label')),
                        datasets: [
                            { label: 'Diésel', data: @json($tendencia->pluck('diesel')), backgroundColor: 'rgba(245, 158, 11, 0.85)', hoverBackgroundColor: '#f59e0b', borderRadius: 8, borderSkipped: false },
                            { label: 'Gasolina', data: @json($tendencia->pluck('gasolina')), backgroundColor: 'rgba(14, 165, 233, 0.85)', hoverBackgroundColor: '#0ea5e9', borderRadius: 8, borderSkipped: false },
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: {
                                position: 'top', align: 'end',
                                labels: { usePointStyle: true, boxWidth: 8, font: { family: 'Figtree', size: 12, weight: '600' }, color: textColor }
                            },
                            tooltip: {
                                backgroundColor: isDark ? '#1e293b' : '#0f172a', titleFont: { size: 13, weight: 'bold' }, bodyFont: { size: 12 }, padding: 12, cornerRadius: 10,
                                callbacks: { label: (ctx) => `${ctx.dataset.label}: ${ctx.raw.toLocaleString('es-CL')} L` }
                            }
                        },
                        scales: {
                            x: { stacked: true, grid: { display: false }, ticks: { color: textColor, font: { weight: '600' } } },
                            y: { stacked: true, beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor, callback: value => value.toLocaleString('es-CL') + ' L' } }
                        }
                    }
                });
            }
        });
    </script>
</x-app-layout>
