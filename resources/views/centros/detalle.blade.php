<x-app-layout>

    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-4">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-violet-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">{{ $contacto }}</h2>
                    <p class="text-xs text-gray-400 mt-0.5 hidden sm:block">Detalle de guías y productos enviados</p>
                </div>
            </div>
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
            DIFERENCIA POR TIPO DE BANDEJA
            ══════════════════════════════════════════ --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-{{ min(count($diferenciaPorTipo), 4) }} gap-3 au d1">
                @foreach($diferenciaPorTipo as $row)
                    @php
                        $diff = $row['diff'];
                        if ($diff === 0) {
                            $iconBg = 'bg-emerald-50 dark:bg-emerald-900/20';
                            $iconColor = 'text-emerald-600 dark:text-emerald-400';
                            $numColor = 'text-emerald-600 dark:text-emerald-400';
                        } elseif ($diff > 0) {
                            $iconBg = 'bg-amber-50 dark:bg-amber-900/20';
                            $iconColor = 'text-amber-600 dark:text-amber-400';
                            $numColor = 'text-amber-600 dark:text-amber-400';
                        } else {
                            $iconBg = 'bg-rose-50 dark:bg-rose-900/20';
                            $iconColor = 'text-rose-600 dark:text-rose-400';
                            $numColor = 'text-rose-600 dark:text-rose-400';
                        }
                    @endphp
                    <div class="stat-card">
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1 truncate">{{ $row['tipo'] }}</p>
                            <p class="text-xl font-black tabular-nums {{ $numColor }}">{{ number_format($diff, 0, ',', '.') }}</p>
                            <p class="text-[10px] text-gray-400 mt-0.5">Guías: {{ number_format($row['guia'], 0, ',', '.') }} · Odoo: {{ number_format($row['odoo'], 0, ',', '.') }}</p>
                        </div>
                        <div class="w-9 h-9 rounded-xl {{ $iconBg }} flex items-center justify-center shrink-0">
                            @if($diff === 0)
                                <svg class="w-4 h-4 {{ $iconColor }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            @elseif($diff > 0)
                                <svg class="w-4 h-4 {{ $iconColor }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                            @else
                                <svg class="w-4 h-4 {{ $iconColor }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- ══════════════════════════════════════════
            GUÍAS POR CENTRO
            ══════════════════════════════════════════ --}}
            <div class="panel au d2">
                <div class="panel-head">
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <span class="text-sm font-bold text-gray-900 dark:text-gray-100">Guías por centro</span>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400">
                            {{ $contacto }}
                        </span>
                    </div>
                </div>

                {{-- KPIs guías --}}
                <div class="p-4">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                        <div class="stat-card">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Total guías</p>
                                <p class="text-xl font-black text-gray-900 dark:text-gray-100 tabular-nums">{{ $guias->count() }}</p>
                            </div>
                            <div class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center">
                                <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Bandejas</p>
                                <p class="text-xl font-black text-indigo-600 dark:text-indigo-400 tabular-nums">{{ number_format($totalBandejas, 0, ',', '.') }}</p>
                            </div>
                            <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center">
                                <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Pallets</p>
                                <p class="text-xl font-black text-gray-900 dark:text-gray-100 tabular-nums">{{ number_format($totalPallets, 0, ',', '.') }}</p>
                            </div>
                            <div class="w-9 h-9 rounded-xl bg-purple-50 dark:bg-purple-900/20 flex items-center justify-center">
                                <svg class="w-4 h-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Monto total</p>
                                <p class="text-xl font-black text-emerald-600 dark:text-emerald-400 tabular-nums">{{ number_format($guias->sum('monto_total'), 0, ',', '.') }}</p>
                            </div>
                            <div class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center">
                                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    {{-- Tabla productos desktop --}}
                    <div class="hidden lg:block panel">
                        <table class="dt">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th class="r">Cantidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($productos as $p)
                                    <tr>
                                        <td>
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center shrink-0">
                                                    <span class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400">{{ strtoupper(substr($p->tipo_bandeja ?? $p->nombre_original, 0, 1)) }}</span>
                                                </div>
                                                <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $p->nombre_original }} - {{ $p->tipo_bandeja }}</span>
                                            </div>
                                        </td>
                                        <td class="text-right font-bold tabular-nums text-gray-800 dark:text-gray-100">
                                            {{ number_format($p->total_unidades, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="py-12 text-center text-sm text-gray-400">Sin productos</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Mobile cards productos --}}
                    <div class="lg:hidden space-y-2">
                        @forelse($productos as $p)
                            <div class="m-card flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center shrink-0">
                                        <span class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400">{{ strtoupper(substr($p->tipo_bandeja ?? $p->nombre_original, 0, 1)) }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">{{ $p->nombre_original }}</p>
                                        <p class="text-[11px] text-gray-400 truncate">{{ $p->tipo_bandeja }}</p>
                                    </div>
                                </div>
                                <p class="text-sm font-black tabular-nums text-gray-900 dark:text-gray-100 shrink-0">
                                    {{ number_format($p->total_unidades, 0, ',', '.') }}
                                </p>
                            </div>
                        @empty
                            <div class="m-card text-center text-sm text-gray-400 py-8">Sin productos</div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- ══════════════════════════════════════════
            SALIDAS REALES (ODOO)
            ══════════════════════════════════════════ --}}
            <div class="panel au d3">
                <div class="panel-head">
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-lg bg-violet-50 dark:bg-violet-900/30 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                            </svg>
                        </div>
                        <span class="text-sm font-bold text-gray-900 dark:text-gray-100">Salidas reales de bandejas</span>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-violet-50 dark:bg-violet-900/20 text-violet-600 dark:text-violet-400">
                            ODOO
                        </span>
                    </div>
                </div>

                <div class="p-4">
                    {{-- KPIs salidas --}}
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-5">
                        <div class="stat-card">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Bandejas enviadas</p>
                                <p class="text-xl font-black text-indigo-600 dark:text-indigo-400 tabular-nums">{{ number_format($totalBandejasOut ?? 0, 0, ',', '.') }}</p>
                            </div>
                            <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center">
                                <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Tipos</p>
                                <p class="text-xl font-black text-gray-900 dark:text-gray-100 tabular-nums">{{ $bandejasPorTipo->count() }}</p>
                            </div>
                            <div class="w-9 h-9 rounded-xl bg-purple-50 dark:bg-purple-900/20 flex items-center justify-center">
                                <svg class="w-4 h-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                </svg>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Último traslado</p>
                                <p class="text-base font-black text-gray-900 dark:text-gray-100">
                                    @if(!empty($bandejasPorTransfer) && $bandejasPorTransfer->count() > 0)
                                        {{ \Carbon\Carbon::parse($bandejasPorTransfer->first()->fecha_traslado)->format('d/m/Y') }}
                                    @else
                                        —
                                    @endif
                                </p>
                            </div>
                            <div class="w-9 h-9 rounded-xl bg-gray-50 dark:bg-gray-800 flex items-center justify-center">
                                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    {{-- Tabla bandejas desktop --}}
                    <div class="hidden lg:block panel">
                        <table class="dt">
                            <thead>
                                <tr>
                                    <th>Tipo bandeja</th>
                                    <th class="r">Cantidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($bandejasPorTipo as $b)
                                    <tr>
                                        <td>
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-7 h-7 rounded-lg bg-violet-50 dark:bg-violet-900/20 flex items-center justify-center shrink-0">
                                                    <span class="text-[10px] font-bold text-violet-600 dark:text-violet-400">{{ strtoupper(substr($b->tipo_bandeja, 0, 1)) }}</span>
                                                </div>
                                                <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $b->tipo_bandeja }}</span>
                                            </div>
                                        </td>
                                        <td class="text-right font-bold tabular-nums text-gray-800 dark:text-gray-100">
                                            {{ number_format($b->total_bandejas, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="py-12 text-center text-sm text-gray-400">Sin registros de salida</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Mobile cards bandejas --}}
                    <div class="lg:hidden space-y-2">
                        @forelse($bandejasPorTipo as $b)
                            <div class="m-card flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-8 h-8 rounded-xl bg-violet-50 dark:bg-violet-900/20 flex items-center justify-center shrink-0">
                                        <span class="text-[10px] font-bold text-violet-600 dark:text-violet-400">{{ strtoupper(substr($b->tipo_bandeja, 0, 1)) }}</span>
                                    </div>
                                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">{{ $b->tipo_bandeja }}</p>
                                </div>
                                <p class="text-sm font-black tabular-nums text-gray-900 dark:text-gray-100 shrink-0">
                                    {{ number_format($b->total_bandejas, 0, ',', '.') }}
                                </p>
                            </div>
                        @empty
                            <div class="m-card text-center text-sm text-gray-400 py-8">Sin registros de salida</div>
                        @endforelse
                    </div>
                </div>
            </div>

        </div>
    </div>

</x-app-layout>
