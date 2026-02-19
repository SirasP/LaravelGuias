<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-3 flex-wrap">
            <div class="flex items-center gap-1.5 min-w-0 text-xs">
                <a href="{{ route('gmail.dtes.index') }}"
                    class="text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition font-medium truncate">
                    Facturas proveedor
                </a>
                <svg class="w-3 h-3 text-gray-300 dark:text-gray-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                </svg>
                <span class="font-bold text-gray-700 dark:text-gray-300 truncate">
                    {{ $tipo['sigla'] ?? 'DTE' }} {{ $document->folio ?? '—' }}
                </span>
            </div>
            <div class="flex items-center gap-1.5 shrink-0 flex-wrap">
                <form method="POST" action="{{ route('gmail.dtes.pay', $document->id) }}" class="contents">
                    @csrf
                    <button type="submit" class="hdr-btn hdr-emerald">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="hidden sm:inline">Registrar pago</span>
                    </button>
                </form>
                <form method="POST" action="{{ route('gmail.dtes.accept', $document->id) }}" class="contents">
                    @csrf
                    <button type="submit" class="hdr-btn hdr-sky">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span class="hidden sm:inline">Aceptar</span>
                    </button>
                </form>
                <form method="POST" action="{{ route('gmail.dtes.draft', $document->id) }}" class="contents">
                    @csrf
                    <button type="submit" class="hdr-btn hdr-gray">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5h8m-8 7h8m-8 7h8M5 5h.01M5 12h.01M5 19h.01"/></svg>
                        <span class="hidden sm:inline">Borrador</span>
                    </button>
                </form>
                <form method="POST" action="{{ route('gmail.dtes.add_stock', $document->id) }}" class="contents">
                    @csrf
                    <button type="submit" class="hdr-btn hdr-violet">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        <span class="hidden sm:inline">Agregar stock</span>
                    </button>
                </form>
                <form method="POST" action="{{ route('gmail.dtes.credit_note', $document->id) }}" class="contents">
                    @csrf
                    <button type="submit" class="hdr-btn hdr-rose">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14H5a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        <span class="hidden sm:inline">Nota crédito</span>
                    </button>
                </form>
                <div class="w-px h-5 bg-gray-200 dark:bg-gray-700 hidden sm:block"></div>
                <a href="{{ route('gmail.dtes.print', $document->id) }}?autoprint=1" target="_blank" class="hdr-btn hdr-gray">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    <span class="hidden sm:inline">Imprimir</span>
                </a>
            </div>
        </div>
    </x-slot>

    @php
        $tipoMap = [
            33 => ['sigla' => 'FAC', 'nombre' => 'Factura electrónica',   'color' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'],
            34 => ['sigla' => 'FEX', 'nombre' => 'Factura exenta',        'color' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300'],
            56 => ['sigla' => 'ND',  'nombre' => 'Nota de débito',        'color' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300'],
            61 => ['sigla' => 'NC',  'nombre' => 'Nota de crédito',       'color' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300'],
        ];

        $tipo = $tipoMap[(int) ($document->tipo_dte ?? 0)] ?? ['sigla' => 'DTE', 'nombre' => 'Documento tributario', 'color' => 'bg-gray-100 text-gray-600'];

        $estadoPagoRaw   = data_get($document, 'payment_status');
        $estadoPago      = $estadoPagoRaw === 'pagado' ? 'Pagado' : 'Sin pagar';
        $workflowStatus  = data_get($document, 'workflow_status', 'borrador');
        $isDraft         = $workflowStatus === 'borrador';
        $inventoryStatus = data_get($document, 'inventory_status', 'pendiente');
        $montoPorPagar   = $estadoPagoRaw === 'pagado' ? 0.0 : (float) ($document->monto_total ?? 0);
        $fechaPago       = data_get($document, 'paid_at') ? \Carbon\Carbon::parse($document->paid_at)->format('d/m/Y') : null;

        $taxSummary = collect($document->tax_summary ?? []);
        $ivaMonto   = (float) ($document->monto_iva ?? 0);
        $ivaLabel   = (string) (collect($taxSummary)->first(fn($t) => str_starts_with(strtoupper((string)($t['label'] ?? '')), 'IVA'))['label'] ?? 'IVA');
        $extraTaxRows = $taxSummary
            ->filter(fn($t) => !str_starts_with(strtoupper((string)($t['label'] ?? '')), 'IVA') && ((float)($t['monto'] ?? 0) > 0))
            ->map(fn($t) => ['label' => trim((string)($t['label'] ?? 'Impuesto')), 'monto' => (float)($t['monto'] ?? 0)])
            ->values();

        // Vencimiento
        $vencDate    = $document->fecha_vencimiento ? \Carbon\Carbon::parse($document->fecha_vencimiento)->startOfDay() : null;
        $hoy         = now()->startOfDay();
        $diasVencido = $vencDate ? (int) $vencDate->diffInDays($hoy, false) : null;
        if ($estadoPagoRaw === 'pagado') {
            $vencDisplay = ['text' => 'Al día', 'class' => 'text-emerald-600 dark:text-emerald-400'];
        } elseif ($diasVencido !== null && $diasVencido > 0) {
            $vencDisplay = ['text' => "Vencido hace {$diasVencido} día" . ($diasVencido > 1 ? 's' : ''), 'class' => 'text-rose-600 dark:text-rose-400 font-semibold'];
        } elseif ($diasVencido === 0) {
            $vencDisplay = ['text' => 'Vence hoy', 'class' => 'text-amber-600 dark:text-amber-400 font-semibold'];
        } else {
            $vencDisplay = ['text' => $vencDate ? $document->fecha_vencimiento : '—', 'class' => 'text-gray-600 dark:text-gray-400'];
        }
    @endphp

    <style>
        [x-cloak] { display:none !important }

        @keyframes fadeUp {
            from { opacity:0; transform:translateY(8px) }
            to   { opacity:1; transform:translateY(0) }
        }
        .au { animation:fadeUp .35s ease both }
        .d1 { animation-delay:.06s }
        .d2 { animation-delay:.12s }
        .d3 { animation-delay:.18s }

        .page-bg { background:#f1f5f9; min-height:100% }
        .dark .page-bg { background:#0d1117 }

        .panel { background:#fff; border:1px solid #e2e8f0; border-radius:18px; overflow:hidden }
        .dark .panel { background:#161c2c; border-color:#1e2a3b }

        .section-label {
            font-size:10px; font-weight:700; text-transform:uppercase;
            letter-spacing:.07em; color:#94a3b8; margin-bottom:10px;
        }

        .chip { display:inline-flex; align-items:center; border-radius:999px; padding:3px 10px; font-size:11px; font-weight:700 }

        .tipo-badge {
            display:inline-flex; align-items:center; border-radius:6px;
            padding:2px 8px; font-size:11px; font-weight:800; letter-spacing:.04em
        }

        /* Tabla líneas */
        .dt { width:100%; border-collapse:collapse; font-size:13px; min-width:820px }
        .dt thead { position:sticky; top:0; z-index:1 }
        .dt thead tr { background:#f8fafc }
        .dark .dt thead tr { background:#0f1623 }
        .dt th {
            padding:9px 14px; text-align:left; font-size:10px; font-weight:700;
            letter-spacing:.07em; text-transform:uppercase; color:#94a3b8; white-space:nowrap;
            box-shadow:inset 0 -2px 0 #e2e8f0;
        }
        .dark .dt th { box-shadow:inset 0 -2px 0 #1e2a3b }
        .dt td { padding:11px 14px; border-bottom:1px solid #f1f5f9; color:#334155; vertical-align:middle }
        .dark .dt td { border-bottom-color:#1a2232; color:#cbd5e1 }
        .dt tbody tr:last-child td { border-bottom:none }
        .dt tbody tr:hover td { background:#f5f7ff }
        .dark .dt tbody tr:hover td { background:rgba(255,255,255,.018) }
        .amt-col { background:rgba(99,102,241,.04); border-left:1px solid #eef2ff; font-weight:800; color:#1e293b }
        .dark .amt-col { background:rgba(99,102,241,.08); border-left-color:#1e2a3b; color:#e2e8f0 }
        .dt tbody tr:hover .amt-col { background:rgba(99,102,241,.09) }
        .dark .dt tbody tr:hover .amt-col { background:rgba(99,102,241,.14) }
        .row-num {
            display:inline-flex; width:20px; height:20px; align-items:center; justify-content:center;
            border-radius:999px; background:#f1f5f9; color:#94a3b8; font-size:10px; font-weight:700;
        }
        .dark .row-num { background:#1e2a3b; color:#64748b }

        /* Tax pills */
        .tax-pill { display:inline-flex; align-items:center; border-radius:6px; padding:2px 8px; font-size:11px; font-weight:700 }
        .tp-iva    { background:#eef2ff; color:#4338ca }
        .tp-exento { background:#ecfdf5; color:#059669 }
        .tp-imp    { background:#fff7ed; color:#c2410c }
        .tp-other  { background:#f1f5f9; color:#475569 }
        .dark .tp-iva    { background:#1e1b4b; color:#a5b4fc }
        .dark .tp-exento { background:#022c22; color:#6ee7b7 }
        .dark .tp-imp    { background:#431407; color:#fdba74 }
        .dark .tp-other  { background:#1e2a3b; color:#94a3b8 }

        /* Tabs */
        .tabs { display:flex; gap:4px; padding:10px 14px 0; border-bottom:1px solid #edf2f7; overflow-x:auto; white-space:nowrap }
        .dark .tabs { border-bottom-color:#1e2a3b }
        .tab {
            border:1px solid #e2e8f0; border-bottom:none; border-radius:9px 9px 0 0;
            background:#f8fafc; color:#64748b; font-size:12px; font-weight:700; padding:7px 13px;
            cursor:pointer; transition:background .12s, color .12s; white-space:nowrap;
        }
        .dark .tab { border-color:#273244; background:#111827; color:#94a3b8 }
        .tab.active { background:#fff; color:#1e293b; border-bottom-color:#fff }
        .dark .tab.active { background:#161c2c; color:#e2e8f0; border-bottom-color:#161c2c }

        /* Empty tab */
        .empty-tab { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:52px 24px; gap:12px; color:#94a3b8; text-align:center }

        /* Sidebar */
        .sidebar-section { padding:16px 18px; border-bottom:1px solid #f1f5f9 }
        .dark .sidebar-section { border-bottom-color:#1e2a3b }
        .sidebar-section:last-child { border-bottom:none }

        .info-row { display:flex; justify-content:space-between; align-items:baseline; gap:8px; padding:4px 0; font-size:13px }
        .info-k { color:#64748b; font-weight:600; white-space:nowrap; flex-shrink:0 }
        .dark .info-k { color:#94a3b8 }
        .info-v { color:#1e293b; font-weight:700; text-align:right; min-width:0; word-break:break-all }
        .dark .info-v { color:#e2e8f0 }

        .total-row { display:flex; justify-content:space-between; align-items:center; gap:8px }
        .total-label { font-size:13px; color:#64748b; font-weight:600 }
        .dark .total-label { color:#94a3b8 }
        .total-val { font-size:13px; font-weight:700; color:#334155; font-variant-numeric:tabular-nums }
        .dark .total-val { color:#cbd5e1 }

        /* Mobile line card */
        .line-card { background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:12px }
        .dark .line-card { background:#111827; border-color:#1e2a3b }

        /* Header action buttons */
        .hdr-btn {
            display:inline-flex; align-items:center; gap:5px;
            padding:7px 11px; border-radius:10px; font-size:12px; font-weight:700;
            border:none; cursor:pointer; transition:all .13s; text-decoration:none; white-space:nowrap;
        }
        .hdr-emerald { background:#059669; color:#fff }
        .hdr-emerald:hover { background:#047857 }
        .hdr-sky     { background:#0284c7; color:#fff }
        .hdr-sky:hover     { background:#0369a1 }
        .hdr-violet  { background:#7c3aed; color:#fff }
        .hdr-violet:hover  { background:#6d28d9 }
        .hdr-indigo  { background:#4f46e5; color:#fff }
        .hdr-indigo:hover  { background:#4338ca }
        .hdr-rose    { background:#e11d48; color:#fff }
        .hdr-rose:hover    { background:#be123c }
        .hdr-gray    { background:#f1f5f9; color:#475569; border:1px solid #e2e8f0 }
        .hdr-gray:hover    { background:#e2e8f0 }
        .dark .hdr-gray    { background:#1e2a3b; color:#94a3b8; border-color:#273244 }
        .dark .hdr-gray:hover { background:#273244 }
        .hdr-btn:active { transform:scale(.97) }
    </style>

    <div class="page-bg">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-5">

            {{-- ════ LAYOUT PRINCIPAL ════ --}}
            <div class="flex flex-col xl:flex-row gap-4 items-start">

                {{-- ── COLUMNA IZQUIERDA ──────────────────────── --}}
                <div class="w-full xl:flex-1 min-w-0 space-y-4">

                    {{-- Cabecera del documento --}}
                    <div class="panel au d1">
                        <div class="px-5 py-4">
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 mb-1.5">
                                        <span class="tipo-badge {{ $tipo['color'] }}">{{ $tipo['sigla'] }}</span>
                                        <span class="text-xs text-gray-400 font-medium">{{ $tipo['nombre'] }}</span>
                                    </div>
                                    <h1 class="text-3xl sm:text-4xl font-black text-gray-900 dark:text-gray-100 tracking-tight tabular-nums leading-none">
                                        {{ $document->folio ?? '—' }}
                                    </h1>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1.5 font-medium truncate">
                                        {{ $document->proveedor_nombre ?? '—' }}
                                        @if($document->proveedor_rut)
                                            <span class="font-mono text-gray-400 ml-1">· {{ $document->proveedor_rut }}</span>
                                        @endif
                                    </p>
                                </div>

                                {{-- Total visible en desktop --}}
                                <div class="hidden sm:flex flex-col items-end gap-1 shrink-0">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Total factura</p>
                                    <p class="text-2xl font-black text-gray-900 dark:text-gray-100 tabular-nums">
                                        $ {{ number_format((float) $document->monto_total, 0, ',', '.') }}
                                    </p>
                                    <span class="chip {{ $estadoPago === 'Pagado' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300' }}">
                                        {{ $estadoPago }}
                                    </span>
                                </div>
                            </div>

                            {{-- Chips de estado --}}
                            <div class="flex flex-wrap items-center gap-2 mt-3 pt-3 border-t border-gray-100 dark:border-gray-800/80">
                                <span class="chip bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-300">
                                    {{ strtoupper((string) $workflowStatus) }}
                                </span>
                                <span class="chip {{ $inventoryStatus === 'ingresado' ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' }}">
                                    {{ $inventoryStatus === 'ingresado' ? 'Stock ingresado' : 'Stock pendiente' }}
                                </span>
                                @if($document->fecha_factura)
                                    <span class="text-[11px] text-gray-400">
                                        Emitida el {{ $document->fecha_factura }}
                                    </span>
                                @endif
                                @if($document->referencia)
                                    <span class="hidden lg:inline text-[11px] text-gray-400 truncate max-w-xs" title="{{ $document->referencia }}">
                                        · {{ Str::limit($document->referencia, 60) }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Tabs + Líneas --}}
                    <div class="panel au d2" x-data="{ tab: 'lineas' }">
                        <div class="tabs">
                            <button class="tab" :class="{ active: tab === 'lineas' }"   @click="tab = 'lineas'">Líneas de factura</button>
                            <button class="tab" :class="{ active: tab === 'contable' }" @click="tab = 'contable'">Apuntes contables</button>
                            <button class="tab" :class="{ active: tab === 'info' }"     @click="tab = 'info'">Otra información</button>
                            <button class="tab" :class="{ active: tab === 'cruzadas' }" @click="tab = 'cruzadas'">Referencias cruzadas</button>
                        </div>

                        {{-- Tab: Líneas --}}
                        <div x-show="tab === 'lineas'">
                            @php
                                $taxClass = fn(string $lbl): string =>
                                    str_starts_with($lbl, 'IVA')                                        ? 'tp-iva'
                                    : ($lbl === 'Exento'                                                ? 'tp-exento'
                                    : (str_contains($lbl, 'Diesel') || str_contains($lbl, 'specí')     ? 'tp-imp'
                                    :                                                                    'tp-other'));
                            @endphp

                            {{-- Desktop --}}
                            <div class="hidden lg:block overflow-x-auto">
                                <table class="dt">
                                    <thead>
                                        <tr>
                                            <th class="text-center w-10">#</th>
                                            <th>Producto / Descripción</th>
                                            <th class="text-right">Cantidad</th>
                                            <th class="text-right">Precio unit.</th>
                                            <th>Impuesto</th>
                                            <th class="text-right pr-5">Importe</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $rowNum = 0; @endphp
                                        @forelse($lines as $l)
                                            @php
                                                $rowNum++;
                                                $taxLabels = collect($l->taxes ?? [])->map(function ($tax) {
                                                    $type = strtoupper((string) ($tax->tax_type ?? ''));
                                                    $code = (string) ($tax->codigo ?? '');
                                                    $desc = trim((string) ($tax->descripcion ?? ''));
                                                    $rate = $tax->tasa;
                                                    if ($type === 'IVA') return !is_null($rate) ? 'IVA ' . rtrim(rtrim((string) $rate, '0'), '.') . '%' : ($desc ?: 'IVA');
                                                    if ($type === 'IMP_ADIC') return $code === '28' ? 'IEC Diesel' : preg_replace('/^Imp\\. adic\\./i', 'Imp. específico', $desc ?: 'Imp. específico');
                                                    if ($type === 'IMPTO_RETEN' && $code === '28') return 'IEC Diesel';
                                                    return preg_replace('/^Imp\\. adic\\./i', 'Imp. específico', $desc ?: 'Impuesto');
                                                })->filter()->values();
                                                if ($taxLabels->isEmpty()) {
                                                    if ((int) ($l->es_exento ?? 0) === 1) $taxLabels = collect(['Exento']);
                                                    elseif (!is_null($l->impuesto_tasa)) $taxLabels = collect(['IVA ' . rtrim(rtrim((string) $l->impuesto_tasa, '0'), '.') . '%']);
                                                    elseif ((float) $document->monto_iva > 0) $taxLabels = collect(['IVA incluido']);
                                                    else $taxLabels = collect(['Sin IVA']);
                                                }
                                            @endphp
                                            <tr>
                                                <td class="text-center">
                                                    <span class="row-num">{{ $rowNum }}</span>
                                                </td>
                                                <td>
                                                    <p class="font-semibold text-gray-800 dark:text-gray-200 leading-tight">{{ $l->descripcion ?? '—' }}</p>
                                                    @if($l->codigo)
                                                        <span class="inline-flex mt-0.5 px-1.5 py-px text-[10px] font-mono rounded bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400">{{ $l->codigo }}</span>
                                                    @endif
                                                </td>
                                                <td class="text-right whitespace-nowrap">
                                                    @if($isDraft)
                                                        <input type="number"
                                                            name="cantidad"
                                                            form="line-form-{{ $l->id }}"
                                                            step="0.0001"
                                                            min="0"
                                                            value="{{ number_format((float) $l->cantidad, 4, '.', '') }}"
                                                            onkeydown="if(event.key==='Enter'){event.preventDefault(); document.getElementById('line-form-{{ $l->id }}').requestSubmit();}"
                                                            class="w-24 rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-xs text-right">
                                                        @if($l->unidad)
                                                            <span class="ml-1 text-[11px] font-semibold text-gray-400">{{ $l->unidad }}</span>
                                                        @endif
                                                    @else
                                                        <span class="font-semibold tabular-nums text-gray-800 dark:text-gray-200">{{ number_format((float) $l->cantidad, 2, ',', '.') }}</span>
                                                        @if($l->unidad)
                                                            <span class="ml-1 text-[11px] font-semibold text-gray-400">{{ $l->unidad }}</span>
                                                        @endif
                                                    @endif
                                                </td>
                                                <td class="text-right tabular-nums text-gray-600 dark:text-gray-400">
                                                    @if($isDraft)
                                                        <input type="number"
                                                            name="precio_unitario"
                                                            form="line-form-{{ $l->id }}"
                                                            step="0.0001"
                                                            min="0"
                                                            value="{{ number_format((float) $l->precio_unitario, 4, '.', '') }}"
                                                            onkeydown="if(event.key==='Enter'){event.preventDefault(); document.getElementById('line-form-{{ $l->id }}').requestSubmit();}"
                                                            class="w-28 rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-xs text-right ml-auto">
                                                    @else
                                                        $ {{ number_format((float) $l->precio_unitario, 0, ',', '.') }}
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="flex flex-wrap gap-1">
                                                        @foreach($taxLabels as $label)
                                                            <span class="tax-pill {{ $taxClass($label) }}">{{ $label }}</span>
                                                        @endforeach
                                                    </div>
                                                </td>
                                                <td class="text-right tabular-nums amt-col pr-5 text-sm">
                                                    $ {{ number_format((float) $l->monto_item, 0, ',', '.') }}
                                                    @if($isDraft)
                                                        <form id="line-form-{{ $l->id }}" method="POST" action="{{ route('gmail.dtes.lines.update', ['id' => $document->id, 'lineId' => $l->id]) }}" class="hidden">
                                                            @csrf
                                                        </form>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center py-12 text-gray-400 text-sm">Sin líneas de detalle.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            {{-- Móvil --}}
                            <div class="lg:hidden p-3 space-y-2">
                                @forelse($lines as $l)
                                    @php
                                        $tlm = collect($l->taxes ?? [])->map(function ($tax) {
                                            $type = strtoupper((string) ($tax->tax_type ?? ''));
                                            $code = (string) ($tax->codigo ?? '');
                                            $desc = trim((string) ($tax->descripcion ?? ''));
                                            $rate = $tax->tasa;
                                            if ($type === 'IVA') return !is_null($rate) ? 'IVA ' . rtrim(rtrim((string) $rate, '0'), '.') . '%' : ($desc ?: 'IVA');
                                            if ($type === 'IMP_ADIC') return $code === '28' ? 'IEC Diesel' : preg_replace('/^Imp\\. adic\\./i', 'Imp. específico', $desc ?: 'Imp. específico');
                                            if ($type === 'IMPTO_RETEN' && $code === '28') return 'IEC Diesel';
                                            return preg_replace('/^Imp\\. adic\\./i', 'Imp. específico', $desc ?: 'Impuesto');
                                        })->filter()->values();
                                        if ($tlm->isEmpty()) {
                                            if ((int) ($l->es_exento ?? 0) === 1) $tlm = collect(['Exento']);
                                            elseif ($l->impuesto_tasa !== null) $tlm = collect(['IVA ' . rtrim(rtrim((string) $l->impuesto_tasa, '0'), '.') . '%']);
                                            elseif ((float) $document->monto_iva > 0) $tlm = collect(['IVA incluido']);
                                            else $tlm = collect(['Sin IVA']);
                                        }
                                    @endphp
                                    <div class="line-card border-l-[3px] border-l-indigo-400 dark:border-l-indigo-600">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-tight">{{ $l->descripcion ?? '—' }}</p>
                                                @if($l->codigo)
                                                    <span class="inline-flex mt-0.5 px-1.5 py-px text-[10px] font-mono rounded bg-gray-100 dark:bg-gray-800 text-gray-500">{{ $l->codigo }}</span>
                                                @endif
                                            </div>
                                            <p class="text-base font-black text-indigo-600 dark:text-indigo-400 tabular-nums shrink-0">
                                                $ {{ number_format((float) $l->monto_item, 0, ',', '.') }}
                                            </p>
                                        </div>
                                        <div class="grid grid-cols-2 gap-x-4 gap-y-2 mt-3 text-xs border-t border-gray-100 dark:border-gray-800 pt-3">
                                            <div>
                                                <p class="text-gray-400 uppercase tracking-wide text-[10px] mb-0.5">Cantidad</p>
                                                <p class="font-semibold text-gray-700 dark:text-gray-300 tabular-nums">
                                                    {{ number_format((float) $l->cantidad, 2, ',', '.') }}
                                                    <span class="text-gray-400 ml-0.5 font-normal">{{ $l->unidad ?? '' }}</span>
                                                </p>
                                            </div>
                                            <div>
                                                <p class="text-gray-400 uppercase tracking-wide text-[10px] mb-0.5">Precio unit.</p>
                                                <p class="font-semibold text-gray-700 dark:text-gray-300 tabular-nums">$ {{ number_format((float) $l->precio_unitario, 0, ',', '.') }}</p>
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap gap-1 mt-2.5">
                                            @foreach($tlm as $label)
                                                <span class="tax-pill {{ $taxClass($label) }}">{{ $label }}</span>
                                            @endforeach
                                        </div>
                                        @if($isDraft)
                                            <form method="POST" action="{{ route('gmail.dtes.lines.update', ['id' => $document->id, 'lineId' => $l->id]) }}" class="grid grid-cols-2 gap-2 mt-3 border-t border-gray-100 dark:border-gray-800 pt-3">
                                                @csrf
                                                <input type="number" name="cantidad" step="0.0001" min="0" value="{{ number_format((float) $l->cantidad, 4, '.', '') }}"
                                                    onkeydown="if(event.key==='Enter'){event.preventDefault(); this.form.requestSubmit();}"
                                                    class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-xs">
                                                <input type="number" name="precio_unitario" step="0.0001" min="0" value="{{ number_format((float) $l->precio_unitario, 4, '.', '') }}"
                                                    onkeydown="if(event.key==='Enter'){event.preventDefault(); this.form.requestSubmit();}"
                                                    class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-xs">
                                            </form>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-center text-sm text-gray-400 py-8">Sin líneas de detalle.</p>
                                @endforelse
                            </div>

                            {{-- Totales --}}
                            <div class="border-t-2 border-gray-100 dark:border-gray-800 px-5 py-4 bg-gray-50/60 dark:bg-gray-900/20">
                                <div class="flex justify-end">
                                    <div class="space-y-1.5 min-w-[230px]">
                                        <div class="flex justify-between items-center gap-10">
                                            <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Neto</span>
                                            <span class="font-semibold tabular-nums text-gray-600 dark:text-gray-400 text-sm">$ {{ number_format((float) $document->monto_neto, 0, ',', '.') }}</span>
                                        </div>
                                        <div class="flex justify-between items-center gap-10">
                                            <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ $ivaLabel }}</span>
                                            <span class="font-semibold tabular-nums text-gray-600 dark:text-gray-400 text-sm">$ {{ $ivaMonto > 0 ? number_format($ivaMonto, 0, ',', '.') : '0' }}</span>
                                        </div>
                                        @foreach($extraTaxRows as $taxRow)
                                            <div class="flex justify-between items-center gap-10">
                                                <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ $taxRow['label'] }}</span>
                                                <span class="font-semibold tabular-nums text-gray-600 dark:text-gray-400 text-sm">$ {{ number_format((float) $taxRow['monto'], 0, ',', '.') }}</span>
                                            </div>
                                        @endforeach
                                        <div class="border-t border-gray-200 dark:border-gray-700 pt-2.5 mt-1 flex justify-between items-baseline gap-10">
                                            <span class="text-xs font-black uppercase tracking-wider text-gray-700 dark:text-gray-300">Total</span>
                                            <span class="text-2xl font-black tabular-nums text-indigo-600 dark:text-indigo-400">$ {{ number_format((float) $document->monto_total, 0, ',', '.') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Tab: Apuntes contables --}}
                        <div x-show="tab === 'contable'" x-cloak class="empty-tab">
                            <div class="w-12 h-12 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M12 7h.01M15 7h.01M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">Sin apuntes contables</p>
                            <p class="text-xs text-gray-400 max-w-xs">Los asientos se generarán al procesar el pago.</p>
                        </div>

                        {{-- Tab: Otra información --}}
                        <div x-show="tab === 'info'" x-cloak class="p-5">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <p class="section-label">Origen</p>
                                    <div class="space-y-2">
                                        <div class="info-row">
                                            <span class="info-k">Archivo XML</span>
                                            <span class="info-v text-xs font-mono text-gray-500">{{ Str::limit($document->xml_filename ?? '—', 30) }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-k">Email origen</span>
                                            <span class="info-v">{{ $document->email_origen ?? '—' }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-k">Importado</span>
                                            <span class="info-v">{{ $document->created_at ? \Carbon\Carbon::parse($document->created_at)->format('d/m/Y H:i') : '—' }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <p class="section-label">Estado</p>
                                    <div class="space-y-2">
                                        <div class="info-row">
                                            <span class="info-k">Pago</span>
                                            <span class="chip {{ $estadoPago === 'Pagado' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300' }}">{{ $estadoPago }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-k">Workflow</span>
                                            <span class="chip bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">{{ strtoupper($workflowStatus) }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-k">Inventario</span>
                                            <span class="chip {{ $inventoryStatus === 'ingresado' ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' }}">
                                                {{ $inventoryStatus === 'ingresado' ? 'Ingresado' : 'Pendiente' }}
                                            </span>
                                        </div>
                                        @if($fechaPago)
                                            <div class="info-row">
                                                <span class="info-k">Fecha pago</span>
                                                <span class="info-v text-emerald-600 dark:text-emerald-400">{{ $fechaPago }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Tab: Referencias cruzadas --}}
                        <div x-show="tab === 'cruzadas'" x-cloak class="empty-tab">
                            <div class="w-12 h-12 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">Sin referencias cruzadas</p>
                            <p class="text-xs text-gray-400 max-w-xs">No hay documentos relacionados a esta factura.</p>
                        </div>
                    </div>

                </div>{{-- /left --}}

                {{-- ── SIDEBAR ────────────────────────────────── --}}
                <div class="w-full xl:w-72 2xl:w-80 shrink-0 space-y-4 au d2">

                    {{-- Estado de pago --}}
                    <div class="panel">
                        <div class="sidebar-section">
                            <p class="section-label">Resumen de pago</p>
                            <div class="space-y-1.5 mb-3">
                                <div class="total-row">
                                    <span class="total-label">Neto</span>
                                    <span class="total-val">$ {{ number_format((float) $document->monto_neto, 0, ',', '.') }}</span>
                                </div>
                                <div class="total-row">
                                    <span class="total-label">{{ $ivaLabel }}</span>
                                    <span class="total-val">$ {{ $ivaMonto > 0 ? number_format($ivaMonto, 0, ',', '.') : '0' }}</span>
                                </div>
                                @foreach($extraTaxRows as $taxRow)
                                    <div class="total-row">
                                        <span class="total-label">{{ $taxRow['label'] }}</span>
                                        <span class="total-val">$ {{ number_format((float) $taxRow['monto'], 0, ',', '.') }}</span>
                                    </div>
                                @endforeach
                            </div>
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-3">
                                <div class="flex justify-between items-baseline">
                                    <span class="text-sm font-bold text-gray-600 dark:text-gray-400">Total</span>
                                    <span class="text-2xl font-black text-gray-900 dark:text-gray-100 tabular-nums">
                                        $ {{ number_format((float) $document->monto_total, 0, ',', '.') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="sidebar-section">
                            @if($estadoPago === 'Pagado')
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center shrink-0">
                                        <svg class="w-4.5 h-4.5 text-emerald-600 dark:text-emerald-400 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold text-emerald-700 dark:text-emerald-400">Pagado</p>
                                        @if($fechaPago)<p class="text-xs text-gray-400">el {{ $fechaPago }}</p>@endif
                                    </div>
                                    <p class="ml-auto text-sm font-bold text-emerald-600 tabular-nums">$ 0</p>
                                </div>
                            @else
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center shrink-0">
                                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold text-amber-700 dark:text-amber-400">Pendiente de pago</p>
                                        <p class="text-xs {{ $vencDisplay['class'] }}">{{ $vencDisplay['text'] }}</p>
                                    </div>
                                    <p class="ml-auto text-sm font-bold text-amber-600 tabular-nums">$ {{ number_format($montoPorPagar, 0, ',', '.') }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Proveedor + Fechas --}}
                    <div class="panel">
                        <div class="sidebar-section">
                            <p class="section-label">Proveedor</p>
                            <p class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-tight">{{ $document->proveedor_nombre ?? '—' }}</p>
                            @if($document->proveedor_rut)
                                <p class="text-xs font-mono text-gray-400 mt-0.5">{{ $document->proveedor_rut }}</p>
                            @endif
                            @if($document->referencia)
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 leading-relaxed">{{ $document->referencia }}</p>
                            @endif
                        </div>
                        <div class="sidebar-section">
                            <p class="section-label">Fechas</p>
                            <div class="space-y-1.5">
                                <div class="info-row">
                                    <span class="info-k">Factura</span>
                                    <span class="info-v">{{ $document->fecha_factura ?? '—' }}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-k">Contable</span>
                                    <span class="info-v">{{ $document->fecha_contable ?? '—' }}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-k">Vencimiento</span>
                                    <span class="info-v {{ $vencDisplay['class'] }}">{{ $document->fecha_vencimiento ?? '—' }}</span>
                                </div>
                            </div>
                        </div>
                       
                    </div>

                </div>{{-- /sidebar --}}

            </div>{{-- /layout --}}

        </div>
    </div>
</x-app-layout>
