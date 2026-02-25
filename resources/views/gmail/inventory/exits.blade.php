<x-app-layout>
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
                    <p class="text-xs text-gray-400 mt-0.5 truncate">Historial FIFO</p>
                </div>
            </div>

            <form method="GET" class="hidden lg:flex gap-2 items-center w-full max-w-md justify-self-center">
                @if ($vista === 'Venta')
                    <input type="hidden" name="tipo" value="Venta">
                @endif
                <input type="text" name="q" value="{{ $q }}"
                    class="f-input flex-1 min-w-0 py-2"
                    placeholder="Buscar destinatario...">
                @if($q)
                    <a href="{{ route('gmail.inventory.exits', $vista === 'Venta' ? ['tipo' => 'Venta'] : []) }}"
                        class="shrink-0 text-xs font-semibold text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition">
                        Limpiar
                    </a>
                @endif
                <button type="submit"
                    class="shrink-0 px-3 py-1.5 text-xs font-semibold rounded-xl bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 transition">
                    Buscar
                </button>
            </form>

            <div class="flex items-center gap-2 justify-end">
                @php $tabHeaderBase = array_filter(['q' => $q]); @endphp
                <div class="hidden sm:flex gap-1 bg-gray-100 dark:bg-gray-800/70 rounded-2xl p-1 shrink-0 mr-1">
                    <a href="{{ route('gmail.inventory.exits', array_merge($tabHeaderBase, ['tipo' => 'Venta'])) }}"
                        class="view-tab {{ $vista === 'Venta' ? 'view-tab-active-green' : 'view-tab-inactive' }}">
                        💰 Ventas
                    </a>
                    <a href="{{ route('gmail.inventory.exits', $tabHeaderBase) }}"
                        class="view-tab {{ $vista !== 'Venta' ? 'view-tab-active-blue' : 'view-tab-inactive' }}">
                        🦺 EPP &amp; Salidas
                    </a>
                </div>

                <a href="{{ route('gmail.inventory.exits.export', array_filter(['q' => $q, 'tipo' => $vista === 'Venta' ? 'Venta' : ''])) }}"
                    class="hidden sm:inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl
                           bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700
                           text-gray-700 dark:text-gray-300 transition">
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
        .page-bg { background:#f1f5f9; min-height:100% }
        .dark .page-bg { background:#0d1117 }
        .f-input {
            border-radius:12px; border:1px solid #e2e8f0; background:#fff;
            padding:9px 12px; font-size:13px; color:#111827; outline:none;
        }
        .f-input:focus { border-color:#f43f5e; box-shadow:0 0 0 3px rgba(244,63,94,.12) }
        .dark .f-input { border-color:#1e2a3b; background:#0d1117; color:#f1f5f9 }
        .kpi-card {
            background:#fff; border:1px solid #e2e8f0; border-radius:16px;
            padding:16px 20px; flex:1;
        }
        .dark .kpi-card { background:#161c2c; border-color:#1e2a3b }
        .exit-card {
            background:#fff; border:1px solid #e2e8f0; border-radius:16px; overflow:hidden;
        }
        .dark .exit-card { background:#161c2c; border-color:#1e2a3b }
        .sell-input {
            border-radius:10px; border:1px solid #e2e8f0; background:#fff;
            padding:8px 12px; font-size:13px; color:#111827; outline:none; width:100%;
        }
        .sell-input:focus { border-color:#10b981; box-shadow:0 0 0 3px rgba(16,185,129,.12) }
        .dark .sell-input { border-color:#1e2a3b; background:#0d1117; color:#f1f5f9 }
        .name-divider {
            display:flex; align-items:center; gap:8px;
            font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase;
            color:#64748b; margin:14px 0 8px;
        }
        .name-divider::after { content:''; flex:1; height:1px; background:#e2e8f0 }
        .dark .name-divider { color:#94a3b8 }
        .dark .name-divider::after { background:#1e2a3b }

        .view-tab {
            display:flex; align-items:center; gap:8px;
            padding:8px 18px; border-radius:12px;
            font-size:12px; font-weight:700; transition:all .15s;
            text-decoration:none; cursor:pointer;
        }
        .view-tab-active-green {
            background:#fff; color:#059669; box-shadow:0 1px 4px rgba(0,0,0,.1);
        }
        .view-tab-active-blue {
            background:#fff; color:#2563eb; box-shadow:0 1px 4px rgba(0,0,0,.1);
        }
        .dark .view-tab-active-green { background:#0d1117; color:#34d399 }
        .dark .view-tab-active-blue  { background:#0d1117; color:#60a5fa }
        .view-tab-inactive { color:#64748b; }
        .view-tab-inactive:hover { color:#111827 }
        .dark .view-tab-inactive { color:#94a3b8 }
        .dark .view-tab-inactive:hover { color:#f1f5f9 }

        .summary-card {
            display:block; background:#fff; border:1px solid #e2e8f0; border-radius:16px;
            padding:14px 16px; transition:border-color .15s, box-shadow .15s;
        }
        .summary-card:hover { border-color:#c7d2fe; box-shadow:0 6px 18px rgba(15,23,42,.06) }
        .dark .summary-card { background:#161c2c; border-color:#1e2a3b }
        .dark .summary-card:hover { border-color:#334155 }
    </style>

    <div class="page-bg">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

            @if (session('success'))
                <div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 px-4 py-3">
                    <p class="text-sm text-emerald-700 dark:text-emerald-400">{{ session('success') }}</p>
                </div>
            @endif

            @if ($vista === 'Venta')

                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="kpi-card">
                        <p class="text-xs text-gray-400 mb-1">Ventas este mes</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {{ $kpiVentas->cnt ?? 0 }}
                        </p>
                    </div>
                    <div class="kpi-card">
                        <p class="text-xs text-gray-400 mb-1">Costo salido este mes</p>
                        <p class="text-2xl font-bold text-rose-600 dark:text-rose-400">
                            $ {{ number_format((float)($kpiVentas->costo ?? 0), 0, ',', '.') }}
                        </p>
                    </div>
                    <div class="kpi-card">
                        <p class="text-xs text-gray-400 mb-1">Total vendido este mes</p>
                        <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                            $ {{ number_format((float)($kpiVentas->venta ?? 0), 0, ',', '.') }}
                        </p>
                        @php
                            $c = (float)($kpiVentas->costo ?? 0);
                            $v = (float)($kpiVentas->venta ?? 0);
                            $mg = ($c > 0 && $v > 0) ? round((($v - $c) / $c) * 100, 1) : null;
                        @endphp
                        @if ($mg !== null)
                            <p class="text-xs mt-0.5 {{ $mg >= 0 ? 'text-emerald-500' : 'text-rose-500' }}">
                                Margen {{ $mg }}%
                            </p>
                        @endif
                    </div>
                    <div class="kpi-card">
                        <p class="text-xs text-gray-400 mb-1">Más vendido este mes</p>
                        @if ($topVenta)
                            <p class="text-base font-bold text-gray-900 dark:text-gray-100 truncate">{{ $topVenta->nombre }}</p>
                            <p class="text-xs text-gray-400">{{ number_format((float)$topVenta->total_qty, 2, ',', '.') }} unidades</p>
                        @else
                            <p class="text-sm text-gray-400">Sin datos</p>
                        @endif
                    </div>
                </div>

                @if ($movements->isEmpty())
                    <div class="bg-white dark:bg-gray-900/40 border border-gray-200 dark:border-gray-800 rounded-2xl p-10 text-center">
                        <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">No hay ventas registradas</p>
                        <p class="text-xs text-gray-400 mt-1">
                            @if($q) No se encontraron resultados para el filtro aplicado.
                            @else Las ventas aparecerán aquí al registrar una salida de tipo Venta.
                            @endif
                        </p>
                    </div>
                @else
                    <p class="text-xs text-gray-500 dark:text-gray-400 px-1">
                        {{ $movements->count() }} {{ $movements->count() === 1 ? 'venta' : 'ventas' }} mostradas
                        &middot; Costo: $&nbsp;{{ number_format((float)$costoVentas, 0, ',', '.') }}
                        @if ((float)$pvVentas > 0)
                            &middot; Vendido: $&nbsp;{{ number_format((float)$pvVentas, 0, ',', '.') }}
                        @endif
                    </p>
                    @php
                        $resumenVentas = $byName->map(function ($movs, $nombre) use ($lines) {
                            return (object) [
                                'nombre' => $nombre,
                                'movimientos' => (int) $movs->count(),
                                'cantidad_total' => (float) $movs->sum(fn($m) => (float) $lines->get($m->id, collect())->sum('cantidad')),
                                'costo_total' => (float) $movs->sum('costo_total'),
                                'venta_total' => (float) $movs->sum(fn($m) => (float) ($m->precio_venta ?? 0)),
                                'sin_precio' => (int) $movs->filter(fn($m) => ((float) ($m->precio_venta ?? 0)) <= 0)->count(),
                                'ultimo' => $movs->sortByDesc('ocurrio_el')->first(),
                            ];
                        })->sortByDesc(fn($r) => $r->ultimo->ocurrio_el ?? null)->values();
                    @endphp

                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                        @foreach($resumenVentas as $r)
                            <a href="{{ route('gmail.inventory.exits.group', ['destinatario' => $r->nombre, 'tipo' => 'Venta']) }}" class="summary-card">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-[11px] uppercase tracking-wide font-bold text-emerald-500">Ficha comercial</p>
                                        <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">{{ $r->nombre }}</p>
                                    </div>
                                    <span class="text-[11px] text-gray-400 shrink-0">{{ $r->ultimo?->ocurrio_el ? \Carbon\Carbon::parse($r->ultimo->ocurrio_el)->format('d/m/Y') : '—' }}</span>
                                </div>
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                                    {{ $r->movimientos }} mov · {{ number_format($r->cantidad_total, 2, ',', '.') }} uds ·
                                    costo $ {{ number_format($r->costo_total, 0, ',', '.') }} ·
                                    venta $ {{ number_format($r->venta_total, 0, ',', '.') }}
                                    @if($r->sin_precio > 0) · {{ $r->sin_precio }} sin precio @endif
                                </p>
                            </a>
                        @endforeach
                    </div>
                @endif

            @else

                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="kpi-card">
                        <p class="text-xs text-gray-400 mb-1">🦺 EPP entregados este mes</p>
                        <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                            {{ $kpiEpp->cnt ?? 0 }}
                        </p>
                        @if ((float)($kpiEpp->costo ?? 0) > 0)
                            <p class="text-xs text-gray-400 mt-0.5">
                                Costo: $&nbsp;{{ number_format((float)$kpiEpp->costo, 0, ',', '.') }}
                            </p>
                        @endif
                    </div>
                    <div class="kpi-card">
                        <p class="text-xs text-gray-400 mb-1">📦 Salidas internas este mes</p>
                        <p class="text-2xl font-bold text-slate-600 dark:text-slate-400">
                            {{ $kpiSalida->cnt ?? 0 }}
                        </p>
                        @if ((float)($kpiSalida->costo ?? 0) > 0)
                            <p class="text-xs text-gray-400 mt-0.5">
                                Costo: $&nbsp;{{ number_format((float)$kpiSalida->costo, 0, ',', '.') }}
                            </p>
                        @endif
                    </div>
                    <div class="kpi-card">
                        <p class="text-xs text-gray-400 mb-1">Más retirado este mes</p>
                        @if ($topOps)
                            <p class="text-base font-bold text-gray-900 dark:text-gray-100 truncate">{{ $topOps->nombre }}</p>
                            <p class="text-xs text-gray-400">{{ number_format((float)$topOps->total_qty, 2, ',', '.') }} unidades</p>
                        @else
                            <p class="text-sm text-gray-400">Sin datos</p>
                        @endif
                    </div>
                </div>

                @if ($movements->isEmpty())
                    <div class="bg-white dark:bg-gray-900/40 border border-gray-200 dark:border-gray-800 rounded-2xl p-10 text-center">
                        <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">No hay registros</p>
                        <p class="text-xs text-gray-400 mt-1">
                            @if($q) No se encontraron resultados para el filtro aplicado.
                            @else Las entregas de EPP y salidas internas aparecerán aquí.
                            @endif
                        </p>
                    </div>
                @else
                    <div x-data="{ sub: '{{ $countEpp > 0 ? 'EPP' : 'Salida' }}' }">

                        <div class="flex gap-1 bg-gray-100 dark:bg-gray-800/70 rounded-2xl p-1 w-fit mb-5">
                            <button type="button" @click="sub = 'EPP'"
                                class="view-tab"
                                :class="sub === 'EPP' ? 'view-tab-active-blue' : 'view-tab-inactive'">
                                🦺 EPP
                                @if ($countEpp > 0)
                                    <span class="ml-1 inline-flex items-center justify-center w-4 h-4 text-[10px] font-bold rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300">
                                        {{ $countEpp }}
                                    </span>
                                @endif
                            </button>
                            <button type="button" @click="sub = 'Salida'"
                                class="view-tab"
                                :class="sub === 'Salida' ? 'view-tab-active-blue' : 'view-tab-inactive'">
                                📦 Salidas
                                @if ($countSalida > 0)
                                    <span class="ml-1 inline-flex items-center justify-center w-4 h-4 text-[10px] font-bold rounded-full bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                        {{ $countSalida }}
                                    </span>
                                @endif
                            </button>
                        </div>

                        <div x-show="sub === 'EPP'" x-cloak>
                            @php $eppByName = $byTipoName->get('EPP', collect()); @endphp
                            @if ($eppByName->isEmpty())
                                <p class="text-sm text-gray-400 text-center py-8">No hay entregas EPP con los filtros actuales.</p>
                            @else
                                @php
                                    $resumenEpp = $eppByName->map(function ($movs, $nombre) use ($lines) {
                                        return (object) [
                                            'nombre' => $nombre,
                                            'movimientos' => (int) $movs->count(),
                                            'cantidad_total' => (float) $movs->sum(fn($m) => (float) $lines->get($m->id, collect())->sum('cantidad')),
                                            'costo_total' => (float) $movs->sum('costo_total'),
                                            'ultimo' => $movs->sortByDesc('ocurrio_el')->first(),
                                        ];
                                    })->sortByDesc(fn($r) => $r->ultimo->ocurrio_el ?? null)->values();
                                @endphp
                                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 mb-2">
                                    @foreach($resumenEpp as $r)
                                        <a href="{{ route('gmail.inventory.exits.group', ['destinatario' => $r->nombre, 'tipo' => 'EPP']) }}" class="summary-card">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <p class="text-[11px] uppercase tracking-wide font-bold text-blue-500">Ficha operativa</p>
                                                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">{{ $r->nombre }}</p>
                                                </div>
                                                <span class="text-[11px] text-gray-400 shrink-0">{{ $r->ultimo?->ocurrio_el ? \Carbon\Carbon::parse($r->ultimo->ocurrio_el)->format('d/m/Y') : '—' }}</span>
                                            </div>
                                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                                                {{ $r->movimientos }} mov · {{ number_format($r->cantidad_total, 2, ',', '.') }} uds ·
                                                costo $ {{ number_format($r->costo_total, 0, ',', '.') }}
                                            </p>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div x-show="sub === 'Salida'" x-cloak>
                            @php $salidaByName = $byTipoName->get('Salida', collect()); @endphp
                            @if ($salidaByName->isEmpty())
                                <p class="text-sm text-gray-400 text-center py-8">No hay salidas internas con los filtros actuales.</p>
                            @else
                                @php
                                    $resumenSalida = $salidaByName->map(function ($movs, $nombre) use ($lines) {
                                        return (object) [
                                            'nombre' => $nombre,
                                            'movimientos' => (int) $movs->count(),
                                            'cantidad_total' => (float) $movs->sum(fn($m) => (float) $lines->get($m->id, collect())->sum('cantidad')),
                                            'costo_total' => (float) $movs->sum('costo_total'),
                                            'ultimo' => $movs->sortByDesc('ocurrio_el')->first(),
                                        ];
                                    })->sortByDesc(fn($r) => $r->ultimo->ocurrio_el ?? null)->values();
                                @endphp
                                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 mb-2">
                                    @foreach($resumenSalida as $r)
                                        <a href="{{ route('gmail.inventory.exits.group', ['destinatario' => $r->nombre, 'tipo' => 'Salida']) }}" class="summary-card">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <p class="text-[11px] uppercase tracking-wide font-bold text-slate-500">Ficha operativa</p>
                                                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">{{ $r->nombre }}</p>
                                                </div>
                                                <span class="text-[11px] text-gray-400 shrink-0">{{ $r->ultimo?->ocurrio_el ? \Carbon\Carbon::parse($r->ultimo->ocurrio_el)->format('d/m/Y') : '—' }}</span>
                                            </div>
                                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                                                {{ $r->movimientos }} mov · {{ number_format($r->cantidad_total, 2, ',', '.') }} uds ·
                                                costo $ {{ number_format($r->costo_total, 0, ',', '.') }}
                                            </p>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                    </div>
                @endif
            @endif

            <a href="{{ route('gmail.inventory.exit.create') }}"
                class="fixed right-5 bottom-5 z-50 sm:hidden w-14 h-14 rounded-full inline-flex items-center justify-center
                       bg-rose-600 hover:bg-rose-700 text-white shadow-xl transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14m-7-7h14" />
                </svg>
            </a>
        </div>
    </div>
</x-app-layout>
