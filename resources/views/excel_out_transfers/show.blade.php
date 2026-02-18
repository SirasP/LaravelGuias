<x-app-layout>

    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-4">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-violet-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Guía {{ $transfer->guia_entrega }}</h2>
                    <p class="text-xs text-gray-400 mt-0.5 hidden sm:block">Guías ODOO · Detalle de transferencia</p>
                </div>
            </div>
            <a href="{{ route('excel_out_transfers.index') }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold rounded-xl
                       text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-800
                       border border-gray-200 dark:border-gray-700
                       hover:border-violet-300 hover:text-violet-600 dark:hover:text-violet-400
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
        .d1 { animation-delay:.04s } .d2 { animation-delay:.08s } .d3 { animation-delay:.12s } .d4 { animation-delay:.16s } .d5 { animation-delay:.20s }

        .page-bg { background:#f1f5f9; min-height:100% }
        .dark .page-bg { background:#0d1117 }

        .panel { background:#fff; border:1px solid #e2e8f0; border-radius:18px; overflow:hidden }
        .dark .panel { background:#161c2c; border-color:#1e2a3b }

        .panel-head { padding:15px 20px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; gap:12px }
        .dark .panel-head { border-bottom-color:#1e2a3b }

        .stat-card { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:16px 18px; display:flex; align-items:center; justify-content:space-between }
        .dark .stat-card { background:#161c2c; border-color:#1e2a3b }

        .dt { width:100%; border-collapse:collapse; font-size:13px }
        .dt thead tr { background:#f8fafc; border-bottom:1px solid #f1f5f9 }
        .dark .dt thead tr { background:#111827; border-bottom-color:#1e2a3b }
        .dt th { padding:10px 16px; text-align:left; font-size:10px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#94a3b8; white-space:nowrap }
        .dt th.r { text-align:right }
        .dt td { padding:12px 16px; border-bottom:1px solid #f8fafc; color:#334155; vertical-align:middle }
        .dark .dt td { border-bottom-color:#1a2232; color:#cbd5e1 }
        .dt tbody tr:last-child td { border-bottom:none }
        .dt tbody tr:hover td { background:#f8fafc }
        .dark .dt tbody tr:hover td { background:#1a2436 }

        .m-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:14px 16px }
        .dark .m-card { background:#161c2c; border-color:#1e2a3b }
    </style>

    <div class="page-bg">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

            {{-- ══════════════════════════════════════════
            STAT CARDS
            ══════════════════════════════════════════ --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">

                {{-- Contacto --}}
                <div class="stat-card au d1">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Contacto</p>
                        <p class="text-base font-black text-gray-900 dark:text-gray-100 truncate">{{ $transfer->contacto ?? '—' }}</p>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-violet-50 dark:bg-violet-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                </div>

                {{-- Chofer --}}
                <div class="stat-card au d2">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Chofer</p>
                        <p class="text-base font-black text-gray-900 dark:text-gray-100 truncate">{{ $transfer->chofer ?? '—' }}</p>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                </div>

                {{-- Patente --}}
                <div class="stat-card au d3">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Patente</p>
                        <p class="text-base font-black text-gray-900 dark:text-gray-100 uppercase tabular-nums">{{ $transfer->patente ?? '—' }}</p>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 17h8m-4-4v4M3 11h18M3 11l2-5h14l2 5M3 11v6a1 1 0 001 1h1m12 0h1a1 1 0 001-1v-6" />
                        </svg>
                    </div>
                </div>

                {{-- Referencia --}}
                <div class="stat-card au d4">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Referencia</p>
                        <p class="text-base font-black text-gray-900 dark:text-gray-100 truncate">{{ $transfer->referencia ?? '—' }}</p>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                        </svg>
                    </div>
                </div>

            </div>

            {{-- ══════════════════════════════════════════
            DETALLE DE PRODUCTOS
            ══════════════════════════════════════════ --}}
            <div class="panel au d5">
                <div class="panel-head">
                    <div class="w-8 h-8 rounded-xl bg-violet-50 dark:bg-violet-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Detalle de productos</h3>
                        <p class="text-[10px] text-gray-400 mt-0.5">{{ $transfer->lines->count() }} {{ $transfer->lines->count() === 1 ? 'ítem' : 'ítems' }} en la transferencia</p>
                    </div>
                </div>

                {{-- Desktop table --}}
                <div class="hidden lg:block overflow-x-auto">
                    <table class="dt">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="r">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transfer->lines as $line)
                                <tr>
                                    <td class="font-medium text-gray-900 dark:text-gray-100">{{ $line->producto ?? '—' }}</td>
                                    <td class="text-right">
                                        <span class="font-mono font-bold tabular-nums text-violet-600 dark:text-violet-400">
                                            {{ number_format((float) $line->cantidad, 3, ',', '.') }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="py-12 text-center text-sm text-gray-400">
                                        Sin ítems registrados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Mobile cards --}}
                <div class="lg:hidden p-3 space-y-2">
                    @forelse($transfer->lines as $line)
                        <div class="m-card">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-8 h-8 rounded-xl bg-violet-50 dark:bg-violet-900/20 flex items-center justify-center shrink-0">
                                        <svg class="w-3.5 h-3.5 text-violet-500 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                        </svg>
                                    </div>
                                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">{{ $line->producto ?? '—' }}</p>
                                </div>
                                <p class="text-base font-black font-mono tabular-nums text-violet-600 dark:text-violet-400 shrink-0">
                                    {{ number_format((float) $line->cantidad, 3, ',', '.') }}
                                </p>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-sm text-gray-400 py-8">
                            Sin ítems registrados.
                        </div>
                    @endforelse
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
