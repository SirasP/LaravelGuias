<x-app-layout>
    <x-slot name="header">
        <div class="w-full flex flex-col gap-3">

            {{-- Top row: title + actions --}}
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-rose-600 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Salidas de inventario</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Historial FIFO</p>
                    </div>
                </div>
                <div class="hidden sm:flex items-center gap-2">
                    <a href="{{ route('gmail.inventory.exits.export', array_filter(['q' => $q, 'desde' => $desde, 'hasta' => $hasta, 'tipo' => $vista === 'Venta' ? 'Venta' : ''])) }}"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl
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
                        Nueva Salida
                    </a>
                </div>
            </div>

            {{-- Main tabs: Ventas | EPP & Salidas --}}
            <div class="flex gap-1 bg-gray-100 dark:bg-gray-800/60 rounded-2xl p-1 w-fit">
                @php
                    $tabBase = array_filter(['q' => $q, 'desde' => $desde, 'hasta' => $hasta]);
                @endphp
                <a href="{{ route('gmail.inventory.exits', array_merge($tabBase, ['tipo' => 'Venta'])) }}"
                   class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold transition
                          {{ $vista === 'Venta'
                              ? 'bg-white dark:bg-gray-900 text-emerald-600 dark:text-emerald-400 shadow-sm'
                              : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200' }}">
                    <span>💰</span>
                    Ventas
                </a>
                <a href="{{ route('gmail.inventory.exits', $tabBase) }}"
                   class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold transition
                          {{ $vista !== 'Venta'
                              ? 'bg-white dark:bg-gray-900 text-blue-600 dark:text-blue-400 shadow-sm'
                              : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200' }}">
                    <span>🦺</span>
                    EPP &amp; Salidas
                </a>
            </div>

            {{-- Filter form --}}
            <form method="GET" class="hidden sm:flex gap-2 items-center">
                @if ($vista === 'Venta')
                    <input type="hidden" name="tipo" value="Venta">
                @endif
                <input type="text" name="q" value="{{ $q }}" class="f-input flex-1 min-w-0"
                    placeholder="Buscar destinatario...">
                <input type="date" name="desde" value="{{ $desde }}" class="f-input w-36 shrink-0" title="Desde">
                <input type="date" name="hasta" value="{{ $hasta }}" class="f-input w-36 shrink-0" title="Hasta">
                <button type="submit"
                    class="shrink-0 px-4 py-2 text-xs font-semibold rounded-xl bg-rose-600 hover:bg-rose-700 text-white transition">
                    Filtrar
                </button>
                @if($q || $desde || $hasta)
                    <a href="{{ route('gmail.inventory.exits', $vista === 'Venta' ? ['tipo' => 'Venta'] : []) }}"
                        class="shrink-0 px-3 py-2 text-xs font-semibold rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition">
                        Limpiar
                    </a>
                @endif
            </form>
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
            background:#fff; border:1px solid #e2e8f0; border-radius:16px;
            overflow:hidden;
        }
        .dark .exit-card { background:#161c2c; border-color:#1e2a3b }
        .sell-input {
            border-radius:10px; border:1px solid #e2e8f0; background:#fff;
            padding:8px 12px; font-size:13px; color:#111827; outline:none; width:100%;
        }
        .sell-input:focus { border-color:#10b981; box-shadow:0 0 0 3px rgba(16,185,129,.12) }
        .dark .sell-input { border-color:#1e2a3b; background:#0d1117; color:#f1f5f9 }
        /* Sub-tab toggle (for EPP / Salidas) */
        .sub-tab-active   { background:#fff; color:#2563eb; box-shadow:0 1px 3px rgba(0,0,0,.1) }
        .dark .sub-tab-active { background:#0d1117; color:#60a5fa }
        /* Name group divider */
        .name-divider {
            display:flex; align-items:center; gap:8px;
            font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase;
            color:#64748b; margin:14px 0 8px;
        }
        .name-divider::after { content:''; flex:1; height:1px; background:#e2e8f0 }
        .dark .name-divider { color:#94a3b8 }
        .dark .name-divider::after { background:#1e2a3b }
    </style>

    <div class="page-bg">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

            {{-- Mobile filter --}}
            <form method="GET" class="sm:hidden space-y-2">
                @if ($vista === 'Venta')
                    <input type="hidden" name="tipo" value="Venta">
                @endif
                <div class="flex gap-2">
                    <input type="text" name="q" value="{{ $q }}" class="f-input flex-1"
                        placeholder="Buscar destinatario...">
                    <button type="submit"
                        class="px-4 py-2 text-xs font-semibold rounded-xl bg-rose-600 hover:bg-rose-700 text-white transition">Buscar</button>
                </div>
                <div class="flex gap-2">
                    <input type="date" name="desde" value="{{ $desde }}" class="f-input flex-1">
                    <input type="date" name="hasta" value="{{ $hasta }}" class="f-input flex-1">
                </div>
            </form>

            @if (session('success'))
                <div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 px-4 py-3">
                    <p class="text-sm text-emerald-700 dark:text-emerald-400">{{ session('success') }}</p>
                </div>
            @endif

            {{-- KPI Cards --}}
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="kpi-card">
                    <p class="text-xs text-gray-400 mb-1">Salidas este mes</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {{ $kpiMes->total_salidas ?? 0 }}
                    </p>
                </div>
                <div class="kpi-card">
                    <p class="text-xs text-gray-400 mb-1">Costo total este mes</p>
                    <p class="text-2xl font-bold text-rose-600 dark:text-rose-400">
                        $ {{ number_format((float) ($kpiMes->costo_total ?? 0), 0, ',', '.') }}
                    </p>
                </div>
                @if ((float) ($kpiMes->precio_venta_total ?? 0) > 0)
                    <div class="kpi-card">
                        <p class="text-xs text-gray-400 mb-1">Ventas registradas este mes</p>
                        <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                            $ {{ number_format((float) ($kpiMes->precio_venta_total ?? 0), 0, ',', '.') }}
                        </p>
                    </div>
                @else
                    <div class="kpi-card">
                        <p class="text-xs text-gray-400 mb-1">Más retirado este mes</p>
                        @if ($topProducto)
                            <p class="text-base font-bold text-gray-900 dark:text-gray-100 truncate">{{ $topProducto->nombre }}</p>
                            <p class="text-xs text-gray-400">{{ number_format((float) $topProducto->total_qty, 2, ',', '.') }} unidades</p>
                        @else
                            <p class="text-sm text-gray-400">Sin datos</p>
                        @endif
                    </div>
                @endif
            </div>

            {{-- ───────────────────────────────────────────────────────── --}}
            {{-- VENTAS VIEW                                               --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            @if ($vista === 'Venta')

                @if ($movements->isEmpty())
                    <div class="bg-white dark:bg-gray-900/40 border border-gray-200 dark:border-gray-800 rounded-2xl p-10 text-center">
                        <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">No hay ventas registradas</p>
                        <p class="text-xs text-gray-400 mt-1">
                            @if($q || $desde || $hasta) No se encontraron resultados para el filtro aplicado.
                            @else Las ventas aparecerán aquí al registrar una salida de tipo Venta.
                            @endif
                        </p>
                    </div>
                @else
                    {{-- Summary bar --}}
                    <div class="flex items-center justify-between px-1">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                            {{ $movements->count() }} {{ $movements->count() === 1 ? 'venta' : 'ventas' }}
                            &middot; Costo: $ {{ number_format((float) $costoVentas, 0, ',', '.') }}
                            @if ((float) $pvVentas > 0)
                                &middot; Venta: $ {{ number_format((float) $pvVentas, 0, ',', '.') }}
                            @endif
                        </p>
                    </div>

                    @foreach ($byName as $nombre => $movs)
                        <div class="name-divider">{{ $nombre }} <span class="font-normal normal-case tracking-normal text-gray-400">({{ $movs->count() }})</span></div>

                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                            @foreach ($movs as $m)
                                @php
                                    $cardLines   = $lines->get($m->id, collect());
                                    $costoTotal  = (float) $m->costo_total;
                                    $precioVenta = $m->precio_venta;
                                    $sellUrl     = route('gmail.inventory.exits.sell', $m->id);
                                @endphp
                                @include('gmail.inventory._exit_card', compact('m','cardLines','costoTotal','precioVenta','sellUrl'))
                            @endforeach
                        </div>
                    @endforeach
                @endif

            {{-- ───────────────────────────────────────────────────────── --}}
            {{-- EPP + SALIDAS VIEW                                        --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            @else
                @if ($movements->isEmpty())
                    <div class="bg-white dark:bg-gray-900/40 border border-gray-200 dark:border-gray-800 rounded-2xl p-10 text-center">
                        <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">No hay registros</p>
                        <p class="text-xs text-gray-400 mt-1">
                            @if($q || $desde || $hasta) No se encontraron resultados para el filtro aplicado.
                            @else Las entregas de EPP y salidas internas aparecerán aquí.
                            @endif
                        </p>
                    </div>
                @else
                    {{-- Sub-tab toggle: EPP | Salidas --}}
                    <div x-data="{ sub: '{{ $countEpp > 0 ? 'EPP' : 'Salida' }}' }">

                        <div class="flex gap-1 bg-gray-100 dark:bg-gray-800/60 rounded-2xl p-1 w-fit mb-5">
                            <button type="button" @click="sub = 'EPP'"
                                class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold transition"
                                :class="sub === 'EPP' ? 'sub-tab-active dark:sub-tab-active' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200'">
                                🦺 EPP
                                @if ($countEpp > 0)
                                    <span class="inline-flex items-center justify-center w-4 h-4 text-[10px] font-bold rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400">
                                        {{ $countEpp }}
                                    </span>
                                @endif
                            </button>
                            <button type="button" @click="sub = 'Salida'"
                                class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold transition"
                                :class="sub === 'Salida' ? 'sub-tab-active dark:sub-tab-active' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200'">
                                📦 Salidas
                                @if ($countSalida > 0)
                                    <span class="inline-flex items-center justify-center w-4 h-4 text-[10px] font-bold rounded-full bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                        {{ $countSalida }}
                                    </span>
                                @endif
                            </button>
                        </div>

                        {{-- EPP panel --}}
                        <div x-show="sub === 'EPP'" x-cloak>
                            @php $eppByName = $byTipoName->get('EPP', collect()); @endphp
                            @if ($eppByName->isEmpty())
                                <p class="text-sm text-gray-400 text-center py-8">No hay entregas EPP con los filtros actuales.</p>
                            @else
                                @foreach ($eppByName as $nombre => $movs)
                                    <div class="name-divider">{{ $nombre }} <span class="font-normal normal-case tracking-normal text-gray-400">({{ $movs->count() }})</span></div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 mb-2">
                                        @foreach ($movs as $m)
                                            @php
                                                $cardLines   = $lines->get($m->id, collect());
                                                $costoTotal  = (float) $m->costo_total;
                                                $precioVenta = null;
                                                $sellUrl     = '';
                                            @endphp
                                            @include('gmail.inventory._exit_card_simple', compact('m','cardLines','costoTotal'))
                                        @endforeach
                                    </div>
                                @endforeach
                            @endif
                        </div>

                        {{-- Salidas panel --}}
                        <div x-show="sub === 'Salida'" x-cloak>
                            @php $salidaByName = $byTipoName->get('Salida', collect()); @endphp
                            @if ($salidaByName->isEmpty())
                                <p class="text-sm text-gray-400 text-center py-8">No hay salidas internas con los filtros actuales.</p>
                            @else
                                @foreach ($salidaByName as $nombre => $movs)
                                    <div class="name-divider">{{ $nombre }} <span class="font-normal normal-case tracking-normal text-gray-400">({{ $movs->count() }})</span></div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 mb-2">
                                        @foreach ($movs as $m)
                                            @php
                                                $cardLines   = $lines->get($m->id, collect());
                                                $costoTotal  = (float) $m->costo_total;
                                            @endphp
                                            @include('gmail.inventory._exit_card_simple', compact('m','cardLines','costoTotal'))
                                        @endforeach
                                    </div>
                                @endforeach
                            @endif
                        </div>

                    </div>{{-- /x-data sub-tab --}}
                @endif
            @endif

            {{-- FAB mobile --}}
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
