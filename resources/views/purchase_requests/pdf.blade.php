{{--
    Plantilla del PDF corporativo.

    Reproduce la identidad del formulario en papel: tipografía con serifas,
    marcos negros y bloque de proveedores al pie. Puede imprimir tanto una
    revisión congelada (con $header y $items del snapshot) como la vista previa
    de un borrador (leyendo el modelo vivo y marcándose como borrador).
--}}
@php
    /** Datos: el snapshot manda; si no hay, se lee el modelo vivo. */
    $h = $header ?? [
        'folio' => $purchaseRequest->folio,
        'company_name' => $purchaseRequest->company_name_snapshot,
        'request_date' => $purchaseRequest->request_date?->toDateString(),
        'required_date' => $purchaseRequest->required_date?->toDateString(),
        'department' => $purchaseRequest->department,
        'requester_name' => $purchaseRequest->requester_name_snapshot,
        'requested_for_name' => $purchaseRequest->requested_for_name,
        'reason' => $purchaseRequest->reason,
        'priority' => $purchaseRequest->priority,
        'urgent_reason' => $purchaseRequest->urgent_reason,
        'cost_center' => $purchaseRequest->cost_center,
        'delivery_location' => $purchaseRequest->delivery_location,
        'suggested_suppliers' => $purchaseRequest->suggested_suppliers ?? [],
    ];

    $lines = collect($items ?? $purchaseRequest->items->map(fn ($i) => [
        'sort_order' => $i->sort_order,
        'product_service' => $i->product_service,
        'specification' => $i->specification,
        'quantity' => (string) $i->quantity,
        'unit' => $i->unit,
        'quantity_note' => $i->quantity_note,
        'destination' => $i->destination,
    ])->all());

    $fmtDate = static function ($value): string {
        if (blank($value)) {
            return '';
        }
        try {
            return \Illuminate\Support\Carbon::parse($value)->format('d-m-Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    };

    // La cantidad se muestra tal cual se guardó, sin ceros de relleno y con
    // coma decimal chilena. Nunca se redondea.
    $fmtQty = static function ($value): string {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }
        if (str_contains($text, '.')) {
            $text = rtrim(rtrim($text, '0'), '.');
        }
        return str_replace('.', ',', $text === '' ? '0' : $text);
    };

    $suppliers = array_values(array_filter((array) ($h['suggested_suppliers'] ?? [])));
    $status = $purchaseRequest->status;
    $isDraftPreview = $revision === null;

    // El original muestra la grilla completa aunque sobren renglones. Se
    // rellena con filas en blanco, nunca con ceros, y sólo si todo cabe en la
    // grilla estándar de 23 renglones.
    $blankRows = $lines->count() <= 23 ? 23 - $lines->count() : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $h['folio'] ?? 'Solicitud de compra' }}</title>
    <style>
        @page { margin: 12mm 12mm 15mm; }
        body { color: #000; font-family: "DejaVu Sans", sans-serif; font-size: 8.5px; line-height: 1.25; }
        table { border-collapse: collapse; width: 100%; }
        .serif { font-family: "DejaVu Serif", serif; }

        .title { font-family: "DejaVu Serif", serif; font-size: 19px; letter-spacing: 2px; text-align: center; }
        .brand { font-family: "DejaVu Serif", serif; font-size: 12px; font-weight: bold; letter-spacing: .5px; }
        .brand-sub { color: #444; font-size: 7.5px; font-style: italic; }

        .meta td { padding: 1.5px 0; }
        .meta .k { font-family: "DejaVu Serif", serif; font-size: 10px; font-weight: bold; text-align: right; padding-right: 10px; }
        .meta .v { font-size: 9.5px; text-align: right; width: 92px; }

        .idbox td { border: 1px solid #000; padding: 3px 6px; }
        .idbox .k { font-family: "DejaVu Serif", serif; font-weight: bold; width: 30%; }

        .section { font-family: "DejaVu Serif", serif; font-size: 12px; font-weight: bold; letter-spacing: .4px; text-align: center; }
        .label { font-family: "DejaVu Serif", serif; font-size: 9.5px; font-weight: bold; }
        .reason { border: 1px solid #000; min-height: 34px; padding: 4px 6px; white-space: pre-wrap; }

        .items th { border: 1px solid #000; font-family: "DejaVu Serif", serif; font-size: 9px; font-weight: bold; padding: 3px 5px; }
        .items td { border: 1px solid #000; height: 9px; padding: 1.5px 5px; vertical-align: top; word-wrap: break-word; }
        .items .n { text-align: right; width: 22px; }
        .items .qty { text-align: right; width: 52px; }
        .items .unit { width: 74px; }
        .items .spec { width: 168px; }
        .note { color: #333; font-size: 7px; line-height: 1.1; }

        .sup td { border: 1px solid #000; height: 11px; padding: 2px 6px; }
        .sup .k { font-family: "DejaVu Serif", serif; font-weight: bold; width: 30%; }

        .hist td { border-bottom: 1px solid #bbb; padding: 3px 4px; }
        .hist th { border-bottom: 1px solid #000; font-size: 8px; padding: 3px 4px; text-align: left; text-transform: uppercase; }

        .stamp { border: 1px solid #000; display: inline-block; font-weight: bold; padding: 2px 7px; }
        .draft { border: 1px dashed #666; color: #444; }
        .footer { border-top: 1px solid #999; bottom: -10mm; color: #444; font-size: 7.5px; left: 0; position: fixed; right: 0; padding-top: 4px; }
    </style>
</head>
<body>

{{-- Cabecera: marca a la izquierda, título al centro, datos a la derecha --}}
<table>
    <tr>
        <td style="width: 30%; vertical-align: top;">
            @if (file_exists(public_path('img/logo-ehe.png')))
                <img src="{{ public_path('img/logo-ehe.png') }}" alt="" style="max-height: 46px;">
            @else
                {{-- Sin asset aprobado no se dibuja un logo inventado. --}}
                <div class="brand">{{ $h['company_name'] ?? 'Agrícola EHE SpA' }}</div>
                <div class="brand-sub">Organic Raspberries from the South of Chile</div>
            @endif
        </td>
        <td style="width: 40%; vertical-align: top; padding-top: 6px;">
            <div class="title">SOLICITUD DE COMPRA</div>
        </td>
        <td style="width: 30%; vertical-align: top;">
            <table class="meta">
                <tr>
                    <td class="k">Fecha de solicitud</td>
                    <td class="v">{{ $fmtDate($h['request_date'] ?? null) }}</td>
                </tr>
                <tr>
                    <td class="k">N.º Solicitud:</td>
                    <td class="v">{{ $h['folio'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="k">Fecha requerida:</td>
                    <td class="v">{{ $fmtDate($h['required_date'] ?? null) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Área, solicitante y, si existe, la persona para quien se pide --}}
<table class="idbox" style="margin-top: 10px;">
    <tr>
        <td class="k">Área / Departamento</td>
        <td>{{ $h['department'] ?? '' }}</td>
    </tr>
    <tr>
        <td class="k">Solicitante</td>
        <td>{{ $h['requester_name'] ?? '' }}</td>
    </tr>
    @if (filled($h['requested_for_name'] ?? null))
        <tr>
            <td class="k">Solicitado para / por</td>
            <td>{{ $h['requested_for_name'] }}</td>
        </tr>
    @endif
    @if (filled($h['cost_center'] ?? null) || filled($h['delivery_location'] ?? null))
        <tr>
            <td class="k">Centro de costo / Entrega</td>
            <td>{{ collect([$h['cost_center'] ?? null, $h['delivery_location'] ?? null])->filter()->implode(' — ') }}</td>
        </tr>
    @endif
</table>

<div class="section" style="margin-top: 11px;">DETALLE DE LA SOLICITUD</div>

<div style="margin-top: 8px;">
    <div class="label" style="margin-bottom: 3px;">Motivo de la compra:</div>
    <div class="reason">{{ $h['reason'] ?? '' }}@if (($h['priority'] ?? '') === 'urgent' && filled($h['urgent_reason'] ?? null))

URGENTE: {{ $h['urgent_reason'] }}@endif</div>
</div>

{{-- Partidas. El encabezado se repite en cada página. --}}
<table class="items" style="margin-top: 10px;">
    <thead style="display: table-header-group;">
        <tr>
            <th class="n">N°</th>
            <th style="text-align: left;">Producto / Servicio</th>
            <th class="spec" style="text-align: left;">Especificación</th>
            <th class="qty">Cantidad</th>
            <th class="unit" style="text-align: left;">Unidad</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lines as $line)
            <tr>
                <td class="n">{{ $line['sort_order'] ?? $loop->iteration }}</td>
                <td>
                    {{ $line['product_service'] ?? '' }}
                    @if (filled($line['destination'] ?? null))
                        <div class="note">Destino: {{ $line['destination'] }}</div>
                    @endif
                </td>
                <td>{{ $line['specification'] ?? '' }}</td>
                <td class="qty">{{ $fmtQty($line['quantity'] ?? null) }}</td>
                <td>
                    {{ $line['unit'] ?? '' }}
                    @if (filled($line['quantity_note'] ?? null))
                        <div class="note">{{ $line['quantity_note'] }}</div>
                    @endif
                </td>
            </tr>
        @endforeach

        {{-- Renglones en blanco del formulario: vacíos, jamás con ceros. --}}
        @for ($i = 0; $i < $blankRows; $i++)
            <tr>
                <td class="n">{{ $lines->count() + $i + 1 }}</td>
                <td></td><td></td><td class="qty"></td><td></td>
            </tr>
        @endfor
    </tbody>
</table>

{{-- Proveedores sugeridos: sugerencia, nunca adjudicación --}}
<table class="sup" style="margin-top: 11px;">
    <tr>
        <td class="k">Nombre Proveedor</td>
        <td>{{ $suppliers[0] ?? '' }}</td>
    </tr>
    @for ($i = 1; $i < 4; $i++)
        <tr>
            <td class="k"></td>
            <td>{{ $suppliers[$i] ?? '' }}</td>
        </tr>
    @endfor
</table>

{{-- Estado, revisión e historial de la decisión --}}
<table style="margin-top: 9px;">
    <tr>
        <td>
            <span class="stamp {{ $isDraftPreview ? 'draft' : '' }}">
                {{ $status->icon() }} {{ $isDraftPreview ? 'BORRADOR — NO ENVIADA' : mb_strtoupper($status->label()) }}
            </span>
        </td>
        <td style="text-align: right;">
            <span class="label">Revisión:</span>
            {{ $isDraftPreview ? 'sin enviar' : ('N° '.$revision->revision_number) }}
        </td>
    </tr>
</table>

@php
    $decisions = $purchaseRequest->relationLoaded('events')
        ? $purchaseRequest->events->whereIn('event_type', ['approved', 'rejected', 'changes_requested', 'cancelled'])
        : collect();
@endphp
@if ($decisions->isNotEmpty())
    <table class="hist" style="margin-top: 10px;">
        <thead>
            <tr>
                <th style="width: 78px;">Fecha</th>
                <th style="width: 150px;">Responsable</th>
                <th style="width: 100px;">Decisión</th>
                <th>Comentario</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($decisions as $event)
                <tr>
                    <td>{{ optional($event->created_at)->format('d-m-Y H:i') }}</td>
                    <td>{{ $event->actor_name_snapshot }}</td>
                    <td>{{ $event->to_status?->label() ?? $event->event_type }}</td>
                    <td>{{ $event->comment }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="footer">
    <table>
        <tr>
            <td>{{ $h['folio'] ?? '' }}@if (! $isDraftPreview) · Revisión {{ $revision->revision_number }}@endif</td>
            <td style="text-align: right;">Generado el {{ now()->format('d-m-Y H:i') }}</td>
        </tr>
    </table>
</div>

</body>
</html>
