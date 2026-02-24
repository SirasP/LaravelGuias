<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @page {
            margin: 14mm 16mm 16mm 16mm;
            size: A4 portrait;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #1e293b;
            background: #ffffff;
            line-height: 1.4;
        }

        /* ── Header banner ── */
        .header-bar {
            background: #0f766e;
            padding: 20px 22px;
            margin-bottom: 0;
        }
        .header-bar table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-bar td {
            vertical-align: middle;
        }
        .header-bar td.left {
            width: 60%;
        }
        .header-bar td.right {
            width: 40%;
            text-align: right;
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
            margin-top: 4px;
        }
        .doc-type {
            font-size: 11px;
            font-weight: bold;
            color: #ccfbf1;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .doc-number {
            font-size: 22px;
            font-weight: bold;
            color: #ffffff;
            margin-top: 3px;
        }

        /* ── Accent strip ── */
        .accent-strip {
            background: #0d9488;
            height: 4px;
            margin-bottom: 20px;
        }

        /* ── Info grid (2 boxes) ── */
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .info-grid td {
            vertical-align: top;
            width: 50%;
            padding: 13px 15px;
            border: 1px solid #cbd5e1;
        }
        .info-grid td.left {
            background: #f8fafc;
            border-right: none;
            border-radius: 4px 0 0 4px;
        }
        .info-grid td.right {
            background: #f0fdfa;
            border-radius: 0 4px 4px 0;
        }
        .info-section-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #0f766e;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 5px;
            margin-bottom: 9px;
        }
        .info-row {
            margin-bottom: 6px;
            line-height: 1.4;
        }
        .info-label {
            color: #64748b;
            font-size: 9.5px;
            display: block;
        }
        .info-value {
            color: #1e293b;
            font-weight: bold;
            font-size: 11.5px;
            display: block;
        }
        .info-value-lg {
            color: #0f766e;
            font-weight: bold;
            font-size: 15px;
            display: block;
            margin-bottom: 2px;
        }
        .info-email {
            color: #0f766e;
            font-size: 10px;
        }

        /* ── Message box ── */
        .message-wrap {
            margin-bottom: 18px;
        }
        .message-box {
            padding: 11px 15px;
            background: #f0fdf4;
            border-left: 4px solid #0f766e;
            border-radius: 0 4px 4px 0;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.7;
            white-space: pre-wrap;
        }

        /* ── Section title ── */
        .section-wrap {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .section-wrap td.title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.10em;
            color: #64748b;
            white-space: nowrap;
            padding-right: 10px;
            vertical-align: middle;
        }
        .section-wrap td.line {
            border-top: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        /* ── Items table ── */
        .items {
            width: 100%;
            border-collapse: collapse;
        }
        .items thead tr {
            background: #0f766e;
        }
        .items thead th {
            padding: 9px 10px;
            font-size: 9.5px;
            font-weight: bold;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            text-align: left;
        }
        .items thead th.right { text-align: right; }
        .items tbody tr.even { background: #f8fafc; }
        .items tbody tr.odd  { background: #ffffff; }
        .items tbody td {
            padding: 8px 10px;
            border-bottom: 1px solid #e8edf2;
            font-size: 11.5px;
            color: #334155;
            vertical-align: middle;
        }
        .items tbody td.right { text-align: right; }
        .items tbody td.mono  {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 10.5px;
        }
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

        /* ── Totals ── */
        .totals-wrap {
            margin-top: 0;
            text-align: right;
        }
        .totals {
            min-width: 240px;
            border-collapse: collapse;
        }
        .totals tr.grand td {
            background: #0f766e;
            padding: 10px 14px;
            color: #ffffff;
            font-weight: bold;
            font-size: 15px;
        }
        .totals .lbl { text-align: left; }
        .totals .amt {
            text-align: right;
            font-family: DejaVu Sans Mono, monospace;
        }

        /* ── Instructions ── */
        .instructions-wrap {
            margin-top: 22px;
            margin-bottom: 16px;
        }
        .instructions {
            width: 100%;
            border-collapse: collapse;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 4px;
        }
        .instructions td {
            padding: 11px 14px;
            vertical-align: top;
        }
        .instructions-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.10em;
            color: #1d4ed8;
            margin-bottom: 4px;
        }
        .instructions-text {
            font-size: 10.5px;
            color: #1d4ed8;
            line-height: 1.6;
        }

        /* ── Footer ── */
        .footer {
            margin-top: 26px;
            border-top: 2px solid #0f766e;
            padding-top: 9px;
            width: 100%;
            border-collapse: collapse;
        }
        .footer td {
            vertical-align: middle;
        }
        .footer td.left {
            font-size: 8.5px;
            color: #94a3b8;
        }
        .footer td.right {
            text-align: right;
            font-size: 8.5px;
            color: #0f766e;
            font-weight: bold;
        }
    </style>
</head>
<body>

    {{-- ── HEADER ── --}}
    <div class="header-bar">
        <table>
            <tr>
                <td class="left">
                    <div class="company-name">{{ config('app.name', 'Empresa') }}</div>
                    <div class="company-tagline">Solicitud de Cotización de Precios</div>
                </td>
                <td class="right">
                    <div class="doc-type">Cotización</div>
                    <div class="doc-number">{{ $order->order_number }}</div>
                </td>
            </tr>
        </table>
    </div>
    <div class="accent-strip"></div>

    {{-- ── INFO ── --}}
    <table class="info-grid">
        <tr>
            <td class="left">
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
            <td class="right">
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

    {{-- ── MENSAJE ── --}}
    @if($message)
    <div class="message-wrap">
        <div class="message-box">{{ $message }}</div>
    </div>
    @endif

    {{-- ── PRODUCTOS ── --}}
    <table class="section-wrap">
        <tr>
            <td class="title">Productos solicitados</td>
            <td class="line"></td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:30px; text-align:center;">#</th>
                <th>Descripción del producto</th>
                <th style="width:50px;">UdM</th>
                <th class="right" style="width:80px;">Cantidad</th>
                <th class="right" style="width:110px;">Precio unit. ref.</th>
                <th class="right" style="width:105px;">Importe ref.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $i => $item)
            <tr class="{{ $i % 2 === 0 ? 'odd' : 'even' }}">
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

    {{-- ── TOTAL ── --}}
    <div class="totals-wrap">
        <table class="totals">
            <tr class="grand">
                <td class="lbl">Total {{ $order->currency }}</td>
                <td class="amt">
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
        <table class="instructions">
            <tr>
                <td>
                    <div class="instructions-title">Instrucciones de respuesta</div>
                    <div class="instructions-text">
                        Responda este correo indicando sus precios unitarios para cada producto.
                        Si no cuenta con disponibilidad de algún ítem, por favor indíquelo.
                        Incluya plazo de entrega estimado y condiciones de pago.
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── FOOTER ── --}}
    <table class="footer">
        <tr>
            <td class="left">
                Generado el {{ now()->format('d/m/Y H:i') }} &mdash;
                Este documento es una solicitud de cotización, no una orden de compra.
            </td>
            <td class="right">{{ config('app.name', 'Sistema') }}</td>
        </tr>
    </table>

</body>
</html>
