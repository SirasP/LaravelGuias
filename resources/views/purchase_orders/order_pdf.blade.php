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
            border-bottom: 3px solid #1d4ed8;
            padding-bottom: 16px;
        }
        .header-left  { display: table-cell; width: 55%; vertical-align: middle; }
        .header-right { display: table-cell; width: 45%; vertical-align: middle; text-align: right; }

        .company-name {
            font-size: 20px;
            font-weight: bold;
            color: #1d4ed8;
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
            color: #1d4ed8;
            font-weight: bold;
            margin-top: 3px;
        }

        /* ── Badge estado ── */
        .status-badge {
            display: inline-block;
            background: #1d4ed8;
            color: #fff;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: .06em;
            text-transform: uppercase;
            padding: 3px 10px;
            border-radius: 999px;
            margin-top: 6px;
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

        /* ── Confirmation message ── */
        .confirm-box {
            background: #eff6ff;
            border-left: 4px solid #1d4ed8;
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
            background: #1d4ed8;
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
            background: #1d4ed8;
            color: #fff;
            font-weight: bold;
            font-size: 13px;
        }
        table.totals .total-row td { padding: 8px 10px; }

        /* ── Delivery box ── */
        .delivery-box {
            margin-top: 20px;
            padding: 10px 14px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-left: 4px solid #16a34a;
            border-radius: 6px;
        }
        .delivery-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: #16a34a;
            margin-bottom: 5px;
        }
        .delivery-text { font-size: 10px; color: #1e293b; line-height: 1.5; }

        /* ── Notes ── */
        .notes-box {
            margin-top: 16px;
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
    </style>
</head>
<body>

    {{-- ── HEADER ── --}}
    <div class="header">
        <div class="header-left">
            {{-- <img src="{{ public_path('images/logo.png') }}" class="logo"> --}}
            <div class="company-name">{{ config('app.name', 'Empresa') }}</div>
            <div class="company-tagline">Sistema de Órdenes de Compra</div>
        </div>
        <div class="header-right">
            <div class="doc-type">Orden de Compra</div>
            <div class="doc-number">N° {{ $order->order_number }}</div>
            <div class="status-badge">CONFIRMADA</div>
        </div>
    </div>

    {{-- ── INFO ── --}}
    <div class="info-grid">
        <div class="info-box">
            <div class="info-title">Información del documento</div>
            <div class="info-row">
                <span class="info-label">N° Orden:&nbsp;</span>
                <span class="info-value">{{ $order->order_number }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Fecha:&nbsp;</span>
                <span class="info-value">{{ \Carbon\Carbon::now()->format('d/m/Y') }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Moneda:&nbsp;</span>
                <span class="info-value">{{ $order->currency }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Estado:&nbsp;</span>
                <span class="info-value">Orden de Compra Confirmada</span>
            </div>
        </div>
        <div class="info-box">
            <div class="info-title">Proveedor</div>
            <div class="info-row">
                <span class="info-value" style="font-size:13px;">{{ $supplierName }}</span>
            </div>
            @if($supplierEmail)
            <div class="info-row" style="margin-top:4px;">
                <span class="info-label">Correo:&nbsp;</span>
                <span class="info-value" style="font-weight:normal; color:#1d4ed8;">{{ $supplierEmail }}</span>
            </div>
            @endif
        </div>
    </div>

    {{-- ── MENSAJE DE CONFIRMACIÓN ── --}}
    <div class="confirm-box">
        Por medio de la presente, confirmamos la <strong>Orden de Compra N° {{ $order->order_number }}</strong>
        con los productos y precios detallados a continuación.<br>
        Por favor proceda con el despacho según las condiciones acordadas.
    </div>

    {{-- ── ITEMS TABLE ── --}}
    <div class="items-title">Detalle de productos confirmados</div>
    <table class="items">
        <thead>
            <tr>
                <th style="width:30px;">#</th>
                <th>Producto</th>
                <th style="width:50px;">UdM</th>
                <th class="right" style="width:80px;">Cantidad</th>
                <th class="right" style="width:110px;">Precio unit.</th>
                <th class="right" style="width:100px;">Importe</th>
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

    {{-- ── INSTRUCCIONES DE DESPACHO ── --}}
    <div class="delivery-box">
        <div class="delivery-title">Instrucciones de despacho</div>
        <div class="delivery-text">
            Por favor coordine el despacho de los productos indicados y confirme la fecha estimada de entrega.<br>
            Incluya esta orden de compra en el envío para facilitar la recepción.
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
        Este documento es una Orden de Compra oficial.
    </div>

</body>
</html>
