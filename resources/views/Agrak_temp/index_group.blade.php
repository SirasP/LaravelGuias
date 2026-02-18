{{-- resources/views/agrak/index_group.blade.php --}}
<x-app-layout>

{{-- ═══════════════════════════════════════════════════
     HEADER
═══════════════════════════════════════════════════ --}}
<x-slot name="header">
    <div class="flex items-center justify-between w-full gap-4">
        <div class="flex items-center gap-2.5 min-w-0">
            <a href="{{ route('agrak.index', request()->except('view')) }}"
               class="hidden sm:inline-flex items-center gap-1 text-xs text-gray-400 hover:text-gray-700
                      dark:hover:text-gray-200 transition shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                AGRAK
            </a>
            <span class="hidden sm:block text-gray-200 dark:text-gray-700 text-sm">/</span>
            <div>
                <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Viajes por camión</h2>
                <p class="text-xs text-gray-400 mt-0.5 hidden sm:block">Agrupados por patente · fecha</p>
            </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            {{-- Volver (mobile) --}}
            <a href="{{ route('agrak.index', request()->except('view')) }}"
               class="sm:hidden text-xs font-medium text-gray-500 hover:text-gray-800 dark:hover:text-gray-200 transition">
                ← Lista
            </a>

            {{-- Exportar Excel --}}
            <a href="{{ route('agrak.export') }}"
               class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl
                      bg-emerald-600 hover:bg-emerald-700 active:scale-95
                      text-white transition shadow-sm shadow-emerald-200 dark:shadow-emerald-900">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                <span class="hidden sm:inline">Exportar todo</span>
                <span class="sm:hidden">Excel</span>
            </a>
        </div>
    </div>
</x-slot>

@php
/**
 * Odoo badge — devuelve array [clase, icono, texto]
 * para renderizar limpio en Blade sin {!! !!}
 */
function odooStatus($m): array {
    if (!$m) return ['badge-gray',   '—',  'Sin match'];
    return match($m->estado) {
        'ok'       => ['badge-green',  '✔',  'Odoo OK ('       . $m->score . ')'],
        'probable' => ['badge-yellow', '⚠',  'Odoo probable (' . $m->score . ')'],
        default    => ['badge-red',    '✖',  'Odoo manual ('   . $m->score . ')'],
    };
}
@endphp

