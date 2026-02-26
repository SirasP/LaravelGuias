<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Acta de Entrega EPP – {{ $destinatario }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10pt;
            color: #1a1a2e;
            background: #ffffff;
            line-height: 1.45;
        }

        .page {
            padding: 18mm 16mm 20mm 16mm;
        }

        /* ── Header top bar ── */
        .header-bar {
            width: 100%;
            border-bottom: 3px solid #1e40af;
            margin-bottom: 12pt;
        }
        .header-bar td {
            padding-bottom: 8pt;
            vertical-align: middle;
        }
        .header-left { width: 60%; }
        .header-right { width: 40%; text-align: right; }

        .company-name {
            font-size: 14pt;
            font-weight: bold;
            color: #1e3a8a;
        }
        .company-sub {
            font-size: 8pt;
            color: #64748b;
            margin-top: 2pt;
        }
        .doc-meta {
            font-size: 8.5pt;
            color: #64748b;
            line-height: 1.6;
        }
        .doc-meta strong {
            font-size: 10pt;
            color: #1e3a8a;
        }

        /* ── Title band ── */
        .title-band {
            background-color: #1e40af;
            color: #ffffff;
            text-align: center;
            padding: 8pt 0 7pt 0;
            margin-bottom: 12pt;
        }
        .title-band h1 {
            font-size: 11.5pt;
            font-weight: bold;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }
        .title-band p {
            font-size: 8pt;
            margin-top: 2pt;
            color: #bfdbfe;
        }

        /* ── Info table ── */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12pt;
            font-size: 9pt;
        }
        .info-table td {
            padding: 5pt 7pt;
            border: 1px solid #cbd5e1;
        }
        .lbl {
            background-color: #eff6ff;
            font-weight: bold;
            color: #1e40af;
            width: 30%;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .val {
            color: #0f172a;
            font-weight: 600;
        }

        /* ── Section title ── */
        .section-title {
            font-size: 8.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #1e40af;
            border-bottom: 1.5px solid #bfdbfe;
            padding-bottom: 3pt;
            margin-bottom: 7pt;
            margin-top: 12pt;
        }

        /* ── Items table ── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12pt;
            font-size: 9pt;
        }
        .items-table thead tr {
            background-color: #1e40af;
            color: #ffffff;
        }
        .items-table thead th {
            padding: 5pt 7pt;
            text-align: left;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .items-table thead th.right { text-align: right; }
        .items-table thead th.center { text-align: center; }

        .items-table tbody tr.odd  { background-color: #ffffff; }
        .items-table tbody tr.even { background-color: #f0f7ff; }

        .items-table tbody td {
            padding: 5pt 7pt;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }
        .items-table tbody td.right  { text-align: right; }
        .items-table tbody td.center { text-align: center; color: #64748b; font-size: 8pt; }

        .product-name { font-weight: 600; color: #0f172a; }
        .product-code { font-size: 7.5pt; color: #64748b; }

        .items-table tfoot td {
            padding: 5pt 7pt;
            background-color: #eff6ff;
            font-weight: bold;
            font-size: 9pt;
            border-top: 2px solid #93c5fd;
        }
        .items-table tfoot td.right { text-align: right; }

        /* ── History table ── */
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12pt;
            font-size: 8.5pt;
        }
        .history-table thead tr { background-color: #1e40af; color: #ffffff; }
        .history-table thead th {
            padding: 4pt 7pt;
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            text-align: left;
        }
        .history-table thead th.right { text-align: right; }
        .history-table tbody tr.odd  { background-color: #ffffff; }
        .history-table tbody tr.even { background-color: #f0f7ff; }
        .history-table tbody td {
            padding: 4pt 7pt;
            border-bottom: 1px solid #e2e8f0;
        }
        .history-table tbody td.right { text-align: right; }
        .mov-id { color: #1e40af; font-weight: 600; }
        .notas-col { color: #64748b; font-size: 7.5pt; }

        /* ── Signature section ── */
        .sig-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10pt;
        }
        .sig-cell {
            width: 48%;
            text-align: center;
            padding: 0 8pt;
            vertical-align: bottom;
        }
        .sig-spacer { width: 4%; }
        .sig-line-spacer {
            height: 44pt;
        }
        .sig-line {
            border-top: 1.5px solid #1e40af;
            margin-bottom: 5pt;
        }
        .sig-name {
            font-size: 8.5pt;
            font-weight: bold;
            color: #1e3a8a;
        }
        .sig-role {
            font-size: 8pt;
            color: #64748b;
            margin-top: 2pt;
        }
        .sig-rut {
            font-size: 7.5pt;
            color: #94a3b8;
            margin-top: 3pt;
        }

        /* ── Footer ── */
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18pt;
            border-top: 1px solid #e2e8f0;
            padding-top: 6pt;
        }
        .footer-left {
            font-size: 7.5pt;
            color: #94a3b8;
            width: 60%;
            padding-top: 5pt;
        }
        .footer-right {
            font-size: 7.5pt;
            color: #94a3b8;
            text-align: right;
            width: 40%;
            padding-top: 5pt;
        }
    </style>
</head>
<body>
<div class="page">

    {{-- ══ HEADER ══ --}}
    <table class="header-bar" cellpadding="0" cellspacing="0">
        <tr>
            <td class="header-left">
                <div class="company-name">Sistema de Inventario</div>
                <div class="company-sub">Control de Equipos de Protección Personal</div>
            </td>
            <td class="header-right">
                <div class="doc-meta">
                    Movimientos: <strong>{{ $movements->count() }}</strong><br>
                    Emitido: {{ now()->format('d/m/Y H:i') }}
                </div>
            </td>
        </tr>
    </table>

    {{-- ══ TITLE BAND ══ --}}
    <div class="title-band">
        <h1>Acta de Entrega de Elementos de Protección Personal</h1>
        <p>Registro de entrega de EPP — Uso Obligatorio según Decreto Supremo N° 594</p>
    </div>

    {{-- ══ INFO TABLE ══ --}}
    <table class="info-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="lbl">Trabajador / Destinatario</td>
            <td class="val" colspan="3">{{ $destinatario }}</td>
        </tr>
        <tr>
            <td class="lbl">Tipo de registro</td>
            <td class="val" style="width:20%">{{ $tipo ?: 'EPP' }}</td>
            <td class="lbl">Período cubierto</td>
            <td class="val">
                @if($primeraMov && $ultimaMov && $primeraMov !== $ultimaMov)
                    {{ \Carbon\Carbon::parse($primeraMov)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($ultimaMov)->format('d/m/Y') }}
                @elseif($ultimaMov)
                    {{ \Carbon\Carbon::parse($ultimaMov)->format('d/m/Y') }}
                @else
                    —
                @endif
            </td>
        </tr>
        <tr>
            <td class="lbl">N° de entregas</td>
            <td class="val">{{ $movements->count() }} {{ $movements->count() === 1 ? 'entrega' : 'entregas' }}</td>
            <td class="lbl">Total unidades entregadas</td>
            <td class="val">{{ number_format($consolidatedLines->sum('cantidad_total'), 2, ',', '.') }}</td>
        </tr>
    </table>

    {{-- ══ PRODUCTS TABLE ══ --}}
    <div class="section-title">Detalle de Elementos Entregados</div>

    <table class="items-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th class="center" style="width:24pt">N°</th>
                <th>Descripción del Elemento</th>
                <th style="width:60pt">Código</th>
                <th class="right" style="width:70pt">Cantidad Total</th>
                <th style="width:40pt">Unidad</th>
            </tr>
        </thead>
        <tbody>
            @foreach($consolidatedLines as $i => $line)
            <tr class="{{ $i % 2 === 0 ? 'odd' : 'even' }}">
                <td class="center">{{ $i + 1 }}</td>
                <td>
                    <span class="product-name">{{ $line->producto }}</span>
                    @if($line->codigo)
                        <br><span class="product-code">{{ $line->codigo }}</span>
                    @endif
                </td>
                <td style="font-size:8.5pt; color:#475569;">{{ $line->codigo ?? '—' }}</td>
                <td class="right" style="font-weight:700;">{{ number_format((float)$line->cantidad_total, 2, ',', '.') }}</td>
                <td style="color:#475569;">{{ $line->unidad ?? 'UN' }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align:right; font-size:8.5pt;">Total unidades entregadas:</td>
                <td class="right">{{ number_format($consolidatedLines->sum('cantidad_total'), 2, ',', '.') }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    {{-- ══ DELIVERY HISTORY (only if more than 1 movement) ══ --}}
    @if($movements->count() > 1)
    <div class="section-title">Historial de Entregas Parciales</div>
    <table class="history-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th style="width:60pt">Fecha</th>
                <th style="width:60pt">Movimiento</th>
                <th>Notas</th>
                <th class="right" style="width:70pt">Artículos</th>
            </tr>
        </thead>
        <tbody>
            @foreach($movements as $mi => $m)
            <tr class="{{ $mi % 2 === 0 ? 'odd' : 'even' }}">
                <td>{{ \Carbon\Carbon::parse($m->ocurrio_el)->format('d/m/Y') }}</td>
                <td class="mov-id">#{{ $m->id }}</td>
                <td class="notas-col">{{ $m->notas ?? '—' }}</td>
                <td class="right">{{ number_format((float)$m->cantidad_total, 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- ══ SIGNATURES ══ --}}
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
                <div class="sig-name">{{ $destinatario }}</div>
                <div class="sig-role">Trabajador / Destinatario</div>
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

    {{-- ══ FOOTER ══ --}}
    <table class="footer-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="footer-left">
                Documento generado el {{ now()->format('d/m/Y') }} a las {{ now()->format('H:i') }} hrs.
                &bull; Sistema de Inventario FIFO
            </td>
            <td class="footer-right">
                Este documento tiene validez legal al ser firmado por ambas partes.
            </td>
        </tr>
    </table>

</div>
</body>
</html>
