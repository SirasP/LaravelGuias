<x-app-layout>

    {{-- ═══════════════════════════════════════════════════
    HEADER — Título limpio y acciones principales
    ═══════════════════════════════════════════════════ --}}
    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-4">
            <div class="flex items-center gap-2.5 min-w-0">
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">DTE Mail</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Guías COMFRUT</p>
                </div>
            </div>

            {{-- Acciones principales --}}
            <div class="flex items-center gap-2 shrink-0">
                @if(auth()->user()->role === 'admin')
                    {{-- Botón Importar XML --}}
                    <a href="{{ route('guias.comfrut.import.form') }}"
                       class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold rounded-xl
                              bg-indigo-600 hover:bg-indigo-700 active:scale-95
                              text-white transition shadow-sm shadow-indigo-200 dark:shadow-indigo-900">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2"
                                  d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <span>Importar XML</span>
                    </a>
                @endif

                {{-- Botón Exportar --}}
                <a href="{{ route('guias.comfrut.export-php', request()->query()) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold rounded-xl
                          bg-emerald-600 hover:bg-emerald-700 active:scale-95
                          text-white transition shadow-sm shadow-emerald-200 dark:shadow-emerald-900">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    <span>Exportar Excel</span>
                </a>
            </div>
        </div>
    </x-slot>

    @php
        $rowsJson = $guias->getCollection()->map(function ($g) {
            $bandejas = $g->detalles->filter(fn($d) => preg_match('/BANDEJ|BDJA/i', $d->nombre_item))->sum('cantidad');
            $pallets  = $g->detalles->filter(fn($d) => preg_match('/PALLET|PALE/i', $d->nombre_item))->sum('cantidad');
            $otros    = $g->detalles->filter(fn($d) => !preg_match('/BANDEJ|BDJA|PALLET|PALE/i', $d->nombre_item))->sum('cantidad');
            return [
                'id'       => $g->id,
                'guia'     => $g->guia_numero,
                'fecha'    => $g->fecha_guia?->format('d-m-Y') ?? '—',
                'productor'=> $g->productor ?? '—',
                'patente'  => strtoupper($g->patente ?? '—'),
                'chofer'   => Str::title(Str::lower($g->detalles->first()->nombre_chofer ?? '—')),
                'bandejas' => $bandejas,
                'pallets'  => $pallets,
                'otros'    => $otros,
                'total'    => number_format($g->cantidad_total, 2, ',', '.'),
            ];
        })->values()->toJson(JSON_UNESCAPED_UNICODE);
    @endphp

    <style>
        [x-cloak] { display:none !important; }
        .qty { display:inline-flex; align-items:center; padding:2px 9px; border-radius:999px; font-size:11px; font-weight:700; white-space:nowrap }
        .qty-blue   { background:#dbeafe; color:#1d4ed8 } .dark .qty-blue   { background:rgba(59,130,246,.15); color:#93c5fd }
        .qty-purple { background:#f3e8ff; color:#7c3aed } .dark .qty-purple { background:rgba(139,92,246,.15); color:#c4b5fd }
        .qty-amber  { background:#fef3c7; color:#b45309 } .dark .qty-amber  { background:rgba(245,158,11,.15); color:#fcd34d }
        .qty-empty  { background:#f1f5f9; color:#94a3b8 } .dark .qty-empty  { background:rgba(255,255,255,.06); color:#475569 }
        .guia-badge { font-family:monospace; font-weight:700; font-size:13px; color:#4f46e5 }
        .dark .guia-badge { color:#a5b4fc }
        .stat-pill { display:inline-flex; align-items:center; gap:4px; padding:5px 12px; border-radius:10px; font-size:12px; font-weight:600; background:#eef2ff; color:#4338ca }
        .dark .stat-pill { background:rgba(99,102,241,.15); color:#a5b4fc }
        .empty-icon { width:48px; height:48px; border-radius:16px; background:#f1f5f9; display:flex; align-items:center; justify-content:center; margin:0 auto 12px }
        .dark .empty-icon { background:rgba(255,255,255,.06) }
    </style>

    <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6" x-data="comfrutIndex({{ $rowsJson }})">

        {{-- Flash --}}
        @if(session('ok'))
            <div class="flash-ok au d1">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                {{ session('ok') }}
            </div>
        @endif

        {{-- Stats Grid (KPI Cards) --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 au d1">
            <x-kpi-card 
                label="Total Guías" 
                value="{{ number_format($total, 0, ',', '.') }}"
                iconBg="bg-indigo-50 dark:bg-indigo-900/20"
            >
                <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </x-kpi-card>

            <x-kpi-card 
                label="Total Bandejas" 
                value="{{ number_format($totalBandejas, 0, ',', '.') }}"
                iconBg="bg-emerald-50 dark:bg-emerald-900/20"
            >
                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
            </x-kpi-card>

            <x-kpi-card 
                label="Total Pallets" 
                value="{{ number_format($totalPallets, 0, ',', '.') }}"
                iconBg="bg-amber-50 dark:bg-amber-900/20"
            >
                <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
            </x-kpi-card>

            <x-kpi-card 
                label="Productores" 
                value="{{ number_format($uniqueProducers, 0, ',', '.') }}"
                iconBg="bg-violet-50 dark:bg-violet-900/20"
            >
                <svg class="w-4 h-4 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20H2v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </x-kpi-card>
        </div>

        {{-- Panel de Control / Filtros --}}
        <div class="t-card p-4 sm:p-5 space-y-4 au d1">
            <form method="GET" action="{{ route('guias.comfrut.index') }}" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                <div class="relative flex-1">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                    </svg>
                    <input name="q" x-model="q" type="text" autocomplete="off"
                           placeholder="Buscar por guía, productor, patente, chofer…"
                           class="w-full pl-9 pr-8 py-2.5 text-sm rounded-xl
                                  border border-gray-200 dark:border-gray-700
                                  bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100
                                  focus:ring-2 focus:ring-indigo-500 focus:border-transparent
                                  outline-none transition placeholder-gray-400 shadow-sm">
                    <button type="button" x-show="q" @click="q = ''; $el.form.submit()"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="flex gap-2">
                    <select name="season" class="flt-select flex-1 sm:flex-initial" onchange="this.form.submit()">
                        <option value="" {{ ($season ?? '') === '' ? 'selected' : '' }}>Todas las cosechas</option>
                        @foreach($availableSeasons as $s)
                            <option value="{{ $s }}" {{ ($season ?? '') == $s ? 'selected' : '' }}>{{ $s }}</option>
                        @endforeach
                    </select>

                    <button type="submit" class="inline-flex items-center justify-center px-4 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white transition active:scale-95 shadow-sm">
                        Buscar
                    </button>

                    @if($q || ($season ?? '') !== '')
                        <a href="{{ route('guias.comfrut.index') }}" class="flt-btn flt-clear flex items-center justify-center whitespace-nowrap">
                            Limpiar
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Barra stats + contador --}}
        <div class="flex items-center justify-between au d1">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="stat-pill">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span class="font-bold">{{ number_format($total, 0, ',', '.') }}</span>
                    guías
                </span>
            </div>

            <div class="flex items-center gap-3 text-xs text-gray-400">
                <span>
                    Mostrando
                    <span x-text="filtered.length" class="font-bold text-gray-700 dark:text-gray-200"></span>
                    <template x-if="filtered.length !== {{ $guias->total() }}">
                        <span> / {{ $guias->total() }} total</span>
                    </template>
                </span>
                <template x-if="q">
                    <button @click="q = ''" class="text-indigo-600 dark:text-indigo-400 hover:underline font-semibold">
                        Limpiar ×
                    </button>
                </template>
            </div>
        </div>

        {{-- ── TABLA DESKTOP ───────────────────────────── --}}
        <div class="hidden lg:block t-card au d2">
            <div class="overflow-x-auto">
                <table class="dt">
                    <thead>
                        <tr>
                            <th>Guía</th>
                            <th>Fecha</th>
                            <th>Productor</th>
                            <th>Patente</th>
                            <th>Chofer</th>
                            <th class="r">Bandejas</th>
                            <th class="r">Pallets</th>
                            <th class="r">Otros</th>
                            <th class="r">Total</th>
                            <th class="r w-16"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="r in filtered" :key="r.id">
                            <tr>
                                <td>
                                    <span class="guia-badge" x-text="r.guia ?? '—'"></span>
                                </td>
                                <td class="text-gray-500 dark:text-gray-400 text-xs whitespace-nowrap">
                                    <div class="flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z" />
                                        </svg>
                                        <span x-text="r.fecha"></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="block max-w-[220px] truncate text-gray-800 dark:text-gray-200 font-medium text-xs"
                                          :title="r.productor" x-text="r.productor"></span>
                                </td>
                                <td>
                                    <span class="pat-badge" x-text="r.patente"></span>
                                </td>
                                <td class="text-gray-600 dark:text-gray-400 text-xs capitalize"
                                    x-text="r.chofer"></td>

                                {{-- Bandejas --}}
                                <td class="text-right">
                                    <span class="qty"
                                          :class="r.bandejas > 0 ? 'qty-blue' : 'qty-empty'"
                                          x-text="r.bandejas > 0 ? r.bandejas.toLocaleString('es-CL') : '—'"></span>
                                </td>
                                {{-- Pallets --}}
                                <td class="text-right">
                                    <span class="qty"
                                          :class="r.pallets > 0 ? 'qty-purple' : 'qty-empty'"
                                          x-text="r.pallets > 0 ? r.pallets.toLocaleString('es-CL') : '—'"></span>
                                </td>
                                {{-- Otros --}}
                                <td class="text-right">
                                    <span class="qty"
                                          :class="r.otros > 0 ? 'qty-amber' : 'qty-empty'"
                                          x-text="r.otros > 0 ? r.otros.toLocaleString('es-CL') : '—'"></span>
                                </td>
                                {{-- Total --}}
                                <td class="text-right font-bold text-gray-800 dark:text-gray-100 tabular-nums"
                                    x-text="r.total"></td>

                                <td class="text-right">
                                    <a :href="`{{ url('/guias/comfrut') }}/${r.id}`"
                                       class="btn-sm btn-indigo transition-all duration-150 py-1.5 px-2.5 rounded-lg flex items-center gap-1 shadow-sm">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span>Ver</span>
                                    </a>
                                </td>
                            </tr>
                        </template>

                        {{-- Empty filtered --}}
                        <tr x-show="filtered.length === 0">
                            <td colspan="10" class="py-14 text-center">
                                <div class="empty-icon">
                                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <p class="text-sm font-semibold text-gray-600 dark:text-gray-300">Sin resultados</p>
                                <p class="text-xs text-gray-400 mt-1">
                                    No hay guías que coincidan con "<span x-text="q" class="italic"></span>".
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Empty DB state --}}
            @if($guias->isEmpty())
            <div class="py-16 text-center px-6">
                <div class="empty-icon">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">No hay guías COMFRUT importadas</p>
                <p class="text-xs text-gray-400 mt-1 mb-4">Sube el primer XML para empezar.</p>
                @if(auth()->user()->role === 'admin')
                    <a href="{{ route('guias.comfrut.import.form') }}"
                       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl
                              bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition shadow-sm shadow-indigo-100 dark:shadow-none">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <span>Importar primera guía</span>
                    </a>
                @endif
            </div>
            @endif
        </div>

        {{-- Paginación desktop --}}
        @if($guias->hasPages())
        <div class="hidden lg:block au d3" data-turbo="false">{{ $guias->links() }}</div>
        @endif

        {{-- ── CARDS MÓVIL ─────────────────────────────── --}}
        <div class="lg:hidden space-y-3 au d2">
            <template x-for="r in filtered" :key="r.id">
                <div class="m-card">
                    {{-- Cabecera --}}
                    <div class="flex items-start justify-between gap-2 mb-2.5">
                        <div class="min-w-0">
                            <span class="guia-badge block" x-text="r.guia ?? '—'"></span>
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5"
                               :title="r.productor" x-text="r.productor"></p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-xs font-bold text-gray-700 dark:text-gray-300 tabular-nums" x-text="r.total"></p>
                            <span class="pat-badge mt-1 inline-block" x-text="r.patente"></span>
                        </div>
                    </div>

                    {{-- Grid --}}
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs mb-3">
                        <div>
                            <p class="text-gray-400 dark:text-gray-600 mb-0.5">Fecha</p>
                            <p class="font-semibold text-gray-700 dark:text-gray-300" x-text="r.fecha"></p>
                        </div>
                        <div>
                            <p class="text-gray-400 dark:text-gray-600 mb-0.5">Chofer</p>
                            <p class="font-medium text-gray-600 dark:text-gray-400 capitalize" x-text="r.chofer"></p>
                        </div>
                    </div>

                    {{-- Cantidades --}}
                    <div class="flex flex-wrap gap-1.5 mb-3">
                        <span class="qty qty-blue" x-show="r.bandejas > 0"
                              x-text="r.bandejas.toLocaleString('es-CL') + ' bandejas'"></span>
                        <span class="qty qty-purple" x-show="r.pallets > 0"
                              x-text="r.pallets.toLocaleString('es-CL') + ' pallets'"></span>
                        <span class="qty qty-amber" x-show="r.otros > 0"
                              x-text="r.otros.toLocaleString('es-CL') + ' otros'"></span>
                    </div>

                    {{-- Footer --}}
                    <div class="flex items-center justify-end border-t border-gray-100 dark:border-gray-800 pt-2.5">
                        <a :href="`{{ url('/guias/comfrut') }}/${r.id}`"
                           class="btn-sm btn-indigo w-full text-center justify-center py-1.5 rounded-xl">
                            Ver detalle
                        </a>
                    </div>
                </div>
            </template>

            <div x-show="filtered.length === 0"
                 class="m-card text-center text-sm text-gray-400 py-12">
                No hay guías que coincidan con la búsqueda.
            </div>
        </div>

        {{-- Paginación móvil --}}
        @if($guias->hasPages())
        <div class="lg:hidden au d3" data-turbo="false">{{ $guias->links() }}</div>
        @endif

    </div>

    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('comfrut', { q: '{{ request('q') }}' });
    });

    function comfrutIndex(rows) {
        return {
            rows,
            get q()    { return Alpine.store('comfrut').q; },
            set q(val) { Alpine.store('comfrut').q = val; },

            get filtered() {
                const q = (Alpine.store('comfrut').q || '').trim().toLowerCase();
                if (!q) return this.rows;
                return this.rows.filter(r =>
                    [r.guia, r.fecha, r.productor, r.patente, r.chofer]
                        .some(v => String(v ?? '').toLowerCase().includes(q))
                );
            },
        };
    }
    </script>

</x-app-layout>
