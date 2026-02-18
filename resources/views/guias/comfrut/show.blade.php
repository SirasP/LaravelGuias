<x-app-layout>

    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-4">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-sky-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Guía #{{ $guia->guia_numero }}</h2>
                    <p class="text-xs text-gray-400 mt-0.5 hidden sm:block">COMFRUT · {{ $guia->tipo_dte }}</p>
                </div>
            </div>
            <a href="{{ route('guias.comfrut.index') }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold rounded-xl
                       text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-800
                       border border-gray-200 dark:border-gray-700
                       hover:border-sky-300 hover:text-sky-600 dark:hover:text-sky-400
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
        .dt th.c { text-align:center }
        .dt td { padding:12px 16px; border-bottom:1px solid #f8fafc; color:#334155; vertical-align:middle }
        .dark .dt td { border-bottom-color:#1a2232; color:#cbd5e1 }
        .dt tbody tr:last-child td { border-bottom:none }
        .dt tbody tr:hover td { background:#f8fafc }
        .dark .dt tbody tr:hover td { background:#1a2436 }

        .m-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:14px 16px }
        .dark .m-card { background:#161c2c; border-color:#1e2a3b }

        .info-row { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #f1f5f9 }
        .dark .info-row { border-bottom-color:#1e2a3b }
        .info-row:last-child { border-bottom:none }
    </style>

    <div class="page-bg">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

            {{-- ══════════════════════════════════════════
            STAT CARDS - RESUMEN
            ══════════════════════════════════════════ --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">

                {{-- Guía --}}
                <div class="stat-card au d1">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Guía</p>
                        <p class="text-base font-black text-gray-900 dark:text-gray-100 tabular-nums">{{ $guia->guia_numero }}</p>
                        <p class="text-[10px] text-gray-400 mt-0.5">{{ $guia->tipo_dte }}</p>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-sky-50 dark:bg-sky-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                        </svg>
                    </div>
                </div>

                {{-- Cantidad total --}}
                <div class="stat-card au d2">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Cantidad total</p>
                        <p class="text-xl font-black text-gray-900 dark:text-gray-100 tabular-nums">{{ number_format($guia->cantidad_total, 2, ',', '.') }}</p>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </div>
                </div>

                {{-- Monto total --}}
                <div class="stat-card au d3 col-span-2 sm:col-span-1">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Monto total</p>
                        <p class="text-xl font-black text-emerald-600 dark:text-emerald-400 tabular-nums">${{ number_format($guia->monto_total, 0, ',', '.') }}</p>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>

            </div>

            {{-- ══════════════════════════════════════════
            DATOS DE LA GUÍA
            ══════════════════════════════════════════ --}}
            <div class="panel au d4">
                <div class="panel-head">
                    <div class="w-8 h-8 rounded-xl bg-sky-50 dark:bg-sky-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Datos de la guía</h3>
                        <p class="text-[10px] text-gray-400 mt-0.5">Información general del documento</p>
                    </div>
                </div>

                {{-- Desktop: grid --}}
                <div class="hidden sm:block px-5 py-3">
                    <div class="grid grid-cols-3 gap-x-8">
                        <div class="info-row">
                            <span class="text-xs text-gray-500">Fecha emisión</span>
                            <span class="text-xs font-semibold text-gray-900 dark:text-gray-100 tabular-nums">{{ $guia->fecha_guia?->format('d-m-Y') ?? '—' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="text-xs text-gray-500">Productor</span>
                            <span class="text-xs font-semibold text-gray-900 dark:text-gray-100">{{ $guia->productor ?? '—' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="text-xs text-gray-500">Patente</span>
                            <span class="text-xs font-semibold text-gray-900 dark:text-gray-100 uppercase">{{ $guia->patente ?? '—' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="text-xs text-gray-500">Tipo DTE</span>
                            <span class="text-xs font-semibold text-gray-900 dark:text-gray-100">{{ $guia->tipo_dte }}</span>
                        </div>
                        <div class="info-row">
                            <span class="text-xs text-gray-500">RUT Productor</span>
                            <span class="text-xs font-semibold text-gray-900 dark:text-gray-100 tabular-nums">{{ $guia->rut_productor ?? '—' }}</span>
                        </div>
                        <div class="info-row border-b-0">
                            <span class="text-xs text-gray-500">&nbsp;</span>
                        </div>
                    </div>
                </div>

                {{-- Mobile: lista simple --}}
                <div class="sm:hidden px-5 py-3">
                    <div class="info-row">
                        <span class="text-xs text-gray-500">Fecha emisión</span>
                        <span class="text-xs font-semibold text-gray-900 dark:text-gray-100 tabular-nums">{{ $guia->fecha_guia?->format('d-m-Y') ?? '—' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="text-xs text-gray-500">Tipo DTE</span>
                        <span class="text-xs font-semibold text-gray-900 dark:text-gray-100">{{ $guia->tipo_dte }}</span>
                    </div>
                    <div class="info-row">
                        <span class="text-xs text-gray-500">Productor</span>
                        <span class="text-xs font-semibold text-gray-900 dark:text-gray-100">{{ $guia->productor ?? '—' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="text-xs text-gray-500">RUT Productor</span>
                        <span class="text-xs font-semibold text-gray-900 dark:text-gray-100 tabular-nums">{{ $guia->rut_productor ?? '—' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="text-xs text-gray-500">Patente</span>
                        <span class="text-xs font-semibold text-gray-900 dark:text-gray-100 uppercase">{{ $guia->patente ?? '—' }}</span>
                    </div>
                </div>
            </div>

            {{-- ══════════════════════════════════════════
            DETALLE DE PRODUCTOS
            ══════════════════════════════════════════ --}}
            <div class="panel au d5">
                <div class="panel-head">
                    <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Detalle de productos</h3>
                        <p class="text-[10px] text-gray-400 mt-0.5">{{ $guia->detalles->count() }} {{ $guia->detalles->count() === 1 ? 'producto' : 'productos' }}</p>
                    </div>
                </div>

                {{-- Desktop table --}}
                <div class="hidden lg:block overflow-x-auto">
                    <table class="dt">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Código</th>
                                <th>Producto</th>
                                <th class="r">Cantidad</th>
                                <th>Unidad</th>
                                <th class="r">Precio</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($guia->detalles as $d)
                                <tr>
                                    <td class="tabular-nums text-gray-400">{{ $d->linea }}</td>
                                    <td>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-[11px] font-mono font-semibold text-gray-600 dark:text-gray-300">
                                            {{ $d->codigo_tipo }}:{{ $d->codigo_valor }}
                                        </span>
                                    </td>
                                    <td class="font-medium text-gray-900 dark:text-gray-100">{{ $d->nombre_item }}</td>
                                    <td class="text-right tabular-nums font-semibold">{{ number_format($d->cantidad, 0, ',', '.') }}</td>
                                    <td class="text-gray-500">{{ $d->unidad }}</td>
                                    <td class="text-right tabular-nums font-semibold">${{ number_format($d->precio, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-12 text-center text-sm text-gray-400">
                                        Sin detalle de productos.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Mobile cards --}}
                <div class="lg:hidden p-3 space-y-2">
                    @forelse($guia->detalles as $d)
                        <div class="m-card">
                            <div class="flex items-start justify-between gap-3 mb-2">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">{{ $d->nombre_item }}</p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-[10px] font-mono font-semibold text-gray-500 dark:text-gray-400">
                                            {{ $d->codigo_tipo }}:{{ $d->codigo_valor }}
                                        </span>
                                        <span class="text-[10px] text-gray-400">Línea {{ $d->linea }}</span>
                                    </div>
                                </div>
                                <p class="text-base font-black tabular-nums text-gray-900 dark:text-gray-100 shrink-0">
                                    ${{ number_format($d->precio, 2, ',', '.') }}
                                </p>
                            </div>
                            <div class="flex items-center justify-between pt-2 border-t border-gray-100 dark:border-gray-800">
                                <span class="text-[11px] text-gray-400">Cantidad</span>
                                <span class="text-xs font-bold text-gray-900 dark:text-gray-100 tabular-nums">
                                    {{ number_format($d->cantidad, 0, ',', '.') }}
                                    <span class="text-[10px] font-normal text-gray-400">{{ $d->unidad }}</span>
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-sm text-gray-400 py-8">
                            Sin detalle de productos.
                        </div>
                    @endforelse
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
