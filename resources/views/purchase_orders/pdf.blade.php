<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @page {
            margin: 16mm 18mm 18mm 18mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1e293b;
            background: #ffffff;
            line-height: 1.45;
        }

        /* Reset mínimo — SIN box-sizing (DomPDF no lo soporta) */
        table { border-collapse: collapse; }
        p, div, span { margin: 0; padding: 0; }

        /* ── Header ── */
        .header-table { width: 100%; }
        .header-wrap  { background: #0f766e; padding: 20px 22px; }

        .company-name    { font-size: 21px; font-weight: bold; color: #ffffff; }
        .company-tagline { font-size: 9.5px; color: #99f6e4; margin-top: 3px; }
        .doc-label       { font-size: 10px; font-weight: bold; color: #ccfbf1;
                           text-transform: uppercase; letter-spacing: 2px; }
        .doc-number      { font-size: 21px; font-weight: bold; color: #ffffff; margin-top: 2px; }

        /* ── Accent strip ── */
        .accent { background: #0d9488; height: 4px; width: 100%; font-size: 0; line-height: 0; }

        /* ── Spacer ── */
        .sp20 { height: 20px; }
        .sp18 { height: 18px; }
        .sp10 { height: 10px; }
        .sp8  { height: 8px; }

        /* ── Info boxes ── */
        .info-table { width: 100%; }
        .info-box-left {
            width: 50%;
            padding: 13px 14px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-right: none;
            vertical-align: top;
        }
        .info-box-right {
            width: 50%;
            padding: 13px 14px;
            background: #f0fdfa;
            border: 1px solid #cbd5e1;
            vertical-align: top;
        }
        .info-section-title {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #0f766e;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        .info-label { color: #64748b; font-size: 9px; display: block; }
        .info-value { color: #1e293b; font-weight: bold; font-size: 11px; display: block; }
        .info-value-lg { color: #0f766e; font-weight: bold; font-size: 14px; display: block; margin-bottom: 1px; }
        .info-email { color: #0f766e; font-size: 9.5px; }
        .info-row   { margin-bottom: 6px; }

        /* ── Message ── */
        .message-box {
            padding: 10px 14px;
            background: #f0fdf4;
            border-left: 4px solid #0f766e;
            font-size: 10.5px;
            color: #1e293b;
            line-height: 1.7;
            white-space: pre-wrap;
            width: 100%;
        }

        /* ── Section header ── */
        .section-title-text {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.10em;
            color: #64748b;
            white-space: nowrap;
            padding-right: 8px;
            vertical-align: middle;
        }
        .section-title-line {
            border-top: 1px solid #e2e8f0;
            vertical-align: middle;
            width: 100%;
        }

        /* ── Items table ── */
        .items-table { width: 100%; }
        .items-table thead tr { background: #0f766e; }
        .items-table thead th {
            padding: 9px 10px;
            font-size: 9px;
            font-weight: bold;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            text-align: left;
        }
        .items-table thead th.r { text-align: right; }
        .items-table tbody td {
            padding: 8px 10px;
            border-bottom: 1px solid #e8edf2;
            font-size: 11px;
            color: #334155;
            vertical-align: middle;
        }
        .items-table tbody td.r  { text-align: right; }
        .items-table tbody td.mono { font-family: DejaVu Sans Mono, monospace; font-size: 10px; }
        .items-table .row-even { background: #f8fafc; }
        .items-table .row-odd  { background: #ffffff; }

        .item-badge {
            display: inline-block;
            width: 19px;
            height: 19px;
            line-height: 19px;
            text-align: center;
            background: #0f766e;
            border-radius: 50%;
            font-size: 8.5px;
            color: #ffffff;
            font-weight: bold;
        }

        /* ── Totals ── */
        .totals-outer { width: 100%; }
        .totals-spacer { width: 60%; }
        .totals-cell   { width: 40%; }
        .totals-inner  { width: 100%; }
        .totals-inner td {
            background: #0f766e;
            padding: 10px 14px;
            color: #ffffff;
            font-weight: bold;
            font-size: 14px;
        }
        .totals-inner td.r {
            text-align: right;
            font-family: DejaVu Sans Mono, monospace;
        }

        /* ── Instructions ── */
        .instr-box { background: #eff6ff; border: 1px solid #bfdbfe; width: 100%; }
        .instr-box td { padding: 11px 14px; vertical-align: top; }
        .instr-title {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.10em;
            color: #1d4ed8;
            margin-bottom: 4px;
        }
        .instr-text { font-size: 10px; color: #1d4ed8; line-height: 1.65; }

        /* ── Footer ── */
        .footer-table { width: 100%; border-top: 2px solid #0f766e; padding-top: 8px; }
        .footer-table td { vertical-align: middle; padding-top: 8px; }
        .footer-left  { font-size: 8px; color: #94a3b8; }
        .footer-right { text-align: right; font-size: 8px; color: #0f766e; font-weight: bold; }
    </style>
</head>
<body>

{{-- ── HEADER ── --}}
<div class="header-wrap">
    <table class="header-table">
        <tr>
            <td style="vertical-align:middle; width:60%;">
                <div class="company-name">{{ config('app.name', 'Empresa') }}</div>
                <div class="company-tagline">Solicitud de Cotización de Precios</div>
            </td>
            <td style="vertical-align:middle; width:40%; text-align:right;">
                <div class="doc-label">Cotización</div>
                <div class="doc-number">{{ $order->order_number }}</div>
            </td>
        </tr>
    </table>
</div>
<div class="accent">&nbsp;</div>
<div class="sp20">&nbsp;</div>

{{-- ── INFO ── --}}
<table class="info-table">
    <tr>
        <td class="info-box-left">
            <div class="info-section-title">Información del documento</div>
            <div class="info-row">
                <span class="info-label">N° Cotización</span>
                <span class="info-value">{{ $order->order_number }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Fecha de emisión</span>
                <span class="info-value">{{ \Carbon\Carbon::parse($order->created_at)->format('d \d\e F \d\e Y') }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Moneda</span>
                <span class="info-value">{{ $order->currency }}</span>
            </div>
            @if($order->sent_at)
            <div class="info-row">
                <span class="info-label">Fecha de envío</span>
                <span class="info-value">{{ \Carbon\Carbon::parse($order->sent_at)->format('d/m/Y') }}</span>
            </div>
            @endif
        </td>
        <td class="info-box-right">
            <div class="info-section-title">Destinatario</div>
            <div class="info-row">
                <span class="info-value-lg">{{ $supplierName }}</span>
                @if($supplierEmail)
                <span class="info-email">{{ $supplierEmail }}</span>
                @endif
            </div>
        </td>
    </tr>
</table>
<div class="sp18">&nbsp;</div>

{{-- ── MENSAJE ── --}}
@if($message)
<div class="message-box">{{ $message }}</div>
<div class="sp18">&nbsp;</div>
@endif

{{-- ── SECCIÓN PRODUCTOS ── --}}
<table width="100%">
    <tr>
        <td class="section-title-text">Productos solicitados</td>
        <td class="section-title-line"></td>
    </tr>
</table>
<div class="sp8">&nbsp;</div>

{{-- ── TABLA ITEMS ── --}}
<table class="items-table" width="100%">
    <thead>
        <tr>
            <th style="width:28px; text-align:center;">#</th>
            <th>Descripción del producto</th>
            <th style="width:48px;">UdM</th>
            <th class="r" style="width:78px;">Cantidad</th>
            <th class="r" style="width:108px;">Precio unit. ref.</th>
            <th class="r" style="width:100px;">Importe ref.</th>
        </tr>
    </thead>
    <tbody>
        @foreach($items as $i => $item)
        <tr class="{{ $i % 2 === 0 ? 'row-odd' : 'row-even' }}">
            <td style="text-align:center;"><span class="item-badge">{{ $i + 1 }}</span></td>
            <td>{{ $item->product_name }}</td>
            <td>{{ $item->unit }}</td>
            <td class="r mono">{{ number_format((float) $item->quantity, 2, ',', '.') }}</td>
            <td class="r mono">
                @if((float)$item->unit_price > 0)
                    {{ number_format((float) $item->unit_price, 2, ',', '.') }}
                @else
                    <span style="color:#cbd5e1;">—</span>
                @endif
            </td>
            <td class="r mono">
                @if((float)$item->line_total > 0)
                    {{ number_format((float) $item->line_total, 2, ',', '.') }}
                @else
                    <span style="color:#cbd5e1;">—</span>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- ── TOTAL ── --}}
<table class="totals-outer" width="100%">
    <tr>
        <td class="totals-spacer"></td>
        <td class="totals-cell">
            <table class="totals-inner" width="100%">
                <tr>
                    <td>Total {{ $order->currency }}</td>
                    <td class="r">
                        @if((float)$order->total > 0)
                            {{ number_format((float) $order->total, 2, ',', '.') }}
                        @else
                            Por cotizar
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- ── INSTRUCCIONES ── --}}
<div class="sp20">&nbsp;</div>
<table class="instr-box" width="100%">
    <tr>
        <td>
            <div class="instr-title">Instrucciones de respuesta</div>
            <div class="instr-text">
                Responda este correo indicando sus precios unitarios para cada producto.
                Si no cuenta con disponibilidad de algún ítem, por favor indíquelo.
                Incluya plazo de entrega estimado y condiciones de pago.
            </div>
        </td>
    </tr>
</table>

{{-- ── FOOTER ── --}}
<div class="sp20">&nbsp;</div>
<table class="footer-table" width="100%">
    <tr>
        <td class="footer-left">
            Generado el {{ now()->format('d/m/Y H:i') }} &mdash;
            Este documento es una solicitud de cotización, no una orden de compra.
        </td>
        <td class="footer-right">{{ config('app.name', 'Sistema') }}</td>
    </tr>
</table>

</body>
</html>
