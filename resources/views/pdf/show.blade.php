<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between w-full gap-6">
            {{-- Izquierda --}}
            <div class="max-w-3xl">
                {{-- Breadcrumb --}}
                <nav class="text-sm text-gray-500 whitespace-nowrap">
                    <ol class="flex items-center gap-1">
                        <li>
                            <a href="{{ route('pdf.index') }}" class="hover:text-gray-900 transition">
                                PDFs
                            </a>
                        </li>
                        <li>/</li>
                        <h2 class=" font-semibold text-xl text-gray-800 leading-tight truncate">
                            {{ $import->original_name }}
                        </h2>

                    </ol>
                </nav>

                {{-- Título --}}


            </div>
        </div>
    </x-slot>


   @php
    $tpl = trim((string) $import->template);

    $isQC    = $tpl === 'QC';
    $isVT    = $tpl === 'VT';
    $isMP    = $tpl === 'MP';
    $isRFP   = $tpl === 'RFP';     // ✅ nuevo
    $isSANCO = $tpl === 'SANCO';   // (opcional, pero ordena)
@endphp

    {{-- Contenido --}}
 @php
$isXml46 = trim((string) $import->template) === 'XML_SII_46';

$linesArr = $import->lines->map(function ($l) use ($isXml46) {

    $text = $l->content;

    if ($isXml46 && str_contains($text, ':')) {

        [$left, $right] = explode(':', $text, 2);

        if (str_contains($left, '/')) {
            // 🔥 nos quedamos con el último nodo de la ruta
            $lastKey = trim(last(explode('/', $left)));

            // 👉 resultado final visible
            $text = $lastKey . ': ' . trim($right);
        }
    }

    return [
        'no'   => $l->line_no,
        'text' => $text,
    ];
})->values();
@endphp



    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg"
                x-data="pdfViewer({{ $linesArr->toJson(JSON_UNESCAPED_UNICODE) }})">
              @php
$meta = is_string($import->meta)
    ? json_decode($import->meta, true)
    : ($import->meta ?? []);

// ================= QC =================
$qcBandejas = $meta['bandejas'] ?? [];
$qcTotal    = $meta['total_bandejas'] ?? null;
$qcKgs      = $meta['kgs_recibido'] ?? null;

// ================= MP =================
$mpKgs      = $meta['kgs_recibido'] ?? null;
$mpBandejas = $meta['bandejas'] ?? [];
@endphp


                
@if($isXml46 && $xmlTotals)
<div class="p-4 sm:p-6 border-b">

    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
       <div class="p-3 rounded-lg bg-gray-50 border">
        <div class="text-xs text-gray-500">Emisor</div>
        <div class="text-sm font-semibold text-gray-900">
            {{ $meta['emisor']['razon_social'] ?? '—' }}
        </div>
        <div class="text-xs text-gray-500">
            RUT: {{ $meta['emisor']['rut'] ?? '—' }}
        </div>
    </div>

        <div class="p-3 rounded-lg bg-gray-50 border">
            <div class="text-xs text-gray-500">Folio SII</div>
            <div class="text-sm font-semibold text-gray-900">
                {{ $meta['folio_sii'] ?? '—' }}
            </div>
        </div>

        <div class="p-3 rounded-lg bg-gray-50 border">
            <div class="text-xs text-gray-500">Fecha emisión</div>
            <div class="text-sm font-semibold text-gray-900">
                {{ $import->doc_fecha ?? '—' }}
            </div>
        </div>

        <div class="p-3 rounded-lg bg-gray-50 border">
            <div class="text-xs text-gray-500">Guía despacho</div>
            <div class="text-sm font-semibold text-gray-900">
                {{ $import->guia_no ?? '—' }}
            </div>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="p-3 rounded-lg bg-gray-50 border">
            <div class="text-xs text-gray-500">Total bandejas</div>
            <div class="text-sm font-semibold text-gray-900">
                {{ number_format($xmlTotals['bandejas'], 0, ',', '.') }}
            </div>
        </div>

        <div class="p-3 rounded-lg bg-gray-50 border">
            <div class="text-xs text-gray-500">Total kilos</div>
            <div class="text-sm font-semibold text-gray-900">
                {{ number_format($xmlTotals['kilos'], 2, ',', '.') }} Kg
            </div>
        </div>
    </div>

