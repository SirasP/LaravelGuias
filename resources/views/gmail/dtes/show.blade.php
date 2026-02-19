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
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    <span class="hidden sm:inline">Imprimir</span>
                </a>
                <a href="{{ route('gmail.dtes.index') }}"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span class="hidden sm:inline">Volver</span>
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
        $montoPorPagar = $estadoPagoRaw === 'pagado' ? 0.0 : (float) ($document->monto_total ?? 0);
        $fechaPago = data_get($document, 'paid_at') ? \Carbon\Carbon::parse($document->paid_at)->format('d/m/Y') : null;

        $taxSummary = collect($document->tax_summary ?? []);

        $ivaMonto = (float) ($document->monto_iva ?? 0);
        $ivaLabel = (string) (collect($taxSummary)->first(function ($tax) {
            return str_starts_with(strtoupper((string) ($tax['label'] ?? '')), 'IVA');
        })['label'] ?? 'IVA');
        $extraTaxRows = $taxSummary
            ->filter(function ($tax) {
                $label = strtoupper((string) ($tax['label'] ?? ''));
                return !str_starts_with($label, 'IVA') && ((float) ($tax['monto'] ?? 0) > 0);
            })
            ->map(function ($tax) {
                return [
                    'label' => trim((string) ($tax['label'] ?? 'Impuesto')),
                    'monto' => (float) ($tax['monto'] ?? 0),
                ];
            })
            ->values();
    @endphp

    <style>
        [x-cloak] { display:none !important }

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
        .panel-head { padding:15px 20px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; gap:12px }
        .dark .panel-head { border-bottom-color:#1e2a3b }

        .stat-card { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:14px 16px; }
        .dark .stat-card { background:#161c2c; border-color:#1e2a3b }
        .m-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:14px 16px }
        .dark .m-card { background:#161c2c; border-color:#1e2a3b }

        .chip { display:inline-flex; align-items:center; border-radius:999px; padding:4px 10px; font-size:11px; font-weight:700 }
        .kv { display:grid; grid-template-columns:170px 1fr; gap:8px 14px; font-size:13px }
        .k { color:#64748b; font-weight:700 }
        .dark .k { color:#94a3b8 }
        .v { color:#111827; font-weight:600 }
        .dark .v { color:#e5e7eb }

        .tabs { display:flex; gap:6px; padding:10px 12px 0; border-bottom:1px solid #edf2f7; overflow-x:auto; white-space:nowrap }
        .dark .tabs { border-bottom-color:#273244 }
        .tab {
            border:1px solid #e2e8f0; border-bottom:none; border-radius:10px 10px 0 0;
            background:#f8fafc; color:#64748b; font-size:12px; font-weight:700; padding:8px 12px;
            cursor:pointer; transition:background .12s, color .12s;
        }
        .dark .tab { border-color:#273244; background:#111827; color:#94a3b8 }
        .tab.active, .tab[data-active] { background:#fff; color:#111827 }
        .dark .tab.active, .dark .tab[data-active] { background:#161c2c; color:#e2e8f0 }

        .dt { width:100%; border-collapse:collapse; font-size:13px; min-width:1120px }
        .dt thead tr { background:#f8fafc; border-bottom:1px solid #f1f5f9 }
        .dark .dt thead tr { background:#111827; border-bottom-color:#1e2a3b }
        .dt th { padding:10px 12px; text-align:left; font-size:10px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#94a3b8; white-space:nowrap }
        .dt td { padding:12px; border-bottom:1px solid #f8fafc; color:#334155; vertical-align:middle }
        .dark .dt td { border-bottom-color:#1a2232; color:#cbd5e1 }
        .dt tbody tr:last-child td { border-bottom:none }

        .tax-pill { display:inline-flex; align-items:center; border-radius:999px; padding:3px 9px; font-size:11px; font-weight:700; background:#eef2ff; color:#4f46e5 }
        .dark .tax-pill { background:#312e81; color:#c7d2fe }

        .totals-zone { border-top:1px solid #e5e7eb }
        .dark .totals-zone { border-top-color:#273244 }
        .totals-grid { width:100%; max-width:520px; margin-left:auto; }
        .totals-table { width:100%; border-collapse:collapse }
        .totals-table td { padding:3px 0 }
        .totals-k { color:#4b5563; font-size:14px; font-weight:600; text-align:right; padding-right:14px; white-space:nowrap }
        .dark .totals-k { color:#9ca3af }
        .totals-v { color:#334155; font-size:14px; font-weight:800; text-align:right; white-space:nowrap }
        .dark .totals-v { color:#e2e8f0 }
        .totals-total td { border-top:1px solid #e5e7eb; padding-top:8px }
        .dark .totals-total td { border-top-color:#273244 }
        .totals-total .totals-k { font-size:18px; font-weight:800 }
        .totals-total .totals-v { font-size:34px; font-weight:900; line-height:1 }
        .totals-pay { margin-top:10px; display:flex; justify-content:space-between; align-items:center; gap:10px; border-top:1px dashed #dbe3ee; padding-top:10px }
        .dark .totals-pay { border-top-color:#273244 }

        .empty-tab { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:48px 24px; gap:12px; color:#94a3b8; text-align:center }

        @media (max-width: 768px) {
            .kv { grid-template-columns:1fr; gap:4px 0 }
            .totals-grid { max-width:100% }
            .totals-k, .totals-v { font-size:13px }
            .totals-total .totals-k { font-size:16px }
            .totals-total .totals-v { font-size:28px }
        }
    </style>

    <div class="page-bg">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">

            {{-- Flash messages --}}
            @if(session('success'))
                <div class="flex items-center gap-3 px-4 py-3 rounded-xl
                        bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-medium
                        dark:bg-emerald-900/20 dark:border-emerald-800 dark:text-emerald-400 au d1">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ session('success') }}
                </div>
            @endif
            @if(session('warning'))
                <div class="flex items-center gap-3 px-4 py-3 rounded-xl
                        bg-amber-50 border border-amber-200 text-amber-700 text-sm font-medium
                        dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-400 au d1">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    {{ session('warning') }}
                </div>
            @endif

            {{-- Barra de acciones --}}
            <div class="panel p-3 au d1">
                <div class="grid grid-cols-2 sm:flex sm:flex-wrap gap-2">
                    <form method="POST" action="{{ route('gmail.dtes.pay', $document->id) }}" class="w-full sm:w-auto">
                        @csrf
                        <button type="submit"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Pagar
                        </button>
                    </form>

                    <form method="POST" action="{{ route('gmail.dtes.credit_note', $document->id) }}" class="w-full sm:w-auto">
                        @csrf
                        <button type="submit"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl bg-rose-600 hover:bg-rose-700 active:scale-95 text-white transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 14H5a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                            Nota de crédito
                        </button>
                    </form>

                    <form method="POST" action="{{ route('gmail.dtes.accept', $document->id) }}" class="w-full sm:w-auto">
                        @csrf
                        <button type="submit"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl bg-sky-600 hover:bg-sky-700 active:scale-95 text-white transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7" />
                            </svg>
                            Aceptar documento
                        </button>
                    </form>

                    <form method="POST" action="{{ route('gmail.dtes.add_stock', $document->id) }}" class="w-full sm:w-auto">
                        @csrf
                        <button type="submit"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl bg-violet-600 hover:bg-violet-700 active:scale-95 text-white transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            Agregar a stock
                        </button>
                    </form>

                    <a href="{{ route('gmail.inventory.index') }}"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-300 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                        </svg>
                        Ver inventario DTE
                    </a>
                </div>
            </div>

            {{-- Cabecera del documento --}}
            <div class="panel au d2">
                <div class="panel-head flex-col sm:flex-row sm:items-start">
                    <div>
                        <p class="text-xs text-gray-400">Factura de proveedor</p>
                        <h1 class="text-xl sm:text-2xl font-extrabold text-gray-900 dark:text-gray-100">{{ $tipo['sigla'] }} {{ $document->folio ?? '—' }}</h1>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="chip bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300">{{ $tipo['nombre'] }}</span>
                        <span class="chip {{ $estadoPago === 'Pagado' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300' }}">{{ $estadoPago }}</span>
                        <span class="chip bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">{{ strtoupper((string) $workflowStatus) }}</span>
                        <span class="chip {{ $inventoryStatus === 'ingresado' ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' }}">
                            {{ $inventoryStatus === 'ingresado' ? 'Stock ingresado' : 'Stock pendiente' }}
                        </span>
                    </div>
                </div>

                <div class="p-4 sm:p-5 grid grid-cols-1 xl:grid-cols-12 gap-4">
                    <div class="xl:col-span-7 grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="m-card">
                            <p class="text-xs text-gray-400 uppercase tracking-wide">Proveedor</p>
                            <p class="text-sm font-bold text-gray-900 dark:text-gray-100 mt-1 leading-tight">{{ $document->proveedor_nombre ?? '—' }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $document->proveedor_rut ?? '—' }}</p>
                        </div>
                        <div class="m-card">
                            <p class="text-xs text-gray-400 uppercase tracking-wide">Referencia</p>
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mt-1 break-words">{{ $document->referencia ?? '—' }}</p>
                        </div>
                        <div class="m-card">
                            <p class="text-xs text-gray-400 uppercase tracking-wide">Archivo XML</p>
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mt-1 break-all">{{ $document->xml_filename ?? '—' }}</p>
                        </div>
                    </div>

                    <div class="xl:col-span-5 m-card">
                        <div class="kv">
                            <div class="k">Fecha factura</div>
                            <div class="v">{{ $document->fecha_factura ?? '—' }}</div>

                            <div class="k">Fecha contable</div>
                            <div class="v">{{ $document->fecha_contable ?? '—' }}</div>

                            <div class="k">Fecha vencimiento</div>
                            <div class="v">{{ $document->fecha_vencimiento ?? '—' }}</div>

                            <div class="k">Tipo DTE</div>
                            <div class="v">{{ $document->tipo_dte ?? '—' }}</div>

                            <div class="k">Número documento</div>
                            <div class="v">{{ $document->folio ?? '—' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Panel con tabs funcionales --}}
            <div class="panel au d3" x-data="{ tab: 'lineas' }">
                <div class="tabs">
                    <button class="tab" :class="{ active: tab === 'lineas' }" @click="tab = 'lineas'">Líneas de factura</button>
                    <button class="tab" :class="{ active: tab === 'contable' }" @click="tab = 'contable'">Apuntes contables</button>
                    <button class="tab" :class="{ active: tab === 'info' }" @click="tab = 'info'">Otra información</button>
                    <button class="tab" :class="{ active: tab === 'cruzadas' }" @click="tab = 'cruzadas'">Referencias cruzadas</button>
                </div>

                {{-- Tab: Líneas de factura --}}
                <div x-show="tab === 'lineas'">
                    <div class="hidden lg:block overflow-x-auto">
                        <table class="dt">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Cuenta</th>
                                    <th>Analítica</th>
                                    <th class="text-right">Cantidad</th>
                                    <th>UdM</th>
                                    <th class="text-right">Precio</th>
                                    <th>Impuestos</th>
                                    <th class="text-right">Importe</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($lines as $l)
                                    @php
                                        $taxLabels = collect($l->taxes ?? [])->map(function ($tax) {
                                            $type = strtoupper((string) ($tax->tax_type ?? ''));
                                            $code = (string) ($tax->codigo ?? '');
                                            $desc = trim((string) ($tax->descripcion ?? ''));
                                            $rate = $tax->tasa;

                                            if ($type === 'IVA') {
                                                if (!is_null($rate)) {
                                                    return 'IVA ' . rtrim(rtrim((string) $rate, '0'), '.') . '%';
                                                }
                                                return $desc !== '' ? $desc : 'IVA';
                                            }

                                            if ($type === 'IMP_ADIC') {
                                                if ($code === '28') {
                                                    return 'IEC Diesel';
                                                }
                                                return $desc !== '' ? preg_replace('/^Imp\\. adic\\./i', 'Impuesto específico', $desc) : 'Impuesto específico';
                                            }

                                            if ($type === 'IMPTO_RETEN' && $code === '28') {
                                                return 'IEC Diesel';
                                            }

                                            return $desc !== '' ? preg_replace('/^Imp\\. adic\\./i', 'Impuesto específico', $desc) : 'Impuesto';
                                        })->filter()->values();

                                        if ($taxLabels->isEmpty()) {
                                            if ((int) ($l->es_exento ?? 0) === 1) {
                                                $taxLabels = collect(['Exento']);
                                            } elseif (!is_null($l->impuesto_tasa)) {
                                                $taxLabels = collect(['IVA ' . rtrim(rtrim((string) $l->impuesto_tasa, '0'), '.') . '%']);
                                            } elseif ((float) $document->monto_iva > 0) {
                                                $taxLabels = collect(['IVA incluido']);
                                            } else {
                                                $taxLabels = collect(['Sin IVA']);
                                            }
                                        }
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="font-semibold">{{ $l->descripcion ?? '—' }}</div>
                                            <div class="text-[11px] text-gray-400">{{ $l->codigo ?? '—' }}</div>
                                        </td>
                                        <td class="text-gray-500 dark:text-gray-400">{{ $l->cuenta ?? '—' }}</td>
                                        <td class="text-gray-400">{{ $l->analitica ?? '—' }}</td>
                                        <td class="text-right">{{ number_format((float) $l->cantidad, 2, ',', '.') }}</td>
                                        <td>{{ $l->unidad ?? '—' }}</td>
                                        <td class="text-right">{{ number_format((float) $l->precio_unitario, 0, ',', '.') }}</td>
                                        <td>
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($taxLabels as $label)
                                                    <span class="tax-pill">{{ $label }}</span>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="font-semibold text-right">$ {{ number_format((float) $l->monto_item, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-10 text-gray-400">Sin líneas de detalle.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Vista móvil de líneas --}}
                    <div class="lg:hidden p-3 space-y-3">
                        @forelse($lines as $l)
                            @php
                                $taxLabelsMobile = collect($l->taxes ?? [])->map(function ($tax) {
                                    $type = strtoupper((string) ($tax->tax_type ?? ''));
                                    $code = (string) ($tax->codigo ?? '');
                                    $desc = trim((string) ($tax->descripcion ?? ''));
                                    $rate = $tax->tasa;

                                    if ($type === 'IVA') {
                                        return !is_null($rate) ? 'IVA ' . rtrim(rtrim((string) $rate, '0'), '.') . '%' : ($desc !== '' ? $desc : 'IVA');
                                    }
                                    if ($type === 'IMP_ADIC') {
                                        return $code === '28' ? 'IEC Diesel' : ($desc !== '' ? preg_replace('/^Imp\\. adic\\./i', 'Impuesto específico', $desc) : 'Impuesto específico');
                                    }
                                    if ($type === 'IMPTO_RETEN' && $code === '28') {
                                        return 'IEC Diesel';
                                    }
                                    return $desc !== '' ? preg_replace('/^Imp\\. adic\\./i', 'Impuesto específico', $desc) : 'Impuesto';
                                })->filter()->values();
                            @endphp
                            <div class="m-card">
                                <p class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ $l->descripcion ?? '—' }}</p>
                                <p class="text-[11px] text-gray-400 mt-0.5">{{ $l->codigo ?? '—' }}</p>
                                <div class="grid grid-cols-2 gap-3 mt-3 text-xs">
                                    <div>
                                        <p class="text-gray-400 uppercase tracking-wide">Cantidad</p>
                                        <p class="font-semibold text-gray-700 dark:text-gray-200">{{ number_format((float) $l->cantidad, 2, ',', '.') }}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-400 uppercase tracking-wide">UdM</p>
                                        <p class="font-semibold text-gray-700 dark:text-gray-200">{{ $l->unidad ?? '—' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-400 uppercase tracking-wide">Precio</p>
                                        <p class="font-semibold text-gray-700 dark:text-gray-200">$ {{ number_format((float) $l->precio_unitario, 0, ',', '.') }}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-400 uppercase tracking-wide">Importe</p>
                                        <p class="font-bold text-gray-900 dark:text-gray-100">$ {{ number_format((float) $l->monto_item, 0, ',', '.') }}</p>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-1 mt-3">
                                    @forelse($taxLabelsMobile as $label)
                                        <span class="tax-pill">{{ $label }}</span>
                                    @empty
                                        <span class="tax-pill">Sin IVA</span>
                                    @endforelse
                                </div>
                            </div>
                        @empty
                            <div class="m-card text-center text-sm text-gray-400 py-10">Sin líneas de detalle.</div>
                        @endforelse
                    </div>

                    {{-- Totales --}}
                    <div class="p-4 totals-zone">
                        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                            <div class="totals-grid">
                                <table class="totals-table">
                                    <tbody>
                                        <tr>
                                            <td class="totals-k">Monto neto:</td>
                                            <td class="totals-v">$ {{ number_format((float) $document->monto_neto, 0, ',', '.') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="totals-k">{{ $ivaLabel }}:</td>
                                            <td class="totals-v">{{ $ivaMonto > 0 ? '$ ' . number_format($ivaMonto, 0, ',', '.') : '$ 0' }}</td>
                                        </tr>
                                        @foreach($extraTaxRows as $taxRow)
                                            <tr>
                                                <td class="totals-k">{{ $taxRow['label'] }}:</td>
                                                <td class="totals-v">$ {{ number_format((float) $taxRow['monto'], 0, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                        <tr class="totals-total">
                                            <td class="totals-k">Total:</td>
                                            <td class="totals-v">$ {{ number_format((float) $document->monto_total, 0, ',', '.') }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div class="totals-pay">
                                    <span class="totals-k flex items-center gap-2 !text-base !font-semibold">
                                        <svg class="w-5 h-5 {{ $estadoPago === 'Pagado' ? 'text-teal-600' : 'text-amber-600' }}" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10A8 8 0 11.001 9.999 8 8 0 0118 10zm-8.75 3.75a.75.75 0 001.5 0v-4a.75.75 0 00-1.5 0v4zm.75-6.5a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                                        </svg>
                                        {{ $estadoPago === 'Pagado' ? ('Pagado' . ($fechaPago ? ' el ' . $fechaPago : '')) : 'Pendiente de pago' }}
                                    </span>
                                    <span class="totals-v !text-4xl">$ {{ number_format((float) $montoPorPagar, 0, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Tab: Apuntes contables --}}
                <div x-show="tab === 'contable'" x-cloak class="empty-tab">
                    <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                        <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M12 7h.01M15 7h.01M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <p class="text-sm font-semibold text-gray-600 dark:text-gray-400">Sin apuntes contables registrados</p>
                    <p class="text-xs text-gray-400 max-w-xs">Los asientos contables se generan automáticamente al procesar el pago del documento.</p>
                </div>

                {{-- Tab: Otra información --}}
                <div x-show="tab === 'info'" x-cloak class="p-4 sm:p-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="m-card">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-3">Origen del documento</p>
                            <div class="kv">
                                <div class="k">Archivo XML</div>
                                <div class="v break-all text-xs">{{ $document->xml_filename ?? '—' }}</div>
                                <div class="k">Email origen</div>
                                <div class="v">{{ $document->email_origen ?? '—' }}</div>
                                <div class="k">Fecha procesado</div>
                                <div class="v">{{ $document->created_at ? \Carbon\Carbon::parse($document->created_at)->format('d/m/Y H:i') : '—' }}</div>
                            </div>
                        </div>
                        <div class="m-card">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-3">Estado del documento</p>
                            <div class="kv">
                                <div class="k">Estado pago</div>
                                <div class="v">
                                    <span class="chip {{ $estadoPago === 'Pagado' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300' }}">
                                        {{ $estadoPago }}
                                    </span>
                                </div>
                                <div class="k">Workflow</div>
                                <div class="v">
                                    <span class="chip bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">{{ strtoupper($workflowStatus) }}</span>
                                </div>
                                <div class="k">Inventario</div>
                                <div class="v">
                                    <span class="chip {{ $inventoryStatus === 'ingresado' ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' }}">
                                        {{ $inventoryStatus === 'ingresado' ? 'Stock ingresado' : 'Stock pendiente' }}
                                    </span>
                                </div>
                                @if($fechaPago)
                                    <div class="k">Fecha pago</div>
                                    <div class="v">{{ $fechaPago }}</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Tab: Referencias cruzadas --}}
                <div x-show="tab === 'cruzadas'" x-cloak class="empty-tab">
                    <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                        <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <p class="text-sm font-semibold text-gray-600 dark:text-gray-400">Sin referencias cruzadas</p>
                    <p class="text-xs text-gray-400 max-w-xs">No hay documentos relacionados a esta factura.</p>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
