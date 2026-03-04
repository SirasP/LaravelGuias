<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @page {
            margin: 16mm 16mm 16mm 16mm;
        }

        * { margin: 0; padding: 0; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #0f172a;
            background: #ffffff;
            line-height: 1.45;
        }

        table { border-collapse: collapse; }

        .sp16 { height: 16px; font-size: 0; line-height: 0; }
        .sp10 { height: 10px; font-size: 0; line-height: 0; }
        .sp8  { height: 8px; font-size: 0; line-height: 0; }

        .header-wrap {
            background: #1d4ed8;
            color: #ffffff;
            padding: 14px 16px;
        }
        .header-table { width: 100%; }
        .company-name { font-size: 19px; font-weight: bold; }
        .company-tagline { font-size: 9px; color: #dbeafe; }
        .doc-type {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #bfdbfe;
            font-weight: bold;
            text-align: right;
        }
        .doc-number {
            font-size: 20px;
            color: #ffffff;
            font-weight: bold;
            text-align: right;
        }
        .status-line {
            text-align: right;
            font-size: 10px;
            color: #dbeafe;
            font-weight: bold;
        }

        .info-table { width: 100%; }
        .info-left, .info-right {
            width: 50%;
            vertical-align: top;
            border: 1px solid #cbd5e1;
            padding: 11px 12px;
            background: #ffffff;
        }
        .info-right { border-left: none; background: #f8fafc; }
        .info-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #475569;
            margin-bottom: 8px;
        }
        .info-row { margin-bottom: 4px; }
        .label { color: #64748b; font-size: 10px; }
        .value { color: #0f172a; font-size: 11px; font-weight: bold; }
        .supplier-name { font-size: 13px; font-weight: bold; color: #1d4ed8; margin-bottom: 3px; }
        .supplier-mail { font-size: 10px; color: #334155; }

        .message-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-left: 4px solid #1d4ed8;
            padding: 9px 12px;
            color: #1e293b;
            font-size: 11px;
            line-height: 1.55;
        }
        .confirm-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-left: 4px solid #1d4ed8;
            padding: 9px 12px;
            color: #1e293b;
            font-size: 11px;
            line-height: 1.55;
        }

        .section-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #475569;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
        }
        .items thead tr {
            background: #1d4ed8;
            color: #ffffff;
        }
        .items thead th {
            padding: 8px 10px;
            text-align: left;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .items thead th.right { text-align: right; }
        .items tbody td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10.5px;
            color: #1e293b;
            vertical-align: top;
        }
        .items tbody tr.row-even { background: #f8fafc; }
        .items td.center { text-align: center; }
        .items td.right { text-align: right; }
        .mono { font-family: DejaVu Sans Mono, monospace; }
        .product { line-height: 1.35; }
        .empty { color: #94a3b8; font-style: italic; }

        .totals { width: 100%; }
        .totals-spacer { width: 60%; }
        .totals-box { width: 40%; }
        .totals-inner { width: 100%; }
        .totals-inner td {
            background: #1d4ed8;
            color: #ffffff;
            padding: 9px 11px;
            font-size: 13px;
            font-weight: bold;
        }
        .totals-inner td.right { text-align: right; }

        .delivery-box {
            padding: 10px 12px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-left: 4px solid #16a34a;
        }
        .delivery-title {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #16a34a;
            margin-bottom: 4px;
        }
        .delivery-text { font-size: 10px; color: #1e293b; line-height: 1.55; }

        .notes-box {
            padding: 9px 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .notes-title {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
            margin-bottom: 4px;
        }
        .notes-text { font-size: 10.5px; color: #334155; line-height: 1.55; white-space: pre-wrap; }

        .footer {
            margin-top: 20px;
            padding-top: 8px;
            border-top: 1px solid #e2e8f0;
            font-size: 8.5px;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>

    <div class="header-wrap">
        <table class="header-table">
            <tr>
                <td style="width:60%; vertical-align:middle;">
                    <div class="company-name">{{ config('app.name', 'Empresa') }}</div>
                    <div class="company-tagline">Sistema de Órdenes de Compra</div>
                </td>
                <td style="width:40%; vertical-align:middle;">
                    <div class="doc-type">Orden de Compra</div>
                    <div class="doc-number">{{ $order->order_number }}</div>
                    <div class="status-line">CONFIRMADA</div>
                </td>
            </tr>
        </table>
    </div>
    <div class="sp16">&nbsp;</div>

    <table class="info-table">
        <tr>
            <td class="info-left">
                <div class="info-title">Información del documento</div>
                <div class="info-row">
                    <span class="label">N° Orden:</span>
                    <span class="value">{{ $order->order_number }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Fecha:</span>
                    <span class="value">
                        @if(!empty($order->updated_at))
                            {{ \Carbon\Carbon::parse($order->updated_at)->format('d/m/Y') }}
                        @else
                            {{ \Carbon\Carbon::now()->format('d/m/Y') }}
                        @endif
                    </span>
                </div>
                <div class="info-row">
                    <span class="label">Moneda:</span>
                    <span class="value">{{ $order->currency }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Estado:</span>
                    <span class="value">Orden de Compra Confirmada</span>
                </div>
            </td>
            <td class="info-right">
                <div class="info-title">Proveedor</div>
                <div class="supplier-name">{{ $supplierName }}</div>
                @if($supplierEmail)
                    <div class="supplier-mail">{{ $supplierEmail }}</div>
                @endif
            </td>
        </tr>
    </table>
    <div class="sp10">&nbsp;</div>

    <div class="confirm-box">
        Por medio de la presente, confirmamos la <strong>Orden de Compra N° {{ $order->order_number }}</strong>
        con los productos y precios detallados a continuación.<br>
        Por favor proceda con el despacho según las condiciones acordadas.
    </div>
    <div class="sp10">&nbsp;</div>

    <div class="section-title">Detalle de productos confirmados</div>
    <div class="sp8">&nbsp;</div>

    <table class="items">
        <thead>
            <tr>
                <th style="width:28px; text-align:center;">#</th>
                <th>Producto</th>
                <th style="width:50px;">UdM</th>
                <th class="right" style="width:80px;">Cantidad</th>
                <th class="right" style="width:110px;">Precio unit.</th>
                <th class="right" style="width:100px;">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $i => $item)
            <tr class="{{ $i % 2 === 1 ? 'row-even' : '' }}">
                <td class="center mono">{{ $i + 1 }}</td>
                <td class="product">{{ $item->product_name }}</td>
                <td>{{ $item->unit ?: 'UN' }}</td>
                <td class="right mono">{{ number_format((float) $item->quantity, 4, ',', '.') }}</td>
                <td class="right mono">
                    @if((float)$item->unit_price > 0)
                        {{ number_format((float) $item->unit_price, 2, ',', '.') }}
                    @else
                        <span class="empty">sin precio</span>
                    @endif
                </td>
                <td class="right mono">
                    @if((float)$item->line_total > 0)
                        {{ number_format((float) $item->line_total, 2, ',', '.') }}
                    @else
                        <span class="empty">sin total</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div class="sp10">&nbsp;</div>

    <table class="totals">
        <tr>
            <td class="totals-spacer"></td>
            <td class="totals-box">
                <table class="totals-inner">
                    <tr>
                        <td>Total {{ $order->currency }}</td>
                        <td class="right mono">
                            @if((float)$order->total > 0)
                                {{ number_format((float) $order->total, 2, ',', '.') }}
                            @else
                                0,00
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <div class="sp10">&nbsp;</div>

    <div class="delivery-box">
        <div class="delivery-title">Instrucciones de despacho</div>
        <div class="delivery-text">
            Por favor coordine el despacho de los productos indicados y confirme la fecha estimada de entrega.<br>
            Incluya esta orden de compra en el envío para facilitar la recepción.
        </div>
    </div>

    @if($order->notes)
        <div class="sp10">&nbsp;</div>
        <div class="notes-box">
            <div class="notes-title">Notas adicionales</div>
            <div class="notes-text">{{ $order->notes }}</div>
        </div>
    @endif

    <div class="footer">
        Documento generado el {{ now()->format('d/m/Y H:i') }} -
        {{ config('app.name', 'Sistema') }} -
        Este documento es una Orden de Compra oficial.
    </div>

</body>
</html>
