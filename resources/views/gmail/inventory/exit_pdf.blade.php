<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Comprobante de Entrega – Mov. #{{ $movement->id }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10pt; color: #1a1a2e; background: #ffffff; line-height: 1.45; }
        .page { padding: 18mm 16mm 20mm 16mm; }

        .header-bar { width: 100%; border-collapse: collapse; border-bottom: 3px solid #1e40af; margin-bottom: 12pt; }
        .header-bar td { padding-bottom: 8pt; vertical-align: middle; }
        .header-left { width: 60%; }
        .header-right { width: 40%; text-align: right; }
        .company-name { font-size: 14pt; font-weight: bold; color: #1e3a8a; }
        .company-sub { font-size: 8pt; color: #64748b; margin-top: 2pt; }
        .doc-meta { font-size: 8.5pt; color: #64748b; line-height: 1.6; }
        .doc-meta strong { font-size: 10pt; color: #1e3a8a; }

        .title-band { background-color: #1e40af; color: #ffffff; text-align: center; padding: 8pt 0 7pt 0; margin-bottom: 12pt; }
        .title-band h1 { font-size: 11.5pt; font-weight: bold; letter-spacing: 0.06em; text-transform: uppercase; }
        .title-band p { font-size: 8pt; margin-top: 2pt; color: #bfdbfe; }

        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 12pt; font-size: 9pt; }
        .info-table td { padding: 5pt 7pt; border: 1px solid #cbd5e1; }
        .lbl { background-color: #eff6ff; font-weight: bold; color: #1e40af; width: 30%; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.04em; }
        .val { color: #0f172a; font-weight: 600; }

        .section-title { font-size: 8.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.07em; color: #1e40af; border-bottom: 1.5px solid #bfdbfe; padding-bottom: 3pt; margin-bottom: 7pt; margin-top: 12pt; }

        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 12pt; font-size: 9pt; }
        .items-table thead tr { background-color: #1e40af; color: #ffffff; }
        .items-table thead th { padding: 5pt 7pt; text-align: left; font-size: 8pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.04em; }
        .items-table thead th.right { text-align: right; }
        .items-table tbody tr.odd  { background-color: #ffffff; }
        .items-table tbody tr.even { background-color: #f0f7ff; }
        .items-table tbody td { padding: 5pt 7pt; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .items-table tbody td.right { text-align: right; }
        .product-name { font-weight: 600; color: #0f172a; }
        .product-code { font-size: 7.5pt; color: #64748b; }
        .items-table tfoot td { padding: 5pt 7pt; background-color: #eff6ff; font-weight: bold; font-size: 9pt; border-top: 2px solid #93c5fd; }
        .items-table tfoot td.right { text-align: right; }

        .sig-table { width: 100%; border-collapse: collapse; margin-top: 20pt; }
        .sig-cell { width: 48%; text-align: center; padding: 0 8pt; vertical-align: bottom; }
        .sig-spacer { width: 4%; }
        .sig-line-spacer { height: 44pt; }
        .sig-line { border-top: 1.5px solid #1e40af; margin-bottom: 5pt; }
        .sig-name { font-size: 8.5pt; font-weight: bold; color: #1e3a8a; }
        .sig-role { font-size: 8pt; color: #64748b; margin-top: 2pt; }
        .sig-rut { font-size: 7.5pt; color: #94a3b8; margin-top: 3pt; }

        .footer-table { width: 100%; border-collapse: collapse; margin-top: 18pt; border-top: 1px solid #e2e8f0; }
        .footer-left { font-size: 7.5pt; color: #94a3b8; width: 60%; padding-top: 5pt; }
        .footer-right { font-size: 7.5pt; color: #94a3b8; text-align: right; width: 40%; padding-top: 5pt; }
    </style>
    @php
        $isEpp = ($movement->tipo_salida ?? '') === 'EPP';
        $isAjuste = $movement->tipo === 'AJUSTE';
    @endphp
</head>
<body>
<div class="page">

    <table class="header-bar" cellpadding="0" cellspacing="0">
        <tr>
            <td class="header-left">
                <div class="company-name">Sistema de Inventario</div>
                <div class="company-sub">Control de Equipos de Protección Personal y Materiales</div>
            </td>
            <td class="header-right">
                <div class="doc-meta">
                    Movimiento: <strong>#{{ $movement->id }}</strong><br>
                    Emitido: {{ now()->format('d/m/Y H:i') }}
                </div>
            </td>
        </tr>
    </table>

    <div class="title-band">
        @if($isAjuste)
            <h1>Comprobante de Ajuste de Inventario</h1>
            <p>Registro de ajuste — {{ $movement->destinatario ?? 'Sin motivo' }}</p>
        @elseif($isEpp)
            <h1>Comprobante de Entrega de EPP</h1>
            <p>Registro de entrega — Uso Obligatorio según D.S. N° 594</p>
        @else
            <h1>Comprobante de Salida de Inventario</h1>
            <p>Registro de movimiento de salida de materiales</p>
        @endif
    </div>

    <table class="info-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="lbl">{{ $isAjuste ? 'Motivo' : 'Destinatario' }}</td>
            <td class="val" colspan="3">{{ $movement->destinatario ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Tipo</td>
            <td class="val" style="width:20%">{{ $movement->tipo_salida ?? $movement->tipo }}</td>
            <td class="lbl">Fecha</td>
            <td class="val">{{ \Carbon\Carbon::parse($movement->ocurrio_el)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="lbl">N° productos</td>
            <td class="val">{{ $lines->count() }} {{ $lines->count() === 1 ? 'ítem' : 'ítems' }}</td>
            <td class="lbl">Total unidades</td>
            <td class="val">{{ number_format($lines->sum('cantidad'), 2, ',', '.') }}</td>
        </tr>
        @if($movement->notas)
        <tr>
            <td class="lbl">Notas</td>
            <td class="val" colspan="3">{{ $movement->notas }}</td>
        </tr>
        @endif
    </table>

    <div class="section-title">Detalle de Productos</div>
    <table class="items-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th style="width:24pt">N°</th>
                <th>Descripción</th>
                <th style="width:60pt">Código</th>
                <th class="right" style="width:70pt">Cantidad</th>
                <th style="width:40pt">Unidad</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lines as $i => $line)
            <tr class="{{ $i % 2 === 0 ? 'odd' : 'even' }}">
                <td style="text-align:center; color:#64748b; font-size:8pt;">{{ $i + 1 }}</td>
                <td>
                    <span class="product-name">{{ $line->producto }}</span>
                    @if($line->codigo)
                        <br><span class="product-code">{{ $line->codigo }}</span>
                    @endif
                </td>
                <td style="font-size:8.5pt; color:#475569;">{{ $line->codigo ?? '—' }}</td>
                <td class="right" style="font-weight:700;">{{ number_format((float)$line->cantidad, 2, ',', '.') }}</td>
                <td style="color:#475569;">{{ $line->unidad ?? 'UN' }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align:right; font-size:8.5pt;">Total unidades:</td>
                <td class="right">{{ number_format($lines->sum('cantidad'), 2, ',', '.') }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    @if(!$isAjuste)
    <table class="sig-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="sig-cell">
                <div class="sig-line-spacer"></div>
                <div class="sig-line"></div>
                <div class="sig-name">Entregado por</div>
                <div class="sig-role">Representante de la empresa</div>
                <div class="sig-rut">Nombre y firma</div>
            </td>
            <td class="sig-spacer"></td>
            <td class="sig-cell">
                <div class="sig-line-spacer"></div>
                <div class="sig-line"></div>
                <div class="sig-name">{{ $movement->destinatario ?? 'Receptor' }}</div>
                <div class="sig-role">Trabajador / Receptor</div>
                <div class="sig-rut">Nombre, RUT y firma</div>
            </td>
        </tr>
        <tr>
            <td class="sig-cell" style="padding-top:5pt;">
                <div class="sig-rut">Fecha: _______________________</div>
            </td>
            <td class="sig-spacer"></td>
            <td class="sig-cell" style="padding-top:5pt;">
                <div class="sig-rut">Fecha: _______________________</div>
            </td>
        </tr>
    </table>
    @endif

    <table class="footer-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="footer-left">
                Documento generado el {{ now()->format('d/m/Y') }} a las {{ now()->format('H:i') }} hrs.
                &bull; Sistema de Inventario FIFO &bull; Mov. #{{ $movement->id }}
            </td>
            <td class="footer-right">
                @if(!$isAjuste)
                    Este documento tiene validez al ser firmado por ambas partes.
                @else
                    Ajuste registrado en el sistema de inventario.
                @endif
            </td>
        </tr>
    </table>

</div>
</body>
</html>
