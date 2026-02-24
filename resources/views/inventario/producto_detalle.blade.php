<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-3 flex-wrap">
            <div class="flex items-center gap-1.5 min-w-0 text-xs">
                <a href="{{ route('inventario.productos') }}"
                   class="text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition font-medium">
                    Productos
                </a>
                <svg class="w-3 h-3 text-gray-300 dark:text-gray-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                </svg>
                <span class="font-bold text-gray-700 dark:text-gray-300 truncate">{{ $producto->nombre }}</span>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold
                    {{ $producto->activo ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $producto->activo ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                    {{ $producto->activo ? 'Activo' : 'Inactivo' }}
                </span>
            </div>
        </div>
    </x-slot>

    <style>
        [x-cloak] { display:none !important }

        .page-bg { background:#f1f5f9; min-height:100% }
        .dark .page-bg { background:#0d1117 }

        .panel { background:#fff; border:1px solid #e2e8f0; border-radius:18px; overflow:hidden }
        .dark .panel { background:#161c2c; border-color:#1e2a3b }

        /* KPI Cards */
        .kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px }
        @media(max-width:900px) { .kpi-grid { grid-template-columns:repeat(2,1fr) } }
        @media(max-width:500px) { .kpi-grid { grid-template-columns:1fr } }

        .kpi-card {
            background:#fff; border:1px solid #e2e8f0; border-radius:16px;
            padding:18px 20px; position:relative; overflow:hidden;
        }
        .dark .kpi-card { background:#161c2c; border-color:#1e2a3b }
        .kpi-card .accent { position:absolute; top:0; left:0; right:0; height:3px; }
        .kpi-label { font-size:10px; font-weight:700; text-transform:uppercase;
                     letter-spacing:.07em; color:#94a3b8; margin-bottom:6px }
        .kpi-value { font-size:22px; font-weight:900; color:#0f172a; line-height:1; font-variant-numeric:tabular-nums }
        .dark .kpi-value { color:#f1f5f9 }
        .kpi-sub { font-size:11px; color:#94a3b8; margin-top:5px; font-weight:500 }

        /* FIFO table */
        .fifo-table { width:100%; border-collapse:collapse; font-size:13px }
        .fifo-table thead th {
            padding:9px 14px; font-size:10px; font-weight:700; text-transform:uppercase;
            letter-spacing:.07em; color:#94a3b8; text-align:left;
            box-shadow:inset 0 -2px 0 #e2e8f0; background:#f8fafc; white-space:nowrap;
        }
        .dark .fifo-table thead th { background:#0f1623; box-shadow:inset 0 -2px 0 #1e2a3b; color:#64748b }
        .fifo-table tbody td {
            padding:11px 14px; border-bottom:1px solid #f1f5f9;
            color:#334155; vertical-align:middle
        }
        .dark .fifo-table tbody td { border-color:#1a2232; color:#cbd5e1 }
        .fifo-table tbody tr:last-child td { border-bottom:none }
        .fifo-table tbody tr:hover td { background:#f5fffb }
        .dark .fifo-table tbody tr:hover td { background:rgba(16,185,129,.02) }

        /* Price table */
        .price-table { width:100%; border-collapse:collapse; font-size:12.5px }
        .price-table thead th {
            padding:8px 12px; font-size:9.5px; font-weight:700; text-transform:uppercase;
            letter-spacing:.07em; color:#94a3b8; text-align:left;
            box-shadow:inset 0 -2px 0 #e2e8f0; background:#f8fafc;
        }
        .dark .price-table thead th { background:#0f1623; box-shadow:inset 0 -2px 0 #1e2a3b }
        .price-table tbody td { padding:9px 12px; border-bottom:1px solid #f1f5f9; color:#334155; vertical-align:middle }
        .dark .price-table tbody td { border-color:#1a2232; color:#cbd5e1 }
        .price-table tbody tr:last-child td { border-bottom:none }

        /* Movement types */
        .mov-badge { display:inline-flex; align-items:center; gap:4px;
                     padding:2px 8px; border-radius:999px; font-size:10px; font-weight:700 }
        .mov-entrada  { background:#dcfce7; color:#166534 }
        .dark .mov-entrada  { background:rgba(22,163,74,.15); color:#86efac }
        .mov-salida   { background:#fee2e2; color:#991b1b }
        .dark .mov-salida   { background:rgba(239,68,68,.15); color:#fca5a5 }
        .mov-ajuste   { background:#fef9c3; color:#854d0e }
        .dark .mov-ajuste   { background:rgba(234,179,8,.1); color:#fde047 }
        .mov-traspaso { background:#e0f2fe; color:#075985 }
        .dark .mov-traspaso { background:rgba(14,165,233,.1); color:#7dd3fc }
    </style>

    <div class="page-bg">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

            {{-- ── CABECERA DEL PRODUCTO ── --}}
            <div class="panel">
                <div class="px-6 py-5">
                    <div class="flex items-start justify-between gap-4 flex-wrap">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                @if($producto->sku)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold
                                                 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 font-mono">
                                        {{ $producto->sku }}
                                    </span>
                                @endif
                                <span class="text-xs text-gray-400 font-medium">#{{ $producto->id }}</span>
                            </div>
                            <h1 class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-gray-100 tracking-tight leading-tight">
                                {{ $producto->nombre }}
                            </h1>
                            @if($producto->descripcion)
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1.5 leading-relaxed max-w-2xl">
                                    {{ $producto->descripcion }}
                                </p>
                            @endif
                        </div>
                        @if($variacion !== null)
                        <div class="shrink-0 text-right">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Variación precio</p>
                            <div class="flex items-center gap-1 justify-end">
                                @if($variacion > 0)
                                    <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                                    </svg>
                                    <span class="text-lg font-black text-rose-600 dark:text-rose-400">+{{ number_format($variacion, 1) }}%</span>
                                @elseif($variacion < 0)
                                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                                    </svg>
                                    <span class="text-lg font-black text-emerald-600 dark:text-emerald-400">{{ number_format($variacion, 1) }}%</span>
                                @else
                                    <span class="text-lg font-black text-gray-500">Sin cambio</span>
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
                {{-- Stock total --}}
                <div class="kpi-card">
                    <div class="accent bg-emerald-500"></div>
                    <p class="kpi-label">Stock disponible</p>
                    <p class="kpi-value text-emerald-700 dark:text-emerald-400">
                        {{ $stockTotal > 0 ? number_format((float)$stockTotal, 2, ',', '.') : '0' }}
                    </p>
                    <p class="kpi-sub">{{ $lotes->count() }} lote{{ $lotes->count() !== 1 ? 's' : '' }} activo{{ $lotes->count() !== 1 ? 's' : '' }} (FIFO)</p>
                </div>

                {{-- Costo promedio FIFO ponderado --}}
                <div class="kpi-card">
                    <div class="accent bg-indigo-500"></div>
                    <p class="kpi-label">Costo prom. FIFO</p>
                    <p class="kpi-value text-indigo-700 dark:text-indigo-400">
                        {{ $costoPromedio > 0 ? '$' . number_format((float)$costoPromedio, 0, ',', '.') : '—' }}
                    </p>
                    <p class="kpi-sub">Ponderado por disponibilidad</p>
                </div>

                {{-- Valor total en inventario --}}
                <div class="kpi-card">
                    <div class="accent bg-violet-500"></div>
                    <p class="kpi-label">Valor en inventario</p>
                    <p class="kpi-value text-violet-700 dark:text-violet-400">
                        {{ $valorTotal > 0 ? '$' . number_format((float)$valorTotal, 0, ',', '.') : '—' }}
                    </p>
                    <p class="kpi-sub">Stock × costo promedio</p>
                </div>

                {{-- Último precio pagado --}}
                <div class="kpi-card">
                    <div class="accent bg-amber-500"></div>
                    <p class="kpi-label">Último precio pagado</p>
                    <p class="kpi-value text-amber-700 dark:text-amber-400">
                        {{ $ultimoPrecio > 0 ? '$' . number_format((float)$ultimoPrecio, 0, ',', '.') : '—' }}
                    </p>
                    @php $lastEntry = $historialPrecios->last(); @endphp
                    <p class="kpi-sub">
                        {{ $lastEntry ? \Carbon\Carbon::parse($lastEntry->ingresado_el)->format('d/m/Y') : 'Sin datos' }}
                    </p>
                </div>
            </div>

            {{-- ── COLA FIFO ── --}}
            @if($lotes->count() > 0)
            <div class="panel">
                <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Cola FIFO — Lotes activos</h3>
                            <p class="text-[11px] text-gray-400">El primer lote de la lista se consume primero</p>
                        </div>
                    </div>
                    <div class="shrink-0 flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="text-[11px] font-bold text-emerald-700 dark:text-emerald-300">{{ $lotes->count() }} lotes</span>
                    </div>
                </div>

                {{-- Barra visual de lotes --}}
                @php $stockMax = max($lotes->max('cantidad_disponible'), 1); @endphp
                <div class="px-5 py-4">
                    <div class="flex gap-1 h-8 rounded-xl overflow-hidden mb-4">
                        @foreach($lotes as $idx => $lote)
                            @php
                                $pct = $stockTotal > 0 ? ($lote->cantidad_disponible / $stockTotal) * 100 : 0;
                                $colors = ['bg-emerald-500','bg-teal-500','bg-cyan-500','bg-sky-500','bg-indigo-500','bg-violet-500'];
                                $color = $colors[$idx % count($colors)];
                                $age = \Carbon\Carbon::parse($lote->ingresado_el)->diffInDays(now());
                            @endphp
                            <div class="{{ $color }} transition-all" style="width:{{ max($pct,1) }}%"
                                 title="Lote {{ $idx+1 }} — {{ number_format($lote->cantidad_disponible,2,',','.') }} uds — {{ $age }}d"></div>
                        @endforeach
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="fifo-table">
                        <thead>
                            <tr>
                                <th class="w-8 text-center">Orden</th>
                                <th>Fecha ingreso</th>
                                <th>Antigüedad</th>
                                <th>Proveedor / Origen</th>
                                <th>Factura</th>
                                <th>Bodega</th>
                                <th class="text-right">Ingresado</th>
                                <th class="text-right">Disponible</th>
                                <th class="text-right">Consumido</th>
                                <th class="text-right">Costo unit.</th>
                                <th class="text-right pr-5">Valor total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lotes as $idx => $lote)
                            @php
                                $isFirst  = $idx === 0;
                                $age      = \Carbon\Carbon::parse($lote->ingresado_el)->diffInDays(now());
                                $ageColor = $age > 180 ? 'text-rose-600 dark:text-rose-400'
                                          : ($age > 60 ? 'text-amber-600 dark:text-amber-400'
                                          : 'text-emerald-600 dark:text-emerald-400');
                                $pctConsumed = $lote->cantidad_ingresada > 0
                                    ? ($lote->cantidad_salida / $lote->cantidad_ingresada) * 100 : 0;
                            @endphp
                            <tr>
                                <td class="text-center">
                                    @if($isFirst)
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full
                                                     bg-emerald-500 text-white text-[10px] font-black">1</span>
                                    @else
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full
                                                     bg-gray-100 dark:bg-gray-800 text-gray-500 text-[10px] font-bold">
                                            {{ $idx + 1 }}
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <span class="font-semibold text-gray-800 dark:text-gray-200">
                                        {{ \Carbon\Carbon::parse($lote->ingresado_el)->format('d/m/Y') }}
                                    </span>
                                    @if($isFirst)
                                        <span class="ml-1.5 inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold
                                                     bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                            Siguiente
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <span class="font-semibold {{ $ageColor }}">{{ $age }}d</span>
                                </td>
                                <td class="max-w-[160px]">
                                    @if($lote->proveedor)
                                        <span class="font-medium text-gray-800 dark:text-gray-200 truncate block" title="{{ $lote->proveedor }}">
                                            {{ Str::limit($lote->proveedor, 28) }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 italic text-xs">{{ ucfirst($lote->origen_tipo ?? 'manual') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($lote->folio)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold
                                                     bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 font-mono">
                                            #{{ $lote->folio }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-[11px] font-medium text-gray-600 dark:text-gray-400">
                                        {{ $lote->bodega_nombre ?? '—' }}
                                    </span>
                                </td>
                                <td class="text-right tabular-nums text-gray-500">
                                    {{ number_format((float)$lote->cantidad_ingresada, 2, ',', '.') }}
                                </td>
                                <td class="text-right">
                                    <span class="font-bold tabular-nums text-emerald-700 dark:text-emerald-400">
                                        {{ number_format((float)$lote->cantidad_disponible, 2, ',', '.') }}
                                    </span>
                                    {{-- mini barra de consumo --}}
                                    <div class="h-1 rounded-full bg-gray-100 dark:bg-gray-800 mt-1 overflow-hidden" style="width:60px;margin-left:auto">
                                        <div class="h-1 rounded-full bg-emerald-500" style="width:{{ number_format(100 - $pctConsumed, 1) }}%"></div>
                                    </div>
                                </td>
                                <td class="text-right tabular-nums text-rose-500 dark:text-rose-400 font-medium">
                                    {{ $lote->cantidad_salida > 0 ? number_format((float)$lote->cantidad_salida, 2, ',', '.') : '—' }}
                                </td>
                                <td class="text-right tabular-nums font-bold text-gray-800 dark:text-gray-200">
                                    ${{ number_format((float)$lote->costo_unitario, 0, ',', '.') }}
                                </td>
                                <td class="text-right tabular-nums font-black text-indigo-700 dark:text-indigo-400 pr-5">
                                    ${{ number_format((float)($lote->cantidad_disponible * $lote->costo_unitario), 0, ',', '.') }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-50 dark:bg-gray-900/40">
                                <td colspan="7" class="px-4 py-3 text-xs font-black uppercase tracking-wider text-gray-500">
                                    Total
                                </td>
                                <td class="px-4 py-3 text-right font-black tabular-nums text-emerald-700 dark:text-emerald-400">
                                    {{ number_format((float)$stockTotal, 2, ',', '.') }}
                                </td>
                                <td colspan="2"></td>
                                <td class="px-5 py-3 text-right font-black tabular-nums text-indigo-700 dark:text-indigo-400">
                                    ${{ number_format((float)$valorTotal, 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            @endif

            {{-- ── HISTORIAL DE PRECIOS + STOCK POR BODEGA ── --}}
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

                {{-- Gráfico + tabla de precios --}}
                <div class="panel xl:col-span-2">
                    <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Historial de precios</h3>
                            <p class="text-[11px] text-gray-400">Desde facturas reales ingresadas al inventario</p>
                        </div>
                    </div>

                    @if($historialPrecios->count() > 0)
                    {{-- Sparkline SVG --}}
                    @php
                        $chartPts  = $historialPrecios->values();
                        $minP = (float) $chartPts->min('costo_unitario');
                        $maxP = (float) $chartPts->max('costo_unitario');
                        $rng  = $maxP - $minP ?: 1;
                        $svgW = 600; $svgH = 90; $pad = 10;
                        $n    = $chartPts->count();
                        $polyline = $chartPts->map(function($p, $i) use ($n, $minP, $rng, $svgW, $svgH, $pad) {
                            $x = $n > 1 ? $pad + ($i / ($n - 1)) * ($svgW - 2*$pad) : $svgW / 2;
                            $y = $svgH - $pad - (((float)$p->costo_unitario - $minP) / $rng) * ($svgH - 2*$pad);
                            return round($x,1) . ',' . round($y,1);
                        })->join(' ');
                        $fillPts = $polyline . " {$svgW},{$svgH} {$pad},{$svgH}";
                    @endphp
                    <div class="px-5 pt-4 pb-2">
                        <svg viewBox="0 0 {{ $svgW }} {{ $svgH }}" class="w-full h-20" preserveAspectRatio="none">
                            <defs>
                                <linearGradient id="priceGrad" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#f59e0b" stop-opacity="0.3"/>
                                    <stop offset="100%" stop-color="#f59e0b" stop-opacity="0.02"/>
                                </linearGradient>
                            </defs>
                            <polygon points="{{ $fillPts }}" fill="url(#priceGrad)"/>
                            <polyline points="{{ $polyline }}" fill="none" stroke="#f59e0b" stroke-width="2.5" stroke-linejoin="round"/>
                            @foreach($chartPts as $i => $cp)
                                @php
                                    $x = $n > 1 ? $pad + ($i / ($n-1)) * ($svgW - 2*$pad) : $svgW/2;
                                    $y = $svgH - $pad - (((float)$cp->costo_unitario - $minP) / $rng) * ($svgH - 2*$pad);
                                @endphp
                                <circle cx="{{ round($x,1) }}" cy="{{ round($y,1) }}" r="{{ $i === $n-1 ? 4 : 3 }}"
                                        fill="{{ $i === $n-1 ? '#f59e0b' : '#fff' }}"
                                        stroke="#f59e0b" stroke-width="2"/>
                            @endforeach
                        </svg>
                        <div class="flex justify-between text-[10px] text-gray-400 mt-1">
                            <span>{{ \Carbon\Carbon::parse($chartPts->first()->ingresado_el)->format('d/m/Y') }}</span>
                            <span>${{ number_format($minP,0,',','.') }} — ${{ number_format($maxP,0,',','.') }}</span>
                            <span>{{ \Carbon\Carbon::parse($chartPts->last()->ingresado_el)->format('d/m/Y') }}</span>
                        </div>
                    </div>

                    {{-- Tabla de precios --}}
                    <div class="overflow-x-auto">
                        <table class="price-table">
                            <thead>
                                <tr>
                                    <th>Fecha ingreso</th>
                                    <th>Proveedor</th>
                                    <th>N° Factura</th>
                                    <th class="text-right">Cantidad</th>
                                    <th class="text-right pr-4">Precio unitario</th>
                                    <th class="text-right pr-4">Vs anterior</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $prevPrice = null; @endphp
                                @foreach($historialPrecios->reverse()->values() as $h)
                                @php
                                    $diff     = $prevPrice !== null ? (float)$h->costo_unitario - $prevPrice : null;
                                    $diffPct  = ($prevPrice && $prevPrice > 0) ? ($diff / $prevPrice) * 100 : null;
                                    $prevPrice = (float)$h->costo_unitario;
                                @endphp
                                <tr>
                                    <td class="font-medium">
                                        {{ \Carbon\Carbon::parse($h->ingresado_el)->format('d/m/Y') }}
                                    </td>
                                    <td class="max-w-[180px]">
                                        @if($h->proveedor)
                                            <span class="truncate block text-gray-700 dark:text-gray-300" title="{{ $h->proveedor }}">
                                                {{ Str::limit($h->proveedor, 30) }}
                                            </span>
                                        @else
                                            <span class="text-gray-400 italic text-xs">{{ ucfirst($h->origen_tipo ?? 'manual') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($h->folio)
                                            <span class="font-mono text-xs font-bold bg-gray-100 dark:bg-gray-800
                                                         text-gray-600 dark:text-gray-400 px-1.5 py-0.5 rounded">
                                                #{{ $h->folio }}
                                            </span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="text-right tabular-nums text-gray-500">
                                        {{ number_format((float)$h->cantidad_ingresada, 2, ',', '.') }}
                                    </td>
                                    <td class="text-right tabular-nums font-black text-gray-900 dark:text-gray-100 pr-4">
                                        ${{ number_format((float)$h->costo_unitario, 0, ',', '.') }}
                                    </td>
                                    <td class="text-right pr-4">
                                        @if($diffPct !== null)
                                            <span class="inline-flex items-center gap-0.5 text-xs font-bold
                                                {{ $diff > 0 ? 'text-rose-600 dark:text-rose-400' : ($diff < 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400') }}">
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
                    <div class="px-5 py-12 text-center">
                        <svg class="w-10 h-10 text-gray-300 dark:text-gray-700 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        <p class="text-sm text-gray-400">Sin historial de precios aún.</p>
                        <p class="text-xs text-gray-400 mt-0.5">Se registrará automáticamente al ingresar facturas.</p>
                    </div>
                    @endif
                </div>

                {{-- Stock por bodega --}}
                <div class="panel">
                    <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-sky-100 dark:bg-sky-900/40 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Stock por bodega</h3>
                    </div>
                    <div class="px-5 py-4 space-y-3">
                        @forelse($stockPorBodega as $b)
                        @php $pct = $stockTotal > 0 ? ($b->stock / $stockTotal) * 100 : 0; @endphp
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    @if($b->es_principal)
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                                    @else
                                        <span class="w-1.5 h-1.5 rounded-full bg-sky-400 shrink-0"></span>
                                    @endif
                                    <span class="text-xs font-bold text-gray-700 dark:text-gray-300 truncate">
                                        {{ $b->bodega }}
                                    </span>
                                    @if($b->es_principal)
                                        <span class="text-[9px] font-bold text-emerald-600 dark:text-emerald-400 uppercase">Principal</span>
                                    @endif
                                </div>
                                <span class="text-xs font-black tabular-nums text-gray-900 dark:text-gray-100 ml-2 shrink-0">
                                    {{ number_format((float)$b->stock, 2, ',', '.') }}
                                </span>
                            </div>
                            <div class="h-1.5 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                                <div class="h-1.5 rounded-full {{ $b->es_principal ? 'bg-emerald-500' : 'bg-sky-400' }}"
                                     style="width:{{ number_format($pct,1) }}%"></div>
                            </div>
                            <div class="flex justify-between mt-0.5">
                                <span class="text-[10px] text-gray-400">{{ number_format($pct,0) }}% del total</span>
                                <span class="text-[10px] text-gray-400">
                                    Costo prom: ${{ number_format((float)$b->costo_promedio,0,',','.') }}
                                </span>
                            </div>
                        </div>
                        @empty
                        <div class="py-8 text-center">
                            <p class="text-sm text-gray-400">Sin stock en bodegas.</p>
                        </div>
                        @endforelse
                    </div>

                    {{-- Total general --}}
                    @if($stockPorBodega->count() > 0)
                    <div class="px-5 py-3 border-t border-gray-100 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-900/20">
                        <div class="flex justify-between items-baseline">
                            <span class="text-xs font-black uppercase tracking-wider text-gray-600 dark:text-gray-400">Total</span>
                            <span class="text-lg font-black tabular-nums text-emerald-700 dark:text-emerald-400">
                                {{ number_format((float)$stockTotal, 2, ',', '.') }}
                            </span>
                        </div>
                        <div class="flex justify-between items-baseline mt-0.5">
                            <span class="text-[10px] text-gray-400">Valor total</span>
                            <span class="text-xs font-bold tabular-nums text-indigo-700 dark:text-indigo-400">
                                ${{ number_format((float)$valorTotal, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- ── MOVIMIENTOS RECIENTES ── --}}
            @if($movimientos->count() > 0)
            <div class="panel">
                <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-violet-100 dark:bg-violet-900/40 flex items-center justify-center shrink-0">
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
                    <table class="fifo-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Bodega</th>
                                <th class="text-right">Cantidad</th>
                                <th class="text-right">Costo unit.</th>
                                <th class="text-right">Costo total</th>
                                <th class="pr-5">Documento / Notas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($movimientos as $m)
                            @php
                                $tipoLabel = match(true) {
                                    str_contains($m->tipo, 'ENTRADA')   => ['label' => str_replace('_', ' ', $m->tipo), 'class' => 'mov-entrada'],
                                    str_contains($m->tipo, 'SALIDA')    => ['label' => str_replace('_', ' ', $m->tipo), 'class' => 'mov-salida'],
                                    str_contains($m->tipo, 'AJUSTE')    => ['label' => str_replace('_', ' ', $m->tipo), 'class' => 'mov-ajuste'],
                                    str_contains($m->tipo, 'TRASPASO')  => ['label' => str_replace('_', ' ', $m->tipo), 'class' => 'mov-traspaso'],
                                    default => ['label' => $m->tipo, 'class' => 'mov-ajuste'],
                                };
                            @endphp
                            <tr>
                                <td class="font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($m->ocurrio_el)->format('d/m/Y H:i') }}
                                </td>
                                <td>
                                    <span class="mov-badge {{ $tipoLabel['class'] }}">{{ $tipoLabel['label'] }}</span>
                                </td>
                                <td class="text-sm text-gray-500 dark:text-gray-400">{{ $m->bodega ?? '—' }}</td>
                                <td class="text-right tabular-nums font-bold
                                    {{ str_contains($m->tipo,'ENTRADA') ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                    {{ str_contains($m->tipo,'SALIDA') ? '−' : '+' }}{{ number_format((float)$m->cantidad, 2, ',', '.') }}
                                </td>
                                <td class="text-right tabular-nums text-gray-600 dark:text-gray-400">
                                    {{ $m->costo_unitario ? '$'.number_format((float)$m->costo_unitario,0,',','.') : '—' }}
                                </td>
                                <td class="text-right tabular-nums font-semibold text-gray-800 dark:text-gray-200">
                                    {{ $m->costo_total ? '$'.number_format((float)$m->costo_total,0,',','.') : '—' }}
                                </td>
                                <td class="pr-5 max-w-[220px]">
                                    @if($m->documento_tipo)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold
                                                     bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400">
                                            {{ $m->documento_tipo }} #{{ $m->documento_id }}
                                        </span>
                                    @endif
                                    @if($m->notas)
                                        <span class="text-xs text-gray-400 truncate block mt-0.5" title="{{ $m->notas }}">
                                            {{ Str::limit($m->notas, 40) }}
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
