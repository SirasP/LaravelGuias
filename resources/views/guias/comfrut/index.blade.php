<x-app-layout>

{{-- ═══════════════════════════════════════════════════
     HEADER — buscador centrado + acciones
═══════════════════════════════════════════════════ --}}
<x-slot name="header">
    <div class="flex items-center gap-3 w-full" x-data="comfrutHeader()">

        {{-- Título --}}
        <div class="hidden sm:block shrink-0">
            <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">DTE Mail</h2>
            <p class="text-xs text-gray-400 mt-0.5">Guías COMFRUT</p>
        </div>
        <div class="hidden sm:block h-5 w-px bg-gray-200 dark:bg-gray-700 shrink-0"></div>

        {{-- Buscador centrado --}}
        <div class="flex-1 flex justify-center">
            <div class="relative w-full max-w-md">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input x-model="q" type="text" autocomplete="off"
                       placeholder="Guía, productor, patente, chofer…"
                       class="w-full pl-9 pr-8 py-2 text-sm rounded-xl
                              border border-gray-200 dark:border-gray-700
                              bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100
                              focus:ring-2 focus:ring-indigo-500 focus:border-transparent
                              outline-none transition placeholder-gray-400">
                <button x-show="q" @click="q = ''"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Acciones --}}
        <div class="flex items-center gap-2 shrink-0">
            {{-- Exportar --}}
            <a href="{{ route('guias.comfrut.export-php', ['q' => $q]) }}"
               class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl
                      bg-emerald-600 hover:bg-emerald-700 active:scale-95
                      text-white transition shadow-sm shadow-emerald-200 dark:shadow-emerald-900">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                <span class="hidden sm:inline">Exportar</span>
            </a>

            @if(auth()->user()->role === 'admin')
                {{-- Importar XML --}}
                <a href="{{ route('guias.comfrut.import.form') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl
                          bg-indigo-600 hover:bg-indigo-700 active:scale-95
                          text-white transition shadow-sm shadow-indigo-200 dark:shadow-indigo-900">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    <span class="hidden sm:inline">Importar XML</span>
                </a>
            @endif
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

    @keyframes fadeUp { from { opacity:0; transform:translateY(8px) } to { opacity:1; transform:translateY(0) } }
    .au { animation:fadeUp .4s cubic-bezier(.22,1,.36,1) both }
    .d1 { animation-delay:.04s } .d2 { animation-delay:.08s } .d3 { animation-delay:.12s }

    .page-bg       { background:#f1f5f9; min-height:100% }
    .dark .page-bg { background:#0d1117 }

    /* Card */
    .t-card        { background:#fff; border:1px solid #e2e8f0; border-radius:16px; overflow:hidden }
    .dark .t-card  { background:#161c2c; border-color:#1e2a3b }

    /* Table */
    .dt { width:100%; border-collapse:collapse; font-size:13px }
    .dt thead tr { background:#f8fafc; border-bottom:1px solid #f1f5f9 }
    .dark .dt thead tr { background:#111827; border-bottom-color:#1e2a3b }
    .dt th { padding:10px 14px; text-align:left; font-size:10px; font-weight:700;
             letter-spacing:.08em; text-transform:uppercase; color:#94a3b8; white-space:nowrap }
    .dt th.r { text-align:right }
    .dt td { padding:11px 14px; border-bottom:1px solid #f8fafc; color:#334155; vertical-align:middle }
    .dark .dt td { border-bottom-color:#1a2232; color:#cbd5e1 }
    .dt tbody tr:last-child td { border-bottom:none }
    .dt tbody tr:hover td { background:#f8fafc }
    .dark .dt tbody tr:hover td { background:#1a2436 }

    /* Mobile card */
    .m-card       { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:14px 16px }
    .dark .m-card { background:#161c2c; border-color:#1e2a3b }

    /* Qty pills */
    .qty { display:inline-flex; align-items:center; padding:2px 9px; border-radius:999px;
           font-size:11px; font-weight:700; white-space:nowrap }
    .qty-blue   { background:#dbeafe; color:#1d4ed8 }
    .qty-purple { background:#f3e8ff; color:#7c3aed }
    .qty-amber  { background:#fef3c7; color:#b45309 }
    .qty-empty  { background:#f1f5f9; color:#94a3b8 }
    .dark .qty-blue   { background:rgba(59,130,246,.15); color:#93c5fd }
    .dark .qty-purple { background:rgba(139,92,246,.15); color:#c4b5fd }
    .dark .qty-amber  { background:rgba(245,158,11,.15); color:#fcd34d }
    .dark .qty-empty  { background:rgba(255,255,255,.06); color:#475569 }

    /* Buttons */
    .btn-sm       { display:inline-flex; align-items:center; gap:4px; padding:4px 10px;
                    border-radius:8px; font-size:11px; font-weight:600; transition:background .15s }
    .btn-indigo   { background:#eef2ff; color:#4f46e5 }
    .btn-indigo:hover { background:#e0e7ff }
    .dark .btn-indigo { background:rgba(99,102,241,.15); color:#a5b4fc }
    .dark .btn-indigo:hover { background:rgba(99,102,241,.25) }

    /* Guía badge */
    .guia-badge { font-family:monospace; font-weight:700; font-size:13px;
                  color:#4f46e5 }
    .dark .guia-badge { color:#a5b4fc }

    /* Patente */
    .pat-badge { display:inline-block; font-family:monospace; font-weight:700; font-size:11px;
                 padding:2px 6px; border-radius:5px; background:#f1f5f9; color:#475569;
                 text-transform:uppercase }
    .dark .pat-badge { background:rgba(255,255,255,.06); color:#94a3b8 }

    /* Stat pill */
    .stat-pill { display:inline-flex; align-items:center; gap:4px; padding:5px 12px;
                 border-radius:10px; font-size:12px; font-weight:600;
                 background:#eef2ff; color:#4338ca }
    .dark .stat-pill { background:rgba(99,102,241,.15); color:#a5b4fc }

    /* Flash */
    .flash-ok { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px;
                padding:12px 16px; font-size:13px; color:#15803d;
                display:flex; align-items:center; gap:8px }
    .dark .flash-ok { background:rgba(16,185,129,.1); border-color:rgba(16,185,129,.25); color:#34d399 }

    /* Mobile search */
    .mob-search { position:relative }
    .mob-search svg { position:absolute; left:12px; top:50%; transform:translateY(-50%);
                      width:14px; height:14px; pointer-events:none; color:#9ca3af }
    .mob-search input { width:100%; padding:10px 10px 10px 36px; border-radius:12px;
                        border:1px solid #e2e8f0; background:#fff; font-size:14px; outline:none }
    .dark .mob-search input { background:#161c2c; border-color:#1e2a3b; color:#f1f5f9 }

    /* Empty state */
    .empty-icon { width:48px; height:48px; border-radius:16px; background:#f1f5f9;
                  display:flex; align-items:center; justify-content:center; margin:0 auto 12px }
    .dark .empty-icon { background:rgba(255,255,255,.06) }
</style>

<div class="page-bg" x-data="comfrutIndex({{ $rowsJson }})">
<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">

    {{-- Flash --}}
    @if(session('ok'))
    <div class="flash-ok au d1">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
        </svg>
        {{ session('ok') }}
    </div>
    @endif

    {{-- Buscador móvil --}}
    <div class="sm:hidden mob-search au d1">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
        </svg>
        <input x-model="q" type="text" inputmode="search" placeholder="Guía, productor, patente…">
    </div>

    {{-- Barra stats + contador --}}
    <div class="flex items-center justify-between au d1">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="stat-pill">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
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
                            <td class="text-gray-500 dark:text-gray-400 text-xs whitespace-nowrap"
                                x-text="r.fecha"></td>
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
                                   class="btn-sm btn-indigo">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    Ver
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
                          bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    Importar primera guía
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
    <div class="lg:hidden space-y-2 au d2">
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
                       class="btn-sm btn-indigo">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
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

function comfrutHeader() {
    return {
        get q()    { return Alpine.store('comfrut').q; },
        set q(val) { Alpine.store('comfrut').q = val; },
    };
}
</script>

</x-app-layout>
