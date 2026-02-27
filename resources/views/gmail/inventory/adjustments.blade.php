<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-3 flex-wrap">
            <div class="flex items-center gap-2 min-w-0 text-xs">
                <a href="{{ route('gmail.inventory.list') }}"
                   class="text-gray-400 hover:text-violet-600 dark:hover:text-violet-400 transition font-medium truncate">Inventario</a>
                <svg class="w-3 h-3 text-gray-300 dark:text-gray-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                </svg>
                <span class="font-bold text-gray-700 dark:text-gray-300">Ajustes de stock</span>
            </div>
            <a href="{{ route('gmail.inventory.adjust.create') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl bg-orange-500 hover:bg-orange-600 text-white transition shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nuevo ajuste
            </a>
        </div>
    </x-slot>

    <div class="py-6 space-y-5">

        {{-- KPI cards --}}
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 px-4 py-3 flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] text-gray-400">Ajustes + (mes)</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ (int)($kpiPos->cnt ?? 0) }}</p>
                    <p class="text-[11px] text-emerald-600">{{ number_format((float)($kpiPos->qty ?? 0), 0, ',', '.') }} uds</p>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 px-4 py-3 flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-rose-50 dark:bg-rose-900/30 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] text-gray-400">Ajustes − (mes)</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ (int)($kpiNeg->cnt ?? 0) }}</p>
                    <p class="text-[11px] text-rose-600">{{ number_format((float)($kpiNeg->qty ?? 0), 0, ',', '.') }} uds</p>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 px-4 py-3 flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-violet-50 dark:bg-violet-900/30 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] text-gray-400">Costo ajustes + (mes)</p>
                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">${{ number_format((float)($kpiPos->costo ?? 0), 0, ',', '.') }}</p>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 px-4 py-3 flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] text-gray-400">Costo ajustes − (mes)</p>
                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">${{ number_format((float)($kpiNeg->costo ?? 0), 0, ',', '.') }}</p>
                </div>
            </div>
        </div>

        {{-- Filtros --}}
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="GET" action="{{ route('gmail.inventory.adjustments') }}"
                  class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 px-4 py-3 flex flex-wrap gap-3 items-end">
                <div class="flex-1 min-w-36">
                    <label class="block text-[11px] text-gray-400 mb-1">Buscar motivo / notas</label>
                    <input type="text" name="q" value="{{ $q }}"
                           placeholder="Pérdida, inventario…"
                           class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm px-3 py-1.5 focus:outline-none focus:border-violet-400">
                </div>
                <div>
                    <label class="block text-[11px] text-gray-400 mb-1">Dirección</label>
                    <select name="dir" class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm px-3 py-1.5 focus:outline-none focus:border-violet-400">
                        <option value="">Todos</option>
                        <option value="AJUSTE+" @selected($dir === 'AJUSTE+')>Solo positivos (+)</option>
                        <option value="AJUSTE-" @selected($dir === 'AJUSTE-')>Solo negativos (−)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] text-gray-400 mb-1">Desde</label>
                    <input type="date" name="desde" value="{{ $desde }}"
                           class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm px-3 py-1.5 focus:outline-none focus:border-violet-400">
                </div>
                <div>
                    <label class="block text-[11px] text-gray-400 mb-1">Hasta</label>
                    <input type="date" name="hasta" value="{{ $hasta }}"
                           class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm px-3 py-1.5 focus:outline-none focus:border-violet-400">
                </div>
                <div class="flex gap-2">
                    <button type="submit"
                            class="px-4 py-1.5 rounded-xl bg-violet-600 hover:bg-violet-700 text-white text-xs font-semibold transition">
                        Filtrar
                    </button>
                    @if($q || $dir || $desde || $hasta)
                        <a href="{{ route('gmail.inventory.adjustments') }}"
                           class="px-4 py-1.5 rounded-xl border border-gray-200 dark:border-gray-700 text-xs font-semibold text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                            Limpiar
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Tabla --}}
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 overflow-hidden">

                @if($movements->isEmpty())
                    <div class="py-16 text-center text-gray-400 text-sm">
                        No hay ajustes registrados{{ $q || $dir ? ' con los filtros actuales' : '' }}.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide">#</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Fecha</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Dir.</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Producto(s)</th>
                                    <th class="px-4 py-3 text-right text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Cantidad</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Motivo</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Notas</th>
                                    <th class="px-4 py-3 text-right text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Costo</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
                                @foreach($movements as $m)
                                    @php
                                        $isPos    = ($m->tipo_salida ?? '') === 'AJUSTE+';
                                        $movLines = $lines->get($m->id, collect());
                                    @endphp
                                    <tr class="hover:bg-gray-50/60 dark:hover:bg-gray-800/40 transition">
                                        <td class="px-4 py-3 text-xs text-gray-400 font-mono">#{{ $m->id }}</td>
                                        <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                            {{ \Carbon\Carbon::parse($m->ocurrio_el)->format('d/m/Y H:i') }}
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($isPos)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 text-[11px] font-bold">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                                                    +
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-rose-50 dark:bg-rose-900/30 text-rose-700 dark:text-rose-400 text-[11px] font-bold">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                                                    −
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($movLines->isEmpty())
                                                <span class="text-xs text-gray-400 italic">—</span>
                                            @elseif($movLines->count() === 1)
                                                <span class="text-xs font-medium text-gray-800 dark:text-gray-200">{{ $movLines->first()->producto }}</span>
                                                <span class="text-[11px] text-gray-400 ml-1">{{ $movLines->first()->unidad }}</span>
                                            @else
                                                <span class="text-xs font-medium text-gray-800 dark:text-gray-200">{{ $movLines->first()->producto }}</span>
                                                <span class="text-[11px] text-gray-400 ml-1">+{{ $movLines->count() - 1 }} más</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right text-xs font-semibold
                                            {{ $isPos ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                            {{ $isPos ? '+' : '−' }}{{ number_format((float)$m->cantidad_total, 2, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-700 dark:text-gray-300 max-w-[160px] truncate" title="{{ $m->destinatario ?? '' }}">
                                            {{ $m->destinatario ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-400 max-w-[140px] truncate" title="{{ $m->notas ?? '' }}">
                                            {{ $m->notas ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-xs text-gray-500">
                                            @if((float)$m->costo_total > 0)
                                                ${{ number_format((float)$m->costo_total, 0, ',', '.') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <a href="{{ route('gmail.inventory.exits.show', $m->id) }}"
                                               class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-violet-50 dark:hover:bg-violet-900/20 text-gray-500 hover:text-violet-600 dark:hover:text-violet-400 text-[11px] font-semibold transition">
                                                Ver
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between text-[11px] text-gray-400">
                        <span>{{ $movements->count() }} ajuste(s){{ $movements->count() >= 300 ? ' (mostrando primeros 300)' : '' }}</span>
                        <a href="{{ route('gmail.inventory.adjust.create') }}"
                           class="inline-flex items-center gap-1 text-orange-500 hover:text-orange-600 font-semibold transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Nuevo ajuste
                        </a>
                    </div>
                @endif
            </div>
        </div>

    </div>
</x-app-layout>