</div>
@endif





                @if(trim((string) $import->template) === 'QC')
                    <div class="p-4 sm:p-6 border-b">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                            <div class="p-3 rounded-lg bg-gray-50 border">
                                <div class="text-xs text-gray-500">Guía (G.Prod)</div>
                                <div class="text-sm font-semibold text-gray-900">
                                    {{ $import->guia_no ?? '—' }}
                                </div>
                            </div>

                            <div class="p-3 rounded-lg bg-gray-50 border">
                                <div class="text-xs text-gray-500">Fecha</div>
                                <div class="text-sm font-semibold text-gray-900">
                                    {{ $import->doc_fecha ?? '—' }}
                                </div>
                            </div>

                            <div class="p-3 rounded-lg bg-gray-50 border md:col-span-2">
                                <div class="text-xs text-gray-500">Productor</div>
                                <div class="text-sm font-semibold text-gray-900 truncate" title="{{ $import->productor }}">
                                    {{ $import->productor ?? '—' }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="p-3 rounded-lg bg-gray-50 border">
                                <div class="text-xs text-gray-500">Kgs recibidos</div>
                                <div class="text-sm font-semibold text-gray-900">
                                    {{ isset($qcKgs) ? number_format((float) $qcKgs, 2, ',', '.') . ' Kg' : '—' }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-sm font-semibold text-gray-800">Bandejas / Materiales</div>
                                <div class="text-xs text-gray-500">
                                    Total bandejas: <span class="font-semibold text-gray-900">{{ $qcTotal ?? '—' }}</span>
                                </div>
                            </div>



                            <div class="overflow-auto border rounded-lg">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50 text-gray-700">
                                        <tr class="text-left">
                                            <th class="p-2 w-28">Código</th>
                                            <th class="p-2">Descripción</th>
                                            <th class="p-2 w-32 text-right">Cantidad</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        @forelse($qcBandejas as $b)
                                            <tr>
                                                <td class="p-2 font-mono text-gray-800">{{ $b['codigo'] ?? '—' }}</td>
                                                <td class="p-2 text-gray-800">{{ $b['descripcion'] ?? '—' }}</td>
                                                <td class="p-2 text-right font-semibold text-gray-900">
                                                    {{ isset($b['cantidad']) ? number_format((float) $b['cantidad'], 2, ',', '.') : '—' }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="p-4 text-center text-gray-500">
                                                    No se detectaron bandejas/materiales en este QC.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @elseif(trim((string) $import->template) === 'RFP')
    @php
        $meta = is_string($import->meta) ? json_decode($import->meta, true) : ($import->meta ?? []);
    @endphp

    <div class="p-4 sm:p-6 border-b">
        {{-- Cabecera --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div class="p-3 rounded-lg bg-gray-50 border">
                <div class="text-xs text-gray-500">Guía Despacho</div>
                <div class="text-sm font-semibold text-gray-900">
                    {{ $import->guia_no ?? '—' }}
                </div>
            </div>

            <div class="p-3 rounded-lg bg-gray-50 border">
                <div class="text-xs text-gray-500">Fecha</div>
                <div class="text-sm font-semibold text-gray-900">
                    {{ $import->doc_fecha ?? '—' }}
                </div>
            </div>

            <div class="p-3 rounded-lg bg-gray-50 border md:col-span-2">
                <div class="text-xs text-gray-500">Productor</div>
                <div class="text-sm font-semibold text-gray-900 truncate"
                     title="{{ $import->productor }}">
                    {{ $import->productor ?? '—' }}
                </div>
            </div>
        </div>

        {{-- Resumen --}}
        <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-3">
            <div class="p-3 rounded-lg bg-gray-50 border">
                <div class="text-xs text-gray-500">Albarán</div>
                <div class="text-sm font-semibold text-gray-900">
                    {{ $meta['albaran'] ?? '—' }}
                </div>
            </div>

            <div class="p-3 rounded-lg bg-gray-50 border">
                <div class="text-xs text-gray-500">Bandejas</div>
                <div class="text-sm font-semibold text-gray-900">
                    {{ isset($meta['bandejas_total'])
                        ? number_format((float) $meta['bandejas_total'], 0, ',', '.')
                        : '—' }}
                </div>
            </div>

            <div class="p-3 rounded-lg bg-gray-50 border">
                <div class="text-xs text-gray-500">Kgs recibidos</div>
                <div class="text-sm font-semibold text-gray-900">
                    {{ isset($meta['kgs_recibido'])
                        ? number_format((float) $meta['kgs_recibido'], 2, ',', '.') . ' Kg'
                        : '—' }}
                </div>
            </div>

            <div class="p-3 rounded-lg bg-gray-50 border">
                <div class="text-xs text-gray-500">Calidad</div>
                <div class="text-sm font-semibold text-gray-900">
                    {{ $meta['calidad'] ?? '—' }}
                </div>
            </div>
        </div>

        {{-- Clasificación --}}
        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="p-3 rounded-lg bg-gray-50 border">
                <div class="text-xs text-gray-500">% IQF</div>
                <div class="text-sm font-semibold text-gray-900">
                    {{ isset($meta['iqf_pct'])
                        ? number_format((float) $meta['iqf_pct'], 2, ',', '.') . ' %'
                        : '—' }}
                </div>
            </div>

            <div class="p-3 rounded-lg bg-gray-50 border">
                <div class="text-xs text-gray-500">% Block</div>
                <div class="text-sm font-semibold text-gray-900">
                    {{ isset($meta['block_pct'])
                        ? number_format((float) $meta['block_pct'], 2, ',', '.') . ' %'
                        : '—' }}
                </div>
            </div>
        </div>
    </div>

                @elseif(trim((string) $import->template) === 'SANCO')
                    @php
                        $meta = is_string($import->meta) ? json_decode($import->meta, true) : ($import->meta ?? []);
                        $destino = $meta['destino'] ?? null;
                        $especie = $meta['especie'] ?? null;
                        $variedad = $meta['variedad'] ?? null;

                        $subtotalCant = $meta['subtotal']['cantidad'] ?? null;
                        $subtotalKgs = $meta['subtotal']['kgs'] ?? null;

                        $totalCant = $meta['total']['cantidad'] ?? null;
                        $totalKgs = $meta['total']['kgs'] ?? null;

                        $detalles = $meta['detalles'] ?? [];
                    @endphp

                    <div class="p-4 sm:p-6 border-b">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                            <div class="p-3 rounded-lg bg-gray-50 border">
                                <div class="text-xs text-gray-500">Guía (Número)</div>
                                <div class="text-sm font-semibold text-gray-900">
                                    {{ $import->guia_no ?? '—' }}
                                </div>
                            </div>

                            <div class="p-3 rounded-lg bg-gray-50 border">
                                <div class="text-xs text-gray-500">Fecha</div>
                                <div class="text-sm font-semibold text-gray-900">
                                    {{ $import->doc_fecha ?? '—' }}
                                </div>
                            </div>

                            <div class="p-3 rounded-lg bg-gray-50 border md:col-span-2">
                                <div class="text-xs text-gray-500">Productor</div>
                                <div class="text-sm font-semibold text-gray-900 truncate" title="{{ $import->productor }}">
                                    {{ $import->productor ?? '—' }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="p-3 rounded-lg bg-gray-50 border">
                                <div class="text-xs text-gray-500">Especie</div>
                                <div class="text-sm font-semibold text-gray-900">
                                    {{ $especie ?? '—' }}
                                </div>
                            </div>

                            <div class="p-3 rounded-lg bg-gray-50 border">
                                <div class="text-xs text-gray-500">Variedad</div>
                                <div class="text-sm font-semibold text-gray-900">
                                    {{ $variedad ?? '—' }}
                                </div>
                            </div>

                            <div class="p-3 rounded-lg bg-gray-50 border">
                                <div class="text-xs text-gray-500">Destino</div>
                                <div class="text-sm font-semibold text-gray-900">
                                    {{ $destino ?? '—' }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3">


                            <div class="p-3 rounded-lg bg-gray-50 border">
                                <div class="text-xs text-gray-500">Total general</div>
                                <div class="text-sm font-semibold text-gray-900">
                                    {{ is_null($totalCant) ? '—' : number_format((float) $totalCant, 2, ',', '.') }}
                                    /
                                    {{ is_null($totalKgs) ? '—' : number_format((float) $totalKgs, 2, ',', '.') . ' Kg' }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-5">
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-sm font-semibold text-gray-800">Detalle de recepción</div>
                                <div class="text-xs text-gray-500">
                                    Líneas: <span
                                        class="font-semibold text-gray-900">{{ is_array($detalles) ? count($detalles) : 0 }}</span>
                                </div>
                            </div>

                            <div class="overflow-auto border rounded-lg">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50 text-gray-700">
                                        <tr class="text-left">
                                            <th class="p-2 w-28">Folio</th>
                                            <th class="p-2 w-28">Fecha</th>
                                            <th class="p-2 w-36">Calibre</th>
                                            <th class="p-2">Envase</th>
                                            <th class="p-2 w-24 text-right">Cant</th>
                                            <th class="p-2 w-28 text-right">Kgs</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        @forelse($detalles as $d)
                                            <tr>
                                                <td class="p-2 font-mono text-gray-800">{{ $d['folio'] ?? '—' }}</td>
                                                <td class="p-2 text-gray-800">{{ $d['fecha'] ?? '—' }}</td>
                                                <td class="p-2 text-gray-800">{{ $d['calibre'] ?? '—' }}</td>
                                                <td class="p-2 text-gray-800">{{ $d['envase'] ?? '—' }}</td>
                                                <td class="p-2 text-right font-semibold text-gray-900">
                                                    {{ isset($d['cantidad']) ? number_format((float) $d['cantidad'], 0, ',', '.') : '—' }}
                                                </td>
                                                <td class="p-2 text-right font-semibold text-gray-900">
                                                    {{ isset($d['kgs']) ? number_format((float) $d['kgs'], 2, ',', '.') : '—' }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="p-4 text-center text-gray-500">
                                                    No se detectaron líneas de detalle en esta guía.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>


                @elseif(trim((string) $import->template) === 'MP')
                    <div class="p-4 sm:p-6 border-b">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                            <div class="p-3 rounded-lg bg-gray-50 border">
                                <div class="text-xs text-gray-500">Guía (G.Despacho)</div>
                                <div class="text-sm font-semibold text-gray-900">
                                    {{ $import->guia_no ?? '—' }}
                                </div>
                            </div>

                            <div class="p-3 rounded-lg bg-gray-50 border">
                                <div class="text-xs text-gray-500">Fecha guía</div>
                                <div class="text-sm font-semibold text-gray-900">
                                    {{ $import->doc_fecha ?? '—' }}
                                </div>
                            </div>

                            <div class="p-3 rounded-lg bg-gray-50 border md:col-span-2">
                                <div class="text-xs text-gray-500">Proveedor</div>
                                <div class="text-sm font-semibold text-gray-900 truncate" title="{{ $import->productor }}">
                                    {{ $import->productor ?? '—' }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="p-3 rounded-lg bg-gray-50 border">
                                <div class="text-xs text-gray-500">Kgs recibidos</div>
                                <div class="text-sm font-semibold text-gray-900">
                                    {{ isset($mpKgs) ? number_format((float) $mpKgs, 2, ',', '.') . ' Kg' : '—' }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <div class="text-sm font-semibold text-gray-800 mb-2">Bandejas / Bandejones</div>

                            <div class="overflow-auto border rounded-lg">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50 text-gray-700">
                                        <tr class="text-left">
                                            <th class="p-2">Descripción</th>
                                            <th class="p-2 w-32 text-right">Cantidad</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        @forelse($mpBandejas as $b)
                                            <tr>
                                                <td class="p-2 text-gray-800">
                                                    {{ $b['descripcion'] ?? '—' }}
                                                </td>
                                                <td class="p-2 text-right font-semibold text-gray-900">
                                                    {{ isset($b['cantidad']) ? number_format((float) $b['cantidad'], 2, ',', '.') : '—' }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="2" class="p-4 text-center text-gray-500">
                                                    No se detectaron bandejas/bandejones en este MP.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @elseif(trim((string) $import->template) === 'VT')
                    <div class="p-4 sm:p-6 border-b">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                            <div class="p-3 rounded-lg bg-gray-50 border">
                                <div class="text-xs text-gray-500">Guía (GDD)</div>
                                <div class="text-sm font-semibold text-gray-900">
                                    {{ $import->guia_no ?? '—' }}
                                </div>
                            </div>

                            <div class="p-3 rounded-lg bg-gray-50 border">
                                <div class="text-xs text-gray-500">Fecha recepción</div>
                                <div class="text-sm font-semibold text-gray-900">
                                    {{ $import->doc_fecha ?? '—' }}
                                </div>
                            </div>

                            <div class="p-3 rounded-lg bg-gray-50 border md:col-span-2">
                                <div class="text-xs text-gray-500">Productor</div>
                                <div class="text-sm font-semibold text-gray-900 truncate" title="{{ $import->productor }}">
                                    {{ $import->productor ?? '—' }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="p-3 rounded-lg bg-gray-50 border">
                                <div class="text-xs text-gray-500">Kgs recibidos</div>
                                <div class="text-sm font-semibold text-gray-900">
                                    @php
                                        $vtKgs = $meta['kgs_recibido'] ?? null;
                                        $vtUnidad = $meta['unidad'] ?? 'Kg';
                                    @endphp

                                 {{ isset($vtKgs) ? number_format((float) ($vtKgs < 100 ? $vtKgs * 1000 : $vtKgs), 2, ',', '.') . ' ' . $vtUnidad : '—' }}

                                </div>
                            </div>

                            <div class="p-3 rounded-lg bg-gray-50 border">
                                <div class="text-xs text-gray-500">Empresa</div>
                                <div class="text-sm font-semibold text-gray-900">
                                    {{ $meta['empresa'] ?? '—' }}
                                </div>
                            </div>

                            <div class="p-3 rounded-lg bg-gray-50 border">
                                <div class="text-xs text-gray-500">Sucursal</div>
                                <div class="text-sm font-semibold text-gray-900">
                                    {{ $meta['sucursal'] ?? '—' }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="p-3 rounded-lg bg-gray-50 border">
                                <div class="text-xs text-gray-500">Producto</div>
                                <div class="text-sm font-semibold text-gray-900">
                                    {{ $meta['producto'] ?? '—' }}
                                </div>
                            </div>

                            <div class="p-3 rounded-lg bg-gray-50 border">
                                <div class="text-xs text-gray-500">Guía de pesaje</div>
                                <div class="text-sm font-semibold text-gray-900">
                                    {{ $meta['guia_pesaje'] ?? '—' }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Sticky toolbar --}}
                <div class="sticky top-0 z-10 bg-white border-b p-4 sm:p-6">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">

                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="px-2 py-1 rounded bg-gray-100 text-gray-700">
                                Total: <span class="font-semibold" x-text="lines.length"></span>
                            </span>

                            <span class="px-2 py-1 rounded bg-gray-100 text-gray-700">
                                Filtradas: <span class="font-semibold" x-text="filtered.length"></span>
                            </span>

                            <span class="px-2 py-1 rounded bg-gray-100 text-gray-700" x-show="q.trim().length">
                                Matches: <span class="font-semibold" x-text="matchIndexes.length"></span>
                            </span>

                            <span class="px-2 py-1 rounded bg-gray-100 text-gray-700">
                                Mostrando: <span class="font-semibold" x-text="visible.length"></span>
                            </span>
                            <span class="px-2 py-1 rounded bg-indigo-50 text-indigo-700" x-show="isSanco">
                                Sanco detectado
                            </span>
                            <span class="px-2 py-1 rounded bg-indigo-50 text-indigo-700" x-show="isVT">
                                VitaFoods detectado
                            </span>

                            <span class="px-2 py-1 rounded bg-indigo-50 text-indigo-700" x-show="isQC">
                                COMFRUT detectado
                            </span>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-2 w-full lg:w-auto">
                            <input x-model="q" @keydown.enter.prevent="focusNextMatch()" type="text"
                                placeholder="Buscar... (Enter = siguiente match)"
                                class="w-full sm:w-96 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" />

                            <div class="flex gap-2">
                                <button type="button" class="px-3 py-2 bg-gray-100 rounded-md hover:bg-gray-200 text-sm"
                                    @click="focusPrevMatch()" :disabled="matchIndexes.length === 0">
                                    ← Match
                                </button>
                                <button type="button" class="px-3 py-2 bg-gray-100 rounded-md hover:bg-gray-200 text-sm"
                                    @click="focusNextMatch()" :disabled="matchIndexes.length === 0">
                                    Match →
                                </button>

                                <input x-model.number="goTo" type="number" min="1"
                                    class="w-28 border-gray-300 rounded-md shadow-sm" placeholder="Línea" />
                                <button type="button" @click="scrollToLine(goTo)"
                                    class="px-3 py-2 bg-gray-100 rounded-md hover:bg-gray-200 text-sm">
                                    Ir
                                </button>
                                <button type="button" @click="reset()"
                                    class="px-3 py-2 bg-gray-100 rounded-md hover:bg-gray-200 text-sm">
                                    Limpiar
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Accesos rápidos QC --}}
                    <div class="mt-3 flex flex-wrap gap-2 text-sm" x-show="isQC">
                        <button class="px-3 py-1.5 rounded bg-indigo-50 text-indigo-700 hover:bg-indigo-100"
                            @click="jumpToContains('datos del productor')">
                            Datos del productor
                        </button>
                        <button class="px-3 py-1.5 rounded bg-indigo-50 text-indigo-700 hover:bg-indigo-100"
                            @click="jumpToContains('resultado analisis')">
                            Resultado análisis
                        </button>
                        <button class="px-3 py-1.5 rounded bg-indigo-50 text-indigo-700 hover:bg-indigo-100"
                            @click="jumpToContains('detalle control de calidad')">
                            Detalle control
                        </button>
                        <button class="px-3 py-1.5 rounded bg-indigo-50 text-indigo-700 hover:bg-indigo-100"
                            @click="jumpToContains('observaciones')">
                            Observaciones
                        </button>
                    </div>
                </div>

                {{-- Tabla --}}
                <div class="p-4 sm:p-6">
                    <div class="overflow-auto max-h-[70vh] border rounded">
                        <table class="w-full text-sm">
                            <tbody>
                                <template x-for="line in visible" :key="line.no">
                                    <tr class="border-b align-top" :id="'line-'+line.no">
                                        <td class="px-2 py-1 text-gray-400 w-20 whitespace-nowrap">
                                            <span x-text="line.no"></span>
                                        </td>

                                        <td class="px-2 py-1 font-mono whitespace-pre break-words">
                                            <span x-html="highlight(line.text)"></span>
                                        </td>

                                        <td class="px-2 py-1 w-28 text-right">
                                            <button type="button"
                                                class="text-xs px-2 py-1 rounded bg-gray-100 hover:bg-gray-200"
                                                @click="copyLine(line.no, line.text)"
                                                x-text="copiedLineNo === line.no ? 'Copiado ✅' : 'Copiar'">
                                            </button>
                                        </td>
                                    </tr>
                                </template>

                                <tr x-show="filtered.length === 0">
                                    <td colspan="3" class="px-3 py-6 text-center text-gray-500">
                                        No hay resultados para tu búsqueda.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 flex items-center justify-between" x-show="filtered.length > visible.length">
                        <div class="text-xs text-gray-500">
                            Mostrando <span class="font-semibold" x-text="visible.length"></span> de
                            <span class="font-semibold" x-text="filtered.length"></span>
                        </div>

                        <button type="button"
                            class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-sm"
                            @click="loadMore()">
                            Cargar más
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        function pdfViewer(lines) {
            return {
                lines,
                q: '',
                goTo: null,

                // virtualización simple
                pageSize: 300,
                page: 1,

                copiedLineNo: null,
                matchCursor: -1,


                isVT: @json(trim((string) $import->template) === 'VT'),
                isCOMFRUT: @json(trim((string) $import->template) === 'COMFRUT'),

                get isQC() {
                    const h = this.lines.slice(0, 120).map(l => (l.text || '').toLowerCase()).join('\n');
                    return h.includes('control de calidad') ||
                        h.includes('resultado analisis') ||
                        h.includes('detalle control de calidad');
                },

                get filtered() {
                    const query = (this.q || '').trim().toLowerCase();
                    if (!query) return this.lines;
                    return this.lines.filter(l => (l.text || '').toLowerCase().includes(query));
                },

                get visible() {
                    return this.filtered.slice(0, this.page * this.pageSize);
                },

                get matchIndexes() {
                    const query = (this.q || '').trim().toLowerCase();
                    if (!query) return [];
                    const out = [];
                    this.filtered.forEach((l, idx) => {
                        if ((l.text || '').toLowerCase().includes(query)) out.push(idx);
                    });
                    return out;
                },

                loadMore() {
                    this.page++;
                },

                reset() {
                    this.q = '';
                    this.goTo = null;
                    this.page = 1;
                    this.matchCursor = -1;
                    this.copiedLineNo = null;
                },

                highlight(text) {
                    const query = (this.q || '').trim();
                    if (!query) return this.escapeHtml(text);

                    const safeText = this.escapeHtml(text);
                    const safeQuery = this.escapeRegExp(query);
                    const re = new RegExp(safeQuery, 'gi');
                    return safeText.replace(re, (m) => `<mark class="px-0.5 rounded bg-yellow-200">${m}</mark>`);
                },

                scrollToLine(n) {
                    if (!n) return;
                    const el = document.getElementById('line-' + n);
                    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                },

                jumpToContains(str) {
                    const needle = (str || '').toLowerCase();
                    const found = this.lines.find(l => (l.text || '').toLowerCase().includes(needle));
                    if (found) this.scrollToLine(found.no);
                },

                focusNextMatch() {
                    if (this.matchIndexes.length === 0) return;

                    this.matchCursor = (this.matchCursor + 1) % this.matchIndexes.length;

                    const idxInFiltered = this.matchIndexes[this.matchCursor];
                    const line = this.filtered[idxInFiltered];

                    const neededPage = Math.ceil((idxInFiltered + 1) / this.pageSize);
                    if (neededPage > this.page) this.page = neededPage;

                    this.$nextTick(() => this.scrollToLine(line.no));
                },

                focusPrevMatch() {
                    if (this.matchIndexes.length === 0) return;

                    this.matchCursor = (this.matchCursor - 1);
                    if (this.matchCursor < 0) this.matchCursor = this.matchIndexes.length - 1;

                    const idxInFiltered = this.matchIndexes[this.matchCursor];
                    const line = this.filtered[idxInFiltered];

                    const neededPage = Math.ceil((idxInFiltered + 1) / this.pageSize);
                    if (neededPage > this.page) this.page = neededPage;

                    this.$nextTick(() => this.scrollToLine(line.no));
                },

                async copyLine(lineNo, text) {
                    try {
                        await navigator.clipboard.writeText(text);
                        this.copiedLineNo = lineNo;
                        setTimeout(() => this.copiedLineNo = null, 900);
                    } catch (e) {
                        window.prompt('Copia la línea:', text);
                    }
                },

                escapeHtml(s) {
                    return (s ?? '').replace(/[&<>"']/g, (c) => ({
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#39;'
                    }[c]));
                },

                escapeRegExp(s) {
                    return (s ?? '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                },
            }
        }
    </script>

</x-app-layout>