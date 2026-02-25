<x-app-layout>
    @php
        $tipoView = $tipo ?? 'facturas';
        $tipoTitle = match ($tipoView) {
            'boletas' => 'Boletas proveedor',
            'guias' => 'Guías de despacho proveedor',
            default => 'Facturas proveedor',
        };
        $tipoListLabel = match ($tipoView) {
            'boletas' => 'boletas',
            'guias' => 'guías',
            default => 'facturas',
        };
    @endphp

    <x-slot name="header">
        <div class="w-full grid grid-cols-1 lg:grid-cols-[auto,1fr,auto] items-center gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-xl bg-indigo-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">{{ $tipoTitle }}</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Listado {{ $tipoListLabel }} · {{ $documents->total() }} documentos</p>
                </div>
            </div>

            <form method="GET" class="hidden lg:block w-full lg:max-w-xl lg:justify-self-center">
                <div class="relative">
                    <input type="hidden" name="tipo" value="{{ $tipo ?? 'facturas' }}">
                    <input type="text" name="q" value="{{ $q }}" class="f-input pl-9" placeholder="Buscar por folio, proveedor, referencia...">
                    @if($q)
                        <a href="{{ route('gmail.dtes.list', ['tipo' => $tipo ?? 'facturas']) }}" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </a>
                    @endif
                </div>
            </form>

            <div class="hidden lg:flex items-center gap-3 justify-end shrink-0">
                @if($q)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        {{ $q }}
                    </span>
                @endif
            </div>
        </div>
    </x-slot>

    @php
        $tipoMap = [
            33 => ['sigla' => 'FAC', 'color' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300'],
            34 => ['sigla' => 'FEX', 'color' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300'],
            56 => ['sigla' => 'ND',  'color' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300'],
            61 => ['sigla' => 'NC',  'color' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300'],
            39 => ['sigla' => 'BOL', 'color' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300'],
            41 => ['sigla' => 'BEX', 'color' => 'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-900/30 dark:text-fuchsia-300'],
            52 => ['sigla' => 'GUI', 'color' => 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300'],
        ];
    @endphp

    <style>
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .au { animation: fadeUp .35s ease both; }
        .d1 { animation-delay: .06s; }
        .d2 { animation-delay: .12s; }
        .d3 { animation-delay: .18s; }

        .page-bg { background:#f1f5f9; min-height:100% }
        .dark .page-bg { background:#0d1117 }

        .panel { background:#fff; border:1px solid #e2e8f0; border-radius:18px; overflow:hidden }
        .dark .panel { background:#161c2c; border-color:#1e2a3b }

        .panel-toolbar {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        .dark .panel-toolbar { border-bottom-color: #1e2a3b }

        .f-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all .15s;
            background: #f1f5f9;
            color: #64748b;
        }
        .f-btn:hover { background: #e2e8f0; color: #334155 }
        .dark .f-btn { background: #1e2a3b; color: #94a3b8 }
        .dark .f-btn:hover { background: #273244 }
        .f-btn.active-all    { background: #6366f1; color: #fff; border-color: #6366f1 }
        .f-btn.active-sinpagar { background: #f43f5e; color: #fff; border-color: #f43f5e }
        .f-btn.active-pendiente { background: #f59e0b; color: #fff; border-color: #f59e0b }
        .f-btn.active-pagado { background: #10b981; color: #fff; border-color: #10b981 }

        .dt { width:100%; border-collapse:collapse; font-size:13px }
        .dt thead tr { background:#f8fafc; border-bottom: 2px solid #f1f5f9 }
        .dark .dt thead tr { background:#111827; border-bottom-color:#1e2a3b }
        .dt th {
            padding: 11px 14px;
            text-align: left;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .07em;
            text-transform: uppercase;
            color: #94a3b8;
            white-space: nowrap;
        }
        .dt td { padding: 13px 14px; border-bottom: 1px solid #f8fafc; color: #334155; vertical-align: middle }
        .dark .dt td { border-bottom-color: #1a2232; color: #cbd5e1 }
        .dt tbody tr:last-child td { border-bottom: none }
        .dt tbody tr:hover td { background: #f8fafc; cursor: pointer }
        .dark .dt tbody tr:hover td { background: #1a2436 }

        .f-input {
            width: 100%;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: #fff;
            padding: 9px 12px;
            font-size: 13px;
            color: #111827;
            outline: none;
        }
        .f-input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.12) }
        .dark .f-input { border-color: #1e2a3b; background: #0d1117; color: #f1f5f9 }

        .chip { display:inline-flex; align-items:center; border-radius:999px; padding:3px 9px; font-size:11px; font-weight:700; white-space:nowrap }

        .m-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 14px;
            display: block;
            transition: box-shadow .15s, border-color .15s;
        }
        .m-card:hover { box-shadow: 0 4px 14px rgba(15,23,42,.08); border-color: #c7d2fe }
        .dark .m-card { background: #161c2c; border-color: #1e2a3b }
        .dark .m-card:hover { border-color: #3730a3 }

        .table-scroll { overflow-x: auto; overflow-y: hidden; -webkit-overflow-scrolling: touch }
        .dt-wide { min-width: 1100px; table-layout: auto }

        .amt-main { font-size: 13px; font-weight: 800; color: #111827; font-variant-numeric: tabular-nums }
        .dark .amt-main { color: #f1f5f9 }
        .amt-sub { font-size: 11px; color: #94a3b8; font-variant-numeric: tabular-nums }

        .tipo-badge { display:inline-flex; align-items:center; border-radius:6px; padding:2px 7px; font-size:10px; font-weight:800; letter-spacing:.04em }
    </style>

    <div class="page-bg" x-data="{ filter: 'todos' }">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">

            {{-- Búsqueda móvil --}}
            <form method="GET" class="lg:hidden">
                <div class="relative">
                    <input type="hidden" name="tipo" value="{{ $tipo ?? 'facturas' }}">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0" />
                    </svg>
                    <input type="text" name="q" value="{{ $q }}" class="f-input pl-9" placeholder="Buscar folio, proveedor...">
                </div>
            </form>

            {{-- Panel principal --}}
            <div class="panel au d1">

                {{-- Toolbar con filtros --}}
                <div class="panel-toolbar">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <a href="{{ route('gmail.dtes.list', array_filter(['q' => $q, 'tipo' => 'facturas'])) }}"
                            class="{{ ($tipo ?? 'facturas') === 'facturas' ? 'f-btn active-all' : 'f-btn' }}">
                            Facturas
                        </a>
                        <a href="{{ route('gmail.dtes.list', array_filter(['q' => $q, 'tipo' => 'boletas'])) }}"
                            class="{{ ($tipo ?? 'facturas') === 'boletas' ? 'f-btn active-all' : 'f-btn' }}">
                            Boletas
                        </a>
                        <a href="{{ route('gmail.dtes.list', array_filter(['q' => $q, 'tipo' => 'guias'])) }}"
                            class="{{ ($tipo ?? 'facturas') === 'guias' ? 'f-btn active-all' : 'f-btn' }}">
                            Guías
                        </a>
                        <button @click="filter = 'todos'" :class="filter === 'todos' ? 'f-btn active-all' : 'f-btn'" class="f-btn">
                            Todas
                        </button>
                        <button @click="filter = 'sinpagar'" :class="filter === 'sinpagar' ? 'f-btn active-sinpagar' : 'f-btn'" class="f-btn">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500 inline-block"></span>
                            Sin pagar
                        </button>
                        <button @click="filter = 'pendiente'" :class="filter === 'pendiente' ? 'f-btn active-pendiente' : 'f-btn'" class="f-btn">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400 inline-block"></span>
                            Pendiente
                        </button>
                        <button @click="filter = 'pagado'" :class="filter === 'pagado' ? 'f-btn active-pagado' : 'f-btn'" class="f-btn">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span>
                            Pagadas
                        </button>
                    </div>
                    <div class="flex items-center gap-3 text-xs text-gray-400 shrink-0">
                        <span class="hidden sm:inline tabular-nums">
                            {{ $documents->total() }} {{ $tipoListLabel }} · pág. {{ $documents->currentPage() }}/{{ $documents->lastPage() }}
                        </span>
                        <span class="sm:hidden tabular-nums">{{ $documents->total() }} total</span>
                        @if($q)
                            <span class="hidden sm:inline text-indigo-500 font-semibold">"{{ $q }}"</span>
                        @endif
                        <p class="text-xs text-gray-300 dark:text-gray-600 hidden sm:block">|</p>
                        <span class="hidden sm:inline text-gray-400">Clic en una fila para ver detalle</span>
                    </div>
                </div>

                {{-- Tabla desktop --}}
                <div class="table-scroll hidden lg:block">
                    <table class="dt dt-wide">
                        <thead>
                            <tr>
                                <th style="width:160px">Documento</th>
                                <th style="width:260px">Proveedor</th>
                                <th style="width:120px">Fecha</th>
                                <th style="width:130px">Vencimiento</th>
                                <th style="width:290px">Referencia</th>
                                <th style="width:140px" class="text-right">Neto</th>
                                <th style="width:150px" class="text-right">Total</th>
                                <th style="width:120px">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($documents as $d)
                                @php
                                    $tipo = $tipoMap[(int) ($d->tipo_dte ?? 0)] ?? ['sigla' => 'DTE', 'color' => 'bg-gray-100 text-gray-600'];
                                    $vencDate = $d->fecha_vencimiento ? \Carbon\Carbon::parse($d->fecha_vencimiento)->startOfDay() : null;
                                    $hoy = now()->startOfDay();
                                    $diasVencido = $vencDate ? (int) $vencDate->diffInDays($hoy, false) : null;
                                    $paymentStatus = data_get($d, 'payment_status');

                                    if ($paymentStatus === 'pagado') {
                                        $statusSlug = 'pagado';
                                        $estado = 'Pagado';
                                        $vencDisplay = ['text' => '—', 'class' => 'text-gray-400'];
                                    } elseif ($diasVencido !== null && $diasVencido > 0) {
                                        $statusSlug = 'sinpagar';
                                        $estado = 'Sin pagar';
                                        $vencDisplay = [
                                            'text' => $diasVencido > 30 ? "Hace {$diasVencido}d" : ($diasVencido === 1 ? 'Ayer' : "Hace {$diasVencido}d"),
                                            'class' => $diasVencido > 15 ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-rose-500 dark:text-rose-400 font-semibold',
                                        ];
                                    } elseif ($diasVencido !== null && $diasVencido === 0) {
                                        $statusSlug = 'sinpagar';
                                        $estado = 'Sin pagar';
                                        $vencDisplay = ['text' => 'Vence hoy', 'class' => 'text-amber-600 dark:text-amber-400 font-bold'];
                                    } else {
                                        $statusSlug = 'pendiente';
                                        $estado = 'Pendiente';
                                        $vencDisplay = ['text' => $vencDate ? 'En ' . abs($diasVencido ?? 0) . 'd' : '—', 'class' => 'text-gray-400'];
                                    }
                                @endphp
                                <tr
                                    x-show="filter === 'todos' || filter === '{{ $statusSlug }}'"
                                    tabindex="0"
                                    onclick="window.location='{{ route('gmail.dtes.show', $d->id) }}'"
                                    onkeydown="if(event.key==='Enter'){window.location='{{ route('gmail.dtes.show', $d->id) }}'}">
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <span class="tipo-badge {{ $tipo['color'] }}">{{ $tipo['sigla'] }}</span>
                                            <span class="font-bold text-gray-900 dark:text-gray-100">{{ $d->folio ?? '—' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="font-semibold text-gray-800 dark:text-gray-200 leading-tight">{{ Str::limit($d->proveedor_nombre ?? '—', 35) }}</div>
                                        <div class="text-[11px] text-gray-400 font-mono mt-0.5">{{ $d->proveedor_rut ?? '' }}</div>
                                    </td>
                                    <td class="text-gray-500 dark:text-gray-400">{{ $d->fecha_factura ?? '—' }}</td>
                                    <td>
                                        <span class="{{ $vencDisplay['class'] }} text-xs">{{ $vencDisplay['text'] }}</span>
                                    </td>
                                    <td>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 truncate" style="max-width:280px" title="{{ $d->referencia }}">
                                            {{ $d->referencia ?? '—' }}
                                        </div>
                                    </td>
                                    <td class="text-right">
                                        <span class="amt-sub">$ {{ number_format((float) $d->monto_neto, 0, ',', '.') }}</span>
                                    </td>
                                    <td class="text-right">
                                        <span class="amt-main">$ {{ number_format((float) $d->monto_total, 0, ',', '.') }}</span>
                                    </td>
                                    <td>
                                        <span class="chip {{
                                            $estado === 'Pagado'
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                                                : ($estado === 'Sin pagar'
                                                    ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300'
                                                    : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300')
                                        }}">{{ $estado }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-12 text-gray-400">
                                        <svg class="w-8 h-8 mx-auto mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        No hay {{ $tipoListLabel }}.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Cards móvil --}}
                <div class="lg:hidden p-3 space-y-2.5 au d2">
                    @forelse($documents as $d)
                        @php
                            $tipo = $tipoMap[(int) ($d->tipo_dte ?? 0)] ?? ['sigla' => 'DTE', 'color' => 'bg-gray-100 text-gray-600'];
                            $vencDate = $d->fecha_vencimiento ? \Carbon\Carbon::parse($d->fecha_vencimiento)->startOfDay() : null;
                            $hoy = now()->startOfDay();
                            $diasVencido = $vencDate ? (int) $vencDate->diffInDays($hoy, false) : null;
                            $paymentStatus = data_get($d, 'payment_status');

                            if ($paymentStatus === 'pagado') {
                                $statusSlug = 'pagado';
                                $estado = 'Pagado';
                                $vencDisplay = ['text' => '—', 'class' => 'text-gray-400'];
                            } elseif ($diasVencido !== null && $diasVencido > 0) {
                                $statusSlug = 'sinpagar';
                                $estado = 'Sin pagar';
                                $vencDisplay = ['text' => $diasVencido === 1 ? 'Ayer' : "Hace {$diasVencido}d", 'class' => 'text-rose-600 dark:text-rose-400 font-semibold'];
                            } elseif ($diasVencido !== null && $diasVencido === 0) {
                                $statusSlug = 'sinpagar';
                                $estado = 'Sin pagar';
                                $vencDisplay = ['text' => 'Vence hoy', 'class' => 'text-amber-600 dark:text-amber-400 font-bold'];
                            } else {
                                $statusSlug = 'pendiente';
                                $estado = 'Pendiente';
                                $vencDisplay = ['text' => '—', 'class' => 'text-gray-400'];
                            }
                        @endphp

                        <a href="{{ route('gmail.dtes.show', $d->id) }}" class="m-card"
                            x-show="filter === 'todos' || filter === '{{ $statusSlug }}'">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="tipo-badge {{ $tipo['color'] }} shrink-0">{{ $tipo['sigla'] }}</span>
                                    <span class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ $d->folio ?? '—' }}</span>
                                </div>
                                <span class="chip shrink-0 {{
                                    $estado === 'Pagado'
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                                        : ($estado === 'Sin pagar'
                                            ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300'
                                            : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300')
                                }}">{{ $estado }}</span>
                            </div>

                            <div class="mt-2.5">
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200 leading-tight">{{ $d->proveedor_nombre ?? '—' }}</p>
                                @if($d->proveedor_rut)
                                    <p class="text-[11px] text-gray-400 font-mono mt-0.5">{{ $d->proveedor_rut }}</p>
                                @endif
                            </div>

                            <div class="mt-3 flex items-end justify-between gap-2">
                                <div class="space-y-0.5">
                                    <div class="flex items-center gap-3 text-xs">
                                        <div>
                                            <p class="text-gray-400">Fecha</p>
                                            <p class="font-semibold text-gray-700 dark:text-gray-300">{{ $d->fecha_factura ?? '—' }}</p>
                                        </div>
                                        @if($vencDisplay['text'] !== '—')
                                            <div>
                                                <p class="text-gray-400">Venc.</p>
                                                <p class="{{ $vencDisplay['class'] }}">{{ $vencDisplay['text'] }}</p>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-[10px] text-gray-400 uppercase tracking-wide">Total</p>
                                    <p class="text-base font-extrabold text-gray-900 dark:text-gray-100 tabular-nums">$ {{ number_format((float) $d->monto_total, 0, ',', '.') }}</p>
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="text-center py-10 text-gray-400">
                            <svg class="w-8 h-8 mx-auto mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            No hay {{ $tipoListLabel }}.
                        </div>
                    @endforelse
                </div>

            </div>

            <div class="au d3">{{ $documents->links() }}</div>
        </div>
    </div>
</x-app-layout>
