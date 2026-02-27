<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-3 flex-wrap">
            <div class="flex items-center gap-1.5 min-w-0 text-xs">
                <a href="{{ route('gmail.inventory.list') }}"
                   class="text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition font-medium">
                    Inventario
                </a>
                <svg class="w-3 h-3 text-gray-300 dark:text-gray-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                </svg>
                <span class="font-bold text-gray-700 dark:text-gray-300 truncate">{{ $producto->nombre }}</span>
            </div>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold shrink-0
                {{ $producto->is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' }}">
                <span class="w-1.5 h-1.5 rounded-full {{ $producto->is_active ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                {{ $producto->is_active ? 'Activo' : 'Inactivo' }}
            </span>
        </div>
    </x-slot>

    <style>
        [x-cloak] { display:none !important }
        .page-bg { background:#f1f5f9; min-height:100% }
        .dark .page-bg { background:#0d1117 }
        .panel { background:#fff; border:1px solid #e2e8f0; border-radius:18px; overflow:hidden }
        .dark .panel { background:#161c2c; border-color:#1e2a3b }

        .kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px }
        @media(max-width:860px)  { .kpi-grid { grid-template-columns:repeat(2,1fr) } }
        @media(max-width:480px)  { .kpi-grid { grid-template-columns:1fr } }

        .kpi { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:16px 18px; position:relative; overflow:hidden }
        .dark .kpi { background:#161c2c; border-color:#1e2a3b }
        .kpi .top-bar { position:absolute; top:0; left:0; right:0; height:3px }
        .kpi-label { font-size:9.5px; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:#94a3b8; margin-bottom:6px }
        .kpi-val { font-size:24px; font-weight:900; line-height:1; font-variant-numeric:tabular-nums }
        .kpi-sub  { font-size:10.5px; color:#94a3b8; margin-top:5px }

        .dt { width:100%; border-collapse:collapse; font-size:13px }
        .dt thead th {
            padding:9px 14px; font-size:9.5px; font-weight:700; text-transform:uppercase;
            letter-spacing:.07em; color:#94a3b8; text-align:left;
            box-shadow:inset 0 -2px 0 #e2e8f0; background:#f8fafc; white-space:nowrap
        }
        .dark .dt thead th { background:#0f1623; box-shadow:inset 0 -2px 0 #1e2a3b; color:#64748b }
        .dt tbody td { padding:11px 14px; border-bottom:1px solid #f1f5f9; color:#334155; vertical-align:middle }
        .dark .dt tbody td { border-color:#1a2232; color:#cbd5e1 }
        .dt tbody tr:last-child td { border-bottom:none }
        .dt tbody tr:hover td { background:#f5fffb }
        .dark .dt tbody tr:hover td { background:rgba(16,185,129,.02) }

        /* Responsive: ocultar columnas secundarias en móvil */
        @media(max-width:640px) {
            .col-hide-sm { display:none }
            .dt tbody td, .dt thead th { padding:9px 8px; font-size:11px }
            .kpi-val { font-size:20px }
        }
        @media(max-width:480px) {
            .kpi-grid { grid-template-columns: repeat(2,1fr) }
        }

        .badge { display:inline-flex; align-items:center; padding:2px 8px; border-radius:999px; font-size:10px; font-weight:700 }
        .badge-entrada  { background:#dcfce7; color:#166534 }
        .badge-salida   { background:#fee2e2; color:#991b1b }
        .badge-ajuste   { background:#fef9c3; color:#854d0e }
        .dark .badge-entrada { background:rgba(22,163,74,.15); color:#86efac }
        .dark .badge-salida  { background:rgba(239,68,68,.15);  color:#fca5a5 }
        .dark .badge-ajuste  { background:rgba(234,179,8,.1);   color:#fde047 }
    </style>

    <div class="page-bg">
    <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

        {{-- ── CABECERA ── --}}
        <div class="panel">
            <div class="px-6 py-5">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                            @if($producto->codigo)
                                <span class="px-2 py-0.5 rounded-md text-[11px] font-bold font-mono
                                             bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                                    {{ $producto->codigo }}
                                </span>
                            @endif
                            <span class="px-2 py-0.5 rounded-md text-[11px] font-bold
                                         bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400">
                                {{ $producto->unidad }}
                            </span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-gray-100 tracking-tight leading-tight">
                            {{ $producto->nombre }}
                        </h1>
                    </div>

                    {{-- Variación de precio --}}
                    @if($variacion !== null)
                    <div class="shrink-0 text-right">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Variación de precio</p>
                        <div class="flex items-center gap-1 justify-end">
                            @if($variacion > 0)
                                <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                                </svg>
                                <span class="text-xl font-black text-rose-600 dark:text-rose-400">+{{ number_format($variacion, 1) }}%</span>
                            @elseif($variacion < 0)
                                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                                </svg>
                                <span class="text-xl font-black text-emerald-600 dark:text-emerald-400">{{ number_format($variacion, 1) }}%</span>
                            @else
                                <span class="text-xl font-black text-gray-400">Sin cambio</span>
                            @endif
                        </div>
                        <p class="text-[10px] text-gray-400 mt-0.5">vs. primer ingreso</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── KPIs ── --}}
        <div class="kpi-grid">
            <div class="kpi">
                <div class="top-bar bg-emerald-500"></div>
                <p class="kpi-label">Stock disponible</p>
                <p class="kpi-val text-emerald-700 dark:text-emerald-400">
                    {{ number_format($stockTotal, 2, ',', '.') }}
                    <span style="font-size:13px;font-weight:600;color:#94a3b8"> {{ $producto->unidad }}</span>
                </p>
                <p class="kpi-sub">{{ $lotes->count() }} lote{{ $lotes->count() !== 1 ? 's' : '' }} activo{{ $lotes->count() !== 1 ? 's' : '' }} en cola FIFO</p>
            </div>

            @if(auth()->user()->canSeeValues())
            <div class="kpi">
                <div class="top-bar bg-indigo-500"></div>
                <p class="kpi-label">Costo promedio FIFO</p>
                <p class="kpi-val text-indigo-700 dark:text-indigo-400">
                    {{ $costoPromedio > 0 ? '$'.number_format($costoPromedio, 0, ',', '.') : '—' }}
                </p>
                <p class="kpi-sub">Ponderado por disponibilidad</p>
            </div>

            <div class="kpi">
                <div class="top-bar bg-violet-500"></div>
                <p class="kpi-label">Valor en inventario</p>
                <p class="kpi-val text-violet-700 dark:text-violet-400">
                    {{ $valorTotal > 0 ? '$'.number_format($valorTotal, 0, ',', '.') : '—' }}
                </p>
                <p class="kpi-sub">Stock × costo promedio</p>
            </div>

            <div class="kpi">
                <div class="top-bar bg-amber-500"></div>
                <p class="kpi-label">Último precio pagado</p>
                <p class="kpi-val text-amber-700 dark:text-amber-400">
                    {{ $ultimoPrecio > 0 ? '$'.number_format((float)$ultimoPrecio, 0, ',', '.') : '—' }}
                </p>
                <p class="kpi-sub">
                    @if($historialPrecios->last()?->fecha_factura)
                        Factura {{ \Carbon\Carbon::parse($historialPrecios->last()->fecha_factura)->format('d/m/Y') }}
                    @elseif($historialPrecios->last()?->ingresado_el)
                        Ingreso {{ \Carbon\Carbon::parse($historialPrecios->last()->ingresado_el)->format('d/m/Y') }}
                    @else
                        Sin datos
                    @endif
                </p>
            </div>
            @endif

            {{-- KPI Stock mínimo editable --}}
            @php $hayMinimo = $producto->stock_minimo !== null; $bajoMin = $hayMinimo && (float)$producto->stock_actual < (float)$producto->stock_minimo; @endphp
            <div class="kpi" x-data="{ editing: false }">
                <div class="top-bar {{ $bajoMin ? 'bg-orange-500' : 'bg-gray-300 dark:bg-gray-700' }}"></div>
                <p class="kpi-label">Stock mínimo</p>
                <div x-show="!editing">
                    <p class="kpi-val {{ $bajoMin ? 'text-orange-600 dark:text-orange-400' : 'text-gray-600 dark:text-gray-300' }}">
                        {{ $hayMinimo ? number_format((float)$producto->stock_minimo, 2, ',', '.') : '—' }}
                        @if($hayMinimo)<span style="font-size:13px;font-weight:600;color:#94a3b8"> {{ $producto->unidad }}</span>@endif
                    </p>
                    <p class="kpi-sub {{ $bajoMin ? 'text-orange-500 font-semibold' : '' }}">
                        {{ $bajoMin ? '⚠ Stock bajo el mínimo' : ($hayMinimo ? 'Alerta activa' : 'Sin alerta configurada') }}
                    </p>
                    @if(auth()->user()?->isAdmin())
                    <button @click="editing=true" class="mt-2 text-[10px] font-semibold text-indigo-500 hover:text-indigo-700 dark:text-indigo-400 hover:underline">
                        {{ $hayMinimo ? 'Cambiar' : 'Configurar' }}
                    </button>
                    @endif
                </div>
                @if(auth()->user()?->isAdmin())
                <div x-show="editing" x-cloak>
                    <form method="POST" action="{{ route('inventario.producto.minimo', $producto->id) }}">
                        @csrf @method('PATCH')
                        <input type="number" name="stock_minimo" step="0.01" min="0"
                            value="{{ $producto->stock_minimo }}"
                            class="w-full text-sm border border-gray-200 dark:border-gray-700 rounded-lg px-2 py-1.5 mt-1 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 focus:border-indigo-400 outline-none"
                            placeholder="Ej: 10">
                        <div class="flex gap-2 mt-2">
                            <button type="submit" class="text-[11px] font-bold px-3 py-1 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition">Guardar</button>
                            <button type="button" @click="editing=false" class="text-[11px] font-bold px-3 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200 transition">Cancelar</button>
                        </div>
                    </form>
                </div>
                @endif
            </div>
        </div>

        {{-- ── COLA FIFO ── --}}
        @if($lotes->count() > 0)
        <div class="panel">
            <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center">
                        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Cola FIFO — Lotes activos</h3>
                        <p class="text-[11px] text-gray-400">El primero de la lista se consume primero (más antiguo)</p>
                    </div>
                </div>
                <div class="flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="text-[11px] font-bold text-emerald-700 dark:text-emerald-300">{{ $lotes->count() }} lotes</span>
                </div>
            </div>

            {{-- Barra visual proporcional de lotes --}}
            @php $stockMax = max((float)$lotes->max('cantidad_disponible'), 1); @endphp
            <div class="px-5 pt-4 pb-2">
                <div class="flex gap-1 h-7 rounded-xl overflow-hidden mb-1">
                    @foreach($lotes as $idx => $lote)
                    @php
                        $pct    = $stockTotal > 0 ? ($lote->cantidad_disponible / $stockTotal) * 100 : 0;
                        $colors = ['bg-emerald-500','bg-teal-500','bg-cyan-500','bg-sky-500','bg-indigo-500','bg-violet-500','bg-purple-500'];
                        $color  = $colors[$idx % count($colors)];
                        $age    = \Carbon\Carbon::parse($lote->ingresado_el)->diffInDays(now());
                    @endphp
                    <div class="{{ $color }}" style="width:{{ max($pct,0.5) }}%"
                         title="Lote {{ $idx+1 }} — {{ number_format($lote->cantidad_disponible,2,',','.') }} {{ $producto->unidad }} — {{ $age }}d antigüedad"></div>
                    @endforeach
                </div>
                <div class="flex justify-between text-[10px] text-gray-400">
                    <span>← Consume primero</span>
                    <span>Consume último →</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="dt">
                    <thead>
                        <tr>
                            <th class="w-10 text-center col-hide-sm">Orden</th>
                            <th>Fecha ingreso</th>
                            <th class="col-hide-sm">Antigüedad</th>
                            <th>Proveedor</th>
                            <th class="col-hide-sm">N° Factura</th>
                            <th class="text-right col-hide-sm">Ingresado</th>
                            <th class="text-right col-hide-sm">Consumido</th>
                            <th class="text-right">Disponible</th>
                            @if(auth()->user()->canSeeValues())
                            <th class="text-right">Costo unit.</th>
                            <th class="text-right pr-5 col-hide-sm">Valor lote</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lotes as $idx => $lote)
                        @php
                            $isFirst = $idx === 0;
                            $age     = \Carbon\Carbon::parse($lote->ingresado_el)->diffInDays(now());
                            $ageColor = $age > 180 ? 'text-rose-500 dark:text-rose-400'
                                      : ($age > 60 ? 'text-amber-500 dark:text-amber-400'
                                      : 'text-emerald-600 dark:text-emerald-400');
                            $pctDisp = $lote->cantidad_ingresada > 0
                                ? ($lote->cantidad_disponible / $lote->cantidad_ingresada) * 100 : 100;
                        @endphp
                        <tr>
                            <td class="text-center col-hide-sm">
                                @if($isFirst)
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-500 text-white text-[10px] font-black">1</span>
                                @else
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 text-[10px] font-bold">{{ $idx + 1 }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="font-semibold text-gray-800 dark:text-gray-200">
                                    {{ \Carbon\Carbon::parse($lote->ingresado_el)->format('d/m/Y') }}
                                </span>
                                @if($isFirst)
                                    <span class="ml-1 px-1.5 py-0.5 rounded text-[9px] font-bold
                                                 bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                        Siguiente
                                    </span>
                                @endif
                            </td>
                            <td class="font-bold {{ $ageColor }} col-hide-sm">{{ $age }}d</td>
                            <td class="max-w-[170px]">
                                @if($lote->proveedor)
                                    <span class="block truncate text-gray-700 dark:text-gray-300 text-sm font-medium"
                                          title="{{ $lote->proveedor }}">
                                        {{ Str::limit($lote->proveedor, 26) }}
                                    </span>
                                @else
                                    <span class="text-gray-400 italic text-xs">Sin proveedor</span>
                                @endif
                            </td>
                            <td>
                                @if($lote->folio)
                                    <span class="font-mono text-xs font-bold px-2 py-0.5 rounded
                                                 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                                        #{{ $lote->folio }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="text-right tabular-nums text-gray-500 col-hide-sm">
                                {{ number_format((float)$lote->cantidad_ingresada, 2, ',', '.') }}
                            </td>
                            <td class="text-right tabular-nums col-hide-sm {{ $lote->cantidad_salida > 0 ? 'text-rose-500 dark:text-rose-400 font-medium' : 'text-gray-300' }}">
                                {{ $lote->cantidad_salida > 0 ? number_format((float)$lote->cantidad_salida, 2, ',', '.') : '—' }}
                            </td>
                            <td class="text-right">
                                <span class="font-bold tabular-nums text-emerald-700 dark:text-emerald-400">
                                    {{ number_format((float)$lote->cantidad_disponible, 2, ',', '.') }}
                                </span>
                                <div class="h-1 bg-gray-100 dark:bg-gray-800 rounded-full mt-1 overflow-hidden" style="width:60px;margin-left:auto">
                                    <div class="h-1 bg-emerald-500 rounded-full" style="width:{{ number_format($pctDisp, 1) }}%"></div>
                                </div>
                            </td>
                            @if(auth()->user()->canSeeValues())
                            <td class="text-right tabular-nums font-bold text-gray-900 dark:text-gray-100">
                                ${{ number_format((float)$lote->costo_unitario, 0, ',', '.') }}
                            </td>
                            <td class="text-right tabular-nums font-black text-indigo-700 dark:text-indigo-400 pr-5 col-hide-sm">
                                ${{ number_format((float)($lote->cantidad_disponible * $lote->costo_unitario), 0, ',', '.') }}
                            </td>
                            @endif
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-50 dark:bg-gray-900/40">
                            <td colspan="7" class="px-4 py-3 text-xs font-black uppercase tracking-wider text-gray-500">Total</td>
                            <td class="px-4 py-3 text-right font-black tabular-nums text-emerald-700 dark:text-emerald-400">
                                {{ number_format($stockTotal, 2, ',', '.') }}
                            </td>
                            <td></td>
                            @if(auth()->user()->canSeeValues())
                            <td class="px-5 py-3 text-right font-black tabular-nums text-indigo-700 dark:text-indigo-400">
                                ${{ number_format($valorTotal, 0, ',', '.') }}
                            </td>
                            @endif
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @endif

        {{-- ── HISTORIAL DE PRECIOS + ESTADÍSTICAS ── --}}
        @if(auth()->user()->canSeeValues())
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

        {{-- Historial (2/3) --}}
        <div class="panel xl:col-span-2">
            <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center">
                    <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Evolución de precios</h3>
                    <p class="text-[11px] text-gray-400">Precios reales pagados según facturas ingresadas al inventario</p>
                </div>
            </div>

            @if($historialPrecios->count() > 0)
            {{-- Sparkline SVG --}}
            @php
                $pts  = $historialPrecios->values();
                $minP = (float) $pts->min('costo_unitario');
                $maxP = (float) $pts->max('costo_unitario');
                $rng  = $maxP - $minP ?: 1;
                $W = 700; $H = 80; $p = 10;
                $n = $pts->count();
                $poly = $pts->map(function($pt,$i) use ($n,$minP,$rng,$W,$H,$p){
                    $x = $n > 1 ? $p + ($i/($n-1))*($W-2*$p) : $W/2;
                    $y = $H - $p - (((float)$pt->costo_unitario - $minP)/$rng)*($H-2*$p);
                    return round($x,1).','.round($y,1);
                })->join(' ');
                $fill = $poly . " {$W},{$H} {$p},{$H}";
            @endphp
            <div class="px-5 pt-5 pb-2">
                <svg viewBox="0 0 {{ $W }} {{ $H }}" class="w-full h-20" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="pg" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%"   stop-color="#f59e0b" stop-opacity="0.25"/>
                            <stop offset="100%" stop-color="#f59e0b" stop-opacity="0.02"/>
                        </linearGradient>
                    </defs>
                    <polygon points="{{ $fill }}" fill="url(#pg)"/>
                    <polyline points="{{ $poly }}" fill="none" stroke="#f59e0b" stroke-width="2.5" stroke-linejoin="round"/>
                    @foreach($pts as $i => $pt)
                    @php
                        $x = $n > 1 ? $p + ($i/($n-1))*($W-2*$p) : $W/2;
                        $y = $H - $p - (((float)$pt->costo_unitario - $minP)/$rng)*($H-2*$p);
                    @endphp
                    <circle cx="{{ round($x,1) }}" cy="{{ round($y,1) }}"
                            r="{{ $i === $n-1 ? 4.5 : 3 }}"
                            fill="{{ $i === $n-1 ? '#f59e0b' : '#fff' }}"
                            stroke="#f59e0b" stroke-width="2"/>
                    @endforeach
                </svg>
                <div class="flex justify-between text-[10px] text-gray-400 mt-1">
                    <span>{{ \Carbon\Carbon::parse($pts->first()->ingresado_el)->format('d/m/Y') }}</span>
                    <span class="font-medium">${{ number_format($minP,0,',','.') }} — ${{ number_format($maxP,0,',','.') }}</span>
                    <span>{{ \Carbon\Carbon::parse($pts->last()->ingresado_el)->format('d/m/Y') }}</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="dt">
                    <thead>
                        <tr>
                            <th>Fecha ingreso</th>
                            <th>Proveedor</th>
                            <th>N° Factura</th>
                            <th>Fecha factura</th>
                            <th class="text-right">Cantidad</th>
                            <th class="text-right">Precio unitario</th>
                            <th class="text-right pr-5">Vs anterior</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $prevP = null; @endphp
                        @foreach($historialPrecios->reverse()->values() as $h)
                        @php
                            $diff    = $prevP !== null ? (float)$h->costo_unitario - $prevP : null;
                            $diffPct = ($prevP && $prevP > 0 && $diff !== null) ? ($diff/$prevP)*100 : null;
                            $prevP   = (float)$h->costo_unitario;
                        @endphp
                        <tr>
                            <td class="font-medium text-gray-700 dark:text-gray-300">
                                {{ \Carbon\Carbon::parse($h->ingresado_el)->format('d/m/Y') }}
                            </td>
                            <td class="max-w-[200px]">
                                @if($h->proveedor)
                                    <span class="truncate block text-gray-700 dark:text-gray-300 font-medium"
                                          title="{{ $h->proveedor }}">
                                        {{ Str::limit($h->proveedor, 32) }}
                                    </span>
                                @else
                                    <span class="text-gray-400 italic text-xs">Sin proveedor</span>
                                @endif
                            </td>
                            <td>
                                @if($h->folio)
                                    <span class="font-mono text-xs font-bold px-1.5 py-0.5 rounded
                                                 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                                        #{{ $h->folio }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="text-gray-500 text-xs">
                                {{ $h->fecha_factura ? \Carbon\Carbon::parse($h->fecha_factura)->format('d/m/Y') : '—' }}
                            </td>
                            <td class="text-right tabular-nums text-gray-500">
                                {{ number_format((float)$h->cantidad_ingresada, 2, ',', '.') }}
                            </td>
                            <td class="text-right tabular-nums font-black text-gray-900 dark:text-gray-100">
                                ${{ number_format((float)$h->costo_unitario, 0, ',', '.') }}
                            </td>
                            <td class="text-right pr-5">
                                @if($diffPct !== null)
                                    <span class="text-xs font-bold {{ $diff > 0 ? 'text-rose-600 dark:text-rose-400' : ($diff < 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400') }}">
                                        @if($diff > 0) ↑ +{{ number_format($diffPct,1) }}%
                                        @elseif($diff < 0) ↓ {{ number_format($diffPct,1) }}%
                                        @else — @endif
                                    </span>
                                @else
                                    <span class="text-gray-300 text-xs">—</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="px-5 py-14 text-center">
                <svg class="w-10 h-10 text-gray-300 dark:text-gray-700 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <p class="text-sm font-semibold text-gray-400">Sin historial de precios aún</p>
                <p class="text-xs text-gray-400 mt-0.5">Se registrará automáticamente al ingresar facturas (DTEs) al inventario.</p>
            </div>
            @endif
        </div>{{-- /panel historial --}}

        {{-- ── ESTADÍSTICAS DE PRECIOS (1/3) ── --}}
        @php
            $preciosArr   = $historialPrecios->pluck('costo_unitario')->map(fn($v) => (float)$v)->filter(fn($v) => $v > 0);
            $precioMin    = $preciosArr->min() ?? 0;
            $precioMax    = $preciosArr->max() ?? 0;
            $precioPromH  = $preciosArr->count() > 0 ? $preciosArr->avg() : 0;
            $totalCompras = $historialPrecios->count();
            $totalUds     = $historialPrecios->sum('cantidad_ingresada');
            $proveedores  = $historialPrecios->whereNotNull('proveedor')->pluck('proveedor')
                                ->filter()->countBy()->sortDesc()->take(3);
        @endphp
        <div class="panel xl:col-span-1 flex flex-col">
            <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-sky-100 dark:bg-sky-900/40 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Estadísticas de precio</h3>
                    <p class="text-[11px] text-gray-400">Histórico completo de compras</p>
                </div>
            </div>

            <div class="px-5 py-4 space-y-4 flex-1">

                {{-- Precio mínimo / máximo --}}
                <div>
                    <p class="text-[9.5px] font-bold uppercase tracking-wider text-gray-400 mb-2">Rango de precios</p>
                    <div class="flex items-center gap-2">
                        <div class="flex-1 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl px-3 py-2 text-center">
                            <p class="text-[9px] font-bold text-emerald-600 dark:text-emerald-400 uppercase mb-0.5">Mínimo</p>
                            <p class="text-base font-black tabular-nums text-emerald-700 dark:text-emerald-300">
                                {{ $precioMin > 0 ? '$'.number_format($precioMin,0,',','.') : '—' }}
                            </p>
                        </div>
                        <div class="flex-1 bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 rounded-xl px-3 py-2 text-center">
                            <p class="text-[9px] font-bold text-rose-600 dark:text-rose-400 uppercase mb-0.5">Máximo</p>
                            <p class="text-base font-black tabular-nums text-rose-700 dark:text-rose-300">
                                {{ $precioMax > 0 ? '$'.number_format($precioMax,0,',','.') : '—' }}
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Barra min/max --}}
                @if($precioMin > 0 && $precioMax > $precioMin)
                <div>
                    <div class="relative h-2 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                        <div class="absolute inset-y-0 left-0 right-0 bg-gradient-to-r from-emerald-400 to-rose-400 rounded-full"></div>
                        {{-- marcador costo promedio actual --}}
                        @php $markerPct = (($costoPromedio - $precioMin) / ($precioMax - $precioMin)) * 100; @endphp
                        @if($costoPromedio >= $precioMin && $costoPromedio <= $precioMax)
                        <div class="absolute top-1/2 -translate-y-1/2 w-3 h-3 bg-white border-2 border-indigo-500 rounded-full shadow"
                             style="left:calc({{ number_format($markerPct,1) }}% - 6px)"></div>
                        @endif
                    </div>
                    <p class="text-[9px] text-center text-gray-400 mt-1">
                        Costo promedio actual: <span class="font-bold text-indigo-600 dark:text-indigo-400">${{ number_format($costoPromedio,0,',','.') }}</span>
                    </p>
                </div>
                @endif

                {{-- Stats --}}
                <div class="space-y-2.5">
                    <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-800">
                        <span class="text-xs text-gray-500 dark:text-gray-400">Precio promedio histórico</span>
                        <span class="text-sm font-bold tabular-nums text-gray-800 dark:text-gray-200">
                            {{ $precioPromH > 0 ? '$'.number_format($precioPromH,0,',','.') : '—' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-800">
                        <span class="text-xs text-gray-500 dark:text-gray-400">N° de compras registradas</span>
                        <span class="text-sm font-black text-sky-700 dark:text-sky-400">{{ $totalCompras }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-800">
                        <span class="text-xs text-gray-500 dark:text-gray-400">Unidades compradas (total)</span>
                        <span class="text-sm font-bold tabular-nums text-gray-800 dark:text-gray-200">
                            {{ number_format((float)$totalUds, 2, ',', '.') }}
                        </span>
                    </div>
                </div>

                {{-- Proveedores más frecuentes --}}
                @if($proveedores->count() > 0)
                <div>
                    <p class="text-[9.5px] font-bold uppercase tracking-wider text-gray-400 mb-2">Proveedores frecuentes</p>
                    <div class="space-y-2">
                        @php $maxCompras = $proveedores->first(); @endphp
                        @foreach($proveedores as $prov => $cnt)
                        <div>
                            <div class="flex items-center justify-between mb-0.5">
                                <span class="text-xs font-medium text-gray-700 dark:text-gray-300 truncate max-w-[160px]"
                                      title="{{ $prov }}">{{ Str::limit($prov, 24) }}</span>
                                <span class="text-[10px] font-bold text-sky-600 dark:text-sky-400 ml-1 shrink-0">
                                    {{ $cnt }} {{ $cnt === 1 ? 'compra' : 'compras' }}
                                </span>
                            </div>
                            <div class="h-1.5 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                                <div class="h-1.5 bg-sky-400 rounded-full"
                                     style="width:{{ number_format(($cnt / $maxCompras) * 100, 1) }}%"></div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

            </div>
        </div>{{-- /panel estadísticas --}}

        </div>{{-- /grid historial+stats --}}
        @endif

        {{-- ── MOVIMIENTOS RECIENTES ── --}}
        @if($movimientos->count() > 0)
        <div class="panel">
            <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-violet-100 dark:bg-violet-900/40 flex items-center justify-center">
                    <svg class="w-4 h-4 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Movimientos recientes</h3>
                    <p class="text-[11px] text-gray-400">Últimos {{ $movimientos->count() }} movimientos de este producto</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="dt">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th class="text-right">Cantidad</th>
                            @if(auth()->user()->canSeeValues())
                            <th class="text-right col-hide-sm">Costo unit.</th>
                            <th class="text-right">Costo total</th>
                            @endif
                            <th class="col-hide-sm">Proveedor / Factura</th>
                            <th class="pr-5 col-hide-sm">Notas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($movimientos as $m)
                        @php
                            $isEntrada = str_contains(strtoupper($m->tipo), 'ENTRADA') || $m->tipo === 'COMPRA';
                            $badgeClass = $isEntrada ? 'badge-entrada' : (str_contains(strtoupper($m->tipo), 'AJUSTE') ? 'badge-ajuste' : 'badge-salida');
                        @endphp
                        <tr>
                            <td class="font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                {{ \Carbon\Carbon::parse($m->ocurrio_el)->format('d/m/Y H:i') }}
                            </td>
                            <td>
                                <span class="badge {{ $badgeClass }}">{{ $m->tipo }}</span>
                            </td>
                            <td class="text-right tabular-nums font-bold {{ $isEntrada ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                {{ $isEntrada ? '+' : '−' }}{{ number_format((float)$m->cantidad, 2, ',', '.') }}
                            </td>
                            @if(auth()->user()->canSeeValues())
                            <td class="text-right tabular-nums text-gray-600 dark:text-gray-400 col-hide-sm">
                                {{ $m->costo_unitario ? '$'.number_format((float)$m->costo_unitario,0,',','.') : '—' }}
                            </td>
                            <td class="text-right tabular-nums font-semibold text-gray-800 dark:text-gray-200">
                                {{ $m->costo_total ? '$'.number_format((float)$m->costo_total,0,',','.') : '—' }}
                            </td>
                            @endif
                            <td class="max-w-[180px] col-hide-sm">
                                @if($m->proveedor)
                                    <span class="text-xs font-medium text-gray-700 dark:text-gray-300 block truncate">{{ $m->proveedor }}</span>
                                @endif
                                @if($m->folio)
                                    <span class="font-mono text-[10px] text-gray-400">#{{ $m->folio }}</span>
                                @endif
                            </td>
                            <td class="pr-5 max-w-[200px] col-hide-sm">
                                @if($m->notas)
                                    <span class="text-xs text-gray-400 block truncate" title="{{ $m->notas }}">
                                        {{ Str::limit($m->notas, 45) }}
                                    </span>
                                @endif
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
