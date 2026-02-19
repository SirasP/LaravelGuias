<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-xl bg-indigo-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Facturas proveedor</h2>
                    <p class="text-xs text-gray-400 mt-0.5 truncate">Detalle DTE</p>
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('gmail.dtes.print', $document->id) }}?autoprint=1" target="_blank"
                    class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white transition">
                    Imprimir documento
                </a>
                <a href="{{ route('gmail.dtes.index') }}"
                    class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    Volver
                </a>
            </div>
        </div>
    </x-slot>

    @php
        $tipoMap = [
            33 => ['sigla' => 'FAC', 'nombre' => 'Factura electronica'],
            34 => ['sigla' => 'FEX', 'nombre' => 'Factura exenta'],
            56 => ['sigla' => 'ND', 'nombre' => 'Nota de debito'],
            61 => ['sigla' => 'NC', 'nombre' => 'Nota de credito'],
        ];

        $tipo = $tipoMap[(int) ($document->tipo_dte ?? 0)] ?? ['sigla' => 'DTE', 'nombre' => 'Documento tributario'];

        $estadoPagoRaw = data_get($document, 'payment_status');
        $estadoPago = $estadoPagoRaw === 'pagado' ? 'Pagado' : 'Sin pagar';
        $workflowStatus = data_get($document, 'workflow_status', 'borrador');
        $inventoryStatus = data_get($document, 'inventory_status', 'pendiente');

        $taxSummary = collect();

        if ((float) $document->monto_iva > 0) {
            $taxSummary->push([
                'label' => 'IVA',
                'monto' => (float) $document->monto_iva,
                'informado' => true,
            ]);
        }

        foreach ($lines as $line) {
            foreach (($line->taxes ?? collect()) as $tax) {
                $type = strtoupper((string) ($tax->tax_type ?? ''));
                $label = trim((string) ($tax->descripcion ?? '')) ?: ('Impuesto ' . ($tax->codigo ?? ''));
                $monto = $tax->monto;
                $key = $type . '|' . $label;

                // Evita duplicar IVA cuando no tiene monto por línea y ya se muestra el IVA global.
                if ($type === 'IVA' && is_null($monto)) {
                    continue;
                }

                if (!$taxSummary->has($key)) {
                    $taxSummary->put($key, [
                        'label' => $label,
                        'monto' => 0.0,
                        'informado' => false,
                    ]);
                }

                $row = $taxSummary->get($key);
                if (!is_null($monto)) {
                    $row['monto'] += (float) $monto;
                    $row['informado'] = true;
                }
                $taxSummary->put($key, $row);
            }
        }

        $taxSummary = $taxSummary->values();
    @endphp

    <style>
        .page-bg { background:#f1f5f9; min-height:100% }
        .dark .page-bg { background:#0d1117 }
        .panel { background:#fff; border:1px solid #e2e8f0; border-radius:16px; overflow:hidden }
        .dark .panel { background:#161c2c; border-color:#1e2a3b }
        .panel-head { padding:14px 16px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; gap:10px }
        .dark .panel-head { border-bottom-color:#1e2a3b }
        .chip { display:inline-flex; align-items:center; border-radius:999px; padding:4px 10px; font-size:11px; font-weight:700 }
        .kv { display:grid; grid-template-columns:170px 1fr; gap:8px 14px; font-size:13px }
        .k { color:#64748b; font-weight:700 }
        .dark .k { color:#94a3b8 }
        .v { color:#111827; font-weight:600 }
        .dark .v { color:#e5e7eb }
        .dt { width:100%; border-collapse:collapse; font-size:13px }
        .dt thead tr { background:#f8fafc; border-bottom:1px solid #f1f5f9 }
        .dark .dt thead tr { background:#111827; border-bottom-color:#1e2a3b }
        .dt th { padding:10px 12px; text-align:left; font-size:10px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#94a3b8; white-space:nowrap }
        .dt td { padding:12px; border-bottom:1px solid #f8fafc; color:#334155; vertical-align:middle }
        .dark .dt td { border-bottom-color:#1a2232; color:#cbd5e1 }
        .dt tbody tr:last-child td { border-bottom:none }

        @media (max-width: 768px) {
            .kv { grid-template-columns:1fr; gap:4px 0 }
        }
    </style>

    <div class="page-bg">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">
            @if(session('success'))
                <div class="panel p-3 text-sm font-semibold text-emerald-700 bg-emerald-50 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-300">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('warning'))
                <div class="panel p-3 text-sm font-semibold text-amber-700 bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:text-amber-300">
                    {{ session('warning') }}
                </div>
            @endif

            <div class="panel p-3">
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('gmail.dtes.pay', $document->id) }}">
                        @csrf
                        <button type="submit" class="px-3 py-2 text-xs font-semibold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white transition">
                            Pagar
                        </button>
                    </form>

                    <form method="POST" action="{{ route('gmail.dtes.credit_note', $document->id) }}">
                        @csrf
                        <button type="submit" class="px-3 py-2 text-xs font-semibold rounded-xl bg-rose-600 hover:bg-rose-700 text-white transition">
                            Nota de credito
                        </button>
                    </form>

                    <form method="POST" action="{{ route('gmail.dtes.accept', $document->id) }}">
                        @csrf
                        <button type="submit" class="px-3 py-2 text-xs font-semibold rounded-xl bg-sky-600 hover:bg-sky-700 text-white transition">
                            Aceptar documento
                        </button>
                    </form>

                    <form method="POST" action="{{ route('gmail.dtes.add_stock', $document->id) }}">
                        @csrf
                        <button type="submit" class="px-3 py-2 text-xs font-semibold rounded-xl bg-violet-600 hover:bg-violet-700 text-white transition">
                            Agregar a stock
                        </button>
                    </form>

                    <a href="{{ route('gmail.inventory.index') }}"
                        class="px-3 py-2 text-xs font-semibold rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                        Ver inventario DTE
                    </a>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <div>
                        <p class="text-xs text-gray-400">Factura de proveedor</p>
                        <h1 class="text-2xl font-extrabold text-gray-900 dark:text-gray-100">{{ $tipo['sigla'] }} {{ $document->folio ?? '—' }}</h1>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="chip bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300">{{ $tipo['nombre'] }}</span>
                        <span class="chip {{ $estadoPago === 'Pagado' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300' }}">{{ $estadoPago }}</span>
                        <span class="chip bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">{{ strtoupper((string) $workflowStatus) }}</span>
                        <span class="chip {{ $inventoryStatus === 'ingresado' ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' }}">
                            {{ $inventoryStatus === 'ingresado' ? 'Stock ingresado' : 'Stock pendiente' }}
                        </span>
                    </div>
                </div>

                <div class="p-4 sm:p-5 grid grid-cols-1 xl:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wide">Proveedor</p>
                            <p class="text-base font-bold text-gray-900 dark:text-gray-100">{{ $document->proveedor_nombre ?? '—' }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $document->proveedor_rut ?? '—' }}</p>
                        </div>

                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wide">Referencia</p>
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $document->referencia ?? '—' }}</p>
                        </div>

                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wide">Archivo XML</p>
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 break-all">{{ $document->xml_filename ?? '—' }}</p>
                        </div>
                    </div>

                    <div class="kv">
                        <div class="k">Fecha factura</div>
                        <div class="v">{{ $document->fecha_factura ?? '—' }}</div>

                        <div class="k">Fecha contable</div>
                        <div class="v">{{ $document->fecha_contable ?? '—' }}</div>

                        <div class="k">Fecha vencimiento</div>
                        <div class="v">{{ $document->fecha_vencimiento ?? '—' }}</div>

                        <div class="k">Tipo DTE</div>
                        <div class="v">{{ $document->tipo_dte ?? '—' }}</div>

                        <div class="k">Numero documento</div>
                        <div class="v">{{ $document->folio ?? '—' }}</div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100">Lineas de factura</p>
                    <p class="text-xs text-gray-400">IVA especifico por linea cuando venga informado en XML</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="dt">
                        <thead>
                            <tr>
                                <th>Linea</th>
                                <th>Codigo</th>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>UdM</th>
                                <th>Precio</th>
                                <th>Impuestos</th>
                                <th>Importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($lines as $l)
                                @php
                                    $taxLabels = collect($l->taxes ?? [])->pluck('descripcion')->filter()->values();
                                    if ($taxLabels->isEmpty()) {
                                        $fallback = $l->impuesto_label;
                                        if (!$fallback) {
                                            if ((int) ($l->es_exento ?? 0) === 1) {
                                                $fallback = 'Exento';
                                            } elseif (!is_null($l->impuesto_tasa)) {
                                                $fallback = 'IVA ' . rtrim(rtrim((string) $l->impuesto_tasa, '0'), '.') . '%';
                                            } elseif ((float) $document->monto_iva > 0) {
                                                $fallback = 'IVA incluido';
                                            } else {
                                                $fallback = 'Sin IVA';
                                            }
                                        }
                                        $taxLabels = collect([$fallback]);
                                    }
                                @endphp
                                <tr>
                                    <td>{{ $l->nro_linea ?? '—' }}</td>
                                    <td>{{ $l->codigo ?? '—' }}</td>
                                    <td>{{ $l->descripcion ?? '—' }}</td>
                                    <td>{{ number_format((float) $l->cantidad, 2, ',', '.') }}</td>
                                    <td>{{ $l->unidad ?? '—' }}</td>
                                    <td>{{ number_format((float) $l->precio_unitario, 0, ',', '.') }}</td>
                                    <td>
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($taxLabels as $label)
                                                <span class="chip bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ $label }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="font-semibold">$ {{ number_format((float) $l->monto_item, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-10 text-gray-400">Sin lineas de detalle.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="p-4 border-t border-gray-100 dark:border-gray-800 flex justify-end">
                    <div class="w-full sm:w-80 text-sm space-y-1">
                        <div class="flex justify-between text-gray-600 dark:text-gray-300">
                            <span>Monto neto</span>
                            <span class="font-semibold">$ {{ number_format((float) $document->monto_neto, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-gray-600 dark:text-gray-300">
                            <span>IVA</span>
                            <span class="font-semibold">$ {{ number_format((float) $document->monto_iva, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-base text-gray-900 dark:text-gray-100">
                            <span>Total</span>
                            <span class="font-bold">$ {{ number_format((float) $document->monto_total, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100">Resumen de impuestos</p>
                </div>
                <div class="p-4">
                    @if($taxSummary->isNotEmpty())
                        <div class="space-y-2">
                            @foreach($taxSummary as $tax)
                                <div class="flex items-center justify-between rounded-xl border border-gray-100 dark:border-gray-800 px-3 py-2">
                                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $tax['label'] }}</span>
                                    <span class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                        {{ $tax['informado'] ? '$ ' . number_format((float) $tax['monto'], 0, ',', '.') : 'Monto no informado en XML' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-400">No se detectaron impuestos adicionales en este XML.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
