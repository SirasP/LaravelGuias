<x-app-layout>

    {{-- ═══════════════════════════════════════
    HEADER
    ═══════════════════════════════════════ --}}
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 w-full">

            {{-- Título + stats --}}
            <div class="flex items-center gap-4 min-w-0">
                <div class="hidden sm:block">
                    <h2 class="text-sm font-bold text-gray-800 dark:text-gray-100 leading-none">Match ODOO</h2>

                </div>
            </div>

            {{-- Buscador desktop --}}
            <form method="GET" class="hidden lg:flex items-center gap-2 flex-1 max-w-xl">
                <div class="relative flex-1">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0" />
                    </svg>
                    <input name="q" value="{{ $q }}" placeholder="Contacto, guía, patente…" class="w-full pl-9 pr-3 py-2 text-sm rounded-xl
                              border border-gray-200 dark:border-gray-700
                              bg-white dark:bg-gray-900
                              text-gray-900 dark:text-gray-100
                              focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition">
                </div>

                <select name="exists" class="px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700
                           bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-200
                           focus:ring-2 focus:ring-indigo-500 outline-none transition">
                    <option value="" {{ ($exists ?? '') === '' ? 'selected' : '' }}>Todos</option>
                    <option value="1" {{ ($exists ?? '') === '1' ? 'selected' : '' }}>Con match</option>
                    <option value="0" {{ ($exists ?? '') === '0' ? 'selected' : '' }}>Sin match</option>
                </select>

                <button type="submit" class="px-4 py-2 text-sm font-semibold rounded-xl
                           bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900
                           hover:bg-gray-700 dark:hover:bg-white transition">
                    Buscar
                </button>

                @if($q || ($exists ?? '') !== '')
                    <a href="{{ route('excel_out_transfers.index') }}"
                        class="px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700
                                          text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                        Limpiar
                    </a>
                @endif
            </form>

            {{-- Descargar Excel --}}
            <a href="{{ route('excel_out_transfers.export', request()->query()) }}" class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-bold rounded-xl
   bg-emerald-600 hover:bg-emerald-700 active:scale-95
   text-white transition-all shadow-md shadow-emerald-300/40
   dark:shadow-emerald-900/40">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                Exportar Excel
            </a>

        </div>
    </x-slot>

    {{-- ═══════════════════════════════════════
    ESTILOS
    ═══════════════════════════════════════ --}}
    <style>
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
            animation: fadeUp .4s cubic-bezier(.22, 1, .36, 1) both;
        }

        .d1 {
            animation-delay: .04s
        }

        .d2 {
            animation-delay: .08s
        }

        .d3 {
            animation-delay: .1s
        }

        .page-wrap {
            background: #f1f5f9;
            min-height: 100%;
        }

        .dark .page-wrap {
            background: #0d1117;
        }

        /* Table card */
        .t-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
        }

        .dark .t-card {
            background: #161c2c;
            border-color: #1e2a3b;
        }

        /* Table */
        .dt {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .dt thead tr {
            border-bottom: 1px solid #f1f5f9;
            background: #f8fafc;
        }

        .dark .dt thead tr {
            border-bottom-color: #1e2a3b;
            background: #111827;
        }

        .dt th {
            padding: 11px 16px;
            text-align: left;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #94a3b8;
            white-space: nowrap;
        }

        .dt td {
            padding: 12px 16px;
            border-bottom: 1px solid #f8fafc;
            color: #334155;
            vertical-align: middle;
        }

        .dark .dt td {
            border-bottom-color: #1a2232;
            color: #cbd5e1;
        }

        .dt tbody tr:last-child td {
            border-bottom: none;
        }

        .dt tbody tr {
            transition: background .1s;
        }

        .dt tbody tr:hover td {
            background: #f8fafc;
        }

        .dark .dt tbody tr:hover td {
            background: #1a2436;
        }

        /* Badges */
        .badge-match {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            background: #dcfce7;
            color: #15803d;
        }

        .badge-nomatch {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            background: #f1f5f9;
            color: #64748b;
        }

        .dark .badge-match {
            background: rgba(16, 185, 129, .15);
            color: #34d399;
        }

        .dark .badge-nomatch {
            background: rgba(255, 255, 255, .05);
            color: #475569;
        }

        /* Mobile cards */
        .m-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 14px 16px;
        }

        .dark .m-card {
            background: #161c2c;
            border-color: #1e2a3b;
        }

        /* Import report */
        .ir-badge-imported {
            background: #dcfce7;
            color: #15803d;
        }

        .ir-badge-duplicate {
            background: #fef3c7;
            color: #92400e;
        }

        .ir-badge-skip {
            background: #f1f5f9;
            color: #64748b;
        }

        .dark .ir-badge-imported {
            background: rgba(16, 185, 129, .15);
            color: #34d399;
        }

        .dark .ir-badge-duplicate {
            background: rgba(245, 158, 11, .15);
            color: #fcd34d;
        }

        .dark .ir-badge-skip {
            background: rgba(255, 255, 255, .05);
            color: #475569;
        }
    </style>

    <div class="page-wrap">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-2 space-y-2">
            {{-- Título + stats --}}
            <div class="hidden sm:flex items-center gap-6 mt-2.5">

                {{-- Total --}}
                <div class="flex items-baseline gap-2">
                    <span class="text-xs uppercase tracking-wider text-gray-400 font-semibold">
                        Total
                    </span>
                    <span class="text-lg font-black text-gray-800 dark:text-gray-100">
                        {{ $total }}
                    </span>
                </div>

                {{-- Match --}}
                <div class="flex items-baseline gap-2">
                    <span class="text-xs uppercase tracking-wider text-emerald-500 font-semibold">
                        Match
                    </span>
                    <span class="text-lg font-black text-emerald-600 dark:text-emerald-400">
                        {{ $matched }}
                    </span>
                    <span class="text-xs text-gray-400">
                        ({{ round($matched / max($total, 1) * 100) }}%)
                    </span>
                </div>

                {{-- Sin Match --}}
                <div class="flex items-baseline gap-2">
                    <span class="text-xs uppercase tracking-wider text-red-500 font-semibold">
                        Sin match
                    </span>
                    <span class="text-lg font-black text-red-600 dark:text-red-400">
                        {{ $unmatched }}
                    </span>
                </div>

            </div>


            {{-- Buscador móvil --}}
            <form method="GET" class="lg:hidden flex gap-2 au d1">
                <div class="relative flex-1">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0" />
                    </svg>
                    <input name="q" value="{{ $q }}" inputmode="search" enterkeyhint="search"
                        placeholder="Contacto, guía, patente…" class="w-full pl-9 pr-3 py-2.5 text-sm rounded-xl
                          border border-gray-200 dark:border-gray-700
                          bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100
                          focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none">
                </div>
                <button type="submit"
                    class="px-4 py-2 text-sm font-bold rounded-xl bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900">
                    Buscar
                </button>
            </form>

            {{-- Stats móvil --}}
            <div class="lg:hidden grid grid-cols-3 gap-2 au d1">

                {{-- Total --}}
                <div class="rounded-xl border border-gray-200 dark:border-gray-700
                bg-white dark:bg-gray-800 p-3 text-center">
                    <div class="text-[10px] uppercase tracking-wider text-gray-400 font-semibold">
                        Total
                    </div>
                    <div class="text-lg font-black text-gray-800 dark:text-gray-100">
                        {{ $total }}
                    </div>
                </div>

                {{-- Match --}}
                <div class="rounded-xl border border-emerald-200 dark:border-emerald-800
                bg-emerald-50 dark:bg-emerald-900/20 p-3 text-center">
                    <div
                        class="text-[10px] uppercase tracking-wider text-emerald-600 dark:text-emerald-400 font-semibold">
                        Match
                    </div>
                    <div class="text-lg font-black text-emerald-600 dark:text-emerald-400">
                        {{ $matched }}
                    </div>
                    <div class="text-[10px] text-emerald-500 opacity-80">
                        {{ round($matched / max($total, 1) * 100) }}%
                    </div>
                </div>

                {{-- Sin Match --}}
                <div class="rounded-xl border border-red-200 dark:border-red-800
                bg-red-50 dark:bg-red-900/20 p-3 text-center">
                    <div class="text-[10px] uppercase tracking-wider text-red-600 dark:text-red-400 font-semibold">
                        Sin match
                    </div>
                    <div class="text-lg font-black text-red-600 dark:text-red-400">
                        {{ $unmatched }}
                    </div>
                </div>

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