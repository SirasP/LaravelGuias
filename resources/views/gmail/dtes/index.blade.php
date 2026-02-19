<x-app-layout>
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
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Facturas proveedor</h2>
                    <p class="text-xs text-gray-400 mt-0.5 truncate">Tablero (admin)</p>
                </div>
            </div>

            <form method="GET" class="hidden lg:block w-full lg:max-w-xl lg:justify-self-center">
                <div class="flex gap-2">
                    <input type="text" name="q" value="{{ $q }}" class="f-input" placeholder="Buscar por folio, proveedor, referencia...">
                    <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white transition">Buscar</button>
                </div>
            </form>

            <div class="hidden lg:flex items-center justify-end text-xs text-gray-400">
                {{ $documents->total() }} registros
            </div>
        </div>
    </x-slot>

    <style>
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .au { animation: fadeUp .35s ease both; }
        .d1 { animation-delay: .06s; }
        .d2 { animation-delay: .12s; }
        .d3 { animation-delay: .18s; }
        .d4 { animation-delay: .24s; }

        .page-bg { background:#f1f5f9; min-height:100% }
        .dark .page-bg { background:#0d1117 }

        .panel { background:#fff; border:1px solid #e2e8f0; border-radius:18px; overflow:hidden }
        .dark .panel { background:#161c2c; border-color:#1e2a3b }

        .panel-head {
            padding:15px 20px;
            border-bottom:1px solid #f1f5f9;
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:12px;
        }
        .dark .panel-head { border-bottom-color:#1e2a3b }

        .stat-card {
            background:#fff;
            border:1px solid #e2e8f0;
            border-radius:16px;
            padding:14px 16px;
        }
        .dark .stat-card { background:#161c2c; border-color:#1e2a3b }

        .dt { width:100%; border-collapse:collapse; font-size:13px }
        .dt thead tr { background:#f8fafc; border-bottom:1px solid #f1f5f9 }
        .dark .dt thead tr { background:#111827; border-bottom-color:#1e2a3b }
        .dt th {
            padding:10px 12px;
            text-align:left;
            font-size:10px;
            font-weight:700;
            letter-spacing:.08em;
            text-transform:uppercase;
            color:#94a3b8;
            white-space:nowrap;
        }
        .dt td { padding:12px; border-bottom:1px solid #f8fafc; color:#334155; vertical-align:middle }
        .dark .dt td { border-bottom-color:#1a2232; color:#cbd5e1 }
        .dt tbody tr:last-child td { border-bottom:none }
        .dt tbody tr:hover td { background:#f8fafc }
        .dark .dt tbody tr:hover td { background:#1a2436 }

        .f-input {
            width:100%;
            border-radius:12px;
            border:1px solid #e2e8f0;
            background:#fff;
            padding:9px 12px;
            font-size:13px;
            color:#111827;
            outline:none;
        }
        .f-input:focus { border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.12) }
        .dark .f-input { border-color:#1e2a3b; background:#0d1117; color:#f1f5f9 }

        .m-card {
            background:#fff;
            border:1px solid #e2e8f0;
            border-radius:14px;
            padding:12px;
        }
        .dark .m-card { background:#161c2c; border-color:#1e2a3b }

        .chip { display:inline-flex; align-items:center; border-radius:999px; padding:4px 10px; font-size:11px; font-weight:700 }

        .table-scroll {
            overflow-x:auto;
            overflow-y:hidden;
            -webkit-overflow-scrolling:touch;
        }
        .dt-wide {
            min-width:1320px;
            table-layout:auto;
        }
        .col-ref {
            max-width:240px;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        @media (min-width: 1280px) {
            .col-ref { max-width:420px; }
        }
        @media (min-width: 1536px) {
            .col-ref {
                max-width:none;
                white-space:normal;
                overflow:visible;
            }
        }
    </style>

    <div class="page-bg">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">
            <form method="GET" class="lg:hidden">
                <div class="flex gap-2">
                    <input type="text" name="q" value="{{ $q }}" class="f-input" placeholder="Buscar por folio, proveedor, referencia...">
                    <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white transition">Buscar</button>
                </div>
            </form>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 au d1">
                <div class="stat-card">
                    <p class="text-[11px] uppercase tracking-wide text-gray-400">Registros</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $documents->total() }}</p>
                </div>
                <div class="stat-card">
                    <p class="text-[11px] uppercase tracking-wide text-gray-400">Pagina</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $documents->currentPage() }} / {{ $documents->lastPage() }}</p>
                </div>
                <div class="stat-card">
                    <p class="text-[11px] uppercase tracking-wide text-gray-400">Total pagina</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-gray-100">$ {{ number_format((float) $documents->sum('monto_total'), 0, ',', '.') }}</p>
                </div>
                <div class="stat-card">
                    <p class="text-[11px] uppercase tracking-wide text-gray-400">Filtro</p>
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 truncate">{{ $q !== '' ? $q : 'Sin filtro' }}</p>
                </div>
            </div>

            @php
                $tipoMap = [
                    33 => ['sigla' => 'FAC', 'nombre' => 'Factura electronica'],
                    34 => ['sigla' => 'FEX', 'nombre' => 'Factura exenta'],
                    56 => ['sigla' => 'ND',  'nombre' => 'Nota de debito'],
                    61 => ['sigla' => 'NC',  'nombre' => 'Nota de credito'],
                ];
            @endphp

            <div class="panel hidden lg:block au d2">
                <div class="panel-head">
                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100">Listado</p>
                    <p class="text-xs text-gray-400">Haz clic en una fila para ver detalle y desplaza horizontalmente si necesitas ver más columnas</p>
                </div>

                <div class="table-scroll">
                    <table class="dt dt-wide">
                        <thead>
                            <tr>
                                <th class="w-[170px]">Folio</th>
                                <th class="w-[280px]">Proveedor</th>
                                <th class="w-[130px]">Fecha factura</th>
                                <th class="w-[130px]">Fecha contable</th>
                                <th class="w-[140px]">Vencimiento</th>
                                <th class="w-[320px]">Referencia</th>
                                <th class="w-[150px] text-right">Imp. no incluidos</th>
                                <th class="w-[150px] text-right">Total facturacion</th>
                                <th class="w-[140px]">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($documents as $d)
                                @php
                                    $tipo = $tipoMap[(int) ($d->tipo_dte ?? 0)] ?? ['sigla' => 'DTE', 'nombre' => 'Documento tributario'];
                                    $vencDate = $d->fecha_vencimiento ? \Carbon\Carbon::parse($d->fecha_vencimiento)->startOfDay() : null;
                                    $hoy = now()->startOfDay();
                                    $diasVencido = $vencDate ? $vencDate->diffInDays($hoy, false) : null;

                                    $paymentStatus = data_get($d, 'payment_status');

                                    if ($paymentStatus === 'pagado') {
                                        $vencHuman = '—';
                                        $estado = 'Pagado';
                                    } elseif ($diasVencido !== null && $diasVencido > 0) {
                                        $vencHuman = $diasVencido === 1 ? 'ayer' : "hace {$diasVencido} dias";
                                        $estado = 'Sin pagar';
                                    } else {
                                        $vencHuman = '—';
                                        $estado = 'Pendiente';
                                    }
                                @endphp
                                <tr class="cursor-pointer" tabindex="0"
                                    onclick="window.location='{{ route('gmail.dtes.show', $d->id) }}'"
                                    onkeydown="if(event.key==='Enter'){window.location='{{ route('gmail.dtes.show', $d->id) }}'}">
                                    <td>
                                        <div class="font-semibold">{{ $tipo['sigla'] }} {{ $d->folio ?? '—' }}</div>
                                        <div class="text-[11px] text-gray-400">{{ $tipo['nombre'] }}</div>
                                    </td>
                                    <td>
                                        <div class="font-semibold">{{ $d->proveedor_nombre ?? '—' }}</div>
                                        <div class="text-[11px] text-gray-400">{{ $d->proveedor_rut ?? '—' }}</div>
                                    </td>
                                    <td>{{ $d->fecha_factura ?? '—' }}</td>
                                    <td>{{ $d->fecha_contable ?? '—' }}</td>
                                    <td><span class="text-rose-600 dark:text-rose-400 font-semibold">{{ $vencHuman }}</span></td>
                                    <td title="{{ $d->referencia }}"><div class="col-ref">{{ $d->referencia ?? '—' }}</div></td>
                                    <td class="text-right">{{ number_format((float) $d->monto_neto, 0, ',', '.') }}</td>
                                    <td class="font-bold text-right">{{ number_format((float) $d->monto_total, 0, ',', '.') }}</td>
                                    <td>
                                        <span class="chip {{
                                            $estado === 'Pagado'
                                                ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                                : ($estado === 'Sin pagar'
                                                    ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300'
                                                    : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300')
                                        }}">
                                            {{ $estado }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-10 text-gray-400">No hay DTE no combustible.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 lg:hidden au d3">
                @forelse($documents as $d)
                    @php
                        $tipo = $tipoMap[(int) ($d->tipo_dte ?? 0)] ?? ['sigla' => 'DTE', 'nombre' => 'Documento tributario'];
                        $vencDate = $d->fecha_vencimiento ? \Carbon\Carbon::parse($d->fecha_vencimiento)->startOfDay() : null;
                        $hoy = now()->startOfDay();
                        $diasVencido = $vencDate ? $vencDate->diffInDays($hoy, false) : null;

                        $paymentStatus = data_get($d, 'payment_status');

                        if ($paymentStatus === 'pagado') {
                            $vencHuman = '—';
                            $estado = 'Pagado';
                        } elseif ($diasVencido !== null && $diasVencido > 0) {
                            $vencHuman = $diasVencido === 1 ? 'ayer' : "hace {$diasVencido} dias";
                            $estado = 'Sin pagar';
                        } else {
                            $vencHuman = '—';
                            $estado = 'Pendiente';
                        }
                    @endphp

                    <a href="{{ route('gmail.dtes.show', $d->id) }}" class="m-card block">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ $tipo['sigla'] }} {{ $d->folio ?? '—' }}</p>
                                <p class="text-[11px] text-gray-400">{{ $tipo['nombre'] }}</p>
                            </div>
                            <span class="chip {{
                                $estado === 'Pagado'
                                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                    : ($estado === 'Sin pagar'
                                        ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300'
                                        : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300')
                            }}">
                                {{ $estado }}
                            </span>
                        </div>

                        <div class="mt-3 space-y-1.5 text-sm">
                            <p class="font-semibold text-gray-800 dark:text-gray-200">{{ $d->proveedor_nombre ?? '—' }}</p>
                            <p class="text-gray-500 dark:text-gray-400">{{ $d->proveedor_rut ?? '—' }}</p>
                            <p class="text-gray-500 dark:text-gray-400 truncate">{{ $d->referencia ?? '—' }}</p>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <p class="text-gray-400">Fecha factura</p>
                                <p class="font-semibold text-gray-700 dark:text-gray-300">{{ $d->fecha_factura ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-400">Vencimiento</p>
                                <p class="font-semibold text-rose-600 dark:text-rose-400">{{ $vencHuman }}</p>
                            </div>
                            <div>
                                <p class="text-gray-400">Imp. no incluidos</p>
                                <p class="font-semibold text-gray-700 dark:text-gray-300">{{ number_format((float) $d->monto_neto, 0, ',', '.') }}</p>
                            </div>
                            <div>
                                <p class="text-gray-400">Total</p>
                                <p class="font-bold text-gray-900 dark:text-gray-100">{{ number_format((float) $d->monto_total, 0, ',', '.') }}</p>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="panel p-8 text-center text-gray-400">No hay DTE no combustible.</div>
                @endforelse
            </div>

            <div>{{ $documents->links() }}</div>
        </div>
    </div>
</x-app-layout>
