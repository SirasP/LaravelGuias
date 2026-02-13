@php
    /* ══════════════════════════════════════════════════════
       SETUP — se ejecuta antes del layout para que $tplColor
       etc. estén disponibles en el slot "header"
    ══════════════════════════════════════════════════════ */
    $tpl = trim((string) $import->template);

    $isQC = $tpl === 'QC';
    $isMP = $tpl === 'MP';
    $isVT = $tpl === 'VT';
    $isRFP = $tpl === 'RFP';
    $isSANCO = $tpl === 'SANCO';
    $isLIQ = $tpl === 'LIQ_COMPUAGRO';
    $isGRR = $tpl === 'GUIA_RECEPCION_RESUMEN';
    $isXML = $tpl === 'XML_SII_46';

    // Meta
    $meta = is_string($import->meta)
        ? json_decode($import->meta, true)
        : ($import->meta ?? []);
    $meta = is_array($meta) ? $meta : [];

    // ── QC / MP ──────────────────────────────────────────
    $qcBandejas = $meta['bandejas'] ?? [];
    $qcTotal = $meta['total_bandejas'] ?? null;
    $qcKgs = $meta['kgs_recibido'] ?? null;
    $mpKgs = $meta['kgs_recibido'] ?? null;
    $mpBandejas = $meta['bandejas'] ?? [];

    // ── SANCO ─────────────────────────────────────────────
    $destino = $meta['destino'] ?? null;
    $especie = $meta['especie'] ?? null;
    $variedad = $meta['variedad'] ?? null;
    $totalCant = $meta['total']['cantidad'] ?? null;
    $totalKgs = $meta['total']['kgs'] ?? null;
    $detalles = $meta['detalles'] ?? [];

    // ── LIQ_COMPUAGRO ─────────────────────────────────────
    $liqDoc = $meta['documento'] ?? [];
    $liqRec = $meta['recepcion'] ?? [];

    // ── GUIA_RECEPCION_RESUMEN ────────────────────────────
    $grrCajas = $meta['total_cajas'] ?? null;
    $grrKgs = $meta['recepcion']['total_kgs'] ?? null;
    $grrGuiaProd = $meta['guia_productor'] ?? null;

    // ── VT ────────────────────────────────────────────────
    $vtKgs = $meta['kgs_recibido'] ?? null;
    $vtUnidad = $meta['unidad'] ?? 'Kg';

    // ── Líneas del visor ──────────────────────────────────
    $linesArr = $import->lines->map(function ($l) use ($isXML) {
        $text = $l->content;
        if ($isXML && str_contains($text, ':')) {
            [$left, $right] = explode(':', $text, 2);
            if (str_contains($left, '/')) {
                $text = trim(last(explode('/', $left))) . ': ' . trim($right);
            }
        }
        return ['no' => $l->line_no, 'text' => $text];
    })->values();

    // ── Badge colour ──────────────────────────────────────
    $tplColor = match ($tpl) {
        'QC' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        'MP' => 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        'SANCO' => 'bg-violet-50 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
        'RFP' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
        'VT' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        'LIQ_COMPUAGRO' => 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
        'GUIA_RECEPCION_RESUMEN' => 'bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400',
        'XML_SII_46' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
        default => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
    };
@endphp

<x-app-layout>

{{-- ═══════════════════════════════════════════════════
     HEADER
═══════════════════════════════════════════════════ --}}
<x-slot name="header">
    <div class="flex items-center justify-between w-full gap-4 min-w-0">

        {{-- Breadcrumb + título --}}
        <div class="flex items-center gap-2.5 min-w-0">
            <a href="{{ route('pdf.index') }}"
               class="hidden sm:inline-flex items-center gap-1 text-xs text-gray-400 hover:text-gray-700
                      dark:hover:text-gray-200 transition shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                DTE / Facturas
            </a>
            <span class="hidden sm:block text-gray-200 dark:text-gray-700 text-sm">/</span>

            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold {{ $tplColor }} shrink-0">
                        {{ $tpl ?: '—' }}
                    </span>
                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate max-w-xs sm:max-w-md">
                        {{ $import->original_name }}
                    </p>
                </div>
                @if($import->guia_no)
                    <p class="text-xs text-gray-400 mt-0.5">
                        Guía <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400">{{ $import->guia_no }}</span>
                        @if($import->doc_fecha) · {{ $import->doc_fecha }} @endif
                    </p>
                @endif
            </div>
        </div>

        {{-- Acciones --}}
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('pdf.import.ver', $import->id) }}"
               class="sm:hidden text-xs font-medium text-gray-500 hover:text-gray-800 dark:hover:text-gray-200 transition">
                ← Volver
            </a>
            @if(!$isGRR)
                <a href="{{ route('pdf.import.ver', $import->id) }}" target="_blank"
                   class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl
                          bg-indigo-600 hover:bg-indigo-700 text-white transition active:scale-95">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <span class="hidden sm:inline">Ver PDF</span>
                </a>
            @endif
        </div>
    </div>
