<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Acta de Entrega EPP – {{ $destinatario }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10pt;
            color: #1a1a2e;
            background: #fff;
            line-height: 1.4;
        }

        /* ── Page layout ── */
        .page {
            width: 100%;
            padding: 20mm 18mm 22mm 18mm;
        }

        /* ── Header ── */
        .header {
            border-bottom: 3px solid #1e40af;
            padding-bottom: 10px;
            margin-bottom: 14px;
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
            font-size: 14pt;
            font-weight: bold;
            color: #1e3a8a;
            letter-spacing: 0.03em;
        }
        .company-sub {
            font-size: 8pt;
            color: #64748b;
            margin-top: 2px;
        }
        .doc-number {
            font-size: 9pt;
            color: #64748b;
        }
        .doc-number strong {
            font-size: 11pt;
            color: #1e3a8a;
        }

        /* ── Document title band ── */
        .title-band {
            background: #1e40af;
            color: #fff;
            text-align: center;
            padding: 8px 0 7px 0;
            border-radius: 4px;
            margin-bottom: 14px;
        }
        .title-band h1 {
            font-size: 12pt;
            font-weight: bold;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .title-band p {
            font-size: 8pt;
            opacity: 0.85;
            margin-top: 2px;
        }

        /* ── Info block ── */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .info-table td {
            padding: 5px 8px;
            font-size: 9.5pt;
            border: 1px solid #cbd5e1;
        }
        .info-table .lbl {
            background: #eff6ff;
            font-weight: bold;
            color: #1e40af;
            width: 28%;
            font-size: 8.5pt;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .info-table .val {
            color: #0f172a;
            font-weight: 600;
        }

        /* ── Products table ── */
        .section-title {
            font-size: 8.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #1e40af;
            border-bottom: 1.5px solid #bfdbfe;
            padding-bottom: 4px;
            margin-bottom: 8px;
            margin-top: 14px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 9pt;
        }
        .items-table thead tr {
            background: #1e40af;
            color: #fff;
        }
        .items-table thead th {
            padding: 6px 8px;
            text-align: left;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }
        .items-table thead th.right { text-align: right; }
        .items-table tbody tr:nth-child(even) { background: #f0f7ff; }
        .items-table tbody tr:nth-child(odd)  { background: #fff; }
        .items-table tbody td {
            padding: 5px 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }
        .items-table tbody td.right {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        .items-table tbody td.center { text-align: center; }
        .items-table .product-name { font-weight: 600; color: #0f172a; }
        .items-table .product-code { font-size: 8pt; color: #64748b; }
        .items-table tfoot td {
            padding: 6px 8px;
            background: #eff6ff;
            font-weight: bold;
            font-size: 9pt;
            border-top: 2px solid #93c5fd;
        }

        /* ── Declaration ── */
        .declaration-box {
            border: 1.5px solid #bfdbfe;
            border-radius: 4px;
            background: #f8faff;
            padding: 10px 12px;
            margin-bottom: 18px;
        }
        .declaration-box p {
            font-size: 9pt;
            color: #1e293b;
            line-height: 1.6;
            text-align: justify;
        }
        .declaration-box p strong {
            color: #1e40af;
        }

        /* ── Obligation note ── */
        .obligation-note {
            font-size: 8pt;
            color: #64748b;
            margin-bottom: 20px;
            padding: 6px 10px;
            border-left: 3px solid #93c5fd;
            background: #f0f9ff;
        }

        /* ── Signature area ── */
        .sig-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .sig-cell {
            width: 48%;
            padding: 0 8px;
            vertical-align: bottom;
            text-align: center;
        }
        .sig-spacer {
            width: 4%;
        }
        .sig-line {
            border-top: 1.5px solid #1e40af;
            margin-bottom: 6px;
            margin-top: 50px;
        }
        .sig-name {
            font-size: 8.5pt;
            font-weight: bold;
            color: #1e3a8a;
        }
        .sig-role {
            font-size: 8pt;
            color: #64748b;
            margin-top: 2px;
        }
        .sig-rut {
            font-size: 8pt;
            color: #94a3b8;
            margin-top: 2px;
        }

        /* ── Footer ── */
        .footer {
            margin-top: 20px;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
            display: table;
            width: 100%;
        }
        .footer-left {
            display: table-cell;
            vertical-align: middle;
            font-size: 7.5pt;
            color: #94a3b8;
        }
        .footer-right {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            font-size: 7.5pt;
            color: #94a3b8;
        }
    </style>
</head>
<body>
<div class="page">

    {{-- ══ HEADER ══ --}}
    <div class="header">
        <div class="header-left">
            <div class="company-name">Sistema de Inventario</div>
            <div class="company-sub">Control de Equipos de Protección Personal</div>
        </div>
        <div class="header-right">
            <div class="doc-number">
                Movimientos: <strong>{{ $movements->count() }}</strong><br>
                Emitido: {{ now()->format('d/m/Y H:i') }}
            </div>
        </div>
    </div>

    {{-- ══ TITLE ══ --}}
    <div class="title-band">
        <h1>Acta de Entrega de Elementos de Protección Personal</h1>
        <p>Registro de entrega de EPP — Uso Obligatorio según Decreto Supremo N° 594</p>
    </div>

    {{-- ══ INFO TABLE ══ --}}
    <table class="info-table">
        <tr>
            <td class="lbl">Trabajador / Destinatario</td>
            <td class="val" colspan="3">{{ $destinatario }}</td>
        </tr>
        <tr>
            <td class="lbl">Tipo de registro</td>
            <td class="val">{{ $tipo ?: 'EPP' }}</td>
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
            <td class="lbl">Total artículos entregados</td>
            <td class="val">{{ number_format($consolidatedLines->sum('cantidad_total'), 2, ',', '.') }} unidades</td>
        </tr>
    </table>

    {{-- ══ PRODUCTS TABLE ══ --}}
    <div class="section-title">Detalle de Elementos Entregados</div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width:28px">N°</th>
                <th>Descripción del Elemento</th>
                <th>Código</th>
                <th class="right">Cantidad Total</th>
                <th>Unidad</th>
            </tr>
        </thead>
        <tbody>
            @foreach($consolidatedLines as $i => $line)
            <tr>
                <td class="center" style="color:#64748b; font-size:8pt;">{{ $i + 1 }}</td>
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
                <td colspan="3" style="text-align:right;">Total unidades entregadas:</td>
                <td class="right">{{ number_format($consolidatedLines->sum('cantidad_total'), 2, ',', '.') }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    {{-- ══ DELIVERY HISTORY ══ --}}
    @if($movements->count() > 1)
    <div class="section-title">Historial de Entregas Parciales</div>
    <table class="items-table" style="font-size:8.5pt;">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>N° Movimiento</th>
                <th>Notas</th>
                <th class="right">Artículos</th>
            </tr>
        </thead>
        <tbody>
            @foreach($movements as $m)
            <tr>
                <td>{{ \Carbon\Carbon::parse($m->ocurrio_el)->format('d/m/Y') }}</td>
                <td style="color:#1e40af; font-weight:600;">#{{ $m->id }}</td>
                <td style="color:#64748b;">{{ $m->notas ?? '—' }}</td>
                <td class="right">{{ number_format((float)$m->cantidad_total, 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- ══ DECLARATION ══ --}}
    <div class="section-title" style="margin-top:16px;">Declaración y Compromiso</div>

    <div class="declaration-box">
        <p>
            Yo, <strong>{{ $destinatario }}</strong>, declaro haber recibido a mi entera satisfacción
            los elementos de protección personal (EPP) detallados en el presente documento, correspondientes
            al período
            @if($primeraMov && $ultimaMov && $primeraMov !== $ultimaMov)
                comprendido entre el <strong>{{ \Carbon\Carbon::parse($primeraMov)->format('d \d\e F \d\e Y') }}</strong>
                y el <strong>{{ \Carbon\Carbon::parse($ultimaMov)->format('d \d\e F \d\e Y') }}</strong>
            @elseif($ultimaMov)
                del <strong>{{ \Carbon\Carbon::parse($ultimaMov)->format('d \d\e F \d\e Y') }}</strong>
            @endif
            .
        </p>
        <br>
        <p>
            Me comprometo a utilizar los elementos recibidos en forma correcta, mantenerlos en buen estado,
            reportar inmediatamente cualquier deterioro o pérdida, y no traspasar dichos elementos a terceras
            personas. Entiendo que la omisión en el uso de los EPP puede acarrear medidas disciplinarias según
            el Reglamento Interno de Orden, Higiene y Seguridad vigente.
        </p>
    </div>

    <div class="obligation-note">
        El uso de los elementos de protección personal es de <strong>carácter obligatorio</strong>
        conforme al Decreto Supremo N° 594 del MINSAL y la Ley N° 16.744. El incumplimiento puede
        ser causal de medidas disciplinarias.
    </div>

    {{-- ══ SIGNATURES ══ --}}
    <table class="sig-table">
        <tr>
            <td class="sig-cell">
                <div class="sig-line"></div>
                <div class="sig-name">Entregado por</div>
                <div class="sig-role">Representante de la empresa</div>
                <div class="sig-rut">Nombre y firma</div>
            </td>
            <td class="sig-spacer"></td>
            <td class="sig-cell">
                <div class="sig-line"></div>
                <div class="sig-name">{{ $destinatario }}</div>
                <div class="sig-role">Trabajador / Destinatario</div>
                <div class="sig-rut">Nombre, RUT y firma</div>
            </td>
        </tr>
        <tr>
            <td class="sig-cell" style="padding-top:6px;">
                <div class="sig-rut">Fecha: _______________________</div>
            </td>
            <td class="sig-spacer"></td>
            <td class="sig-cell" style="padding-top:6px;">
                <div class="sig-rut">Fecha: _______________________</div>
            </td>
        </tr>
    </table>

    {{-- ══ FOOTER ══ --}}
    <div class="footer">
        <div class="footer-left">
            Documento generado el {{ now()->format('d/m/Y') }} a las {{ now()->format('H:i') }} hrs. •
            Sistema de Inventario FIFO
        </div>
        <div class="footer-right">
            Este documento tiene validez legal al ser firmado por ambas partes.
        </div>
    </div>

</div>
</body>
</html>