<style>
    [x-cloak] { display:none !important; }

    @keyframes fadeUp { from { opacity:0; transform:translateY(8px) } to { opacity:1; transform:translateY(0) } }
    .au { animation:fadeUp .4s cubic-bezier(.22,1,.36,1) both }
    .d1 { animation-delay:.04s } .d2 { animation-delay:.08s } .d3 { animation-delay:.12s }

    /* Page */
    .page-bg       { background:#f1f5f9; min-height:100% }
    .dark .page-bg { background:#0d1117 }

    /* Card */
    .g-card        { background:#fff; border:1px solid #e2e8f0; border-radius:18px; overflow:hidden }
    .dark .g-card  { background:#161c2c; border-color:#1e2a3b }
    .g-head        { padding:14px 20px; background:#f8fafc; border-bottom:1px solid #f1f5f9;
                     display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap }
    .dark .g-head  { background:#111827; border-bottom-color:#1e2a3b }

    /* Trip row */
    .trip-btn      { width:100%; text-align:left; padding:14px 20px; display:flex;
                     align-items:flex-start; justify-content:space-between; gap:16px;
                     transition:background .15s; cursor:pointer; border:none; background:transparent }
    .trip-btn:hover { background:#f8fafc }
    .dark .trip-btn:hover { background:#1a2436 }

    /* Detail table */
    .dt { width:100%; border-collapse:collapse; font-size:12px }
    .dt thead tr { background:#f8fafc; border-bottom:1px solid #f1f5f9 }
    .dark .dt thead tr { background:#111827; border-bottom-color:#1e2a3b }
    .dt th { padding:8px 12px; text-align:left; font-size:10px; font-weight:700;
             letter-spacing:.08em; text-transform:uppercase; color:#94a3b8; white-space:nowrap }
    .dt td { padding:9px 12px; border-bottom:1px solid #f8fafc; color:#334155; vertical-align:middle }
    .dark .dt td { border-bottom-color:#1a2232; color:#cbd5e1 }
    .dt tbody tr:last-child td { border-bottom:none }
    .dt tbody tr:hover td { background:#f8fafc }
    .dark .dt tbody tr:hover td { background:#1a2436 }

    /* Badges — Odoo status */
    .badge-green  { background:#dcfce7; color:#15803d }
    .badge-yellow { background:#fef9c3; color:#854d0e }
    .badge-red    { background:#fee2e2; color:#dc2626 }
    .badge-gray   { background:#f1f5f9; color:#475569 }
    .dark .badge-green  { background:rgba(22,163,74,.15);  color:#4ade80 }
    .dark .badge-yellow { background:rgba(234,179,8,.15);  color:#facc15 }
    .dark .badge-red    { background:rgba(220,38,38,.15);  color:#f87171 }
    .dark .badge-gray   { background:rgba(255,255,255,.06);color:#94a3b8 }
    .odoo-badge { display:inline-flex; align-items:center; gap:4px; padding:3px 9px;
                  border-radius:999px; font-size:11px; font-weight:700 }

    /* Stat pills */
    .stat-pill { display:inline-flex; align-items:center; gap:3px; padding:4px 10px;
                 border-radius:8px; font-size:11px; font-weight:600; white-space:nowrap;
                 background:#f1f5f9; color:#475569 }
    .dark .stat-pill { background:rgba(255,255,255,.06); color:#94a3b8 }
    .stat-pill b { color:#1e293b; font-weight:800 }
    .dark .stat-pill b { color:#f1f5f9 }
    .stat-pill-indigo { background:#eef2ff; color:#4338ca }
    .dark .stat-pill-indigo { background:rgba(99,102,241,.15); color:#a5b4fc }
    .stat-pill-emerald { background:#ecfdf5; color:#065f46 }
    .dark .stat-pill-emerald { background:rgba(16,185,129,.12); color:#34d399 }
    .stat-pill-amber { background:#fffbeb; color:#92400e }
    .dark .stat-pill-amber { background:rgba(245,158,11,.12); color:#fcd34d }

    /* Mode filter pills */
    .mode-pill { display:inline-flex; align-items:center; gap:5px; padding:6px 14px;
                 border-radius:10px; font-size:12px; font-weight:700; transition:all .15s;
                 text-decoration:none }
    .mode-pill-default { background:#fff; border:1px solid #e2e8f0; color:#475569 }
    .dark .mode-pill-default { background:#161c2c; border-color:#1e2a3b; color:#94a3b8 }
    .mode-pill-default:hover { border-color:#6366f1; color:#4f46e5 }
    .mode-pill-active-all     { background:#1e293b; color:#fff; border:1px solid transparent }
    .dark .mode-pill-active-all { background:#f1f5f9; color:#1e293b }
    .mode-pill-active-pending { background:#dc2626; color:#fff; border:1px solid transparent }
    .mode-pill-active-ok      { background:#16a34a; color:#fff; border:1px solid transparent }

    /* Patente chip */
    .pat-chip { display:inline-flex; align-items:center; padding:4px 10px; border-radius:7px;
                background:#1e293b; color:#fff; font-family:monospace; font-weight:800;
                font-size:13px; letter-spacing:.05em; text-transform:uppercase }
    .dark .pat-chip { background:#f1f5f9; color:#1e293b }

    /* Bin code */
    .bin-code { font-family:monospace; font-weight:700; font-size:12px;
                color:#4f46e5 }
    .dark .bin-code { color:#a5b4fc }

    /* Flash */
    .flash-ok { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px;
                padding:12px 16px; font-size:13px; color:#15803d;
                display:flex; align-items:center; gap:8px }
    .dark .flash-ok { background:rgba(16,185,129,.1); border-color:rgba(16,185,129,.25); color:#34d399 }

    /* Chevron */
    .chevron { width:16px; height:16px; transition:transform .2s; flex-shrink:0; color:#94a3b8 }
    .chevron.open { transform:rotate(180deg) }

    /* Warn chip (registrar camión) */
    .warn-chip { display:inline-flex; align-items:center; gap:3px; padding:2px 7px; border-radius:999px;
                 font-size:10px; font-weight:700; background:#fef3c7; color:#92400e; text-decoration:none }
    .warn-chip:hover { background:#fde68a }
    .dark .warn-chip { background:rgba(245,158,11,.15); color:#fcd34d }

    /* Divider trip */
    .trip-divider { border-top:1px solid #f1f5f9 }
    .dark .trip-divider { border-top-color:#1e2a3b }
</style>

<div class="page-bg">
<div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

    {{-- Flash --}}
    @if(session('ok'))
    <div class="flash-ok au d1">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
        </svg>
        {{ session('ok') }}
    </div>
    @endif

    {{-- ── Filtros de modo ─────────────────────── --}}
    <div class="flex items-center gap-2 flex-wrap au d1">
        @php
            $modo = request('modo', 'all');
        @endphp

        <a href="{{ request()->fullUrlWithQuery(['modo' => 'all']) }}"
           class="mode-pill {{ $modo === 'all' ? 'mode-pill-active-all' : 'mode-pill-default' }}">
            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                <path d="M3 4a1 1 0 011-1h12a1 1 0 010 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 010 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h6a1 1 0 010 2H4a1 1 0 01-1-1z"/>
            </svg>
            Todos
            <span class="text-[10px] font-bold opacity-60">{{ $groups->total() }}</span>
        </a>

        <a href="{{ request()->fullUrlWithQuery(['modo' => 'pendientes']) }}"
           class="mode-pill {{ $modo === 'pendientes' ? 'mode-pill-active-pending' : 'mode-pill-default' }}">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Pendientes
        </a>

        <a href="{{ request()->fullUrlWithQuery(['modo' => 'ok']) }}"
           class="mode-pill {{ $modo === 'ok' ? 'mode-pill-active-ok' : 'mode-pill-default' }}">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
            Confirmados
        </a>

        {{-- Total pág --}}
        <span class="ml-auto text-xs text-gray-400 hidden sm:block">
            {{ $groups->firstItem() }}–{{ $groups->lastItem() }} de {{ $groups->total() }} grupos
        </span>
    </div>

    {{-- ── LISTA DE GRUPOS ─────────────────────── --}}
    @forelse($groups as $gi => $g)
        @php
            $fechaLabel  = $g->fecha_registro
                ? \Carbon\Carbon::parse($g->fecha_registro)->format('d-m-Y')
                : '—';
            $trips       = $g->trips ?? [];
            $groupMatch  = $g->odoo_match ?? null;
            [$odooClass, $odooIcon, $odooText] = odooStatus($groupMatch);

            // Totales del grupo
            $totalBins     = collect($trips)->sum(fn($t) => (int)($t['bins'] ?? 0));
            $totalBandejas = collect($trips)->sum(fn($t) => (int)($t['total_bandejas'] ?? 0));
        @endphp

        <div class="g-card au" style="animation-delay: {{ min($gi * 0.04, 0.3) }}s">

            {{-- ── Cabecera del grupo ─── --}}
            <div class="g-head">
                <div class="flex items-center gap-2.5 flex-wrap min-w-0">
                    <span class="pat-chip">{{ $g->patente_norm ?: '—' }}</span>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $fechaLabel }}</span>
                    <span class="text-gray-200 dark:text-gray-700">·</span>
                    <span class="odoo-badge {{ $odooClass }}">
                        {{ $odooIcon }} {{ $odooText }}
                    </span>
                    @if($groupMatch)
                        <span class="text-[11px] text-gray-400 font-mono">
                            #{{ $groupMatch->excel_out_transfer_id }}
                        </span>
                    @endif
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <span class="stat-pill">
                        {{ count($trips) }} viaje{{ count($trips) !== 1 ? 's' : '' }}
                    </span>
                    <span class="stat-pill stat-pill-indigo">
                        <b>{{ $totalBins }}</b> bins
                    </span>
                    <span class="stat-pill stat-pill-emerald">
                        <b>{{ number_format($totalBandejas) }}</b> bdjas
                    </span>
                </div>
            </div>

            {{-- ── Viajes ─────────────────────────── --}}
            <div>
                @forelse($trips as $idx => $trip)
                    @php
                        $horaIni  = $trip['hora_inicio'] ?? '—';
                        $horaFin  = $trip['hora_fin']    ?? '—';
                        $bins     = (int)($trip['bins'] ?? 0);
                        $bandejas = (int)($trip['total_bandejas'] ?? 0);
                        $chofer   = $trip['nombre_chofer'] ?? '—';
                        $export   = $trip['exportadora']   ?? '—';
                        $items    = collect($trip['items'] ?? []);
                    @endphp

                    <div x-data="{ open: false }"
                         class="{{ $idx > 0 ? 'trip-divider' : '' }}">

                        {{-- Botón expandir --}}
                        <button @click="open = !open" class="trip-btn">

                            <div class="min-w-0 space-y-1.5">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase">
                                        {{ $chofer }}
                                    </span>
                                    <span class="text-xs text-gray-400 font-semibold">
                                        Viaje #{{ $idx + 1 }}
                                    </span>
                                    @if(!($trip['camion_existe'] ?? true) && ($trip['patente'] ?? null))
                                        <a href="{{ route('agrak.create', ['patente' => $trip['patente']]) }}"
                                           class="warn-chip"
                                           @click.stop>
                                            ⚠ Registrar camión
                                        </a>
                                    @endif
                                </div>

                                <div class="flex flex-wrap gap-1.5">
                                    <span class="stat-pill stat-pill-indigo">
                                        ⏱ {{ $horaIni }} – {{ $horaFin }}
                                    </span>
                                    <span class="stat-pill stat-pill-emerald">
                                        {{ $export }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <span class="stat-pill">
                                    <b>{{ $bins }}</b> bins
                                </span>
                                <span class="stat-pill stat-pill-amber">
                                    <b>{{ number_format($bandejas) }}</b> bdjas
                                </span>
                                <svg class="chevron" :class="{ open }"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </div>
                        </button>

                        {{-- Tabla de bins --}}
                        <div x-show="open" x-collapse
                             class="border-t border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/20">

                            @if($items->count())
                            <div class="px-5 pt-4 pb-1">
                                <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2">
                                    Bins del viaje — {{ $items->count() }} registros
                                </p>
                            </div>
                            <div class="overflow-x-auto px-5 pb-4">
                                <div class="rounded-xl overflow-hidden border border-gray-100 dark:border-gray-800">
                                    <table class="dt">
                                        <thead>
                                            <tr>
                                                <th>Hora</th>
                                                <th>Bin</th>
                                                <th class="text-right">Bandejas</th>
                                                <th>Máquina</th>
                                                <th>Cuartel</th>
                                                <th class="text-right w-16"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($items as $bin)
                                            <tr>
                                                <td class="text-gray-400 text-[11px] font-mono">{{ $bin->hora_registro }}</td>
                                                <td>
                                                    <span class="bin-code">{{ $bin->codigo_bin }}</span>
                                                </td>
                                                <td class="text-right font-bold tabular-nums">
                                                    {{ number_format($bin->numero_bandejas_palet) }}
                                                </td>
                                                <td class="text-gray-500 dark:text-gray-400">{{ $bin->maquina }}</td>
                                                <td class="text-gray-600 dark:text-gray-400">{{ $bin->cuartel }}</td>
                                                <td class="text-right">
                                                    <a href="{{ route('agrak.show', $bin->id) }}"
                                                       class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold
                                                              bg-indigo-50 text-indigo-600 hover:bg-indigo-100
                                                              dark:bg-indigo-900/20 dark:text-indigo-400 dark:hover:bg-indigo-900/40 transition">
                                                        Ver
                                                    </a>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            @else
                            <div class="px-5 py-4 text-sm text-gray-400">Sin bins registrados en este viaje.</div>
                            @endif
                        </div>

                    </div>
                @empty
                    <div class="px-5 py-4 text-sm text-gray-400">No se detectaron viajes en este grupo.</div>
                @endforelse
            </div>

        </div>
    @empty
        <div class="g-card au d1 px-6 py-14 text-center">
            <div class="w-12 h-12 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M8 17h8m-4-4v4M3 11h18M3 11l2-5h14l2 5M3 11v6a1 1 0 001 1h1m12 0h1a1 1 0 001-1v-6"/>
                </svg>
            </div>
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Sin grupos</p>
            <p class="text-xs text-gray-400 mt-1">No hay viajes para el filtro seleccionado.</p>
        </div>
    @endforelse

    {{-- Paginación --}}
    <div class="au d3" data-turbo="false">{{ $groups->links() }}</div>

</div>
</div>

</x-app-layout>