<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1e293b;
            background: #fff;
        }

        /* ── Header banner ── */
        .header-bar {
            background: #0f766e;
            padding: 22px 28px;
            margin-bottom: 0;
        }
        .header-table {
            display: table;
            width: 100%;
        }
        .header-left {
            display: table-cell;
            vertical-align: middle;
            width: 60%;
        }
        .header-right {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            width: 40%;
        }
        .company-name {
            font-size: 22px;
            font-weight: bold;
            color: #ffffff;
            letter-spacing: 0.5px;
        }
        .company-tagline {
            font-size: 10px;
            color: #99f6e4;
            margin-top: 3px;
            letter-spacing: 0.3px;
        }
        .doc-type {
            font-size: 12px;
            font-weight: bold;
            color: #ccfbf1;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .doc-number {
            font-size: 20px;
            font-weight: bold;
            color: #ffffff;
            margin-top: 4px;
        }

        /* ── Accent strip ── */
        .accent-strip {
            background: #0d9488;
            height: 4px;
            margin-bottom: 24px;
        }

        /* ── Info grid ── */
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 22px;
            border-collapse: separate;
        }
        .info-box {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding: 14px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
        }
        .info-box-left  { border-right: none; border-radius: 4px 0 0 4px; background: #f8fafc; }
        .info-box-right { border-radius: 0 4px 4px 0; background: #f0fdfa; }
        .info-section-title {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: #0f766e;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 5px;
            margin-bottom: 9px;
        }
        .info-row { margin-bottom: 5px; line-height: 1.4; }
        .info-label { color: #64748b; font-size: 9.5px; display: block; }
        .info-value { color: #1e293b; font-weight: bold; font-size: 11px; }
        .info-value-lg { color: #0f766e; font-weight: bold; font-size: 14px; display: block; margin-bottom: 2px; }
        .info-email { color: #0f766e; font-size: 10px; font-weight: normal; }

        /* ── Message box ── */
        .message-wrap { margin-bottom: 20px; }
        .message-box {
            padding: 12px 16px;
            background: #f0fdf4;
            border-left: 4px solid #0f766e;
            border-radius: 0 4px 4px 0;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        /* ── Section header ── */
        .section-header {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }
        .section-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .10em;
            color: #64748b;
            display: table-cell;
            vertical-align: middle;
        }
        .section-line {
            display: table-cell;
            vertical-align: middle;
            padding-left: 10px;
        }
        .section-line-inner {
            border-top: 1px solid #e2e8f0;
            width: 100%;
            display: block;
        }

        /* ── Items table ── */
        table.items {
            width: 100%;
            border-collapse: collapse;
        }
        table.items thead tr {
            background: #0f766e;
        }
        table.items thead th {
            padding: 9px 10px;
            text-align: left;
            font-size: 9.5px;
            font-weight: bold;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: .07em;
        }
        table.items thead th.right { text-align: right; }
        table.items tbody tr { background: #ffffff; }
        table.items tbody tr.alt { background: #f8fafc; }
        table.items tbody td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11px;
            color: #334155;
            vertical-align: middle;
        }
        table.items tbody td.right { text-align: right; }
        table.items tbody td.mono  { font-family: DejaVu Sans Mono, monospace; font-size: 10.5px; }
        .item-num {
            display: inline-block;
            width: 20px;
            height: 20px;
            line-height: 20px;
            text-align: center;
            background: #0f766e;
            border-radius: 50%;
            font-size: 9px;
            color: #ffffff;
            font-weight: bold;
        }
        table.items tfoot td {
            padding: 0;
            border: none;
        }

        /* ── Totals ── */
        .totals-outer {
            margin-top: 2px;
            text-align: right;
        }
        table.totals {
            display: inline-table;
            min-width: 240px;
            border-collapse: collapse;
            margin-top: 0;
        }
        table.totals tr.subtotal-row td {
            padding: 5px 12px;
            font-size: 10.5px;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
        }
        table.totals .grand-row {
            background: #0f766e;
        }
        table.totals .grand-row td {
            padding: 10px 12px;
            color: #ffffff;
            font-weight: bold;
            font-size: 14px;
        }
        .total-label { text-align: left; }
        .total-amount { text-align: right; font-family: DejaVu Sans Mono, monospace; }

        /* ── Instructions ── */
        .instructions-wrap { margin-top: 24px; margin-bottom: 16px; }
        .instructions-box {
            display: table;
            width: 100%;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 4px;
            padding: 12px 16px;
        }
        .instructions-icon { display: table-cell; width: 24px; vertical-align: top; }
        .instructions-body { display: table-cell; vertical-align: top; padding-left: 8px; }
        .instructions-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .10em;
            color: #1d4ed8;
            margin-bottom: 5px;
        }
        .instructions-text { font-size: 10.5px; color: #1d4ed8; line-height: 1.6; }

        /* ── Footer ── */
        .footer {
            margin-top: 28px;
            border-top: 2px solid #0f766e;
            padding-top: 10px;
            display: table;
            width: 100%;
        }
        .footer-left {
            display: table-cell;
            vertical-align: middle;
            font-size: 8.5px;
            color: #94a3b8;
        }
        .footer-right {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            font-size: 8.5px;
            color: #0f766e;
            font-weight: bold;
        }
    </style>
</head>
<body>

    {{-- ── HEADER BANNER ── --}}
    <div class="header-bar">
        <div class="header-table">
            <div class="header-left">
                <div class="company-name">{{ config('app.name', 'Empresa') }}</div>
                <div class="company-tagline">Solicitud de Cotización de Precios</div>
            </div>
            <div class="header-right">
                <div class="doc-type">Cotización</div>
                <div class="doc-number">{{ $order->order_number }}</div>
            </div>
        </div>
    </div>
    <div class="accent-strip"></div>

    {{-- ── INFO ── --}}
    <div class="info-grid">
        <div class="info-box info-box-left">
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
        </div>
        <div class="info-box info-box-right">
            <div class="info-section-title">Destinatario</div>
            <div class="info-row">
                <span class="info-value-lg">{{ $supplierName }}</span>
                @if($supplierEmail)
                <span class="info-email">{{ $supplierEmail }}</span>
                @endif
            </div>
        </div>
    </div>

    {{-- ── MENSAJE PERSONALIZADO ── --}}
    @if($message)
    <div class="message-wrap">
        <div class="message-box">{{ $message }}</div>
    </div>
    @endif

    {{-- ── ITEMS TABLE ── --}}
    <div class="section-header">
        <span class="section-title">Productos solicitados</span>
        <span class="section-line"><span class="section-line-inner"></span></span>
    </div>
    <table class="items">
        <thead>
            <tr>
                <th style="width:28px;">#</th>
                <th>Descripción del producto</th>
                <th style="width:52px;">UdM</th>
                <th class="right" style="width:78px;">Cantidad</th>
                <th class="right" style="width:110px;">Precio unit. ref.</th>
                <th class="right" style="width:100px;">Importe ref.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $i => $item)
            <tr class="{{ $i % 2 === 0 ? '' : 'alt' }}">
                <td style="text-align:center;"><span class="item-num">{{ $i + 1 }}</span></td>
                <td>{{ $item->product_name }}</td>
                <td>{{ $item->unit }}</td>
                <td class="right mono">{{ number_format((float) $item->quantity, 2, ',', '.') }}</td>
                <td class="right mono">
                    @if((float)$item->unit_price > 0)
                        {{ number_format((float) $item->unit_price, 2, ',', '.') }}
                    @else
                        <span style="color:#cbd5e1;">—</span>
                    @endif
                </td>
                <td class="right mono">
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

    {{-- ── TOTALS ── --}}
    <div class="totals-outer">
        <table class="totals">
            <tr class="grand-row">
                <td class="total-label">Total {{ $order->currency }}</td>
                <td class="total-amount">
                    @if((float)$order->total > 0)
                        {{ number_format((float) $order->total, 2, ',', '.') }}
                    @else
                        Por cotizar
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- ── INSTRUCCIONES ── --}}
    <div class="instructions-wrap">
        <div class="instructions-box">
            <div class="instructions-body">
                <div class="instructions-title">Instrucciones de respuesta</div>
                <div class="instructions-text">
                    Responda este correo indicando sus precios unitarios para cada producto.
                    Si no cuenta con disponibilidad de algún ítem, por favor indíquelo.
                    Incluya plazo de entrega estimado y condiciones de pago.
                </div>
            </div>
        </div>
    </div>

    {{-- ── FOOTER ── --}}
    <div class="footer">
        <div class="footer-left">
            Generado el {{ now()->format('d/m/Y H:i') }} &mdash;
            Este documento es una solicitud de cotización, no una orden de compra.
        </div>
        <div class="footer-right">{{ config('app.name', 'Sistema') }}</div>
    </div>

</body>
</html>
