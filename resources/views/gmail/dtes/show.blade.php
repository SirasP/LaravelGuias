<x-app-layout>
    @php
        $estadoPagoRaw = data_get($document, 'payment_status', 'sin_pagar');
        $workflowStatus = data_get($document, 'workflow_status', 'aceptado');
        $isDraft = $workflowStatus === 'borrador';
        $inventoryStatus = data_get($document, 'inventory_status', 'pendiente');
        $isBoletaType = in_array((int) ($document->tipo_dte ?? 0), [39, 41], true);
        $isGuiaType = (int) ($document->tipo_dte ?? 0) === 52;
        $backRoute = $isGuiaType
            ? route('gmail.dtes.guias.list')
            : ($isBoletaType ? route('gmail.dtes.boletas.list') : route('gmail.dtes.facturas.list'));
        $backLabel = $isGuiaType
            ? 'Guías proveedor'
            : ($isBoletaType ? 'Boletas proveedor' : 'Facturas proveedor');
        $headerTipoSigla = match ((int) ($document->tipo_dte ?? 0)) {
            33 => 'FAC',
            34 => 'FEX',
            56 => 'ND',
            61 => 'NC',
            39 => 'BOL',
            41 => 'BEX',
            52 => 'GUI',
            default => 'DTE',
        };
    @endphp
    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-3 flex-wrap">
            <div class="flex items-center gap-1.5 min-w-0 text-xs">
                <a href="{{ $backRoute }}"
                    class="text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition font-medium truncate">
                    {{ $backLabel }}
                </a>
                <svg class="w-3 h-3 text-gray-300 dark:text-gray-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                </svg>
                <span class="font-bold text-gray-700 dark:text-gray-300 truncate">
                    {{ $headerTipoSigla }} {{ $document->folio ?? '—' }}
                </span>
            </div>
            <div class="flex items-center gap-1.5 shrink-0 flex-wrap">
                @if($canSeeValues)
                <form id="doc-pay-form" method="POST" action="{{ $estadoPagoRaw === 'pagado' ? route('gmail.dtes.unpay', $document->id) : route('gmail.dtes.pay', $document->id) }}" class="contents">
                    @csrf
                    <button type="button"
                        @click="openConfirm({
                            title: '{{ $estadoPagoRaw === 'pagado' ? 'Cancelar pago' : 'Registrar pago' }}',
                            message: '{{ $estadoPagoRaw === 'pagado' ? 'Se quitará el estado de pagado de este documento.' : 'Se marcará este documento como pagado.' }}',
                            confirmLabel: 'Confirmar',
                            type: 'confirm',
                            callback: () => document.getElementById('doc-pay-form').submit()
                        })"
                        class="hdr-btn {{ $estadoPagoRaw === 'pagado' ? 'hdr-gray' : 'hdr-emerald' }}">
                        @if($estadoPagoRaw === 'pagado')
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            <span class="hidden sm:inline">Cancelar pago</span>
                        @else
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span class="hidden sm:inline">Registrar pago</span>
                        @endif
                    </button>
                </form>
                <form id="doc-workflow-form" method="POST" action="{{ $isDraft ? route('gmail.dtes.accept', $document->id) : route('gmail.dtes.draft', $document->id) }}" class="contents">
                    @csrf
                    <button type="button"
                        @click="openConfirm({
                            title: '{{ $isDraft ? 'Aceptar borrador' : 'Enviar a borrador' }}',
                            message: '{{ $isDraft ? 'El documento quedará como aceptado.' : 'El documento volverá al estado borrador.' }}',
                            confirmLabel: 'Confirmar',
                            type: 'confirm',
                            callback: () => document.getElementById('doc-workflow-form').submit()
                        })"
                        class="hdr-btn {{ $isDraft ? 'hdr-sky' : 'hdr-gray' }}">
                        @if($isDraft)
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span class="hidden sm:inline">Aceptar borrador</span>
                        @else
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5h8m-8 7h8m-8 7h8M5 5h.01M5 12h.01M5 19h.01"/></svg>
                            <span class="hidden sm:inline">Borrador</span>
                        @endif
                    </button>
                </form>
                @endif
                @if($inventoryStatus === 'combustible')
                    <span class="hdr-btn hdr-gray cursor-default opacity-70" title="Factura de combustible — no aplica a inventario de productos">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <span class="hidden sm:inline">Combustible</span>
                    </span>
                @elseif($inventoryStatus !== 'ingresado')
                    <form id="doc-stock-form" method="POST" action="{{ route('gmail.dtes.add_stock', $document->id) }}" class="contents">
                        @csrf
                        {{-- bodega_id se inyecta por JS al confirmar --}}
                        <input type="hidden" name="bodega_id" id="doc-stock-bodega-id">
                        <button type="button"
                            @click="openConfirm({
                                title: 'Agregar stock',
                                message: 'Selecciona la bodega de destino para este ingreso.',
                                confirmLabel: 'Agregar',
                                type: 'confirm',
                                selectLabel: 'Bodega de destino',
                                selectOptions: window.__stockBodegas,
                                selectDefault: window.__stockBodegaDefault,
                                callback: (bodegaId) => {
                                    document.getElementById('doc-stock-bodega-id').value = bodegaId ?? '';
                                    startStockAddReview(
                                        '{{ route('gmail.dtes.stock_review', $document->id) }}',
                                        '{{ route('gmail.dtes.stock_products') }}',
                                        'doc-stock-form',
                                        bodegaId
                                    );
                                }
                            })"
                            class="hdr-btn hdr-violet">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            <span class="hidden sm:inline">Agregar stock</span>
                        </button>
                    </form>
                @else
                    <form id="doc-rollback-stock-form" method="POST" action="{{ route('gmail.dtes.rollback_stock', $document->id) }}" class="contents">
                        @csrf
                        <button type="button"
                            @click="openConfirm({
                                title: 'Anular ingreso de stock',
                                message: 'Se revertirá este ingreso y el documento quedará pendiente. Si tuvo salidas FIFO, no se podrá anular.',
                                confirmLabel: 'Anular',
                                type: 'warning',
                                callback: () => document.getElementById('doc-rollback-stock-form').submit()
                            })"
                            class="hdr-btn hdr-gray">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            <span class="hidden sm:inline">Anular stock</span>
                        </button>
                    </form>
                @endif
                @if($canSeeValues)
                <form id="doc-credit-note-form" method="POST" action="{{ route('gmail.dtes.credit_note', $document->id) }}" class="contents">
                    @csrf
                    <button type="button"
                        @click="openConfirm({
                            title: 'Crear nota de crédito',
                            message: 'Se creará una nota de crédito desde este documento.',
                            confirmLabel: 'Crear NC',
                            type: 'confirm',
                            callback: () => document.getElementById('doc-credit-note-form').submit()
                        })"
                        class="hdr-btn hdr-rose">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14H5a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        <span class="hidden sm:inline">Nota crédito</span>
                    </button>
                </form>
                @endif
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
            39 => ['sigla' => 'BOL', 'nombre' => 'Boleta electrónica',    'color' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300'],
            41 => ['sigla' => 'BEX', 'nombre' => 'Boleta exenta',         'color' => 'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-900/40 dark:text-fuchsia-300'],
            52 => ['sigla' => 'GUI', 'nombre' => 'Guía de despacho',      'color' => 'bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300'],
        ];

        $tipo = $tipoMap[(int) ($document->tipo_dte ?? 0)] ?? ['sigla' => 'DTE', 'nombre' => 'Documento tributario', 'color' => 'bg-gray-100 text-gray-600'];

        $estadoPagoRaw   = data_get($document, 'payment_status');
        $estadoPago      = $estadoPagoRaw === 'pagado' ? 'Pagado' : 'Sin pagar';
        $workflowStatus  = data_get($document, 'workflow_status', 'aceptado');
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
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap');

        [x-cloak] { display:none !important }

        @keyframes fadeUp {
            from { opacity:0; transform:translateY(12px) }
            to   { opacity:1; transform:translateY(0) }
        }
        .au { animation:fadeUp .4s cubic-bezier(0.16, 1, 0.3, 1) both }
        .d1 { animation-delay:.04s }
        .d2 { animation-delay:.08s }
        .d3 { animation-delay:.12s }

        .page-bg { 
            background:#f8fafc; 
            min-height:100%; 
            font-family: 'Outfit', sans-serif !important;
        }
        .dark .page-bg { background:#090d16 }

        /* Premium Card Panels */
        .panel { 
            background:#fff; 
            border:1px solid rgba(226,232,240,0.8); 
            border-radius:20px; 
            overflow:hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -2px rgba(0, 0, 0, 0.02);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .dark .panel { 
            background:#111827; 
            border-color:#1f2937;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3), 0 4px 6px -4px rgba(0, 0, 0, 0.3);
        }
        .panel:hover {
            box-shadow: 0 12px 20px -3px rgba(0, 0, 0, 0.04), 0 4px 8px -4px rgba(0, 0, 0, 0.04);
            transform: translateY(-1px);
        }
        .dark .panel:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.4), 0 8px 10px -6px rgba(0, 0, 0, 0.4);
        }

        .section-label {
            font-size:10px; font-weight:800; text-transform:uppercase;
            letter-spacing:.08em; color:#94a3b8; margin-bottom:10px;
        }
        .dark .section-label { color:#64748b; }

        /* Status & Action Badges with breathing glows */
        @keyframes pulseGlowGreen {
            0%, 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.15); }
            50% { box-shadow: 0 0 8px 3px rgba(16, 185, 129, 0.35); }
        }
        @keyframes pulseGlowRed {
            0%, 100% { box-shadow: 0 0 0 0 rgba(244, 63, 94, 0.15); }
            50% { box-shadow: 0 0 8px 3px rgba(244, 63, 94, 0.35); }
        }

        .chip { 
            display:inline-flex; 
            align-items:center; 
            border-radius:999px; 
            padding:4px 12px; 
            font-size:11px; 
            font-weight:800;
            letter-spacing: .02em;
        }
        .chip[class*="emerald"] {
            animation: pulseGlowGreen 3s infinite;
        }
        .chip[class*="rose"] {
            animation: pulseGlowRed 3s infinite;
        }

        .tipo-badge {
            display:inline-flex; align-items:center; border-radius:8px;
            padding:3px 10px; font-size:11px; font-weight:900; letter-spacing:.06em
        }

        /* Tabla de líneas premium con interacciones suaves */
        .dt { width:100%; border-collapse:separate; border-spacing:0; font-size:13px; min-width:820px }
        .dt thead { position:sticky; top:0; z-index:1 }
        .dt thead tr { background:#f8fafc }
        .dark .dt thead tr { background:#182235; }
        .dt th {
            padding:12px 16px; text-align:left; font-size:10px; font-weight:850;
            letter-spacing:.08em; text-transform:uppercase; color:#64748b; white-space:nowrap;
            border-bottom: 2px solid #edf2f7;
        }
        .dark .dt th { border-bottom-color:#243249; color:#9ca3af; }
        .dt td { 
            padding:14px 16px; 
            border-bottom:1px solid #f1f5f9; 
            color:#334155; 
            vertical-align:middle; 
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); 
        }
        .dark .dt td { border-bottom-color:#1f2937; color:#cbd5e1 }
        .dt tbody tr:last-child td { border-bottom:none }
        
        .dt tbody tr {
            transition: all 0.2s ease;
        }
        .dt tbody tr:hover td { 
            background:#f8fafc; 
            transform: translateX(4px);
        }
        .dark .dt tbody tr:hover td { 
            background:#161f30; 
        }
        
        /* Resaltado premium columna de importes */
        .amt-col { 
            background:rgba(99,102,241,0.02); 
            border-left:1px solid #edf2f7; 
            font-weight:800; 
            color:#4f46e5; 
            font-variant-numeric: tabular-nums;
        }
        .dark .amt-col { 
            background:rgba(99,102,241,0.04); 
            border-left-color:#243249; 
            color:#818cf8; 
        }
        .dt tbody tr:hover .amt-col { background:rgba(99,102,241,0.06) }
        .dark .dt tbody tr:hover .amt-col { background:rgba(99,102,241,0.1) }
        
        .row-num {
            display:inline-flex; width:22px; height:22px; align-items:center; justify-content:center;
            border-radius:999px; background:#f1f5f9; color:#94a3b8; font-size:10px; font-weight:800;
        }
        .dark .row-num { background:#1f2937; color:#64748b; }

        /* Impuestos */
        .tax-pill { display:inline-flex; align-items:center; border-radius:8px; padding:3px 10px; font-size:11px; font-weight:800 }
        .tp-iva    { background:#eef2ff; color:#4338ca }
        .tp-exento { background:#ecfdf5; color:#059669 }
        .tp-imp    { background:#fff7ed; color:#c2410c }
        .tp-other  { background:#f1f5f9; color:#475569 }
        .dark .tp-iva    { background:#1e1b4b; color:#a5b4fc }
        .dark .tp-exento { background:#022c22; color:#6ee7b7 }
        .dark .tp-imp    { background:#431407; color:#fdba74 }
        .dark .tp-other  { background:#1f2937; color:#94a3b8 }

        /* Modern Sliding Pill Tabs */
        .tabs { 
            display:flex; 
            gap:6px; 
            padding:8px; 
            margin: 16px 16px 6px;
            background:#f1f5f9; 
            border-radius:14px; 
            overflow-x:auto; 
            white-space:nowrap 
        }
        .dark .tabs { background:#0f1623; }
        .tab {
            border:none; 
            border-radius:10px;
            background:transparent; 
            color:#64748b; 
            font-size:12px; 
            font-weight:800; 
            padding:8px 16px;
            cursor:pointer; 
            transition:all 0.25s cubic-bezier(0.4, 0, 0.2, 1); 
            white-space:nowrap;
        }
        .dark .tab { color:#94a3b8 }
        .tab:hover {
            color:#1e293b;
        }
        .dark .tab:hover {
            color:#f8fafc;
        }
        .tab.active { 
            background:#fff; 
            color:#4f46e5; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
        }
        .dark .tab.active { 
            background:#1e2a3b; 
            color:#818cf8; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
        }

        /* Empty state */
        .empty-tab { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:52px 24px; gap:12px; color:#94a3b8; text-align:center }

        /* Receipt Style Sidebar Sections */
        .sidebar-section { padding:20px 24px; border-bottom:1px dashed #e2e8f0; }
        .dark .sidebar-section { border-bottom-color:#2d3748; }
        .sidebar-section:last-child { border-bottom:none }

        /* PHYSICAL INVOICE TICKET EFFECT (Radial Notch Cutouts) */
        .w-full.xl\:w-72.2xl\:w-80 > .panel:first-child {
            position: relative;
            overflow: visible !important;
        }
        .w-full.xl\:w-72.2xl\:w-80 > .panel:first-child::before,
        .w-full.xl\:w-72.2xl\:w-80 > .panel:first-child::after {
            content: '';
            position: absolute;
            bottom: 74px; /* Alineado exactamente sobre la línea punteada dashed */
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #f8fafc;
            border: 1px solid rgba(226,232,240,0.8);
            z-index: 10;
        }
        .dark .w-full.xl\:w-72.2xl\:w-80 > .panel:first-child::before,
        .dark .w-full.xl\:w-72.2xl\:w-80 > .panel:first-child::after {
            background: #090d16;
            border-color: #1f2937;
        }
        .w-full.xl\:w-72.2xl\:w-80 > .panel:first-child::before {
            left: -9px;
            box-shadow: inset -3px 0 3px -1px rgba(0,0,0,0.04);
        }
        .w-full.xl\:w-72.2xl\:w-80 > .panel:first-child::after {
            right: -9px;
            box-shadow: inset 3px 0 3px -1px rgba(0,0,0,0.04);
        }

        .info-row { display:flex; justify-content:space-between; align-items:baseline; gap:8px; padding:5px 0; font-size:13px }
        .info-k { color:#64748b; font-weight:700; white-space:nowrap; flex-shrink:0 }
        .dark .info-k { color:#94a3b8 }
        .info-v { color:#1e293b; font-weight:800; text-align:right; min-width:0; word-break:break-all }
        .dark .info-v { color:#cbd5e1 }

        .total-row { display:flex; justify-content:space-between; align-items:center; gap:8px; padding:2px 0 }
        .total-label { font-size:13px; color:#64748b; font-weight:700 }
        .dark .total-label { color:#94a3b8 }
        .total-val { font-size:13px; font-weight:800; color:#334155; font-variant-numeric:tabular-nums }
        .dark .total-val { color:#cbd5e1 }

        /* Mobile line card */
        .line-card { background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:14px }
        .dark .line-card { background:#1f2937; border-color:#374151 }

        /* Premium Actions Buttons */
        .hdr-btn {
            display:inline-flex; 
            align-items:center; 
            gap:6px;
            padding:8px 14px; 
            border-radius:12px; 
            font-size:12px; 
            font-weight:800;
            border:none; 
            cursor:pointer; 
            transition:all 0.2s cubic-bezier(0.4, 0, 0.2, 1); 
            text-decoration:none; 
            white-space:nowrap;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        .hdr-btn:active { transform:scale(.96) }
        
        .hdr-emerald { 
            background:#10b981; 
            color:#fff; 
        }
        .hdr-emerald:hover { 
            background:#059669; 
            box-shadow: 0 4px 10px -1px rgba(16, 185, 129, 0.25);
        }
        .hdr-sky { 
            background:#0ea5e9; 
            color:#fff; 
        }
        .hdr-sky:hover { 
            background:#0284c7; 
            box-shadow: 0 4px 10px -1px rgba(14, 165, 233, 0.25);
        }
        .hdr-violet { 
            background:#8b5cf6; 
            color:#fff; 
        }
        .hdr-violet:hover { 
            background:#7c3aed; 
            box-shadow: 0 4px 10px -1px rgba(139, 92, 246, 0.25);
        }
        .hdr-indigo { 
            background:#6366f1; 
            color:#fff; 
        }
        .hdr-indigo:hover { 
            background:#4f46e5; 
            box-shadow: 0 4px 10px -1px rgba(99, 102, 241, 0.25);
        }
        .hdr-rose { 
            background:#f43f5e; 
            color:#fff; 
        }
        .hdr-rose:hover { 
            background:#e11d48; 
            box-shadow: 0 4px 10px -1px rgba(244, 63, 94, 0.25);
        }
        .hdr-gray { 
            background:#f8fafc; 
            color:#475569; 
            border:1px solid #e2e8f0; 
        }
        .hdr-gray:hover { 
            background:#f1f5f9; 
            color:#1e293b;
        }
        .dark .hdr-gray { 
            background:#1f2937; 
            color:#cbd5e1; 
            border-color:#374151; 
        }
        .dark .hdr-gray:hover { 
            background:#374151; 
            color:#f9fafb;
        }

        /* FUTURISTIC FROSTED GLASS EDIT MODAL */
        .fixed.inset-0.z-50 > div.bg-white, 
        .fixed.inset-0.z-50 > div.bg-white.dark\:bg-gray-900 {
            background: rgba(255, 255, 255, 0.85) !important;
            backdrop-filter: blur(24px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.6) !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
        }
        .dark .fixed.inset-0.z-50 > div.bg-white, 
        .dark .fixed.inset-0.z-50 > div.bg-white.dark\:bg-gray-900 {
            background: rgba(17, 24, 39, 0.82) !important;
            backdrop-filter: blur(24px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6) !important;
        }

        /* Custom Sleek Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 99px;
        }
        .dark ::-webkit-scrollbar-thumb {
            background: #374151;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
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

                                {{-- Total visible en desktop (solo si puede ver valores) --}}
                                @if($canSeeValues)
                                <div class="hidden sm:flex flex-col items-end gap-1 shrink-0">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Total factura</p>
                                    <p class="text-2xl font-black text-gray-900 dark:text-gray-100 tabular-nums">
                                        $ {{ number_format((float) $document->monto_total, 0, ',', '.') }}
                                    </p>
                                    <span class="chip {{ $estadoPago === 'Pagado' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300' }}">
                                        {{ $estadoPago }}
                                    </span>
                                </div>
                                @endif
                            </div>

                            {{-- Chips de estado --}}
                            <div class="flex flex-wrap items-center gap-2 mt-3 pt-3 border-t border-gray-100 dark:border-gray-800/80">
                                <span class="chip bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-300">
                                    {{ strtoupper((string) $workflowStatus) }}
                                </span>
                                <span class="chip {{ $inventoryStatus === 'ingresado' ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' }}">
                                    {{ $inventoryStatus === 'ingresado' ? 'Stock ingresado' : 'Stock pendiente' }}
                                </span>
                                {{-- Vínculo a Orden de Compra / Recepción (aditivo) --}}
                                @php
                                    $vinOc = null;
                                    if (($document->recepcion_id ?? null) || ($document->purchase_order_id ?? null)) {
                                        $fcDb = \Illuminate\Support\Facades\DB::connection('fuelcontrol');
                                        $vinOc = $document->purchase_order_id
                                            ? $fcDb->table('purchase_orders')->where('id', $document->purchase_order_id)->value('order_number')
                                            : null;
                                    }
                                @endphp
                                @if($document->recepcion_id ?? null)
                                    <a href="{{ route('purchase_orders.receptions.show', $document->recepcion_id) }}"
                                       class="chip bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 hover:underline">
                                        Recepción #{{ $document->recepcion_id }}{{ $vinOc ? ' · OC '.$vinOc : '' }}
                                    </a>
                                @elseif($document->purchase_order_id ?? null)
                                    <a href="{{ route('purchase_orders.show', $document->purchase_order_id) }}"
                                       class="chip bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 hover:underline">
                                        OC {{ $vinOc ?? ('#'.$document->purchase_order_id) }}
                                    </a>
                                @endif
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
                    <div class="panel au d2" x-data="dteTabPanel({{ $document->id }})" x-init="init()">
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
                                            @if($canSeeValues)
                                            <th class="text-right">Precio unit.</th>
                                            @endif
                                            <th>Impuesto</th>
                                            @if($canSeeValues)
                                            <th class="text-right pr-5">Importe</th>
                                            @endif
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
                                                @if($canSeeValues)
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
                                                @endif
                                                <td>
                                                    <div class="flex flex-wrap gap-1">
                                                        @foreach($taxLabels as $label)
                                                            <span class="tax-pill {{ $taxClass($label) }}">{{ $label }}</span>
                                                        @endforeach
                                                    </div>
                                                </td>
                                                @if($canSeeValues)
                                                <td class="text-right tabular-nums amt-col pr-5 text-sm">
                                                    $ {{ number_format((float) $l->monto_item, 0, ',', '.') }}
                                                    @if($isDraft)
                                                        <form id="line-form-{{ $l->id }}" method="POST" action="{{ route('gmail.dtes.lines.update', ['id' => $document->id, 'lineId' => $l->id]) }}" class="hidden">
                                                            @csrf
                                                        </form>
                                                    @endif
                                                </td>
                                                @endif
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
                                            @if($canSeeValues)
                                            <p class="text-base font-black text-indigo-600 dark:text-indigo-400 tabular-nums shrink-0">
                                                $ {{ number_format((float) $l->monto_item, 0, ',', '.') }}
                                            </p>
                                            @endif
                                        </div>
                                        <div class="grid grid-cols-2 gap-x-4 gap-y-2 mt-3 text-xs border-t border-gray-100 dark:border-gray-800 pt-3">
                                            <div>
                                                <p class="text-gray-400 uppercase tracking-wide text-[10px] mb-0.5">Cantidad</p>
                                                <p class="font-semibold text-gray-700 dark:text-gray-300 tabular-nums">
                                                    {{ number_format((float) $l->cantidad, 2, ',', '.') }}
                                                    <span class="text-gray-400 ml-0.5 font-normal">{{ $l->unidad ?? '' }}</span>
                                                </p>
                                            </div>
                                            @if($canSeeValues)
                                            <div>
                                                <p class="text-gray-400 uppercase tracking-wide text-[10px] mb-0.5">Precio unit.</p>
                                                <p class="font-semibold text-gray-700 dark:text-gray-300 tabular-nums">$ {{ number_format((float) $l->precio_unitario, 0, ',', '.') }}</p>
                                            </div>
                                            @endif
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
                            @if($canSeeValues)
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
                            @endif
                        </div>

                        {{-- Tab: Apuntes contables --}}
                        <div x-show="tab === 'contable'" x-cloak>

                            {{-- Estado: cargando --}}
                            <div x-show="apuntes.loading" class="flex flex-col items-center justify-center py-16 gap-3">
                                <svg class="animate-spin h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Consultando Odoo…</p>
                            </div>

                            {{-- Estado: error de conexión --}}
                            <div x-show="apuntes.loaded && apuntes.error" class="p-6">
                                <div class="rounded-xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 p-4 flex gap-3 items-start">
                                    <svg class="w-5 h-5 text-rose-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                    </svg>
                                    <div>
                                        <p class="text-sm font-semibold text-rose-700 dark:text-rose-300">Error al conectar con Odoo</p>
                                        <p class="text-xs text-rose-600 dark:text-rose-400 mt-0.5" x-text="apuntes.error"></p>
                                    </div>
                                </div>
                            </div>

                            {{-- Estado: no encontrado en Odoo --}}
                            <div x-show="apuntes.loaded && !apuntes.error && !apuntes.found" class="empty-tab">
                                <div class="w-12 h-12 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M12 7h.01M15 7h.01M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">Sin apuntes contables</p>
                                <p class="text-xs text-gray-400 max-w-xs" x-text="apuntes.message || 'No se encontró esta factura en Odoo.'"></p>
                            </div>

                            {{-- Estado: datos encontrados --}}
                            <div x-show="apuntes.loaded && apuntes.found">

                                {{-- Tabla desktop --}}
                                <div class="hidden lg:block overflow-x-auto">
                                    <table class="dt">
                                        <thead>
                                            <tr>
                                                <th class="w-40">Cuenta</th>
                                                <th>Etiqueta</th>
                                                <th>Dist. analítica</th>
                                                <th class="text-right">Débito</th>
                                                <th class="text-right">Crédito</th>
                                                <th>Impuestos</th>
                                                <th class="w-8"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="(line, i) in apuntes.lines" :key="i">
                                                <tr>
                                                    {{-- Cuenta: código + nombre --}}
                                                    <td class="whitespace-nowrap">
                                                        <p class="font-mono text-xs font-semibold text-indigo-600 dark:text-indigo-400 leading-tight" x-text="line.account_code"></p>
                                                        <p class="text-xs text-gray-600 dark:text-gray-400 leading-tight mt-0.5" x-text="line.account_name_es"></p>
                                                    </td>
                                                    {{-- Etiqueta --}}
                                                    <td class="text-sm text-gray-700 dark:text-gray-300 max-w-[200px] truncate" x-text="line.description !== '—' ? line.description : ''"></td>
                                                    {{-- Distribución analítica --}}
                                                    <td class="max-w-[160px]">
                                                        <template x-if="line.analytic && line.analytic.length">
                                                            <div class="flex flex-wrap gap-1">
                                                                <template x-for="(a, ai) in line.analytic" :key="ai">
                                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[10px] font-medium whitespace-nowrap"
                                                                          :class="analyticBadgeClass(a.plan)">
                                                                        <span x-text="a.name"></span>
                                                                        <span x-show="a.pct < 100" class="opacity-60" x-text="a.pct + '%'"></span>
                                                                    </span>
                                                                </template>
                                                            </div>
                                                        </template>
                                                        <template x-if="!line.analytic || !line.analytic.length">
                                                            <span class="text-gray-300 dark:text-gray-600 text-xs">—</span>
                                                        </template>
                                                    </td>
                                                    {{-- Débito --}}
                                                    <td class="text-right whitespace-nowrap">
                                                        <span x-show="line.debit > 0" class="tabular-nums text-sm font-semibold text-gray-700 dark:text-gray-200"
                                                              x-text="'$ ' + line.debit.toLocaleString('es-CL', {minimumFractionDigits:0})"></span>
                                                        <span x-show="!line.debit" class="text-gray-300 dark:text-gray-600 text-sm">—</span>
                                                    </td>
                                                    {{-- Crédito --}}
                                                    <td class="text-right whitespace-nowrap">
                                                        <span x-show="line.credit > 0" class="tabular-nums text-sm font-semibold text-gray-700 dark:text-gray-200"
                                                              x-text="'$ ' + line.credit.toLocaleString('es-CL', {minimumFractionDigits:0})"></span>
                                                        <span x-show="!line.credit" class="text-gray-300 dark:text-gray-600 text-sm">—</span>
                                                    </td>
                                                    {{-- Impuestos --}}
                                                    <td>
                                                        <template x-if="line.taxes && line.taxes.length">
                                                            <div class="flex flex-wrap gap-1">
                                                                <template x-for="(t, ti) in line.taxes" :key="ti">
                                                                    <span class="inline-flex px-1.5 py-0.5 rounded-md text-[10px] font-medium bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 whitespace-nowrap" x-text="t"></span>
                                                                </template>
                                                            </div>
                                                        </template>
                                                        <template x-if="!line.taxes || !line.taxes.length">
                                                            <span class="text-gray-300 dark:text-gray-600 text-xs">—</span>
                                                        </template>
                                                    </td>
                                                    {{-- Acción editar (solo borrador) --}}
                                                    <td class="pr-3 w-8 text-right">
                                                        <template x-if="apuntes.isDraft">
                                                            <button @click="openEditLine(line)"
                                                                class="text-gray-300 hover:text-indigo-500 dark:text-gray-600 dark:hover:text-indigo-400 transition-colors"
                                                                title="Editar línea">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                                </svg>
                                                            </button>
                                                        </template>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                        <tfoot>
                                            <tr class="border-t-2 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                                <td colspan="3" class="py-2 pl-4 text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Total</td>
                                                <td class="text-right py-2 font-black tabular-nums text-indigo-600 dark:text-indigo-400"
                                                    x-text="'$ ' + apuntes.lines.reduce((s,l) => s+l.debit, 0).toLocaleString('es-CL', {minimumFractionDigits:0})"></td>
                                                <td class="text-right py-2 font-black tabular-nums text-indigo-600 dark:text-indigo-400"
                                                    x-text="'$ ' + apuntes.lines.reduce((s,l) => s+l.credit, 0).toLocaleString('es-CL', {minimumFractionDigits:0})"></td>
                                                <td colspan="2"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                {{-- Tarjetas mobile --}}
                                <div class="lg:hidden space-y-2 p-3">
                                    <template x-for="(line, i) in apuntes.lines" :key="i">
                                        <div class="rounded-xl border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 p-3 space-y-2">
                                            {{-- Cuenta --}}
                                            <div class="flex items-start gap-2">
                                                <span class="font-mono text-xs font-semibold text-indigo-600 dark:text-indigo-400 whitespace-nowrap" x-text="line.account_code"></span>
                                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200 leading-tight" x-text="line.account_name_es"></span>
                                            </div>
                                            {{-- Etiqueta --}}
                                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate" x-show="line.description !== '—'" x-text="line.description"></p>
                                            {{-- Distribución analítica --}}
                                            <div x-show="line.analytic && line.analytic.length" class="flex flex-wrap gap-1">
                                                <template x-for="(a, ai) in line.analytic" :key="ai">
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[10px] font-medium"
                                                          :class="analyticBadgeClass(a.plan)"
                                                          x-text="a.name + (a.pct < 100 ? ' · '+a.pct+'%' : '')"></span>
                                                </template>
                                            </div>
                                            {{-- Impuestos --}}
                                            <div x-show="line.taxes && line.taxes.length" class="flex flex-wrap gap-1">
                                                <template x-for="(t, ti) in line.taxes" :key="ti">
                                                    <span class="inline-flex px-1.5 py-0.5 rounded-md text-[10px] font-medium bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300" x-text="t"></span>
                                                </template>
                                            </div>
                                            {{-- Débito / Crédito --}}
                                            <div class="flex gap-4 pt-0.5">
                                                <div x-show="line.debit > 0" class="flex gap-1.5 items-baseline">
                                                    <span class="text-[11px] uppercase tracking-wide font-semibold text-gray-400">Débito</span>
                                                    <span class="text-sm font-semibold tabular-nums text-gray-700 dark:text-gray-200"
                                                          x-text="'$ ' + line.debit.toLocaleString('es-CL', {minimumFractionDigits:0})"></span>
                                                </div>
                                                <div x-show="line.credit > 0" class="flex gap-1.5 items-baseline">
                                                    <span class="text-[11px] uppercase tracking-wide font-semibold text-gray-400">Crédito</span>
                                                    <span class="text-sm font-semibold tabular-nums text-gray-700 dark:text-gray-200"
                                                          x-text="'$ ' + line.credit.toLocaleString('es-CL', {minimumFractionDigits:0})"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                    {{-- Total mobile --}}
                                    <div class="rounded-xl bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800 p-3 flex justify-between items-center">
                                        <span class="text-xs font-black uppercase tracking-wide text-gray-500 dark:text-gray-400">Total</span>
                                        <div class="flex gap-5">
                                            <div class="flex gap-1.5 items-baseline">
                                                <span class="text-[11px] font-semibold uppercase text-gray-400">Débito</span>
                                                <span class="text-sm font-black tabular-nums text-indigo-600 dark:text-indigo-400"
                                                      x-text="'$ ' + apuntes.lines.reduce((s,l) => s+l.debit, 0).toLocaleString('es-CL', {minimumFractionDigits:0})"></span>
                                            </div>
                                            <div class="flex gap-1.5 items-baseline">
                                                <span class="text-[11px] font-semibold uppercase text-gray-400">Crédito</span>
                                                <span class="text-sm font-black tabular-nums text-indigo-600 dark:text-indigo-400"
                                                      x-text="'$ ' + apuntes.lines.reduce((s,l) => s+l.credit, 0).toLocaleString('es-CL', {minimumFractionDigits:0})"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>{{-- /found --}}

                        </div>{{-- /tab contable --}}

                        {{-- Modal: editar apunte contable — teleportado al <body> para escapar del transform del panel --}}
                        <template x-teleport="body">
                        <div x-show="editModal.open" x-cloak
                             class="fixed inset-0 z-50 flex items-center justify-center p-4"
                             @keydown.escape.window="editModal.open = false">

                            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="editModal.open = false"></div>

                            {{-- Panel del modal — ocupa toda la altura disponible para evitar scroll externo --}}
                            <div class="relative z-10 w-full max-w-xl flex flex-col bg-white dark:bg-gray-900 rounded-2xl shadow-2xl"
                                 style="max-height: 90vh"
                                 @click.stop>

                                {{-- Header fijo --}}
                                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-800 shrink-0">
                                    <div>
                                        <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100">Editar apunte contable</h3>
                                        <p class="text-xs text-gray-400 mt-0.5" x-text="editModal.line ? editModal.line.account_code + ' · ' + editModal.line.account_name_es : ''"></p>
                                    </div>
                                    <button @click="editModal.open = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>

                                {{-- Contenido scrollable —— sin dropdowns flotantes adentro --}}
                                <div class="flex-1 overflow-y-auto px-5 py-5 space-y-6">

                                    {{-- ① Cuenta contable --}}
                                    <div>
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Cuenta contable</label>

                                        {{-- Buscador --}}
                                        <input type="text" x-model="editModal.accountSearch"
                                               @input="editModal.accountPicked = null"
                                               placeholder="Buscar por código o nombre…"
                                               class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"/>

                                        {{-- Lista inline — no flotante --}}
                                        <div x-show="editModal.accountSearch && !editModal.accountPicked"
                                             class="mt-1 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                            <div class="max-h-44 overflow-y-auto divide-y divide-gray-50 dark:divide-gray-800">
                                                <template x-for="acc in filteredAccounts()" :key="acc.odoo_id">
                                                    <div @click="pickAccount(acc)"
                                                         class="flex items-center gap-2 px-3 py-2 cursor-pointer hover:bg-indigo-50 dark:hover:bg-indigo-900/30">
                                                        <span class="font-mono text-xs font-semibold text-indigo-600 dark:text-indigo-400 shrink-0" x-text="acc.code"></span>
                                                        <span class="text-sm text-gray-700 dark:text-gray-300 truncate" x-text="acc.label.replace(acc.code + ' ', '')"></span>
                                                    </div>
                                                </template>
                                                <div x-show="filteredAccounts().length === 0" class="px-3 py-2 text-sm text-gray-400 italic">Sin resultados</div>
                                            </div>
                                        </div>

                                        {{-- Seleccionado --}}
                                        <div x-show="editModal.accountPicked"
                                             class="mt-2 flex items-center gap-2 px-3 py-2 rounded-lg bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800">
                                            <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            <span class="text-sm text-indigo-700 dark:text-indigo-300 truncate" x-text="editModal.accountPicked?.label ?? ''"></span>
                                        </div>
                                    </div>

                                    {{-- ② Distribución analítica --}}
                                    <div>
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Distribución analítica</label>

                                        {{-- Chips de cuentas ya seleccionadas + campo % --}}
                                        <div x-show="editModal.analyticRows.length > 0" class="space-y-1.5 mb-3">
                                            <template x-for="(row, idx) in editModal.analyticRows" :key="row._key">
                                                <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700">
                                                    {{-- Dot color por plan --}}
                                                    <span class="w-2 h-2 rounded-full shrink-0"
                                                          :class="{
                                                            'bg-sky-500':    (row.plan||'').toLowerCase().includes('project') || (row.plan||'').toLowerCase().includes('proyecto'),
                                                            'bg-violet-500': (row.plan||'').toLowerCase().includes('centros'),
                                                            'bg-orange-500': (row.plan||'').toLowerCase().includes('maquinaria'),
                                                            'bg-emerald-500':(row.plan||'').toLowerCase().includes('plantación') || (row.plan||'').toLowerCase().includes('plantacion'),
                                                            'bg-teal-500':   (row.plan||'').toLowerCase().includes('operacional') || (row.plan||'').toLowerCase().includes('cosecha'),
                                                            'bg-yellow-500': (row.plan||'').toLowerCase().includes('construc'),
                                                            'bg-gray-400':   true,
                                                          }"></span>
                                                    {{-- Nombre + plan --}}
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate" x-text="row.name"></p>
                                                        <p class="text-[11px] text-gray-400 truncate" x-text="row.plan"></p>
                                                    </div>
                                                    {{-- % --}}
                                                    <div class="flex items-center gap-1 shrink-0">
                                                        <input type="number" x-model="row.pct" min="0" max="100"
                                                               class="w-16 rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-2 py-1 text-sm text-right focus:outline-none focus:ring-2 focus:ring-violet-400"/>
                                                        <span class="text-xs text-gray-400">%</span>
                                                    </div>
                                                    {{-- Quitar --}}
                                                    <button @click="removeAnalyticRow(idx)" type="button"
                                                            class="text-gray-300 hover:text-rose-500 dark:text-gray-600 dark:hover:text-rose-400 transition-colors shrink-0">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                    </button>
                                                </div>
                                            </template>
                                        </div>

                                        {{-- Buscador para agregar cuentas analíticas --}}
                                        <div class="border border-dashed border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                                            <div class="px-3 pt-3 pb-2">
                                                <input type="text" x-model="editModal.analyticSearch"
                                                       placeholder="Buscar y agregar cuenta analítica…"
                                                       class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400"/>
                                            </div>
                                            {{-- Lista inline sin flotante --}}
                                            <div class="max-h-44 overflow-y-auto divide-y divide-gray-50 dark:divide-gray-800/50">
                                                <template x-for="acc in filteredAnalytic()" :key="acc.odoo_id">
                                                    <div @click="addAnalytic(acc)"
                                                         class="flex items-center gap-2 px-3 py-2 cursor-pointer hover:bg-violet-50 dark:hover:bg-violet-900/20">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-violet-400 shrink-0"></span>
                                                        <div class="flex-1 min-w-0">
                                                            <span class="text-sm text-gray-700 dark:text-gray-300" x-text="acc.name"></span>
                                                            <span class="text-[11px] text-gray-400 ml-1.5" x-text="acc.plan_name"></span>
                                                        </div>
                                                        <svg class="w-4 h-4 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                    </div>
                                                </template>
                                                <div x-show="filteredAnalytic().length === 0 && editModal.catalogLoaded"
                                                     class="px-3 py-2 text-sm text-gray-400 italic">Sin resultados</div>
                                                <div x-show="!editModal.catalogLoaded"
                                                     class="px-3 py-2 text-sm text-gray-400">Cargando catálogo…</div>
                                            </div>
                                        </div>
                                    </div>

                                </div>{{-- /contenido --}}

                                {{-- Footer fijo --}}
                                <div class="flex justify-end gap-2 px-5 py-4 border-t border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50 shrink-0 rounded-b-2xl">
                                    <button @click="editModal.open = false" type="button"
                                            class="px-4 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                        Cancelar
                                    </button>
                                    <button @click="saveEditLine()" type="button"
                                            :disabled="editModal.saving"
                                            class="px-4 py-2 text-sm font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition-colors disabled:opacity-50 flex items-center gap-2">
                                        <svg x-show="editModal.saving" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                        </svg>
                                        <span x-text="editModal.saving ? 'Guardando…' : 'Guardar'"></span>
                                    </button>
                                </div>

                            </div>{{-- /panel --}}
                        </div>{{-- /overlay --}}
                        </template>{{-- /x-teleport --}}

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

                    {{-- Resumen de pago (solo admin) --}}
                    @if($canSeeValues)
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
                    @endif

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

    <div x-data="stockMatcherModal()" x-cloak
         x-show="open"
         x-on:stock-matcher-open.window="openWith($event.detail)"
         class="fixed inset-0 z-[210] flex items-end sm:items-center justify-center p-4"
         style="background:rgba(15,23,42,.55);"
         @click.self="close()">

        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl border border-gray-100 dark:border-gray-800 w-full max-w-4xl max-h-[92vh] overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                <div>
                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100">Resolver productos no reconocidos</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Selecciona el producto correcto para cada línea y se aprenderá para próximos ingresos.</p>
                </div>
                <button type="button" @click="close()"
                    class="w-7 h-7 flex items-center justify-center rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-500 text-xl leading-none transition">&times;</button>
            </div>

            <div class="p-4 space-y-3 overflow-y-auto max-h-[calc(92vh-150px)]">
                <template x-for="(row, idx) in rows" :key="row.line_id">
                    <div class="rounded-xl border p-3 space-y-2 transition-colors"
                         :class="row.skipped
                             ? 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40 opacity-60'
                             : 'border-gray-200 dark:border-gray-700'">

                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-xs text-gray-400">Línea #<span x-text="row.line_id"></span></p>
                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 leading-snug" x-text="row.descripcion"></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    <span x-text="'Unidad: ' + (row.unidad || 'UN')"></span>
                                    <span class="mx-1">·</span>
                                    <span x-text="'Cantidad: ' + row.cantidad"></span>
                                </p>
                            </div>
                            <button type="button" @click="toggleSkip(row)"
                                class="shrink-0 flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold transition-colors"
                                :class="row.skipped
                                    ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 hover:bg-amber-200'
                                    : 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700'">
                                <template x-if="row.skipped">
                                    <span>↩ Incluir</span>
                                </template>
                                <template x-if="!row.skipped">
                                    <span>Saltar</span>
                                </template>
                            </button>
                        </div>

                        <div class="relative" x-show="!row.skipped">
                            <input type="text"
                                class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm px-3 py-2 focus:outline-none focus:border-violet-400"
                                placeholder="Buscar producto en inventario..."
                                x-model="row.search"
                                @focus="onFocusRow(row)"
                                @input="onSearch(row)"
                                @click="onFocusRow(row)"
                                @blur="onBlurRow(row)">
                            <div class="absolute left-0 right-0 mt-1 rounded-lg border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-lg max-h-56 overflow-y-auto z-40"
                                x-show="row.showOptions && (row.loading || row.options.length > 0)">
                                <p class="px-3 py-2 text-[11px] text-gray-400" x-show="row.loading">Buscando productos...</p>
                                <template x-for="opt in row.options" :key="opt.id">
                                    <button type="button"
                                        class="w-full text-left px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800/70 text-sm"
                                        @click="selectOption(row, opt)">
                                        <span class="font-medium text-gray-800 dark:text-gray-200" x-text="opt.nombre"></span>
                                        <span class="text-xs text-gray-400" x-text="' · ' + (opt.codigo || 'Sin código') + ' · ' + (opt.unidad || 'UN')"></span>
                                    </button>
                                </template>
                            </div>

                            {{-- Sin coincidencias --}}
                            <div x-show="!row.loading && row.search.trim().length >= 2 && row.options.length === 0"
                                 class="mt-1.5 flex items-center justify-between gap-2 px-1">
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    No existe en inventario.
                                </p>
                                <div class="flex items-center gap-2 shrink-0">
                                    <button type="button" @click="quickAdd(row)"
                                        :disabled="row.createLoading"
                                        class="flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300 hover:bg-violet-200 disabled:opacity-50 transition">
                                        <span x-text="row.createLoading ? 'Agregando...' : '+ Agregar al inventario'"></span>
                                    </button>
                                    <button type="button" @click="toggleSkip(row)"
                                        class="text-[11px] font-semibold text-gray-400 hover:text-amber-600 dark:hover:text-amber-400 transition">
                                        Saltar
                                    </button>
                                </div>
                            </div>
                            <p x-show="row.createError" x-text="row.createError"
                               class="mt-1 text-xs text-red-500 dark:text-red-400 px-1"></p>

                            <p class="text-[11px] text-gray-400 mt-1" x-show="!row.search.trim()">
                                Mostrando 5 sugerencias. Escribe para buscar más.
                            </p>
                            <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-1 font-medium" x-show="row.product_id && !row.skipped">
                                ✓ <span x-text="row.selected_label"></span>
                            </p>
                        </div>

                        {{-- Mensaje cuando está saltada --}}
                        <p x-show="row.skipped"
                           class="text-[11px] text-amber-600 dark:text-amber-400">
                            Esta línea no se ingresará al inventario.
                        </p>
                    </div>
                </template>
            </div>

            <div class="px-5 py-4 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between gap-2">
                <p class="text-[11px] text-gray-400" x-show="rows.some(r => r.skipped)">
                    <span x-text="rows.filter(r => r.skipped).length"></span> línea(s) se saltarán.
                </p>
                <div class="flex gap-2 ml-auto">
                    <button type="button" @click="close()"
                        class="px-4 py-2 text-xs font-semibold rounded-xl border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                        Cancelar
                    </button>
                    <button type="button" @click="submit()"
                        :disabled="!canSubmit || submitting"
                        class="px-4 py-2 text-xs font-bold rounded-xl text-white bg-violet-600 hover:bg-violet-700 disabled:opacity-50 transition">
                        <span x-text="submitting ? 'Guardando...' : 'Agregar stock y aprender'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div x-data="{
            show: false,
            title: '',
            message: '',
            confirmLabel: 'Confirmar',
            type: 'danger',
            _cb: null,
            selectLabel: '',
            selectOptions: [],
            selectedValue: null,
        }"
        x-on:confirm-dialog.window="
            title         = $event.detail.title         ?? '¿Estás seguro?';
            message       = $event.detail.message       ?? '';
            confirmLabel  = $event.detail.confirmLabel  ?? 'Confirmar';
            type          = $event.detail.type          ?? 'danger';
            _cb           = $event.detail.callback      ?? null;
            selectLabel   = $event.detail.selectLabel   ?? '';
            selectOptions = $event.detail.selectOptions ?? [];
            selectedValue = $event.detail.selectDefault ?? (selectOptions.length > 0 ? selectOptions[0].value : null);
            show          = true;
        "
        x-show="show" x-cloak
        @keydown.escape.window="show = false"
        class="fixed inset-0 z-[200] flex items-end sm:items-center justify-center p-4"
        style="background:rgba(15,23,42,.55);"
        @click.self="show = false">

        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl border border-gray-100 dark:border-gray-800 w-full max-w-sm"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-4">

            <div class="p-5">
                <div class="flex items-start gap-3">
                    <div class="shrink-0 w-10 h-10 rounded-full flex items-center justify-center"
                         :class="{
                             'bg-rose-100 dark:bg-rose-900/30': type === 'danger',
                             'bg-blue-100 dark:bg-blue-900/30': type === 'confirm',
                             'bg-amber-100 dark:bg-amber-900/30': type === 'warning',
                         }">
                        <template x-if="type === 'danger'">
                            <svg class="w-5 h-5 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </template>
                        <template x-if="type === 'confirm'">
                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </template>
                        <template x-if="type === 'warning'">
                            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </template>
                    </div>
                    <div class="min-w-0 flex-1 pt-0.5">
                        <p class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-snug" x-text="title"></p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 leading-relaxed" x-text="message" x-show="message"></p>
                    </div>
                </div>

                {{-- Select opcional dentro del modal --}}
                <template x-if="selectOptions.length > 0">
                    <div class="mt-4">
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5" x-text="selectLabel"></label>
                        <select x-model="selectedValue"
                            class="w-full text-sm font-medium rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-400 transition cursor-pointer">
                            <template x-for="opt in selectOptions" :key="opt.value">
                                <option :value="opt.value" x-text="opt.label"></option>
                            </template>
                        </select>
                    </div>
                </template>
            </div>

            <div class="px-5 pb-5 flex gap-2 justify-end">
                <button type="button" @click="show = false"
                    class="px-4 py-2 text-xs font-semibold rounded-xl border border-gray-200 dark:border-gray-700
                           text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                    Cancelar
                </button>
                <button type="button"
                    @click="show = false; if (_cb) _cb(selectedValue)"
                    class="px-4 py-2 text-xs font-bold rounded-xl text-white transition"
                    :class="{
                        'bg-rose-600 hover:bg-rose-700': type === 'danger',
                        'bg-blue-600 hover:bg-blue-700': type === 'confirm',
                        'bg-amber-600 hover:bg-amber-700': type === 'warning',
                    }"
                    x-text="confirmLabel">
                </button>
            </div>
        </div>
    </div>

    <script>
    // Bodegas disponibles para el ingreso de stock (generado server-side)
    window.__stockBodegas = @json($bodegas->map(fn($b) => ['value' => $b->id, 'label' => $b->nombre]));
    window.__stockBodegaDefault = {{ $bodegas->firstWhere('es_principal', true)->id ?? $bodegas->first()?->id ?? 'null' }};
    </script>
    <script>
    /**
     * Color de badge según el plan analítico al que pertenece la cuenta.
     * Tailwind necesita ver los strings completos en el template para no purgarlos.
     * Colores definidos:
     *   sky      → Proyecto / Project
     *   violet   → Centros de costo
     *   orange   → Maquinaria
     *   emerald  → Plantación
     *   teal     → Operacional / Costo cosecha
     *   yellow   → Construcción
     *   gray     → resto
     */
    function analyticBadgeClass(plan) {
        const p = (plan || '').toLowerCase();
        if (p.includes('project') || p.includes('proyecto'))
            return 'bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300 ring-1 ring-inset ring-sky-200 dark:ring-sky-800';
        if (p.includes('centros de costo') || p.includes('centro de costo'))
            return 'bg-violet-50 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300 ring-1 ring-inset ring-violet-200 dark:ring-violet-800';
        if (p.includes('maquinaria') || p.includes('maquinas'))
            return 'bg-orange-50 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300 ring-1 ring-inset ring-orange-200 dark:ring-orange-800';
        if (p.includes('plantación') || p.includes('plantacion'))
            return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 ring-1 ring-inset ring-emerald-200 dark:ring-emerald-800';
        if (p.includes('construc'))
            return 'bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300 ring-1 ring-inset ring-yellow-200 dark:ring-yellow-800';
        if (p.includes('cosecha') || p.includes('operacional') || p.includes('licencias') || p.includes('sistemas'))
            return 'bg-teal-50 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300 ring-1 ring-inset ring-teal-200 dark:ring-teal-800';
        return 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300 ring-1 ring-inset ring-gray-200 dark:ring-gray-700';
    }

    /**
     * Componente principal del panel de tabs del DTE.
     * Extiende la gestión de tabs para incluir la carga lazy de apuntes contables.
     */
    function dteTabPanel(docId) {
        return {
            tab: 'lineas',
            apuntes: {
                loaded:  false,
                loading: false,
                error:   null,
                found:   false,
                message: null,
                move:    null,
                lines:   [],
            },

            init() {
                // Carga apuntes la primera vez que el usuario cambia al tab contable
                this.$watch('tab', (val) => {
                    if (val === 'contable' && !this.apuntes.loaded && !this.apuntes.loading) {
                        this._loadApuntes();
                    }
                });
            },

            _loadApuntes() {
                this.apuntes.loading = true;
                this.apuntes.error   = null;

                fetch(`/gmail/dtes/${docId}/apuntes`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.error || (!data.found && data.message && data.message.startsWith('Error'))) {
                        this.apuntes.error = data.message || data.error || 'Error desconocido';
                    } else {
                        this.apuntes.found   = data.found   ?? false;
                        this.apuntes.move    = data.move    ?? null;
                        this.apuntes.lines   = data.lines   ?? [];
                        this.apuntes.message = data.message ?? null;
                        this.apuntes.isDraft = data.is_draft ?? false;
                    }
                    this.apuntes.loaded  = true;
                    this.apuntes.loading = false;
                })
                .catch(err => {
                    this.apuntes.error   = err.message || 'Error de red';
                    this.apuntes.loaded  = true;
                    this.apuntes.loading = false;
                });
            },

            // ── Modal edición de apunte ──────────────────────────────────────────
            editModal: {
                open:           false,
                saving:         false,
                line:           null,
                accountSearch:  '',
                accountPicked:  null,
                analyticSearch: '',
                analyticRows:   [],     // [{_key, accId, name, plan, pct}]
                catalogLoaded:  false,
                accounts:       [],
                analyticAccts:  [],
            },

            openEditLine(line) {
                const em = this.editModal;
                em.line           = line;
                em.accountSearch  = line.account_code + ' ' + line.account_name_es;
                em.accountPicked  = { odoo_id: line.account_odoo_id, code: line.account_code, label: em.accountSearch };
                em.analyticSearch = '';
                em.analyticRows   = (line.analytic || []).map((a, i) => ({
                    _key: i, accId: a.id, name: a.name, plan: a.plan, pct: a.pct
                }));
                em.saving = false;
                em.open   = true;

                if (! em.catalogLoaded) {
                    fetch('/gmail/dtes/apuntes/catalogo', { headers: { 'Accept': 'application/json' } })
                        .then(r => r.json())
                        .then(d => {
                            em.accounts      = d.accounts  || [];
                            em.analyticAccts = d.analytic  || [];
                            em.catalogLoaded = true;
                        });
                }
            },

            filteredAccounts() {
                const q = this.editModal.accountSearch.toLowerCase();
                if (! q) return this.editModal.accounts.slice(0, 40);
                return this.editModal.accounts.filter(a => a.label.toLowerCase().includes(q)).slice(0, 40);
            },

            pickAccount(acc) {
                this.editModal.accountPicked = acc;
                this.editModal.accountSearch = acc.label;
            },

            // Analítica: lista filtrada excluyendo los ya seleccionados
            filteredAnalytic() {
                const q    = this.editModal.analyticSearch.toLowerCase();
                const used = new Set(this.editModal.analyticRows.map(r => r.accId));
                return this.editModal.analyticAccts
                    .filter(a => ! used.has(a.odoo_id))
                    .filter(a => ! q || a.name.toLowerCase().includes(q) || (a.plan_name || '').toLowerCase().includes(q))
                    .slice(0, 50);
            },

            addAnalytic(acc) {
                this.editModal.analyticRows.push({
                    _key: Date.now(), accId: acc.odoo_id, name: acc.name, plan: acc.plan_name, pct: 100
                });
                this.editModal.analyticSearch = '';
            },

            removeAnalyticRow(idx) {
                this.editModal.analyticRows.splice(idx, 1);
            },

            async saveEditLine() {
                const em = this.editModal;
                em.saving = true;

                const dist = {};
                for (const row of em.analyticRows) {
                    if (row.accId) dist[String(row.accId)] = Number(row.pct);
                }

                try {
                    const res = await fetch(`/gmail/dtes/${docId}/apuntes/${em.line.line_id}`, {
                        method:  'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept':       'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                            'X-HTTP-Method-Override': 'PATCH',
                        },
                        body: JSON.stringify({
                            account_odoo_id:       em.accountPicked?.odoo_id ?? null,
                            analytic_distribution: Object.keys(dist).length ? dist : null,
                        }),
                    });
                    const data = await res.json();
                    if (data.ok) {
                        const idx = this.apuntes.lines.findIndex(l => l.line_id === em.line.line_id);
                        if (idx >= 0) {
                            const l = this.apuntes.lines[idx];
                            if (em.accountPicked) {
                                l.account_odoo_id = em.accountPicked.odoo_id;
                                l.account_code    = em.accountPicked.code;
                                l.account_name_es = em.accountPicked.label.replace(em.accountPicked.code + ' ', '');
                            }
                            l.analytic = em.analyticRows
                                .filter(r => r.accId)
                                .map(r => ({ id: r.accId, name: r.name, plan: r.plan, pct: Number(r.pct) }));
                        }
                        em.open = false;
                    } else {
                        alert(data.error || 'Error al guardar.');
                    }
                } catch (e) {
                    alert('Error de red: ' + e.message);
                } finally {
                    em.saving = false;
                }
            },
        };
    }

    function openConfirm(options) {
        window.dispatchEvent(new CustomEvent('confirm-dialog', { detail: options }));
    }

    async function startStockAddReview(reviewUrl, productsUrl, submitFormId, bodegaId) {
        try {
            const response = await fetch(reviewUrl, { headers: { 'Accept': 'application/json' } });
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error('No se pudo revisar la coincidencia de productos.');
            }

            if (data.already_posted) {
                window.location.reload();
                return;
            }

            if (!Array.isArray(data.unresolved) || data.unresolved.length === 0) {
                // Ingresar directo: bodega_id ya está en el <select> dentro del form
                document.getElementById(submitFormId)?.submit();
                return;
            }

            // Hay líneas sin resolver → abrir modal de mapeo pasando la bodega seleccionada
            window.dispatchEvent(new CustomEvent('stock-matcher-open', {
                detail: {
                    rows: data.unresolved,
                    productsUrl: productsUrl,
                    submitUrl: '{{ route('gmail.dtes.add_stock_mapping', $document->id) }}',
                    createProductUrl: '{{ route('gmail.inventory.api.products.create') }}',
                    csrf: '{{ csrf_token() }}',
                    bodegaId: bodegaId ? Number(bodegaId) : null,
                }
            }));
        } catch (error) {
            window.dispatchEvent(new CustomEvent('show-toast', {
                detail: { msg: error?.message || 'Error revisando productos.', type: 'error' }
            }));
        }
    }

    function stockMatcherModal() {
        return {
            open: false,
            rows: [],
            productsUrl: '',
            createProductUrl: '',
            submitUrl: '',
            csrf: '',
            bodegaId: null,
            allProducts: [],
            submitting: false,

            get canSubmit() {
                // Válido si al menos una fila está resuelta y todas las no-saltadas tienen product_id
                const active = this.rows.filter(r => !r.skipped);
                const resolved = this.rows.filter(r => !r.skipped && Number(r.product_id) > 0);
                return this.rows.length > 0 && active.every(r => Number(r.product_id) > 0) && resolved.length > 0;
            },

            async openWith(detail) {
                this.rows = (detail?.rows || []).map(r => ({
                    ...r,
                    search: '',
                    options: [],
                    product_id: null,
                    selected_label: '',
                    loading: false,
                    timer: null,
                    searchReqId: 0,
                    showOptions: false,
                    skipped: false,
                    createLoading: false,
                    createError: '',
                }));
                this.productsUrl = detail?.productsUrl || '';
                this.createProductUrl = detail?.createProductUrl || '';
                this.submitUrl = detail?.submitUrl || '';
                this.csrf = detail?.csrf || '';
                this.bodegaId = detail?.bodegaId || null;
                this.open = true;

                try {
                    const r = await fetch(this.productsUrl + '?limit=1000', { headers: { 'Accept': 'application/json' } });
                    this.allProducts = await r.json();
                    this.rows.forEach(row => {
                        row.options = this.allProducts.slice(0, 25);
                    });
                } catch (_) {
                    this.allProducts = [];
                }
            },

            close() {
                this.open = false;
                this.rows = [];
                this.submitting = false;
                this.bodegaId = null;
            },

            onSearch(row) {
                if (row.timer) {
                    clearTimeout(row.timer);
                }

                row.timer = setTimeout(async () => {
                    const q = (row.search || '').trim();
                    row.showOptions = true;
                    if (!q) {
                        row.loading = false;
                        row.options = this.allProducts.slice(0, 5);
                        return;
                    }

                    if (q.length < 2) {
                        const qLower = q.toLowerCase();
                        row.loading = false;
                        row.options = this.allProducts
                            .filter(p => ((p.nombre || '').toLowerCase().includes(qLower) || (p.codigo || '').toLowerCase().includes(qLower)))
                            .slice(0, 5);
                        return;
                    }

                    row.loading = true;
                    const reqId = ++row.searchReqId;
                    try {
                        const response = await fetch(this.productsUrl + '?q=' + encodeURIComponent(q) + '&limit=120', {
                            headers: { 'Accept': 'application/json' }
                        });
                        const data = await response.json();
                        if (reqId !== row.searchReqId) return;
                        row.options = Array.isArray(data) ? data : [];
                    } catch (_) {
                        if (reqId !== row.searchReqId) return;
                        row.options = [];
                    } finally {
                        if (reqId === row.searchReqId) row.loading = false;
                    }
                }, 220);
            },

            onFocusRow(row) {
                row.showOptions = true;
                if ((row.search || '').trim() === '') {
                    row.options = this.allProducts.slice(0, 5);
                } else {
                    this.onSearch(row);
                }
            },

            onBlurRow(row) {
                setTimeout(() => {
                    row.showOptions = false;
                }, 150);
            },

            selectOption(row, opt) {
                row.product_id = Number(opt.id);
                row.selected_label = `${opt.nombre} (${opt.codigo || 'Sin código'})`;
                row.search = opt.nombre || '';
                row.options = [];
                row.showOptions = false;
                row.skipped = false;
            },

            toggleSkip(row) {
                row.skipped = !row.skipped;
                if (row.skipped) {
                    row.product_id = null;
                    row.selected_label = '';
                    row.search = '';
                    row.showOptions = false;
                }
            },

            async quickAdd(row) {
                if (row.createLoading) return;
                row.createLoading = true;
                row.createError = '';
                try {
                    const res = await fetch(this.createProductUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                        },
                        body: JSON.stringify({
                            nombre: row.descripcion || row.search.trim(),
                            codigo: row.codigo || null,
                            unidad: row.unidad || 'UN',
                        }),
                    });
                    const data = await res.json();
                    if (!res.ok) {
                        const firstError = data?.errors ? Object.values(data.errors)[0]?.[0] : null;
                        row.createError = firstError || 'No se pudo agregar el producto.';
                        return;
                    }
                    this.allProducts.push(data);
                    this.selectOption(row, data);
                } catch (_) {
                    row.createError = 'Error de conexión. Intenta de nuevo.';
                } finally {
                    row.createLoading = false;
                }
            },

            submit() {
                if (!this.canSubmit || this.submitting) return;
                this.submitting = true;

                const activeRows = this.rows.filter(r => !r.skipped);
                const skippedRows = this.rows.filter(r => r.skipped);

                const form = document.createElement('form');
                form.method = 'POST';
                form.action = this.submitUrl;

                const csrf = document.createElement('input');
                csrf.type = 'hidden';
                csrf.name = '_token';
                csrf.value = this.csrf;
                form.appendChild(csrf);

                activeRows.forEach((r, idx) => {
                    const lineInput = document.createElement('input');
                    lineInput.type = 'hidden';
                    lineInput.name = `mappings[${idx}][line_id]`;
                    lineInput.value = String(r.line_id);
                    form.appendChild(lineInput);

                    const productInput = document.createElement('input');
                    productInput.type = 'hidden';
                    productInput.name = `mappings[${idx}][product_id]`;
                    productInput.value = String(r.product_id);
                    form.appendChild(productInput);
                });

                // Enviar IDs de líneas saltadas para que el servidor las omita completamente
                skippedRows.forEach(r => {
                    const skipInput = document.createElement('input');
                    skipInput.type = 'hidden';
                    skipInput.name = 'skipped_lines[]';
                    skipInput.value = String(r.line_id);
                    form.appendChild(skipInput);
                });

                // Incluir bodega seleccionada
                if (this.bodegaId) {
                    const bodegaInput = document.createElement('input');
                    bodegaInput.type = 'hidden';
                    bodegaInput.name = 'bodega_id';
                    bodegaInput.value = String(this.bodegaId);
                    form.appendChild(bodegaInput);
                }

                document.body.appendChild(form);
                form.submit();
            },
        };
    }
    </script>
</x-app-layout>
