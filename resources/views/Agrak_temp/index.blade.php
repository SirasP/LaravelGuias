<x-app-layout>

    {{-- ═══════════════════════════════════════════════════
    HEADER — título + buscador centrado + acciones
    ═══════════════════════════════════════════════════ --}}
    <x-slot name="header">
        <div class="flex items-center gap-3 w-full" x-data="agrakHeader()">

            {{-- Título (desktop) --}}
            <div class="hidden sm:block shrink-0">
                <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">AGRAK</h2>
                <p class="text-xs text-gray-400 mt-0.5">Bins / Campo</p>
            </div>
            <div class="hidden sm:block h-5 w-px bg-gray-200 dark:bg-gray-700 shrink-0"></div>

            {{-- Buscador centrado (sincronizado con Alpine store) --}}
            <div class="flex-1 flex justify-center">
                <div class="relative w-full max-w-md">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0" />
                    </svg>
                    <input x-model="q" type="text" autocomplete="off"
                        placeholder="Bin, cuartel, chofer, patente, exportadora…" class="w-full pl-9 pr-8 py-2 text-sm rounded-xl
                              border border-gray-200 dark:border-gray-700
                              bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100
                              focus:ring-2 focus:ring-indigo-500 focus:border-transparent
                              outline-none transition placeholder-gray-400">
                    <button x-show="q" @click="q = ''"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Acciones --}}
            <div class="flex items-center gap-2 shrink-0">
                {{-- Ver por camión --}}
                <a href="{{ route('agrak.index', array_merge(request()->all(), ['view' => 'group'])) }}" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl
                      border border-gray-200 dark:border-gray-700
                      bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300
                      hover:bg-gray-50 dark:hover:bg-gray-700 transition active:scale-95">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 17h8m-4-4v4M3 11h18M3 11l2-5h14l2 5M3 11v6a1 1 0 001 1h1m12 0h1a1 1 0 001-1v-6" />
                    </svg>
                    <span>Por camión</span>
                </a>

                {{-- Exportar Excel --}}
                @if(Route::has('agrak.export'))
                    <a href="{{ route('agrak.export', request()->all()) }}" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl
                          bg-emerald-600 hover:bg-emerald-700 active:scale-95
                          text-white transition shadow-sm shadow-emerald-200 dark:shadow-emerald-900">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        <span class="hidden sm:inline">Excel</span>
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
        [x-cloak] {
            display: none !important;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(8px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .au {
            animation: fadeUp .4s cubic-bezier(.22, 1, .36, 1) both
        }

        .d1 {
            animation-delay: .04s
        }

        .d2 {
            animation-delay: .08s
        }

        .d3 {
            animation-delay: .12s
        }

        /* Page */
        .page-bg {
            background: #f1f5f9;
            min-height: 100%
        }

        .dark .page-bg {
            background: #0d1117
        }

        /* Card wrapper */
        .t-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden
        }

        .dark .t-card {
            background: #161c2c;
            border-color: #1e2a3b
        }

        /* Desktop table */
        .dt {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px
        }

        .dt thead tr {
            background: #f8fafc;
            border-bottom: 1px solid #f1f5f9
        }

        .dark .dt thead tr {
            background: #111827;
            border-bottom-color: #1e2a3b
        }

        .dt th {
            padding: 10px 14px;
            text-align: left;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #94a3b8;
            white-space: nowrap
        }

        .dt td {
            padding: 11px 14px;
            border-bottom: 1px solid #f8fafc;
            color: #334155;
            vertical-align: middle
        }

        .dark .dt td {
            border-bottom-color: #1a2232;
            color: #cbd5e1
        }

        .dt tbody tr:last-child td {
            border-bottom: none
        }

        .dt tbody tr:hover td {
            background: #f8fafc
        }

        .dark .dt tbody tr:hover td {
            background: #1a2436
        }

        /* Mobile card */
        .m-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 14px 16px
        }

        .dark .m-card {
            background: #161c2c;
            border-color: #1e2a3b
        }

        /* Buttons */
        .btn-sm {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            transition: background .15s
        }

        .btn-indigo {
            background: #eef2ff;
            color: #4f46e5
        }

        .btn-indigo:hover {
            background: #e0e7ff
        }

        .btn-gray {
            background: #f1f5f9;
            color: #475569
        }

        .btn-gray:hover {
            background: #e2e8f0
        }

        .dark .btn-indigo {
            background: rgba(99, 102, 241, .15);
            color: #a5b4fc
        }

        .dark .btn-indigo:hover {
            background: rgba(99, 102, 241, .25)
        }

        .dark .btn-gray {
            background: rgba(255, 255, 255, .06);
            color: #94a3b8
        }

        .dark .btn-gray:hover {
            background: rgba(255, 255, 255, .1)
        }

        /* Sort link */
        .sort-link {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            cursor: pointer;
            transition: color .15s
        }

        .sort-link:hover {
            color: #6366f1
        }

        .dark .sort-link:hover {
            color: #a5b4fc
        }

        /* Filter bar */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px
        }

        .flt-select {
            padding: 6px 10px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #475569;
            transition: border-color .15s;
            outline: none;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 8px center;
            padding-right: 26px
        }

        .flt-select:focus {
            border-color: #6366f1
        }

        .dark .flt-select {
            background-color: #161c2c;
            border-color: #1e2a3b;
            color: #94a3b8;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E")
        }

        .flt-btn {
            padding: 6px 14px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: background .15s
        }

        .flt-apply {
            background: #4f46e5;
            color: #fff
        }

        .flt-apply:hover {
            background: #4338ca
        }

        .flt-clear {
            background: #f1f5f9;
            color: #475569
        }

        .flt-clear:hover {
            background: #e2e8f0
        }

        .dark .flt-clear {
            background: rgba(255, 255, 255, .06);
            color: #94a3b8
        }

        .dark .flt-clear:hover {
            background: rgba(255, 255, 255, .1)
        }

        /* Flash */
        .flash-ok {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 13px;
            color: #15803d;
            display: flex;
            align-items: center;
            gap: 8px
        }

        .dark .flash-ok {
            background: rgba(16, 185, 129, .1);
            border-color: rgba(16, 185, 129, .25);
            color: #34d399
        }

        /* Bin badge */
        .bin-badge {
            font-family: monospace;
            font-weight: 700;
            font-size: 13px;
            color: #4f46e5
        }

        .dark .bin-badge {
            color: #a5b4fc
        }

        /* Patente badge */
        .pat-badge {
            display: inline-block;
            font-family: monospace;
            font-weight: 700;
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 5px;
            background: #f1f5f9;
            color: #475569
        }

        .dark .pat-badge {
            background: rgba(255, 255, 255, .06);
            color: #94a3b8
        }

        /* Mobile search (hidden on sm+) */
        .mob-search {
            position: relative
        }

        .mob-search svg {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 14px;
            height: 14px;
            color: #9ca3af;
            pointer-events: none
        }

        .mob-search input {
            width: 100%;
            padding: 10px 10px 10px 36px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: #fff;
            font-size: 14px;
            outline: none
        }

        .dark .mob-search input {
            background: #161c2c;
            border-color: #1e2a3b;
            color: #f1f5f9
        }
    </style>

    <div class="page-bg" x-data="agrakIndex({{ $rowsJson }})">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">

            {{-- Flash --}}
            @if(session('ok'))
                <div class="flash-ok au d1">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ session('ok') }}
                </div>
            @endif

            {{-- Buscador móvil --}}
            <div class="sm:hidden mob-search au d1">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0" />
                </svg>
                <input x-model="q" type="text" inputmode="search" placeholder="Bin, chofer, patente…">
            </div>

            {{-- ── Barra de filtros (server-side) ───────────────────── --}}
            <form method="GET" action="{{ route('agrak.index') }}" class="t-card au d1">
                <div class="px-4 py-3 filter-bar">
                    <input type="hidden" name="q" value="{{ $q }}">

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

                    {{-- Hidden order params --}}
                    <input type="hidden" name="order_by" value="{{ $orderBy ?? '' }}">
                    <input type="hidden" name="dir" value="{{ $dir ?? 'desc' }}">

                    <button type="submit" class="flt-btn flt-apply">Filtrar</button>

                    @if($campo || $cuartel || $especie || $q)
                        <a href="{{ route('agrak.index') }}" class="flt-btn flt-clear">Limpiar</a>
                    @endif

                    {{-- Filtros activos como chips --}}
                    @foreach(array_filter(['Campo' => $campo, 'Cuartel' => $cuartel, 'Especie' => $especie]) as $label => $val)
                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-1 rounded-full
                             bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400">
                            {{ $label }}: {{ $val }}
                            <a href="{{ route('agrak.index', array_merge(request()->except(strtolower($label)))) }}"
                                class="ml-0.5 text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-200">✕</a>
                        </span>
                    @endforeach

                    {{-- Separador + por camión (mobile) --}}
                    <div class="sm:hidden ml-auto">
                        <a href="{{ route('agrak.index', array_merge(request()->all(), ['view' => 'group'])) }}"
                            class="flt-btn flt-clear flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 17h8m-4-4v4M3 11h18M3 11l2-5h14l2 5M3 11v6a1 1 0 001 1h1m12 0h1a1 1 0 001-1v-6" />
                            </svg>
                            Por camión
                        </a>
                    </div>

                    {{-- Stats --}}
                    <div class="ml-auto hidden sm:flex items-center gap-3 text-xs text-gray-400">
                        <span>
                            <span x-text="filtered.length" class="font-bold text-gray-700 dark:text-gray-200"></span>
                            <span x-show="filtered.length !== {{ $items->total() }}"
                                class="ml-0.5 text-gray-400 dark:text-gray-600">
                                / {{ $items->total() }} total
                            </span>
                        </span>
                        @if($items->total() > 0)
                            <span class="text-gray-200 dark:text-gray-700">|</span>
                            <span class="font-semibold text-gray-500 dark:text-gray-400">
                                Pág. {{ $items->currentPage() }} / {{ $items->lastPage() }}
                            </span>
                        @endif
                    </div>
                </div>
            </form>

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
                                        <div class="text-gray-800 dark:text-gray-200 font-medium text-xs"
                                            x-text="r.cuartel ?? '—'"></div>
                                        <div class="text-gray-400 text-[11px]" x-show="r.campo" x-text="r.campo"></div>
                                    </td>
                                    <td>
                                        <div class="text-gray-800 dark:text-gray-200 text-xs font-medium"
                                            x-text="r.especie ?? '—'"></div>
                                        <div class="text-gray-400 text-[11px]" x-show="r.variedad" x-text="r.variedad">
                                        </div>
                                    </td>
                                    <td class="text-gray-500 dark:text-gray-400 text-xs whitespace-nowrap">
                                        <span x-text="r.fecha ?? '—'"></span>
                                        <span class="text-gray-300 dark:text-gray-700 ml-0.5" x-show="r.hora"
                                            x-text="r.hora"></span>
                                    </td>
                                    <td class="text-right font-bold text-gray-700 dark:text-gray-300 tabular-nums"
                                        x-text="r.bandejas ?? '—'"></td>
                                    <td class="text-gray-500 dark:text-gray-400 text-xs" x-text="r.maquina ?? '—'"></td>
                                    <td class="text-gray-700 dark:text-gray-300 text-xs" x-text="r.chofer ?? '—'"></td>
                                    <td>
                                        <span class="pat-badge" x-text="r.patente ?? '—'"></span>
                                    </td>
                                    <td class="text-gray-500 dark:text-gray-400 text-xs max-w-[140px] truncate"
                                        x-text="r.exportadora ?? '—'"></td>
                                    <td class="text-right">
                                        <a :href="`{{ url('/agrak') }}/${r.id}`" class="btn-sm btn-indigo">
                                            Ver
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
            <div class="lg:hidden space-y-2 au d2">
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
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs mb-3">
                            <div>
                                <p class="text-gray-400 dark:text-gray-600 mb-0.5">Fecha</p>
                                <p class="font-semibold text-gray-700 dark:text-gray-300"
                                    x-text="r.fecha ? r.fecha + (r.hora ? ' ' + r.hora : '') : '—'"></p>
                            </div>
                            <div>
                                <p class="text-gray-400 dark:text-gray-600 mb-0.5">Especie</p>
                                <p class="font-semibold text-gray-700 dark:text-gray-300"
                                    x-text="r.especie + (r.variedad ? ' · ' + r.variedad : '')"></p>
                            </div>
                            <div>
                                <p class="text-gray-400 dark:text-gray-600 mb-0.5">Chofer</p>
                                <p class="font-medium text-gray-600 dark:text-gray-400" x-text="r.chofer ?? '—'"></p>
                            </div>
                            <div>
                                <p class="text-gray-400 dark:text-gray-600 mb-0.5">Exportadora</p>
                                <p class="font-medium text-gray-600 dark:text-gray-400 truncate"
                                    x-text="r.exportadora ?? '—'"></p>
                            </div>
                        </div>

                        {{-- Footer --}}
                        <div
                            class="flex items-center justify-between border-t border-gray-100 dark:border-gray-800 pt-2.5">
                            <span class="text-[11px] text-gray-400"
                                x-text="r.maquina ? 'Máq: ' + r.maquina : ''"></span>
                            <a :href="`{{ url('/agrak') }}/${r.id}`" class="btn-sm btn-indigo">Ver detalle</a>
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

        /* ── Header ── */
        function agrakHeader() {
            return {
                get q() { return Alpine.store('agrak').q; },
                set q(val) { Alpine.store('agrak').q = val; },
            };
        }
    </script>

</x-app-layout>