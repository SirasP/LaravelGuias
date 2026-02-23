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

        /* ── Header ── */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 24px;
            border-bottom: 3px solid #0f766e;
            padding-bottom: 16px;
        }
        .header-left  { display: table-cell; width: 55%; vertical-align: middle; }
        .header-right { display: table-cell; width: 45%; vertical-align: middle; text-align: right; }

        /* Logo: coloca tu logo en public/images/logo.png y descomenta la línea de abajo */
        /* .logo { max-height: 55px; max-width: 180px; } */
        .company-name {
            font-size: 20px;
            font-weight: bold;
            color: #0f766e;
            letter-spacing: -0.5px;
        }
        .company-tagline {
            font-size: 10px;
            color: #64748b;
            margin-top: 3px;
        }

        .doc-type {
            font-size: 22px;
            font-weight: bold;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .doc-number {
            font-size: 13px;
            color: #0f766e;
            font-weight: bold;
            margin-top: 3px;
        }

        /* ── Info boxes ── */
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .info-box {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding: 12px 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }
        .info-box:first-child { margin-right: 8px; }
        .info-box + .info-box { padding-left: 22px; }
        .info-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #94a3b8;
            margin-bottom: 8px;
        }
        .info-row { margin-bottom: 4px; }
        .info-label { color: #64748b; font-size: 10px; }
        .info-value { color: #1e293b; font-weight: bold; font-size: 11px; }

        /* ── Message ── */
        .message-box {
            background: #f0fdf4;
            border-left: 4px solid #10b981;
            padding: 10px 14px;
            margin-bottom: 20px;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.5;
        }

        /* ── Items table ── */
        .items-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: #64748b;
            margin-bottom: 8px;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        table.items thead tr {
            background: #0f766e;
            color: #fff;
        }
        table.items thead th {
            padding: 8px 10px;
            text-align: left;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        table.items thead th.right { text-align: right; }
        table.items tbody tr:nth-child(even) { background: #f8fafc; }
        table.items tbody tr:nth-child(odd)  { background: #fff; }
        table.items tbody td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11px;
            color: #334155;
        }
        table.items tbody td.right { text-align: right; }
        table.items tbody td.mono  { font-family: DejaVu Sans Mono, monospace; }
        .row-num {
            display: inline-block;
            width: 18px;
            height: 18px;
            line-height: 18px;
            text-align: center;
            background: #e2e8f0;
            border-radius: 50%;
            font-size: 9px;
            color: #64748b;
            font-weight: bold;
        }

        /* ── Totals ── */
        .totals-wrap { margin-top: 0; }
        table.totals {
            width: 260px;
            margin-left: auto;
            border-collapse: collapse;
        }
        table.totals td { padding: 5px 10px; font-size: 11px; }
        table.totals .total-row {
            background: #0f766e;
            color: #fff;
            font-weight: bold;
            font-size: 13px;
        }
        table.totals .total-row td { padding: 8px 10px; }

        /* ── Notes ── */
        .notes-box {
            margin-top: 20px;
            padding: 10px 14px;
            background: #fafafa;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }
        .notes-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: #94a3b8;
            margin-bottom: 6px;
        }
        .notes-text { font-size: 11px; color: #475569; line-height: 1.5; }

        /* ── Footer ── */
        .footer {
            margin-top: 28px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
        }

        /* ── Reply instructions ── */
        .reply-box {
            margin-top: 16px;
            padding: 10px 14px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
        }
        .reply-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: #3b82f6;
            margin-bottom: 5px;
        }
        .reply-text { font-size: 10px; color: #1d4ed8; line-height: 1.5; }

        /* ── Spacer ── */
        .spacer { margin-bottom: 8px; }
    </style>
</head>
<body>

    {{-- ── HEADER ── --}}
    <div class="header">
        <div class="header-left">
            {{-- Si tienes logo PNG: <img src="{{ public_path('images/logo.png') }}" class="logo"> --}}
            <div class="company-name">{{ config('app.name', 'Empresa') }}</div>
            <div class="company-tagline">Sistema de Cotizaciones</div>
        </div>
        <div class="header-right">
            <div class="doc-type">Cotización</div>
            <div class="doc-number">N° {{ $order->order_number }}</div>
        </div>
    </div>

    {{-- ── INFO ── --}}
    <div class="info-grid">
        <div class="info-box">
            <div class="info-title">Información del documento</div>
            <div class="info-row">
                <span class="info-label">N° Cotización:&nbsp;</span>
                <span class="info-value">{{ $order->order_number }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Fecha emisión:&nbsp;</span>
                <span class="info-value">{{ \Carbon\Carbon::parse($order->created_at)->format('d/m/Y') }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Moneda:&nbsp;</span>
                <span class="info-value">{{ $order->currency }}</span>
            </div>
            @if($order->sent_at)
            <div class="info-row">
                <span class="info-label">Fecha envío:&nbsp;</span>
                <span class="info-value">{{ \Carbon\Carbon::parse($order->sent_at)->format('d/m/Y') }}</span>
            </div>
            @endif
        </div>
        <div class="info-box">
            <div class="info-title">Destinatario</div>
            <div class="info-row">
                <span class="info-value" style="font-size:13px;">{{ $supplierName }}</span>
            </div>
            @if($supplierEmail)
            <div class="info-row" style="margin-top:4px;">
                <span class="info-label">Correo:&nbsp;</span>
                <span class="info-value" style="font-weight:normal; color:#0f766e;">{{ $supplierEmail }}</span>
            </div>
            @endif
        </div>
    </div>

    {{-- ── MENSAJE PERSONALIZADO ── --}}
    @if($message)
    <div class="message-box">
        {!! nl2br(e($message)) !!}
    </div>
    @endif

    {{-- ── ITEMS TABLE ── --}}
    <div class="items-title">Detalle de productos solicitados</div>
    <table class="items">
        <thead>
            <tr>
                <th style="width:30px;">#</th>
                <th>Producto</th>
                <th style="width:50px;">UdM</th>
                <th class="right" style="width:80px;">Cantidad</th>
                <th class="right" style="width:110px;">Precio unit. ref.</th>
                <th class="right" style="width:100px;">Importe ref.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $i => $item)
            <tr>
                <td class="right"><span class="row-num">{{ $i + 1 }}</span></td>
                <td>{{ $item->product_name }}</td>
                <td>{{ $item->unit }}</td>
                <td class="right mono">{{ number_format((float) $item->quantity, 4, ',', '.') }}</td>
                <td class="right mono">
                    @if((float)$item->unit_price > 0)
                        {{ number_format((float) $item->unit_price, 2, ',', '.') }}
                    @else
                        <span style="color:#94a3b8;">—</span>
                    @endif
                </td>
                <td class="right mono">
                    @if((float)$item->line_total > 0)
                        {{ number_format((float) $item->line_total, 2, ',', '.') }}
                    @else
                        <span style="color:#94a3b8;">—</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ── TOTALS ── --}}
    <div class="totals-wrap">
        <table class="totals">
            <tr class="total-row">
                <td>Total {{ $order->currency }}</td>
                <td class="right">
                    @if((float)$order->total > 0)
                        {{ number_format((float) $order->total, 2, ',', '.') }}
                    @else
                        —
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- ── INSTRUCCIONES DE RESPUESTA ── --}}
    <div class="reply-box">
        <div class="reply-title">Instrucciones de respuesta</div>
        <div class="reply-text">
            Por favor responda este correo con sus precios unitarios para cada producto listado.<br>
            Si no tiene disponibilidad de algún ítem, indíquelo en su respuesta.<br>
            Incluya también el plazo de entrega estimado y condiciones de pago.
        </div>
    </div>

    {{-- ── NOTAS ── --}}
    @if($order->notes)
    <div class="notes-box">
        <div class="notes-title">Notas adicionales</div>
        <div class="notes-text">{{ $order->notes }}</div>
    </div>
    @endif

    {{-- ── FOOTER ── --}}
    <div class="footer">
        Documento generado el {{ now()->format('d/m/Y H:i') }} &mdash;
        {{ config('app.name', 'Sistema') }} &mdash;
        Este documento es una solicitud de cotización, no una orden de compra.
    </div>

</body>
</html>