</x-slot>

{{-- ═══════════════════════════════════════════════════
     ESTILOS
═══════════════════════════════════════════════════ --}}
<style>
    @keyframes fadeUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
    .au{animation:fadeUp .4s cubic-bezier(.22,1,.36,1) both}
    .d1{animation-delay:.04s}.d2{animation-delay:.08s}.d3{animation-delay:.12s}

    .page-bg{background:#f1f5f9;min-height:100%}
    .dark .page-bg{background:#0d1117}

    /* Card wrapper */
    .mc{background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden}
    .dark .mc{background:#161c2c;border-color:#1e2a3b}
    .mc-head{padding:14px 20px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between}
    .dark .mc-head{border-bottom-color:#1e2a3b}
    .mc-body{padding:16px 20px}

    /* Info cells */
    .ic{background:#f8fafc;border:1px solid #f1f5f9;border-radius:12px;padding:12px 14px}
    .dark .ic{background:#1a2436;border-color:#1e2a3b}
    .ic-lbl{font-size:10px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#94a3b8;margin-bottom:4px}
    .ic-val{font-size:14px;font-weight:700;color:#1e293b;line-height:1.3}
    .dark .ic-val{color:#f1f5f9}
    .ic-sub{font-size:11px;color:#94a3b8;margin-top:2px}

    /* Table */
    .dt{width:100%;border-collapse:collapse;font-size:13px}
    .dt thead tr{background:#f8fafc;border-bottom:1px solid #f1f5f9}
    .dark .dt thead tr{background:#111827;border-bottom-color:#1e2a3b}
    .dt th{padding:10px 14px;text-align:left;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94a3b8}
    .dt td{padding:11px 14px;border-bottom:1px solid #f8fafc;color:#334155;vertical-align:middle}
    .dark .dt td{border-bottom-color:#1a2232;color:#cbd5e1}
    .dt tbody tr:last-child td{border-bottom:none}
    .dt tbody tr:hover td{background:#f8fafc}
    .dark .dt tbody tr:hover td{background:#1a2436}

    /* Sticky viewer toolbar */
    .sticky-bar{position:sticky;top:0;z-index:20;background:#fff;border-bottom:1px solid #f1f5f9;padding:12px 20px}
    .dark .sticky-bar{background:#161c2c;border-bottom-color:#1e2a3b}

    /* Viewer lines */
    .line-row{display:flex;align-items:baseline;gap:8px;padding:3px 0;border-radius:4px}
    .line-row:hover{background:rgba(0,0,0,.025)}
    .dark .line-row:hover{background:rgba(255,255,255,.03)}
    .line-no{font-size:11px;color:#94a3b8;font-family:monospace;min-width:36px;text-align:right;flex-shrink:0}
    .line-text{font-family:monospace;font-size:12px;color:#334155;white-space:pre-wrap;word-break:break-all;flex:1}
    .dark .line-text{color:#cbd5e1}

    /* Buttons */
    .qjump{display:inline-flex;align-items:center;padding:5px 12px;border-radius:8px;font-size:11px;font-weight:600;
           background:#eef2ff;color:#4338ca;transition:background .15s;cursor:pointer;border:none}
    .qjump:hover{background:#e0e7ff}
    .dark .qjump{background:rgba(99,102,241,.15);color:#a5b4fc}
    .ctrl{display:inline-flex;align-items:center;gap:4px;padding:6px 10px;border-radius:8px;
          font-size:11px;font-weight:600;background:#f1f5f9;color:#475569;border:none;cursor:pointer;transition:background .15s}
    .ctrl:hover{background:#e2e8f0}
    .ctrl:disabled{opacity:.35;cursor:not-allowed}
    .dark .ctrl{background:rgba(255,255,255,.06);color:#94a3b8}
    .dark .ctrl:hover{background:rgba(255,255,255,.1)}

    /* Section divider label */
    .s-label{font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#94a3b8;
             margin-bottom:10px;display:flex;align-items:center;gap:8px}
    .s-label::after{content:'';flex:1;height:1px;background:#e2e8f0}
    .dark .s-label::after{background:#1e2a3b}
</style>

<div class="page-bg">
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5"
     x-data="pdfViewer({{ $linesArr->toJson(JSON_UNESCAPED_UNICODE) }}, @json($isQC))">

{{-- ══════════════════════════════════════════════════
     SECCIÓN META — varía según template
══════════════════════════════════════════════════ --}}

{{-- ── XML SII 46 ──────────────────────────────── --}}
@if($isXML && $xmlTotals)
    <div class="mc au d1">
        <div class="mc-head">
            <span class="text-sm font-bold text-gray-800 dark:text-gray-100">XML SII · Tipo 46</span>
            <span class="text-xs text-slate-500 dark:text-slate-400 font-mono">Folio {{ $meta['folio_sii'] ?? '—' }}</span>
        </div>
        <div class="mc-body">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="ic sm:col-span-2">
                    <div class="ic-lbl">Emisor</div>
                    <div class="ic-val">{{ $meta['emisor']['razon_social'] ?? '—' }}</div>
                    <div class="ic-sub">RUT {{ $meta['emisor']['rut'] ?? '—' }}</div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Fecha emisión</div>
                    <div class="ic-val">{{ $import->doc_fecha ?? '—' }}</div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Guía despacho</div>
                    <div class="ic-val text-indigo-600 dark:text-indigo-400">{{ $import->guia_no ?? '—' }}</div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Total bandejas</div>
                    <div class="ic-val">{{ number_format($xmlTotals['bandejas'], 0, ',', '.') }}</div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Total kilos</div>
                    <div class="ic-val text-emerald-600 dark:text-emerald-400">
                        {{ number_format($xmlTotals['kilos'], 2, ',', '.') }} kg
                    </div>
                </div>
                @if(isset($meta['receptor']['razon_social']))
                    <div class="ic sm:col-span-2">
                        <div class="ic-lbl">Receptor</div>
                        <div class="ic-val">{{ $meta['receptor']['razon_social'] }}</div>
                        <div class="ic-sub">RUT {{ $meta['receptor']['rut'] ?? '—' }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif

{{-- ── QC ─────────────────────────────────────── --}}
@if($isQC)
    <div class="mc au d1">
        <div class="mc-head">
            <span class="text-sm font-bold text-gray-800 dark:text-gray-100">Control de Calidad</span>
            @if($qcTotal)
                <span class="text-xs font-semibold text-gray-500">{{ $qcTotal }} bandejas</span>
            @endif
        </div>
        <div class="mc-body space-y-4">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="ic">
                    <div class="ic-lbl">Guía (G.Prod)</div>
                    <div class="ic-val text-indigo-600 dark:text-indigo-400">{{ $import->guia_no ?? '—' }}</div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Fecha</div>
                    <div class="ic-val">{{ $import->doc_fecha ?? '—' }}</div>
                </div>
                <div class="ic sm:col-span-2">
                    <div class="ic-lbl">Productor</div>
                    <div class="ic-val truncate" title="{{ $import->productor }}">{{ $import->productor ?? '—' }}</div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Kgs recibidos</div>
                    <div class="ic-val text-emerald-600 dark:text-emerald-400">
                        {{ isset($qcKgs) ? number_format((float) $qcKgs, 2, ',', '.') . ' kg' : '—' }}
                    </div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Total bandejas</div>
                    <div class="ic-val">{{ $qcTotal ?? '—' }}</div>
                </div>
            </div>

            @if(count($qcBandejas))
                <div>
                    <p class="s-label">Bandejas / Materiales</p>
                    <div class="rounded-xl overflow-hidden border border-gray-100 dark:border-gray-800">
                        <table class="dt">
                            <thead><tr><th>Código</th><th>Descripción</th><th class="text-right">Cantidad</th></tr></thead>
                            <tbody>
                                @forelse($qcBandejas as $b)
                                    <tr>
                                        <td class="font-mono">{{ $b['codigo'] ?? '—' }}</td>
                                        <td>{{ $b['descripcion'] ?? '—' }}</td>
                                        <td class="text-right font-semibold">
                                            {{ isset($b['cantidad']) ? number_format((float) $b['cantidad'], 2, ',', '.') : '—' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="py-8 text-center text-gray-400 text-sm">Sin bandejas detectadas</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif

{{-- ── MP ─────────────────────────────────────── --}}
@if($isMP)
    <div class="mc au d1">
        <div class="mc-head">
            <span class="text-sm font-bold text-gray-800 dark:text-gray-100">Reporte MP</span>
        </div>
        <div class="mc-body space-y-4">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="ic">
                    <div class="ic-lbl">Guía (G.Despacho)</div>
                    <div class="ic-val text-indigo-600 dark:text-indigo-400">{{ $import->guia_no ?? '—' }}</div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Fecha guía</div>
                    <div class="ic-val">{{ $import->doc_fecha ?? '—' }}</div>
                </div>
                <div class="ic sm:col-span-2">
                    <div class="ic-lbl">Proveedor</div>
                    <div class="ic-val truncate" title="{{ $import->productor }}">{{ $import->productor ?? '—' }}</div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Kgs recibidos</div>
                    <div class="ic-val text-emerald-600 dark:text-emerald-400">
                        {{ isset($mpKgs) ? number_format((float) $mpKgs, 2, ',', '.') . ' kg' : '—' }}
                    </div>
                </div>
            </div>

            @if(count($mpBandejas))
                <div>
                    <p class="s-label">Bandejas / Bandejones</p>
                    <div class="rounded-xl overflow-hidden border border-gray-100 dark:border-gray-800">
                        <table class="dt">
                            <thead><tr><th>Descripción</th><th class="text-right">Cantidad</th></tr></thead>
                            <tbody>
                                @forelse($mpBandejas as $b)
                                    <tr>
                                        <td>{{ $b['descripcion'] ?? '—' }}</td>
                                        <td class="text-right font-semibold">
                                            {{ isset($b['cantidad']) ? number_format((float) $b['cantidad'], 2, ',', '.') : '—' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="py-8 text-center text-gray-400 text-sm">Sin bandejas detectadas</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif

{{-- ── RFP ────────────────────────────────────── --}}
@if($isRFP)
    <div class="mc au d1">
        <div class="mc-head">
            <span class="text-sm font-bold text-gray-800 dark:text-gray-100">Recepción Fruta Producción</span>
        </div>
        <div class="mc-body">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="ic">
                    <div class="ic-lbl">Guía despacho</div>
                    <div class="ic-val text-indigo-600 dark:text-indigo-400">{{ $import->guia_no ?? '—' }}</div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Fecha</div>
                    <div class="ic-val">{{ $import->doc_fecha ?? '—' }}</div>
                </div>
                <div class="ic sm:col-span-2">
                    <div class="ic-lbl">Productor</div>
                    <div class="ic-val truncate" title="{{ $import->productor }}">{{ $import->productor ?? '—' }}</div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Albarán</div>
                    <div class="ic-val">{{ $meta['albaran'] ?? '—' }}</div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Bandejas</div>
                    <div class="ic-val">
                        {{ isset($meta['bandejas_total']) ? number_format((float) $meta['bandejas_total'], 0, ',', '.') : '—' }}
                    </div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Kgs recibidos</div>
                    <div class="ic-val text-emerald-600 dark:text-emerald-400">
                        {{ isset($meta['kgs_recibido']) ? number_format((float) $meta['kgs_recibido'], 2, ',', '.') . ' kg' : '—' }}
                    </div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Calidad</div>
                    <div class="ic-val">{{ $meta['calidad'] ?? '—' }}</div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">% IQF</div>
                    <div class="ic-val">
                        {{ isset($meta['iqf_pct']) ? number_format((float) $meta['iqf_pct'], 2, ',', '.') . '%' : '—' }}
                    </div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">% Block</div>
                    <div class="ic-val">
                        {{ isset($meta['block_pct']) ? number_format((float) $meta['block_pct'], 2, ',', '.') . '%' : '—' }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- ── SANCO ──────────────────────────────────── --}}
@if($isSANCO)
    <div class="mc au d1">
        <div class="mc-head">
            <span class="text-sm font-bold text-gray-800 dark:text-gray-100">Guía Recepción Fruta Granel — SANCO</span>
        </div>
        <div class="mc-body space-y-4">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="ic">
                    <div class="ic-lbl">Guía</div>
                    <div class="ic-val text-indigo-600 dark:text-indigo-400">{{ $import->guia_no ?? '—' }}</div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Fecha</div>
                    <div class="ic-val">{{ $import->doc_fecha ?? '—' }}</div>
                </div>
                <div class="ic sm:col-span-2">
                    <div class="ic-lbl">Productor</div>
                    <div class="ic-val truncate" title="{{ $import->productor }}">{{ $import->productor ?? '—' }}</div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Especie</div>
                    <div class="ic-val">{{ $especie ?? '—' }}</div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Variedad</div>
                    <div class="ic-val">{{ $variedad ?? '—' }}</div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Destino</div>
                    <div class="ic-val">{{ $destino ?? '—' }}</div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Total general</div>
                    <div class="ic-val text-emerald-600 dark:text-emerald-400">
                        {{ is_null($totalCant) ? '—' : number_format((float) $totalCant, 0, ',', '.') }}
                        @if(!is_null($totalKgs)) · {{ number_format((float) $totalKgs, 2, ',', '.') }} kg @endif
                    </div>
                </div>
            </div>

            @if(count($detalles))
                <div>
                    <p class="s-label">Detalle de recepción <span class="font-normal normal-case ml-1 text-gray-300">{{ count($detalles) }} líneas</span></p>
                    <div class="rounded-xl overflow-hidden border border-gray-100 dark:border-gray-800">
                        <table class="dt">
                            <thead><tr>
                                <th>Folio</th><th>Fecha</th><th>Calibre</th><th>Envase</th>
                                <th class="text-right">Cant.</th><th class="text-right">Kgs</th>
                            </tr></thead>
                            <tbody>
                                @forelse($detalles as $d)
                                    <tr>
                                        <td class="font-mono text-xs">{{ $d['folio'] ?? '—' }}</td>
                                        <td class="text-xs">{{ $d['fecha'] ?? '—' }}</td>
                                        <td>{{ $d['calibre'] ?? '—' }}</td>
                                        <td>{{ $d['envase'] ?? '—' }}</td>
                                        <td class="text-right font-semibold">
                                            {{ isset($d['cantidad']) ? number_format((int) $d['cantidad'], 0, ',', '.') : '—' }}
                                        </td>
                                        <td class="text-right font-semibold text-emerald-600 dark:text-emerald-400">
                                            {{ isset($d['kgs']) ? number_format((float) $d['kgs'], 2, ',', '.') : '—' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="py-8 text-center text-gray-400 text-sm">Sin líneas de detalle</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif

{{-- ── VT (VitaFoods / Excel) ─────────────────── --}}
@if($isVT)
    <div class="mc au d1">
        <div class="mc-head">
            <span class="text-sm font-bold text-gray-800 dark:text-gray-100">VitaFoods · Excel</span>
            @if($meta['source'] ?? null)
                <span class="text-xs text-amber-500 dark:text-amber-400 font-semibold uppercase tracking-wide">
                    {{ $meta['source'] }}
                </span>
            @endif
        </div>
        <div class="mc-body">
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <div class="ic">
                    <div class="ic-lbl">Guía (GDD)</div>
                    <div class="ic-val text-indigo-600 dark:text-indigo-400">{{ $import->guia_no ?? '—' }}</div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Fecha recepción</div>
                    <div class="ic-val">{{ $import->doc_fecha ?? '—' }}</div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Productor</div>
                    <div class="ic-val truncate text-sm" title="{{ $import->productor }}">{{ $import->productor ?? '—' }}</div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Kgs recibidos</div>
                    <div class="ic-val text-emerald-600 dark:text-emerald-400">
                        {{ isset($vtKgs)
            ? number_format((float) ($vtKgs < 100 ? $vtKgs * 1000 : $vtKgs), 2, ',', '.') . ' ' . $vtUnidad
            : '—' }}
                    </div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Empresa</div>
                    <div class="ic-val">{{ $meta['empresa'] ?? '—' }}</div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Sucursal</div>
                    <div class="ic-val">{{ $meta['sucursal'] ?? '—' }}</div>
                </div>
                <div class="ic sm:col-span-2">
                    <div class="ic-lbl">Producto</div>
                    <div class="ic-val">{{ $meta['producto'] ?? '—' }}</div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Guía de pesaje</div>
                    <div class="ic-val">{{ $meta['guia_pesaje'] ?? '—' }}</div>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- ── LIQ_COMPUAGRO ──────────────────────────── --}}
@if($isLIQ)
    <div class="mc au d1">
        <div class="mc-head">
            <span class="text-sm font-bold text-gray-800 dark:text-gray-100">Liquidación Compuagro</span>
            @if($liqDoc['liquidacion_no'] ?? null)
                <span class="text-xs font-mono font-bold text-rose-600 dark:text-rose-400">
                    Liq. #{{ $liqDoc['liquidacion_no'] }}
                </span>
            @endif
        </div>
        <div class="mc-body space-y-4">

            {{-- Documento --}}
            <div>
                <p class="s-label">Datos del documento</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <div class="ic sm:col-span-2">
                        <div class="ic-lbl">Productor</div>
                        <div class="ic-val truncate" title="{{ $liqDoc['productor'] ?? $import->productor }}">
                            {{ $liqDoc['productor'] ?? $import->productor ?? '—' }}
                        </div>
                    </div>
                    <div class="ic">
                        <div class="ic-lbl">Producto</div>
                        <div class="ic-val">{{ $liqDoc['producto'] ?? '—' }}</div>
                    </div>
                    <div class="ic">
                        <div class="ic-lbl">Variedad</div>
                        <div class="ic-val">{{ $liqDoc['variedad'] ?? '—' }}</div>
                    </div>
                    <div class="ic">
                        <div class="ic-lbl">Período liq.</div>
                        <div class="ic-val text-xs leading-5">{{ $liqDoc['periodo_liquidacion'] ?? '—' }}</div>
                    </div>
                    <div class="ic">
                        <div class="ic-lbl">Período contrato</div>
                        <div class="ic-val text-xs leading-5">{{ $liqDoc['periodo_contrato'] ?? '—' }}</div>
                    </div>
                </div>
            </div>

            {{-- Recepción --}}
            @if($liqRec)
                <div>
                    <p class="s-label">
                        Recepción
                        @if($liqRec['recepcion_no'] ?? null)
                            <span class="font-normal normal-case ml-1 text-gray-400">#{{ $liqRec['recepcion_no'] }}</span>
                        @endif
                    </p>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="ic">
                            <div class="ic-lbl">Guía</div>
                            <div class="ic-val text-indigo-600 dark:text-indigo-400">
                                {{ $liqRec['guia_no'] ?? $import->guia_no ?? '—' }}
                            </div>
                        </div>
                        <div class="ic">
                            <div class="ic-lbl">Fecha</div>
                            <div class="ic-val">{{ $liqRec['doc_fecha'] ?? $import->doc_fecha ?? '—' }}</div>
                        </div>
                        <div class="ic">
                            <div class="ic-lbl">Tipo guía</div>
                            <div class="ic-val">{{ $liqRec['tipo_guia'] ?? '—' }}</div>
                        </div>
                        <div class="ic">
                            <div class="ic-lbl">Total kgs</div>
                            <div class="ic-val text-emerald-600 dark:text-emerald-400">
                                {{ isset($liqRec['total_kgs']) ? number_format((float) $liqRec['total_kgs'], 2, ',', '.') . ' kg' : '—' }}
                            </div>
                        </div>

                        @foreach([
                                ['exportacion_kgs', 'exportacion_pct', 'Exportación', 'text-sky-600 dark:text-sky-400'],
                                ['mercado_interno_kgs', 'mercado_interno_pct', 'Mcdo. interno', 'text-indigo-600 dark:text-indigo-400'],
                                ['desecho_kgs', 'desecho_pct', 'Desecho', 'text-gray-500'],
                            ] as [$kgsKey, $pctKey, $label, $cls])
                            <div class="ic">
                                <div class="ic-lbl">{{ $label }}</div>
                                <div class="ic-val {{ $cls }}">
                                    {{ isset($liqRec[$kgsKey]) ? number_format((float) $liqRec[$kgsKey], 2, ',', '.') . ' kg' : '—' }}
                                </div>
                                @if(isset($liqRec[$pctKey]))
                                    <div class="ic-sub">{{ number_format((float) $liqRec[$pctKey], 1, ',', '.') }}%</div>
                                @endif
                            </div>
                        @endforeach

                        <div class="ic">
                            <div class="ic-lbl">Total neto</div>
                            <div class="ic-val text-rose-600 dark:text-rose-400">
                                {{ isset($liqRec['total_neto']) ? '$ ' . number_format((float) $liqRec['total_neto'], 0, ',', '.') : '—' }}
                            </div>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
@endif

{{-- ── GUIA_RECEPCION_RESUMEN ─────────────────── --}}
@if($isGRR)
    <div class="mc au d1">
        <div class="mc-head">
            <span class="text-sm font-bold text-gray-800 dark:text-gray-100">Guía Recepción — Resumen</span>
        </div>
        <div class="mc-body">
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <div class="ic">
                    <div class="ic-lbl">Guía recepción</div>
                    <div class="ic-val text-indigo-600 dark:text-indigo-400">{{ $import->guia_no ?? '—' }}</div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Fecha</div>
                    <div class="ic-val">{{ $import->doc_fecha ?? '—' }}</div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Guía productor</div>
                    <div class="ic-val font-mono">{{ $grrGuiaProd ?? '—' }}</div>
                </div>
                <div class="ic sm:col-span-3">
                    <div class="ic-lbl">Productor</div>
                    <div class="ic-val truncate" title="{{ $import->productor }}">{{ $import->productor ?? '—' }}</div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Total cajas</div>
                    <div class="ic-val">{{ isset($grrCajas) ? number_format((int) $grrCajas, 0, ',', '.') : '—' }}</div>
                </div>
                <div class="ic">
                    <div class="ic-lbl">Total kilos</div>
                    <div class="ic-val text-emerald-600 dark:text-emerald-400">
                        {{ isset($grrKgs) ? number_format((float) $grrKgs, 2, ',', '.') . ' kg' : '—' }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- ══════════════════════════════════════════════════
     VISOR DE LÍNEAS
══════════════════════════════════════════════════ --}}
@if($linesArr->count())
    <div class="mc au d2">

        {{-- Toolbar sticky --}}
        <div class="sticky-bar space-y-2">

            {{-- Fila 1: stats + buscador + controles --}}
            <div class="flex flex-col sm:flex-row sm:items-center gap-2">

                {{-- Stats (desktop) --}}
                <div class="hidden sm:flex items-center gap-2 shrink-0">
                    <span class="text-xs px-2 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                        <span x-text="lines.length"></span> líneas
                    </span>
                    <span class="text-xs px-2 py-1 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-400"
                          x-show="q.trim()">
                        <span x-text="matchIndexes.length"></span> matches
                    </span>
                </div>

                {{-- Buscador --}}
                <div class="flex-1 relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                    </svg>
                    <input x-model="q" @keydown.enter.prevent="focusNextMatch()" type="text"
                           placeholder="Buscar… Enter = siguiente"
                           class="w-full pl-9 pr-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700
                                  bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100
                                  focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition">
                </div>

                {{-- Controles --}}
                <div class="flex items-center gap-1.5 shrink-0 flex-wrap">
                    <button type="button" class="ctrl" @click="focusPrevMatch()" :disabled="matchIndexes.length === 0">← Prev</button>
                    <button type="button" class="ctrl" @click="focusNextMatch()" :disabled="matchIndexes.length === 0">Next →</button>
                    <input x-model.number="goTo" type="number" min="1" placeholder="Línea"
                           class="w-20 px-2 py-1.5 text-sm border border-gray-200 dark:border-gray-700 rounded-lg
                                  bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200
                                  focus:ring-2 focus:ring-indigo-500 outline-none">
                    <button type="button" class="ctrl" @click="scrollToLine(goTo)">Ir</button>
                    <button type="button" class="ctrl" @click="reset()" x-show="q || goTo">✕</button>
                </div>
            </div>

            {{-- Accesos rápidos QC --}}
            <div class="flex flex-wrap gap-1.5" x-show="isQC">
                <button type="button" class="qjump" @click="jumpToContains('datos del productor')">Datos productor</button>
                <button type="button" class="qjump" @click="jumpToContains('resultado analisis')">Resultado análisis</button>
                <button type="button" class="qjump" @click="jumpToContains('detalle control de calidad')">Detalle control</button>
                <button type="button" class="qjump" @click="jumpToContains('observaciones')">Observaciones</button>
            </div>
        </div>

        {{-- Líneas --}}
        <div class="overflow-auto max-h-[60vh] px-4 sm:px-5 py-4">
            <template x-for="line in visible" :key="line.no">
                <div class="line-row" :id="'line-' + line.no">
                    <span class="line-no" x-text="line.no"></span>
                    <span class="line-text" x-html="highlight(line.text)"></span>
                    <button type="button"
                            class="shrink-0 text-[10px] px-2 py-0.5 rounded-lg bg-gray-100 dark:bg-gray-800
                                   text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition ml-2"
                            @click="copyLine(line.no, line.text)"
                            x-text="copiedLineNo === line.no ? '✓' : 'cp'">
                    </button>
                </div>
            </template>
            <div x-show="filtered.length === 0 && q" class="py-12 text-center text-sm text-gray-400">
                Sin resultados para "<span x-text="q"></span>".
            </div>
        </div>

        {{-- Cargar más --}}
        <div class="px-5 py-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between"
             x-show="filtered.length > visible.length">
            <p class="text-xs text-gray-400">
                Mostrando <span x-text="visible.length"></span> / <span x-text="filtered.length"></span>
            </p>
            <button type="button"
                    class="px-4 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white transition"
                    @click="page++">
                Cargar más
            </button>
        </div>
    </div>
@endif

</div>
</div>

<script>
function pdfViewer(lines, isQC) {
    return {
        lines,
        isQC,
        q: '',
        goTo: null,
        page: 1,
        pageSize: 300,
        copiedLineNo: null,
        matchCursor: -1,

        get filtered() {
            const q = (this.q || '').trim().toLowerCase();
            if (!q) return this.lines;
            return this.lines.filter(l => (l.text || '').toLowerCase().includes(q));
        },

        get visible() {
            return this.filtered.slice(0, this.page * this.pageSize);
        },

        get matchIndexes() {
            const q = (this.q || '').trim().toLowerCase();
            if (!q) return [];
            return this.filtered.reduce((acc, l, idx) => {
                if ((l.text || '').toLowerCase().includes(q)) acc.push(idx);
                return acc;
            }, []);
        },

        reset() { this.q = ''; this.goTo = null; this.page = 1; this.matchCursor = -1; },

        scrollToLine(n) {
            if (!n) return;
            const el = document.getElementById('line-' + n);
            el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        },

        jumpToContains(str) {
            const found = this.lines.find(l => (l.text || '').toLowerCase().includes(str.toLowerCase()));
            if (found) this.scrollToLine(found.no);
        },

        focusNextMatch() {
            if (!this.matchIndexes.length) return;
            this.matchCursor = (this.matchCursor + 1) % this.matchIndexes.length;
            const idx  = this.matchIndexes[this.matchCursor];
            const line = this.filtered[idx];
            const needed = Math.ceil((idx + 1) / this.pageSize);
            if (needed > this.page) this.page = needed;
            this.$nextTick(() => this.scrollToLine(line.no));
        },

        focusPrevMatch() {
            if (!this.matchIndexes.length) return;
            this.matchCursor = this.matchCursor <= 0
                ? this.matchIndexes.length - 1
                : this.matchCursor - 1;
            const idx  = this.matchIndexes[this.matchCursor];
            const line = this.filtered[idx];
            const needed = Math.ceil((idx + 1) / this.pageSize);
            if (needed > this.page) this.page = needed;
            this.$nextTick(() => this.scrollToLine(line.no));
        },

        highlight(text) {
            const q = (this.q || '').trim();
            if (!q) return this.esc(text);
            const re = new RegExp(this.escRe(q), 'gi');
            return this.esc(text).replace(re,
                m => `<mark class="px-0.5 rounded bg-yellow-200 dark:bg-yellow-700">${m}</mark>`);
        },

        async copyLine(no, text) {
            try {
                await navigator.clipboard.writeText(text);
                this.copiedLineNo = no;
                setTimeout(() => this.copiedLineNo = null, 800);
            } catch { window.prompt('Copia:', text); }
        },

        esc(s) {
            return (s ?? '').replace(/[&<>"']/g, c =>
                ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        },
        escRe(s) {
            return (s ?? '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        },
    };
}
</script>

</x-app-layout>