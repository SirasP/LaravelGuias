<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Factura {{ $document->folio ?? '' }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color:#111; margin:24px; font-size:13px }
        .top { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:16px }
        .title { font-size:28px; font-weight:800; margin:0 }
        .subtitle { color:#555; margin:2px 0 0 }
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-bottom:14px }
        .box { border:1px solid #ddd; border-radius:8px; padding:12px }
        .k { color:#666; font-weight:700; width:150px; display:inline-block }
        .v { font-weight:600 }
        table { width:100%; border-collapse:collapse; margin-top:10px }
        th, td { border:1px solid #ddd; padding:8px; text-align:left }
        th { background:#f5f5f5; font-size:11px; text-transform:uppercase; letter-spacing:.06em }
        .right { text-align:right }
        .totals { margin-top:12px; margin-left:auto; width:320px }
        .totals div { display:flex; justify-content:space-between; padding:3px 0 }
        .totals .t { font-size:18px; font-weight:800 }
        @media print {
            body { margin:10mm }
            .no-print { display:none }
        }
    </style>
</head>
<body>
@php
    $tipoMap = [
        33 => ['sigla' => 'FAC', 'nombre' => 'Factura electronica'],
        34 => ['sigla' => 'FEX', 'nombre' => 'Factura exenta'],
        56 => ['sigla' => 'ND', 'nombre' => 'Nota de debito'],
        61 => ['sigla' => 'NC', 'nombre' => 'Nota de credito'],
    ];
    $tipo = $tipoMap[(int) ($document->tipo_dte ?? 0)] ?? ['sigla' => 'DTE', 'nombre' => 'Documento tributario'];

    $taxSummary = collect($document->tax_summary ?? []);
    $ivaMonto = (float) ($document->monto_iva ?? 0);
    $impuestoEspecifico = $taxSummary
        ->filter(function ($tax) {
            $label = strtoupper((string) ($tax['label'] ?? ''));
            return str_contains($label, 'IMPUESTO ESPECIFICO') || str_contains($label, 'ILA');
        })
        ->sum(function ($tax) {
            return (float) ($tax['monto'] ?? 0);
        });
@endphp

<div class="no-print" style="margin-bottom:14px;">
    <button onclick="window.print()">Imprimir</button>
</div>

<div class="top">
    <div>
        <p class="subtitle">Factura de proveedor</p>
        <h1 class="title">{{ $tipo['sigla'] }} {{ $document->folio ?? '—' }}</h1>
        <p class="subtitle">{{ $tipo['nombre'] }}</p>
    </div>
    <div>
        <div><span class="k">Fecha factura:</span> <span class="v">{{ $document->fecha_factura ?? '—' }}</span></div>
        <div><span class="k">Fecha contable:</span> <span class="v">{{ $document->fecha_contable ?? '—' }}</span></div>
        <div><span class="k">Vencimiento:</span> <span class="v">{{ $document->fecha_vencimiento ?? '—' }}</span></div>
    </div>
</div>

<div class="grid">
    <div class="box">
        <div><span class="k">Proveedor:</span> <span class="v">{{ $document->proveedor_nombre ?? '—' }}</span></div>
        <div><span class="k">RUT:</span> <span class="v">{{ $document->proveedor_rut ?? '—' }}</span></div>
        <div><span class="k">Referencia:</span> <span class="v">{{ $document->referencia ?? '—' }}</span></div>
    </div>
    <div class="box">
        <div><span class="k">Archivo XML:</span> <span class="v">{{ $document->xml_filename ?? '—' }}</span></div>
        <div><span class="k">Tipo DTE:</span> <span class="v">{{ $document->tipo_dte ?? '—' }}</span></div>
        <div><span class="k">Folio:</span> <span class="v">{{ $document->folio ?? '—' }}</span></div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>Linea</th>
            <th>Codigo</th>
            <th>Producto</th>
            <th class="right">Cantidad</th>
            <th>UdM</th>
            <th class="right">Precio</th>
            <th>Impuestos</th>
            <th class="right">Importe</th>
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
                <td class="right">{{ number_format((float) $l->cantidad, 2, ',', '.') }}</td>
                <td>{{ $l->unidad ?? '—' }}</td>
                <td class="right">{{ number_format((float) $l->precio_unitario, 0, ',', '.') }}</td>
                <td>{{ $taxLabels->map(fn($x) => preg_replace('/^Imp\\. adic\\./i', 'Impuesto específico', (string) $x))->implode(' | ') }}</td>
                <td class="right">{{ number_format((float) $l->monto_item, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="8" style="text-align:center;color:#777;">Sin lineas de detalle.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="totals">
    <div><span>Monto neto</span><span>{{ number_format((float) $document->monto_neto, 0, ',', '.') }}</span></div>
    <div><span>IVA</span><span>{{ $ivaMonto > 0 ? number_format($ivaMonto, 0, ',', '.') : 'No aplica' }}</span></div>
    @if($impuestoEspecifico > 0)
        <div><span>Impuesto específico</span><span>{{ number_format($impuestoEspecifico, 0, ',', '.') }}</span></div>
    @endif
    <div class="t"><span>Total</span><span>{{ number_format((float) $document->monto_total, 0, ',', '.') }}</span></div>
</div>

<script>
    window.addEventListener('load', () => {
        if (window.location.search.includes('autoprint=1')) {
            window.print();
        }
    });
</script>
</body>
</html>
