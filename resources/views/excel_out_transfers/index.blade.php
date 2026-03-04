<x-app-layout>

    {{-- ═══════════════════════════════════════
    HEADER
    ═══════════════════════════════════════ --}}
    <x-slot name="header">
        <div class="flex items-center gap-3 w-full">

            {{-- Título (desktop) --}}
            <div class="hidden sm:block shrink-0">
                <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Match ODOO</h2>
                <p class="text-xs text-gray-400 mt-0.5">Transferencias</p>
            </div>
            <div class="hidden sm:block h-5 w-px bg-gray-200 dark:bg-gray-700 shrink-0"></div>

            {{-- Buscador centrado (desktop) --}}
            <form method="GET" class="hidden sm:flex items-center gap-2 flex-1 max-w-xl">
                <div class="relative flex-1">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0" />
                    </svg>
                    <input name="q" value="{{ $q }}" placeholder="Contacto, guía, patente…"
                        class="w-full pl-9 pr-3 py-2 text-sm rounded-xl
                              border border-gray-200 dark:border-gray-700
                              bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100
                              focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition placeholder-gray-400">
                </div>

                <select name="exists" class="flt-select">
                    <option value="" {{ ($exists ?? '') === '' ? 'selected' : '' }}>Todos</option>
                    <option value="1" {{ ($exists ?? '') === '1' ? 'selected' : '' }}>Con match</option>
                    <option value="0" {{ ($exists ?? '') === '0' ? 'selected' : '' }}>Sin match</option>
                </select>

                <button type="submit" class="flt-btn flt-apply">Buscar</button>

                @if($q || ($exists ?? '') !== '')
                    <a href="{{ route('excel_out_transfers.index') }}" class="flt-btn flt-clear">Limpiar</a>
                @endif
            </form>

            {{-- Exportar Excel --}}
            <a href="{{ route('excel_out_transfers.export', request()->query()) }}"
                class="shrink-0 inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl
                      bg-emerald-600 hover:bg-emerald-700 active:scale-95
                      text-white transition shadow-sm shadow-emerald-200 dark:shadow-emerald-900">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                <span class="hidden sm:inline">Exportar Excel</span>
            </a>

        </div>
    </x-slot>

    <style>
        [x-cloak] { display: none !important; }
        .badge-match { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; background:#dcfce7; color:#15803d }
        .badge-nomatch { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; background:#f1f5f9; color:#64748b }
        .dark .badge-match { background:rgba(16,185,129,.15); color:#34d399 }
        .dark .badge-nomatch { background:rgba(148,163,184,0.1); color:#94a3b8 }
        .ir-badge-imported { background:#dcfce7; color:#15803d } .dark .ir-badge-imported { background:rgba(16,185,129,.15); color:#34d399 }
        .ir-badge-duplicate { background:#fef3c7; color:#92400e } .dark .ir-badge-duplicate { background:rgba(245,158,11,.15); color:#fcd34d }
        .ir-badge-skip { background:#f1f5f9; color:#64748b } .dark .ir-badge-skip { background:rgba(255,255,255,.05); color:#475569 }
    </style>

    <div class="page-bg">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">
            {{-- Buscador móvil --}}
            <div class="sm:hidden au d1">
                <form method="GET" class="space-y-2">
                    <div class="mob-search">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0" />
                        </svg>
                        <input name="q" value="{{ $q }}" type="text" inputmode="search" placeholder="Contacto, guía, patente…">
                    </div>
                    <div class="flex gap-2">
                        <select name="exists" class="flt-select flex-1">
                            <option value="" {{ ($exists ?? '') === '' ? 'selected' : '' }}>Todos</option>
                            <option value="1" {{ ($exists ?? '') === '1' ? 'selected' : '' }}>Con match</option>
                            <option value="0" {{ ($exists ?? '') === '0' ? 'selected' : '' }}>Sin match</option>
                        </select>
                        <button type="submit" class="flt-btn flt-apply">Buscar</button>
                        @if($q || ($exists ?? '') !== '')
                            <a href="{{ route('excel_out_transfers.index') }}" class="flt-btn flt-clear">Limpiar</a>
                        @endif
                    </div>
                </form>
            </div>

            {{-- Stats Grid (KPI Cards) --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-4 au d1">
                <x-kpi-card 
                    label="Total Transferencias" 
                    value="{{ $total }}"
                    iconBg="bg-indigo-50 dark:bg-indigo-900/20"
                    :trend="0"
                >
                    <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 0v10m0-10a2 2 0 012 2h2a2 2 0 012-2"></path></svg>
                </x-kpi-card>

                <x-kpi-card 
                    label="Con Match ODOO" 
                    value="{{ $matched }}"
                    iconBg="bg-emerald-50 dark:bg-emerald-900/20"
                    :pct="$total > 0 ? ($matched / $total * 100) : 0"
                >
                    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </x-kpi-card>

                <x-kpi-card 
                    label="Sin Match" 
                    value="{{ $unmatched }}"
                    iconBg="bg-rose-50 dark:bg-rose-900/20"
                    :pct="$total > 0 ? -($unmatched / $total * 100) : 0"
                >
                    <svg class="w-4 h-4 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </x-kpi-card>
            </div>


            {{-- Flash ok --}}
            @if(session('ok'))
                <div
                    class="rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-300 au d1">
                    {{ session('ok') }}
                </div>
            @endif

            {{-- ── TABLA DESKTOP ────────────────────────────────────────── --}}
            <div class="hidden lg:block t-card au d2">
                <div class="overflow-x-auto">
                    <table class="dt">
                        <thead>
                            <tr>
                                <th>Contacto</th>
                                <th>Fecha prevista</th>
                                <th>Patente</th>
                                <th>Chofer</th>
                                <th>Guía</th>
                                <th>Referencia</th>
                                <th>Detalle</th>
                                <th>
                                    @php
                                        $isActive = ($orderBy ?? '') === 'exists_guia';
                                        $nextDir = ($isActive && ($dir ?? '') === 'asc') ? 'desc' : 'asc';
                                    @endphp
                                    <a href="{{ request()->fullUrlWithQuery(['order_by' => 'exists_guia', 'dir' => $nextDir]) }}"
                                        class="inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200 transition-colors">
                                        Match
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            @if($isActive && ($dir ?? '') === 'asc')
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                                    d="M5 15l7-7 7 7" />
                                            @else
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                                    d="M19 9l-7 7-7-7" />
                                            @endif
                                        </svg>
                                    </a>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $r)
                                <tr>
                                    <td class="font-semibold">{{ $r->contacto ?? '—' }}</td>
                                    <td class="text-gray-500 dark:text-gray-400">
                                        <span>{{ $r->fecha_prevista?->format('d-m-Y') ?? '—' }}</span>
                                        @if($r->fecha_prevista)
                                            <span
                                                class="block text-xs opacity-50">{{ $r->fecha_prevista->format('H:i') }}</span>
                                        @endif
                                    </td>
                                    <td class="font-mono text-sm">{{ $r->patente ?? '—' }}</td>
                                    <td class="uppercase text-gray-600 dark:text-gray-400 text-xs">{{ $r->chofer ?? '—' }}
                                    </td>
                                    <td class="font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                        {{ $r->guia_entrega ?? '—' }}
                                    </td>
                                    <td class="text-gray-500 dark:text-gray-400 text-xs">{{ $r->referencia ?? '—' }}</td>
                                    <td>
                                        <a href="{{ route('excel_out_transfers.show', $r) }}"
                                            class="inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 dark:text-indigo-400
                                                          hover:text-indigo-800 dark:hover:text-indigo-200 transition-colors">
                                            Ver →
                                        </a>
                                    </td>
                                    <td>
                                        @if((int) ($r->exists_guia ?? 0) === 1)
                                            <div class="flex items-center gap-2">
                                                <span class="badge-match">✔ Match</span>
                                                <a href="{{ route('pdf.index', ['q' => $r->guia_entrega]) }}"
                                                    class="text-xs text-emerald-700 dark:text-emerald-400 hover:underline"> Ver →</a>
                                            </div>
                                        @else
                                            <span class="badge-nomatch">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-12 text-center text-gray-400 text-sm">
                                        No hay registros que coincidan con los filtros.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Paginación desktop --}}
            <div class="hidden lg:block au d3" data-turbo="false">
                {{ $rows->links() }}
            </div>

            {{-- ── CARDS MÓVIL ─────────────────────────────────────────── --}}
            <div class="lg:hidden space-y-2 au d2">
                @forelse($rows as $r)
                    <div class="m-card">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <span class="font-bold text-sm text-gray-800 dark:text-gray-100 leading-snug">
                                {{ $r->contacto ?? '—' }}
                            </span>
                            @if((int) ($r->exists_guia ?? 0) === 1)
                                <span class="badge-match shrink-0">✔ Match</span>
                            @else
                                <span class="badge-nomatch shrink-0">Sin match</span>
                            @endif
                        </div>

                        <div class="grid grid-cols-2 gap-y-1.5 text-xs text-gray-500 dark:text-gray-400 mb-3">
                            <div>
                                <span class="text-gray-400 dark:text-gray-600">Guía</span>
                                <p class="font-bold font-mono text-indigo-600 dark:text-indigo-400 text-sm">
                                    {{ $r->guia_entrega ?? '—' }}
                                </p>
                            </div>
                            <div>
                                <span class="text-gray-400 dark:text-gray-600">Patente</span>
                                <p class="font-mono font-semibold text-gray-700 dark:text-gray-300">{{ $r->patente ?? '—' }}
                                </p>
                            </div>
                            <div>
                                <span class="text-gray-400 dark:text-gray-600">Fecha</span>
                                <p class="text-gray-700 dark:text-gray-300">
                                    {{ $r->fecha_prevista?->format('d-m-Y') ?? '—' }}
                                </p>
                            </div>
                            <div>
                                <span class="text-gray-400 dark:text-gray-600">Chofer</span>
                                <p class="uppercase text-gray-700 dark:text-gray-300">{{ $r->chofer ?? '—' }}</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between border-t border-gray-100 dark:border-gray-800 pt-2.5">
                            <a href="{{ route('excel_out_transfers.show', $r) }}"
                                class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                                Ver detalle →
                            </a>
                            @if((int) ($r->exists_guia ?? 0) === 1)
                                <a href="{{ route('pdf.index', ['q' => $r->guia_entrega]) }}"
                                    class="text-xs text-emerald-600 dark:text-emerald-400 hover:underline">
                                    Ver PDF
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="m-card text-center text-sm text-gray-400 py-10">
                        No hay registros.
                    </div>
                @endforelse
            </div>

            {{-- Paginación móvil --}}
            <div class="lg:hidden au d3" data-turbo="false">
                {{ $rows->links() }}
            </div>

            {{-- ── REPORTE DE IMPORTACIÓN ──────────────────────────────── --}}
            @if(session('import_report'))
                <div class="t-card au d3">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                        <p class="text-sm font-bold text-gray-800 dark:text-gray-100">Detalle de importación</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="dt">
                            <thead>
                                <tr>
                                    <th>Archivo</th>
                                    <th>Estado</th>
                                    <th>Guía</th>
                                    <th>Detalle</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(session('import_report') as $rep)
                                    @php $st = $rep['status'] ?? ''; @endphp
                                    <tr>
                                        <td class="text-xs text-gray-500 dark:text-gray-400">{{ $rep['file'] ?? '—' }}</td>
                                        <td>
                                            <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold
                                                                        {{ $st === 'imported' ? 'ir-badge-imported' : '' }}
                                                                        {{ $st === 'duplicate' ? 'ir-badge-duplicate' : '' }}
                                                                        {{ $st === 'skip' ? 'ir-badge-skip' : '' }}">
                                                {{ $st }}
                                            </span>
                                        </td>
                                        <td class="font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                            {{ $rep['guia'] ?? '—' }}
                                        </td>
                                        <td class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $rep['reason'] ?? '' }}

                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

        </div>
    </div>

</x-app-layout>