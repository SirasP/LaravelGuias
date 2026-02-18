<x-app-layout>

    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-4">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-emerald-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Registro #{{ $item->id }}</h2>
                    <p class="text-xs text-gray-400 mt-0.5 hidden sm:block">Agrak · {{ $item->codigo_bin }}</p>
                </div>
            </div>
            <a href="{{ route('agrak.index') }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold rounded-xl
                       text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-800
                       border border-gray-200 dark:border-gray-700
                       hover:border-emerald-300 hover:text-emerald-600 dark:hover:text-emerald-400
                       transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Volver
            </a>
        </div>
    </x-slot>

    <style>
        @keyframes fadeUp { from { opacity:0; transform:translateY(8px) } to { opacity:1; transform:translateY(0) } }
        .au { animation: fadeUp .4s cubic-bezier(.22,1,.36,1) both }
        .d1 { animation-delay:.04s } .d2 { animation-delay:.08s } .d3 { animation-delay:.12s } .d4 { animation-delay:.16s }

        .page-bg { background:#f1f5f9; min-height:100% }
        .dark .page-bg { background:#0d1117 }

        .panel { background:#fff; border:1px solid #e2e8f0; border-radius:18px; overflow:hidden }
        .dark .panel { background:#161c2c; border-color:#1e2a3b }

        .panel-head { padding:15px 20px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; gap:12px }
        .dark .panel-head { border-bottom-color:#1e2a3b }

        .stat-card { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:16px 18px; display:flex; align-items:center; justify-content:space-between }
        .dark .stat-card { background:#161c2c; border-color:#1e2a3b }

        .info-row { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #f1f5f9 }
        .dark .info-row { border-bottom-color:#1e2a3b }
        .info-row:last-child { border-bottom:none }
    </style>

    <div class="page-bg">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

            {{-- ══════════════════════════════════════════
            STAT CARDS - RESUMEN
            ══════════════════════════════════════════ --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">

                {{-- Código BIN --}}
                <div class="stat-card au d1">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Código BIN</p>
                        <p class="text-base font-black text-gray-900 dark:text-gray-100 truncate">{{ $item->codigo_bin }}</p>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                        </svg>
                    </div>
                </div>

                {{-- Fecha / Hora --}}
                <div class="stat-card au d2">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Fecha</p>
                        <p class="text-base font-black text-gray-900 dark:text-gray-100 tabular-nums">{{ \Carbon\Carbon::parse($item->fecha_registro)->format('d-m-Y') }}</p>
                        <p class="text-[10px] text-gray-400 mt-0.5">{{ $item->hora_registro }}</p>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>

                {{-- Campo / Cuartel --}}
                <div class="stat-card au d3">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Campo · Cuartel</p>
                        <p class="text-base font-black text-gray-900 dark:text-gray-100 truncate">{{ $item->nombre_campo ?? '—' }}</p>
                        <p class="text-[10px] text-gray-400 mt-0.5 truncate">{{ $item->cuartel ?? '—' }}</p>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                </div>

                {{-- Especie / Variedad --}}
                <div class="stat-card au d4">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Especie · Variedad</p>
                        <p class="text-base font-black text-gray-900 dark:text-gray-100 truncate">{{ $item->especie ?? '—' }}</p>
                        <p class="text-[10px] text-gray-400 mt-0.5 truncate">{{ $item->variedad ?? '—' }}</p>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-violet-50 dark:bg-violet-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                        </svg>
                    </div>
                </div>

            </div>

            {{-- ══════════════════════════════════════════
            PANELES DE DETALLE
            ══════════════════════════════════════════ --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                {{-- ── OPERACIÓN ──────────────────────── --}}
                <div class="panel au d2">
                    <div class="panel-head">
                        <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Operación</h3>
                            <p class="text-[10px] text-gray-400 mt-0.5">Datos del proceso</p>
                        </div>
                    </div>
                    <div class="px-5 py-3">
                        <div class="info-row">
                            <span class="text-xs text-gray-500">Usuario</span>
                            <span class="text-xs font-semibold text-gray-900 dark:text-gray-100">{{ $item->usuario ?? '—' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="text-xs text-gray-500">ID usuario</span>
                            <span class="text-xs font-semibold text-gray-900 dark:text-gray-100 tabular-nums">{{ $item->id_usuario ?? '—' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="text-xs text-gray-500">Cuadrilla</span>
                            <span class="text-xs font-semibold text-gray-900 dark:text-gray-100">{{ $item->cuadrilla ?? '—' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="text-xs text-gray-500">Vuelta</span>
                            <span class="text-xs font-semibold text-gray-900 dark:text-gray-100 tabular-nums">{{ $item->vuelta ?? '—' }}</span>
                        </div>
                    </div>
                </div>

                {{-- ── TRANSPORTE ─────────────────────── --}}
                <div class="panel au d3">
                    <div class="panel-head">
                        <div class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 17h8m-4-4v4M3 11h18M3 11l2-5h14l2 5M3 11v6a1 1 0 001 1h1m12 0h1a1 1 0 001-1v-6" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Transporte</h3>
                            <p class="text-[10px] text-gray-400 mt-0.5">Vehículo y chofer</p>
                        </div>
                    </div>
                    <div class="px-5 py-3">
                        <div class="info-row">
                            <span class="text-xs text-gray-500">Máquina</span>
                            <span class="text-xs font-semibold text-gray-900 dark:text-gray-100">{{ $item->maquina ?? '—' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="text-xs text-gray-500">Chofer</span>
                            <span class="text-xs font-semibold text-gray-900 dark:text-gray-100">{{ $item->nombre_chofer ?? '—' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="text-xs text-gray-500">Patente</span>
                            <span class="text-xs font-semibold text-gray-900 dark:text-gray-100">{{ $item->patente_camion ?? '—' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="text-xs text-gray-500">Bandejas</span>
                            <span class="text-xs font-semibold text-gray-900 dark:text-gray-100 tabular-nums">{{ $item->numero_bandejas_palet ?? '—' }}</span>
                        </div>
                    </div>
                </div>

                {{-- ── EXPORTACIÓN ────────────────────── --}}
                <div class="panel au d4">
                    <div class="panel-head">
                        <div class="w-8 h-8 rounded-xl bg-violet-50 dark:bg-violet-900/20 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Exportación</h3>
                            <p class="text-[10px] text-gray-400 mt-0.5">Exportadoras y sellos</p>
                        </div>
                    </div>
                    <div class="px-5 py-3">
                        <div class="info-row">
                            <span class="text-xs text-gray-500">Exportadora 1</span>
                            <span class="text-xs font-semibold text-gray-900 dark:text-gray-100">{{ $item->exportadora_1 ?? '—' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="text-xs text-gray-500">Exportadora 2</span>
                            <span class="text-xs font-semibold text-gray-900 dark:text-gray-100">{{ $item->exportadora_2 ?? '—' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="text-xs text-gray-500">Sello 1</span>
                            <span class="text-xs font-semibold text-gray-900 dark:text-gray-100 tabular-nums">{{ $item->numero_sello_1 ?? '—' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="text-xs text-gray-500">Sello 2</span>
                            <span class="text-xs font-semibold text-gray-900 dark:text-gray-100 tabular-nums">{{ $item->numero_sello_2 ?? '—' }}</span>
                        </div>
                    </div>
                </div>

            </div>

            {{-- ══════════════════════════════════════════
            OBSERVACIÓN
            ══════════════════════════════════════════ --}}
            @if($item->observacion)
                <div class="panel au d4">
                    <div class="panel-head">
                        <div class="w-8 h-8 rounded-xl bg-sky-50 dark:bg-sky-900/20 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Observación</h3>
                        </div>
                    </div>
                    <div class="px-5 py-4">
                        <p class="text-sm text-gray-700 dark:text-gray-200 whitespace-pre-wrap leading-relaxed">{{ $item->observacion }}</p>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
