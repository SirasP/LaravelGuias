<x-app-layout>
    @php
        $baseHeaderParams = array_filter([
            'q' => $q ?: null,
            'range' => $range ?: null,
            'flag' => $flag ?: null,
        ]);

        $ventasCountTrend = (int) ($kpiVentas->cnt ?? 0) - (int) ($kpiVentasPrev->cnt ?? 0);
        $eppCountTrend = (int) ($kpiEpp->cnt ?? 0) - (int) ($kpiEppPrev->cnt ?? 0);
        $salidaCountTrend = (int) ($kpiSalida->cnt ?? 0) - (int) ($kpiSalidaPrev->cnt ?? 0);

        $formatTrend = function (int $value): string {
            if ($value > 0) return '+' . $value;
            return (string) $value;
        };
    @endphp

    <x-slot name="header">
        <div class="w-full grid grid-cols-1 lg:grid-cols-[auto,1fr,auto] items-center gap-3">

            <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-xl bg-rose-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Salidas de inventario</h2>
                    <p class="text-xs text-gray-400 mt-0.5 truncate">Historial FIFO consolidado</p>
                </div>
            </div>

            <form method="GET" class="hidden lg:flex gap-2 items-center w-full max-w-sm justify-self-center">
                @if ($vista === 'Venta')
                    <input type="hidden" name="tipo" value="Venta">
                @endif
                @if($range)
                    <input type="hidden" name="range" value="{{ $range }}">
                @endif
                @if($flag)
                    <input type="hidden" name="flag" value="{{ $flag }}">
                @endif
                <input type="text" name="q" value="{{ $q }}" class="f-input flex-1 min-w-0 py-2" placeholder="Buscar destinatario...">
                @if($q)
                    <a href="{{ route('gmail.inventory.exits', $vista === 'Venta' ? array_merge(['tipo' => 'Venta'], array_filter(['range' => $range, 'flag' => $flag])) : array_filter(['range' => $range])) }}"
                       class="shrink-0 text-xs font-semibold text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition">Limpiar</a>
                @endif
                <button type="submit"
                    class="shrink-0 px-3 py-1.5 text-xs font-semibold rounded-xl bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 transition">
                    Buscar
                </button>
            </form>

            <div class="flex items-center gap-2 justify-end">
                <div class="hidden sm:flex gap-1 bg-gray-100 dark:bg-gray-800/70 rounded-2xl p-1 shrink-0 mr-1">
                    <a href="{{ route('gmail.inventory.exits', array_merge($baseHeaderParams, ['tipo' => 'Venta'])) }}"
                        class="view-tab {{ $vista === 'Venta' ? 'view-tab-active-green' : 'view-tab-inactive' }}">
                        Ventas
                    </a>
                    <a href="{{ route('gmail.inventory.exits', $baseHeaderParams) }}"
                        class="view-tab {{ $vista !== 'Venta' ? 'view-tab-active-blue' : 'view-tab-inactive' }}">
                        EPP y Salidas
                    </a>
                </div>

                <a href="{{ route('gmail.inventory.exits.export', array_filter(['q' => $q, 'range' => $range, 'flag' => $flag, 'tipo' => $vista === 'Venta' ? 'Venta' : ''])) }}"
                    class="hidden sm:inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    CSV
                </a>

                <a href="{{ route('gmail.inventory.exit.create') }}"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl bg-rose-600 hover:bg-rose-700 text-white transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14m-7-7h14" />
                    </svg>
                    <span class="hidden sm:inline">Nueva Salida</span>
                    <span class="sm:hidden">Nueva</span>
                </a>
            </div>
        </div>
    </x-slot>

    <style>
        [x-cloak] { display:none !important }
        .page-bg { background:#f1f5f9; min-height:100% }
        .dark .page-bg { background:#0d1117 }

        .f-input {
            border-radius:12px; border:1px solid #e2e8f0; background:#fff;
            padding:9px 12px; font-size:13px; color:#111827; outline:none;
        }
        .f-input:focus { border-color:#f43f5e; box-shadow:0 0 0 3px rgba(244,63,94,.12) }
        .dark .f-input { border-color:#1e2a3b; background:#0d1117; color:#f1f5f9 }

        .view-tab {
            display:flex; align-items:center; gap:8px;
            padding:8px 16px; border-radius:12px;
            font-size:12px; font-weight:700; transition:all .15s; text-decoration:none;
        }
        .view-tab-active-green { background:#fff; color:#059669; box-shadow:0 1px 4px rgba(0,0,0,.1) }
        .view-tab-active-blue  { background:#fff; color:#2563eb; box-shadow:0 1px 4px rgba(0,0,0,.1) }
        .dark .view-tab-active-green { background:#0d1117; color:#34d399 }
        .dark .view-tab-active-blue  { background:#0d1117; color:#60a5fa }
        .view-tab-inactive { color:#64748b }
        .dark .view-tab-inactive { color:#94a3b8 }

        .chip-link {
            display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px;
            border:1px solid #e2e8f0; background:#fff; color:#64748b; font-size:11px; font-weight:700;
            transition:all .12s;
        }
        .chip-link:hover { border-color:#cbd5e1; color:#334155 }
        .chip-link.active { background:#111827; border-color:#111827; color:#fff }
        .dark .chip-link { background:#111827; border-color:#1f2937; color:#94a3b8 }
        .dark .chip-link:hover { border-color:#334155; color:#e2e8f0 }
        .dark .chip-link.active { background:#2563eb; border-color:#2563eb; color:#fff }

        .kpi-card {
            background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:16px 18px;
        }
        .dark .kpi-card { background:#161c2c; border-color:#1e2a3b }

        .group-card {
            background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:14px 16px;
            transition:border-color .15s, box-shadow .15s; cursor:pointer;
        }
        .group-card:hover { border-color:#c7d2fe; box-shadow:0 6px 18px rgba(15,23,42,.06) }
        .dark .group-card { background:#161c2c; border-color:#1e2a3b }
        .dark .group-card:hover { border-color:#334155 }

        .detail-box {
            background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:12px;
        }
        .dark .detail-box { background:#0f172a; border-color:#1e293b }

        .mini-pill {
            display:inline-flex; align-items:center; gap:5px; padding:4px 8px; border-radius:999px;
            font-size:10px; font-weight:700;
        }
    </style>

    <div class="page-bg" x-data="{
        activeGroup: null,
        drawerOpen: false,
        drawerData: null,
        toggleGroup(key) { this.activeGroup = this.activeGroup === key ? null : key; },
        openDrawer(payload) { this.drawerData = payload; this.drawerOpen = true; },
        closeDrawer() { this.drawerOpen = false; }
    }" @keydown.escape.window="closeDrawer()">

        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

            @if (session('success'))
                <div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 px-4 py-3">
                    <p class="text-sm text-emerald-700 dark:text-emerald-400">{{ session('success') }}</p>
                </div>
            @endif

            @php
                $baseQuick = array_filter([
                    'tipo' => $vista === 'Venta' ? 'Venta' : null,
                    'q' => $q ?: null,
                    'flag' => $flag ?: null,
                ]);
                $baseWithoutFlag = array_filter([
                    'tipo' => $vista === 'Venta' ? 'Venta' : null,
                    'q' => $q ?: null,
                    'range' => $range ?: null,
                ]);
            @endphp

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('gmail.inventory.exits', array_filter(['tipo' => $vista === 'Venta' ? 'Venta' : null, 'q' => $q ?: null])) }}"
                    class="chip-link {{ $range === '' ? 'active' : '' }}">Todo</a>
                <a href="{{ route('gmail.inventory.exits', array_merge($baseQuick, ['range' => 'today'])) }}"
                    class="chip-link {{ $range === 'today' ? 'active' : '' }}">Hoy</a>
                <a href="{{ route('gmail.inventory.exits', array_merge($baseQuick, ['range' => '7d'])) }}"
                    class="chip-link {{ $range === '7d' ? 'active' : '' }}">7 días</a>
                <a href="{{ route('gmail.inventory.exits', array_merge($baseQuick, ['range' => '30d'])) }}"
                    class="chip-link {{ $range === '30d' ? 'active' : '' }}">30 días</a>

                @if($vista === 'Venta')
                    <a href="{{ route('gmail.inventory.exits', array_merge($baseWithoutFlag, ['flag' => 'sin_precio'])) }}"
                        class="chip-link {{ $flag === 'sin_precio' ? 'active' : '' }}">Sin precio venta</a>
                @endif
            </div>

            @if ($vista === 'Venta')
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
                    <div class="kpi-card">
                        <p class="text-xs text-gray-400 mb-1">Ventas del mes</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $kpiVentas->cnt ?? 0 }}</p>
                        <p class="text-xs mt-1 {{ $ventasCountTrend >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                            vs mes anterior: {{ $formatTrend($ventasCountTrend) }}
                        </p>
                    </div>
                    <div class="kpi-card">
                        <p class="text-xs text-gray-400 mb-1">Costo total (mes)</p>
                        <p class="text-2xl font-bold text-rose-600 dark:text-rose-400">$ {{ number_format((float)($kpiVentas->costo ?? 0), 0, ',', '.') }}</p>
                    </div>
                    <div class="kpi-card">
                        <p class="text-xs text-gray-400 mb-1">Venta total (mes)</p>
                        <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">$ {{ number_format((float)($kpiVentas->venta ?? 0), 0, ',', '.') }}</p>
                        @php
                            $c = (float)($kpiVentas->costo ?? 0);
                            $v = (float)($kpiVentas->venta ?? 0);
                            $mg = ($c > 0 && $v > 0) ? round((($v - $c) / $c) * 100, 1) : null;
                        @endphp
                        @if($mg !== null)
                            <p class="text-xs mt-1 {{ $mg >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">Margen: {{ $mg }}%</p>
                        @endif
                    </div>
                    <div class="kpi-card">
                        <p class="text-xs text-gray-400 mb-1">Producto top (mes)</p>
                        @if($topVenta)
                            <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">{{ $topVenta->nombre }}</p>
                            <p class="text-xs text-gray-400 mt-1">{{ number_format((float)$topVenta->total_qty, 2, ',', '.') }} unidades</p>
                        @else
                            <p class="text-sm text-gray-400">Sin datos</p>
                        @endif
                    </div>
                </div>

                @if($movements->isEmpty())
                    <div class="bg-white dark:bg-gray-900/40 border border-gray-200 dark:border-gray-800 rounded-2xl p-10 text-center">
                        <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">No hay ventas registradas</p>
                        <p class="text-xs text-gray-400 mt-1">No se encontraron resultados para el filtro aplicado.</p>
                    </div>
                @else
                    @php
                        $ventaGroups = $byName->map(function ($movs, $nombre) use ($lines) {
                            $totalQty = $movs->sum(fn($m) => (float) $lines->get($m->id, collect())->sum('cantidad'));
                            $totalCost = (float) $movs->sum('costo_total');
                            $totalSell = (float) $movs->sum(fn($m) => (float) ($m->precio_venta ?? 0));
                            $sinPrecio = $movs->filter(fn($m) => ((float) ($m->precio_venta ?? 0)) <= 0)->count();
                            $lastDate = $movs->max('ocurrio_el');
                            return (object) [
                                'nombre' => $nombre,
                                'movs' => $movs,
                                'total_qty' => $totalQty,
                                'total_cost' => $totalCost,
                                'total_sell' => $totalSell,
                                'sin_precio' => $sinPrecio,
                                'last_date' => $lastDate,
                            ];
                        })->sortByDesc('last_date')->values();
                    @endphp

                    <div class="space-y-3">
                        @foreach($ventaGroups as $g)
                            @php $groupKey = 'venta-' . $loop->index; @endphp
                            <div class="group-card" @click="toggleGroup('{{ $groupKey }}')">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">{{ $g->nombre }}</p>
                                        <p class="text-xs text-gray-400 mt-0.5">{{ $g->movs->count() }} movimientos · Último {{ \Carbon\Carbon::parse($g->last_date)->format('d/m/Y') }}</p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 text-[11px]">
                                        <span class="mini-pill bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ number_format($g->total_qty, 2, ',', '.') }} uds</span>
                                        <span class="mini-pill bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300">Costo $ {{ number_format($g->total_cost, 0, ',', '.') }}</span>
                                        <span class="mini-pill bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Venta $ {{ number_format($g->total_sell, 0, ',', '.') }}</span>
                                        @if($g->sin_precio > 0)
                                            <span class="mini-pill bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">{{ $g->sin_precio }} sin precio</span>
                                        @endif
                                    </div>
                                </div>

                                <div x-show="activeGroup === '{{ $groupKey }}'" x-cloak class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-800 space-y-2" @click.stop>
                                    @foreach($g->movs as $m)
                                        @php
                                            $detailLines = $lines->get($m->id, collect())->map(fn($l) => [
                                                'producto' => $l->producto,
                                                'unidad' => $l->unidad,
                                                'cantidad' => (float) $l->cantidad,
                                                'costo_unitario' => (float) $l->costo_unitario,
                                                'costo_total' => (float) $l->costo_total,
                                            ])->values();
                                        @endphp
                                        <div class="detail-box">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <div>
                                                    <p class="text-xs font-bold text-gray-900 dark:text-gray-100">Movimiento #{{ $m->id }}</p>
                                                    <p class="text-[11px] text-gray-400">{{ \Carbon\Carbon::parse($m->ocurrio_el)->format('d/m/Y') }} · {{ $detailLines->count() }} líneas</p>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    @if(((float) ($m->precio_venta ?? 0)) <= 0)
                                                        <span class="mini-pill bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">Sin precio venta</span>
                                                    @endif
                                                    <button type="button" class="chip-link"
                                                        @click="openDrawer({
                                                            id: {{ (int) $m->id }},
                                                            destinatario: @js($m->destinatario ?? '—'),
                                                            fecha: @js(\Carbon\Carbon::parse($m->ocurrio_el)->format('d/m/Y')),
                                                            notas: @js($m->notas),
                                                            tipo: 'Venta',
                                                            costoTotal: {{ (float) $m->costo_total }},
                                                            precioVenta: {{ (float) ($m->precio_venta ?? 0) }},
                                                            lines: @js($detailLines)
                                                        })">Ver detalle</button>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @else

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                    <div class="kpi-card">
                        <p class="text-xs text-gray-400 mb-1">EPP del mes</p>
                        <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $kpiEpp->cnt ?? 0 }}</p>
                        <p class="text-xs mt-1 {{ $eppCountTrend >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                            vs mes anterior: {{ $formatTrend($eppCountTrend) }}
                        </p>
                    </div>
                    <div class="kpi-card">
                        <p class="text-xs text-gray-400 mb-1">Salidas internas (mes)</p>
                        <p class="text-2xl font-bold text-slate-700 dark:text-slate-300">{{ $kpiSalida->cnt ?? 0 }}</p>
                        <p class="text-xs mt-1 {{ $salidaCountTrend >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                            vs mes anterior: {{ $formatTrend($salidaCountTrend) }}
                        </p>
                    </div>
                    <div class="kpi-card">
                        <p class="text-xs text-gray-400 mb-1">Producto top (operaciones)</p>
                        @if($topOps)
                            <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">{{ $topOps->nombre }}</p>
                            <p class="text-xs text-gray-400 mt-1">{{ number_format((float)$topOps->total_qty, 2, ',', '.') }} unidades</p>
                        @else
                            <p class="text-sm text-gray-400">Sin datos</p>
                        @endif
                    </div>
                </div>

                @if($movements->isEmpty())
                    <div class="bg-white dark:bg-gray-900/40 border border-gray-200 dark:border-gray-800 rounded-2xl p-10 text-center">
                        <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">No hay registros</p>
                        <p class="text-xs text-gray-400 mt-1">No se encontraron resultados para el filtro aplicado.</p>
                    </div>
                @else
                    <div x-data="{ sub: '{{ $countEpp > 0 ? 'EPP' : 'Salida' }}' }" class="space-y-4">
                        <div class="flex gap-1 bg-gray-100 dark:bg-gray-800/70 rounded-2xl p-1 w-fit">
                            <button type="button" @click="sub = 'EPP'" class="view-tab" :class="sub === 'EPP' ? 'view-tab-active-blue' : 'view-tab-inactive'">
                                EPP
                                <span class="ml-1 inline-flex items-center justify-center min-w-[18px] h-4 px-1 text-[10px] font-bold rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300">{{ $countEpp }}</span>
                            </button>
                            <button type="button" @click="sub = 'Salida'" class="view-tab" :class="sub === 'Salida' ? 'view-tab-active-blue' : 'view-tab-inactive'">
                                Salidas
                                <span class="ml-1 inline-flex items-center justify-center min-w-[18px] h-4 px-1 text-[10px] font-bold rounded-full bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200">{{ $countSalida }}</span>
                            </button>
                        </div>

                        @foreach(['EPP', 'Salida'] as $tipo)
                            @php
                                $groups = $byTipoName->get($tipo, collect())->map(function ($movs, $nombre) use ($lines) {
                                    $totalQty = $movs->sum(fn($m) => (float) $lines->get($m->id, collect())->sum('cantidad'));
                                    $totalCost = (float) $movs->sum('costo_total');
                                    $lastDate = $movs->max('ocurrio_el');
                                    return (object) [
                                        'nombre' => $nombre,
                                        'movs' => $movs,
                                        'total_qty' => $totalQty,
                                        'total_cost' => $totalCost,
                                        'last_date' => $lastDate,
                                    ];
                                })->sortByDesc('last_date')->values();
                            @endphp

                            <div x-show="sub === '{{ $tipo }}'" x-cloak class="space-y-3">
                                @if($groups->isEmpty())
                                    <p class="text-sm text-gray-400 text-center py-8">No hay registros {{ strtolower($tipo) }} con estos filtros.</p>
                                @else
                                    @foreach($groups as $g)
                                        @php $groupKey = strtolower($tipo) . '-' . $loop->index; @endphp
                                        <div class="group-card" @click="toggleGroup('{{ $groupKey }}')">
                                            <div class="flex flex-wrap items-center justify-between gap-3">
                                                <div class="min-w-0">
                                                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">{{ $g->nombre }}</p>
                                                    <p class="text-xs text-gray-400 mt-0.5">{{ $g->movs->count() }} movimientos · Último {{ \Carbon\Carbon::parse($g->last_date)->format('d/m/Y') }}</p>
                                                </div>
                                                <div class="flex flex-wrap items-center gap-2 text-[11px]">
                                                    <span class="mini-pill bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ number_format($g->total_qty, 2, ',', '.') }} uds</span>
                                                    <span class="mini-pill bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300">Costo $ {{ number_format($g->total_cost, 0, ',', '.') }}</span>
                                                </div>
                                            </div>

                                            <div x-show="activeGroup === '{{ $groupKey }}'" x-cloak class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-800 space-y-2" @click.stop>
                                                @foreach($g->movs as $m)
                                                    @php
                                                        $detailLines = $lines->get($m->id, collect())->map(fn($l) => [
                                                            'producto' => $l->producto,
                                                            'unidad' => $l->unidad,
                                                            'cantidad' => (float) $l->cantidad,
                                                            'costo_unitario' => (float) $l->costo_unitario,
                                                            'costo_total' => (float) $l->costo_total,
                                                        ])->values();
                                                    @endphp
                                                    <div class="detail-box">
                                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                                            <div>
                                                                <p class="text-xs font-bold text-gray-900 dark:text-gray-100">Movimiento #{{ $m->id }}</p>
                                                                <p class="text-[11px] text-gray-400">{{ \Carbon\Carbon::parse($m->ocurrio_el)->format('d/m/Y') }} · {{ $detailLines->count() }} líneas</p>
                                                            </div>
                                                            <button type="button" class="chip-link"
                                                                @click="openDrawer({
                                                                    id: {{ (int) $m->id }},
                                                                    destinatario: @js($m->destinatario ?? '—'),
                                                                    fecha: @js(\Carbon\Carbon::parse($m->ocurrio_el)->format('d/m/Y')),
                                                                    notas: @js($m->notas),
                                                                    tipo: @js($tipo),
                                                                    costoTotal: {{ (float) $m->costo_total }},
                                                                    precioVenta: 0,
                                                                    lines: @js($detailLines)
                                                                })">Ver detalle</button>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif

            <a href="{{ route('gmail.inventory.exit.create') }}"
                class="fixed right-5 bottom-5 z-50 sm:hidden w-14 h-14 rounded-full inline-flex items-center justify-center bg-rose-600 hover:bg-rose-700 text-white shadow-xl transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14m-7-7h14" />
                </svg>
            </a>
        </div>

        <div x-cloak x-show="drawerOpen" class="fixed inset-0 z-[120]" aria-hidden="true">
            <div class="absolute inset-0 bg-slate-900/55" @click="closeDrawer()"></div>
            <div class="absolute right-0 top-0 h-full w-full max-w-xl bg-white dark:bg-slate-900 border-l border-slate-200 dark:border-slate-700 shadow-2xl overflow-y-auto"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="translate-x-full">

                <div class="p-5 border-b border-slate-200 dark:border-slate-700 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs text-slate-400" x-text="drawerData ? ('Movimiento #' + drawerData.id) : ''"></p>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100 truncate" x-text="drawerData ? drawerData.destinatario : ''"></h3>
                        <p class="text-xs text-slate-500 mt-1" x-text="drawerData ? (drawerData.tipo + ' · ' + drawerData.fecha) : ''"></p>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200" @click="closeDrawer()">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-5 space-y-4" x-show="drawerData">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="kpi-card !p-3">
                            <p class="text-[11px] text-slate-400">Costo total</p>
                            <p class="text-base font-bold text-rose-600" x-text="drawerData ? ('$ ' + Number(drawerData.costoTotal).toLocaleString('es-CL')) : '$ 0'"></p>
                        </div>
                        <div class="kpi-card !p-3">
                            <p class="text-[11px] text-slate-400">Precio venta</p>
                            <p class="text-base font-bold text-emerald-600" x-text="drawerData && drawerData.precioVenta > 0 ? ('$ ' + Number(drawerData.precioVenta).toLocaleString('es-CL')) : '—'"></p>
                        </div>
                    </div>

                    <template x-if="drawerData && drawerData.notas">
                        <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-3 text-xs text-slate-600 dark:text-slate-300" x-text="drawerData.notas"></div>
                    </template>

                    <div class="rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                        <table class="w-full text-xs">
                            <thead class="bg-slate-50 dark:bg-slate-800/60">
                                <tr>
                                    <th class="text-left px-3 py-2 font-semibold text-slate-500">Producto</th>
                                    <th class="text-right px-3 py-2 font-semibold text-slate-500">Cant.</th>
                                    <th class="text-right px-3 py-2 font-semibold text-slate-500">C. Unit.</th>
                                    <th class="text-right px-3 py-2 font-semibold text-slate-500">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="line in (drawerData?.lines || [])" :key="line.producto + '-' + line.unidad">
                                    <tr class="border-t border-slate-100 dark:border-slate-800">
                                        <td class="px-3 py-2">
                                            <p class="font-semibold text-slate-800 dark:text-slate-200" x-text="line.producto"></p>
                                            <p class="text-slate-400" x-text="line.unidad"></p>
                                        </td>
                                        <td class="px-3 py-2 text-right text-slate-700 dark:text-slate-300" x-text="Number(line.cantidad).toLocaleString('es-CL', {maximumFractionDigits: 2})"></td>
                                        <td class="px-3 py-2 text-right text-slate-500" x-text="'$ ' + Number(line.costo_unitario).toLocaleString('es-CL', {maximumFractionDigits: 2})"></td>
                                        <td class="px-3 py-2 text-right font-semibold text-slate-800 dark:text-slate-200" x-text="'$ ' + Number(line.costo_total).toLocaleString('es-CL', {maximumFractionDigits: 0})"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
