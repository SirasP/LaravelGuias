<x-app-layout>

    {{-- ═══════════════════════════════════════════════════
    HEADER — título + acciones principales
    ═══════════════════════════════════════════════════ --}}
    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-4">
            <div class="flex items-center gap-2.5 min-w-0">
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">AGRAK</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Bins / Campo</p>
                </div>
            </div>

            {{-- Acciones --}}
            <div class="flex items-center gap-2 shrink-0">
                {{-- Ver por camión --}}
                <a href="{{ route('agrak.index', array_merge(request()->all(), ['view' => 'group'])) }}" 
                   class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold rounded-xl
                          border border-gray-200 dark:border-gray-700
                          bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300
                          hover:bg-gray-50 dark:hover:bg-gray-700 transition active:scale-95 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2"
                            d="M8 17h8m-4-4v4M3 11h18M3 11l2-5h14l2 5M3 11v6a1 1 0 001 1h1m12 0h1a1 1 0 001-1v-6" />
                    </svg>
                    <span>Ver por camión</span>
                </a>

                {{-- Exportar Excel --}}
                @if(Route::has('agrak.export'))
                    <a href="{{ route('agrak.export', request()->all()) }}" 
                       class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold rounded-xl
                              bg-emerald-600 hover:bg-emerald-700 active:scale-95
                              text-white transition shadow-sm shadow-emerald-200 dark:shadow-emerald-900">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        <span class="hidden sm:inline">Exportar Excel</span>
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    @php
        /* ── Serializar filas para Alpine (client-side filter) ── */
        $rowsJson = $items->getCollection()->map(fn($it) => [
            'id' => $it->id,
            'bin' => $it->codigo_bin,
            'campo' => $it->campo,
            'cuartel' => $it->cuartel,
            'especie' => $it->especie,
            'variedad' => $it->variedad,
            'fecha' => optional(\Carbon\Carbon::parse($it->fecha_registro))->format('d-m-Y'),
            'hora' => $it->hora_registro,
            'bandejas' => $it->numero_bandejas_palet,
            'maquina' => $it->maquina,
            'chofer' => $it->nombre_chofer,
            'patente' => $it->patente_camion,
            'exportadora' => $it->exportadora_1 ?? $it->exportadora_2,
            'sello' => $it->numero_sello,
        ])->values()->toJson(JSON_UNESCAPED_UNICODE);

        $isDateSort = ($orderBy ?? 'fecha_registro') === 'fecha_registro';
        $isBandejaSort = ($orderBy ?? '') === 'numero_bandejas_palet';
        $nextDir = ($dir ?? 'desc') === 'desc' ? 'asc' : 'desc';
        $sortArrowDn = '<path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>';
        $sortArrowUp = '<path d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z"/>';
    @endphp

    <style>
        [x-cloak] { display: none !important; }
        .sort-link { display:inline-flex; align-items:center; gap:3px; cursor:pointer; transition:color .15s }
        .sort-link:hover { color:#6366f1 } .dark .sort-link:hover { color:#a5b4fc }
        .filter-bar { display:flex; flex-wrap:wrap; align-items:center; gap:8px }
        .bin-badge { font-family:monospace; font-weight:700; font-size:13px; color:#4f46e5 }
        .dark .bin-badge { color:#a5b4fc }
    </style>

    <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6" x-data="agrakIndex({{ $rowsJson }})">

        {{-- Flash --}}
        @if(session('ok'))
            <div class="flash-ok au d1">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
                {{ session('ok') }}
            </div>
        @endif

        {{-- ── KPIs ─────────────────────────────────────── --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 au d1">
            <x-kpi-card 
                label="Total Bins" 
                value="{{ number_format($stats['total_bins'], 0, ',', '.') }}"
                iconBg="bg-indigo-50 dark:bg-indigo-900/20"
            >
                <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
            </x-kpi-card>

            <x-kpi-card 
                label="Total Bandejas" 
                value="{{ number_format($stats['total_bandejas'], 0, ',', '.') }}"
                iconBg="bg-emerald-50 dark:bg-emerald-900/20"
            >
                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
            </x-kpi-card>

            <x-kpi-card 
                label="Variedades" 
                value="{{ number_format($stats['variedades'], 0, ',', '.') }}"
                iconBg="bg-amber-50 dark:bg-amber-900/20"
            >
                <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </x-kpi-card>

            <x-kpi-card 
                label="Cuarteles" 
                value="{{ number_format($stats['cuarteles'], 0, ',', '.') }}"
                iconBg="bg-violet-50 dark:bg-violet-900/20"
            >
                <svg class="w-4 h-4 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                </svg>
            </x-kpi-card>
        </div>

        {{-- Panel de Control / Filtros --}}
        <div class="t-card p-4 sm:p-5 space-y-4 au d1">
            <form method="GET" action="{{ route('agrak.index') }}" class="space-y-3.5">
                {{-- Buscador + Cosecha --}}
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                    <div class="relative flex-1">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0" />
                        </svg>
                        <input name="q" x-model="q" type="text" autocomplete="off"
                            placeholder="Buscar por bin, cuartel, chofer, patente, exportadora…" 
                            class="w-full pl-9 pr-8 py-2.5 text-sm rounded-xl
                                  border border-gray-200 dark:border-gray-700
                                  bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100
                                  focus:ring-2 focus:ring-indigo-500 focus:border-transparent
                                  outline-none transition placeholder-gray-400 shadow-sm">
                        <button type="button" x-show="q" @click="q = ''"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex gap-2">
                        {{-- Cosecha --}}
                        <select name="season" class="flt-select flex-1 sm:flex-initial" onchange="this.form.submit()">
                            <option value="">Todas las cosechas</option>
                            @foreach($availableSeasons as $s)
                                <option value="{{ $s }}" @selected($season === $s)>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="h-px bg-gray-100 dark:bg-gray-800/80 my-1"></div>

                {{-- Filtros Avanzados (Dropdowns de Categorías) --}}
                <div class="flex flex-wrap items-center gap-2">
                    {{-- Campo --}}
                    <select name="campo" class="flt-select">
                        <option value="">Campo — todos</option>
                        @foreach($campos as $c)
                            <option value="{{ $c }}" @selected($campo === $c)>{{ $c }}</option>
                        @endforeach
                    </select>

                    {{-- Cuartel --}}
                    <select name="cuartel" class="flt-select">
                        <option value="">Cuartel — todos</option>
                        @foreach($cuarteles as $c)
                            <option value="{{ $c }}" @selected($cuartel === $c)>{{ $c }}</option>
                        @endforeach
                    </select>

                    {{-- Especie --}}
                    <select name="especie" class="flt-select">
                        <option value="">Especie — todas</option>
                        @foreach($especies as $e)
                            <option value="{{ $e }}" @selected($especie === $e)>{{ $e }}</option>
                        @endforeach
                    </select>

                    {{-- Rango de fechas de cosecha --}}
                    <div class="flex items-center gap-1.5">
                        <label for="f-desde" class="text-[11px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wide">Desde</label>
                        <input id="f-desde" type="date" name="desde" value="{{ $desde ?? '' }}"
                               max="{{ $hasta ?: null }}" class="flt-date">

                        <label for="f-hasta" class="text-[11px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wide">Hasta</label>
                        <input id="f-hasta" type="date" name="hasta" value="{{ $hasta ?? '' }}"
                               min="{{ $desde ?: null }}" class="flt-date">
                    </div>

                    {{-- Hidden order params --}}
                    <input type="hidden" name="order_by" value="{{ $orderBy ?? '' }}">
                    <input type="hidden" name="dir" value="{{ $dir ?? 'desc' }}">

                    <div class="flex items-center gap-2 ml-0 sm:ml-auto">
                        <button type="submit" class="inline-flex items-center justify-center px-4 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white transition active:scale-95 shadow-sm">
                            Filtrar
                        </button>

                        @if($campo || $cuartel || $especie || $q || $season || $desde || $hasta)
                            <a href="{{ route('agrak.index') }}" class="flt-btn flt-clear flex items-center justify-center whitespace-nowrap">
                                Limpiar
                            </a>
                        @endif
                    </div>
                </div>
            </form>

            {{-- Chips de filtros activos --}}
            @if($campo || $cuartel || $especie || $desde || $hasta)
                <div class="flex flex-wrap gap-1.5 pt-1">
                    @foreach(array_filter([
                        'Campo' => $campo,
                        'Cuartel' => $cuartel,
                        'Especie' => $especie,
                        'Desde' => $desde ? \Carbon\Carbon::parse($desde)->format('d/m/Y') : '',
                        'Hasta' => $hasta ? \Carbon\Carbon::parse($hasta)->format('d/m/Y') : '',
                    ]) as $label => $val)
                        <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full border
                             bg-indigo-50 text-indigo-700 border-indigo-100 dark:bg-indigo-900/20 dark:text-indigo-400 dark:border-indigo-900/30">
                            {{ $label }}: {{ $val }}
                            <a href="{{ route('agrak.index', array_merge(request()->except(strtolower($label)))) }}"
                                class="ml-0.5 text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-200">✕</a>
                        </span>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Barra stats + contador --}}
        <div class="flex items-center justify-between au d1">
            <div>
                <p class="text-xs text-gray-400">
                    <span>Mostrando</span>
                    <span x-text="filtered.length" class="font-bold text-gray-700 dark:text-gray-200"></span>
                    <span x-show="filtered.length !== {{ $items->total() }}" class="text-gray-400 dark:text-gray-600">
                        / {{ $items->total() }} total
                    </span>
                    <span>bins</span>
                </p>
            </div>
            
            <div class="flex items-center gap-3 text-xs text-gray-400">
                @if($items->total() > 0)
                    <span class="font-semibold text-gray-500 dark:text-gray-400">
                        Página {{ $items->currentPage() }} de {{ $items->lastPage() }}
                    </span>
                @endif
                <template x-if="q">
                    <button @click="q = ''" class="text-indigo-600 dark:text-indigo-400 hover:underline font-semibold">
                        Limpiar ×
                    </button>
                </template>
            </div>
        </div>

        {{-- ── TABLA DESKTOP ─────────────────────────────── --}}
        <div class="hidden lg:block t-card au d2">
            <div class="overflow-x-auto">
                <table class="dt">
                    <thead>
                        <tr>
                            <th class="w-12">ID</th>
                            <th>Bin</th>
                            <th>Cuartel</th>
                            <th>Especie / Variedad</th>
                            <th>
                                <a class="sort-link"
                                    href="{{ request()->fullUrlWithQuery(['order_by' => 'fecha_registro', 'dir' => $isDateSort ? $nextDir : 'desc']) }}">
                                    Fecha
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        {!! $isDateSort && ($dir ?? 'desc') === 'asc' ? $sortArrowUp : $sortArrowDn !!}
                                    </svg>
                                </a>
                            </th>
                            <th>
                                <a class="sort-link"
                                    href="{{ request()->fullUrlWithQuery(['order_by' => 'numero_bandejas_palet', 'dir' => $isBandejaSort ? $nextDir : 'desc']) }}">
                                    Bandejas
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        {!! $isBandejaSort && ($dir ?? 'desc') === 'asc' ? $sortArrowUp : $sortArrowDn !!}
                                    </svg>
                                </a>
                            </th>
                            <th>Máquina</th>
                            <th>Chofer</th>
                            <th>Patente</th>
                            <th>Exportadora</th>
                            <th class="text-right w-16"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="r in filtered" :key="r.id">
                            <tr>
                                <td class="text-gray-400 text-xs font-mono" x-text="r.id"></td>
                                <td>
                                    <span class="bin-badge" x-text="r.bin ?? '—'"></span>
                                </td>
                                <td>
                                    <div class="text-gray-800 dark:text-gray-200 font-semibold text-xs"
                                        x-text="r.cuartel ?? '—'"></div>
                                    <div class="text-gray-400 text-[11px]" x-show="r.campo" x-text="r.campo"></div>
                                </td>
                                <td>
                                    <div class="text-gray-800 dark:text-gray-200 text-xs font-semibold"
                                        x-text="r.especie ?? '—'"></div>
                                    <div class="text-gray-400 text-[11px]" x-show="r.variedad" x-text="r.variedad">
                                    </div>
                                </td>
                                <td class="text-gray-500 dark:text-gray-400 text-xs whitespace-nowrap">
                                    <div class="flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z" />
                                        </svg>
                                        <span x-text="r.fecha ?? '—'"></span>
                                        <span class="text-gray-300 dark:text-gray-700 ml-0.5" x-show="r.hora" x-text="r.hora"></span>
                                    </div>
                                </td>
                                <td class="text-right font-bold text-gray-800 dark:text-gray-100 tabular-nums"
                                    x-text="r.bandejas ?? '—'"></td>
                                <td class="text-gray-500 dark:text-gray-400 text-xs" x-text="r.maquina ?? '—'"></td>
                                <td class="text-gray-700 dark:text-gray-300 text-xs" x-text="r.chofer ?? '—'"></td>
                                <td>
                                    <span class="pat-badge" x-text="r.patente ?? '—'"></span>
                                </td>
                                <td class="text-gray-500 dark:text-gray-400 text-xs max-w-[140px] truncate"
                                    x-text="r.exportadora ?? '—'"></td>
                                <td class="text-right">
                                    <a :href="`{{ url('/agrak') }}/${r.id}`" 
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
                        <tr x-show="filtered.length === 0">
                            <td colspan="11" class="py-14 text-center text-sm text-gray-400">
                                No hay resultados para "<span x-text="q"></span>".
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Paginación desktop --}}
        <div class="hidden lg:block au d3" data-turbo="false">{{ $items->links() }}</div>

        {{-- ── CARDS MÓVIL ────────────────────────────────── --}}
        <div class="lg:hidden space-y-3.5 au d2">
            <template x-for="r in filtered" :key="r.id">
                <div class="m-card">
                    {{-- Cabecera tarjeta --}}
                    <div class="flex items-start justify-between gap-2 mb-2.5">
                        <div>
                            <span class="bin-badge block" x-text="r.bin ?? '—'"></span>
                            <p class="text-xs text-gray-400 mt-0.5"
                                x-text="(r.cuartel ?? '—') + (r.campo ? ' · ' + r.campo : '')"></p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-xs font-bold text-gray-700 dark:text-gray-300 tabular-nums">
                                <span x-text="r.bandejas ?? '—'"></span>
                                <span class="font-normal text-gray-400"> bandejas</span>
                            </p>
                            <span class="pat-badge mt-1 inline-block" x-text="r.patente ?? '—'"></span>
                        </div>
                    </div>

                    {{-- Grid de datos --}}
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs mb-3.5">
                        <div>
                            <p class="text-gray-400 dark:text-gray-500 mb-0.5">Fecha</p>
                            <p class="font-semibold text-gray-700 dark:text-gray-300"
                                x-text="r.fecha ? r.fecha + (r.hora ? ' ' + r.hora : '') : '—'"></p>
                        </div>
                        <div>
                            <p class="text-gray-400 dark:text-gray-500 mb-0.5">Especie</p>
                            <p class="font-semibold text-gray-700 dark:text-gray-300"
                                x-text="r.especie + (r.variedad ? ' · ' + r.variedad : '')"></p>
                        </div>
                        <div>
                            <p class="text-gray-400 dark:text-gray-500 mb-0.5">Chofer</p>
                            <p class="font-medium text-gray-600 dark:text-gray-400" x-text="r.chofer ?? '—'"></p>
                        </div>
                        <div>
                            <p class="text-gray-400 dark:text-gray-500 mb-0.5">Exportadora</p>
                            <p class="font-medium text-gray-600 dark:text-gray-400 truncate"
                                x-text="r.exportadora ?? '—'"></p>
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="flex items-center justify-between border-t border-gray-100 dark:border-gray-800 pt-3">
                        <span class="text-[11px] text-gray-400 font-medium"
                            x-text="r.maquina ? 'Máq: ' + r.maquina : ''"></span>
                        <a :href="`{{ url('/agrak') }}/${r.id}`" class="btn-sm btn-indigo px-4 py-1.5 rounded-xl">Ver detalle</a>
                    </div>
                </div>
            </template>
            <div x-show="filtered.length === 0" class="m-card text-center text-sm text-gray-400 py-12">
                No hay resultados.
            </div>
        </div>

        {{-- Paginación móvil --}}
        <div class="lg:hidden au d3" data-turbo="false">{{ $items->links() }}</div>

    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('agrak', { q: '{{ request('q') }}' });
        });

        /* ── Body index ── */
        function agrakIndex(rows) {
            return {
                rows,
                get q() { return Alpine.store('agrak').q; },
                set q(val) { Alpine.store('agrak').q = val; },

                get filtered() {
                    const q = (Alpine.store('agrak').q || '').trim().toLowerCase();
                    if (!q) return this.rows;
                    return this.rows.filter(r =>
                        [r.id, r.bin, r.campo, r.cuartel, r.especie, r.variedad,
                        r.chofer, r.patente, r.exportadora, r.maquina, r.sello, r.fecha]
                            .some(v => String(v ?? '').toLowerCase().includes(q))
                    );
                },
            };
        }
    </script>

</x-app-layout>