<div class="text-left">
    <h2 class="text-lg font-semibold mb-4 flex items-center gap-2">
        📄 Documento Tributario Electrónico
    </h2>

    @if($movimiento->requiere_revision)
        <div class="mb-4 p-3 bg-red-50 border-l-4 border-red-500 text-red-700 rounded text-sm flex items-start gap-2">
            <span class="text-lg">⚠</span>
            <div>
                <strong>Requiere Revisión</strong>
                <p class="text-xs mt-1">Este documento debe ser revisado según Ley 18.502</p>
            </div>
        </div>
    @endif

    @php
        try {
            $xmlObj = simplexml_load_string($xml);
            $xmlObj->registerXPathNamespace('sii', 'http://www.sii.cl/SiiDte');

            $documento = $xmlObj->xpath('//sii:Documento')[0] ?? null;
            $idDoc = $xmlObj->xpath('//sii:IdDoc')[0] ?? null;
            $emisor = $xmlObj->xpath('//sii:Emisor')[0] ?? null;
            $receptor = $xmlObj->xpath('//sii:Receptor')[0] ?? null;
            $totales = $xmlObj->xpath('//sii:Totales')[0] ?? null;
            $detalles = $xmlObj->xpath('//sii:Detalle') ?? [];


            // Tipo de documento
            $tiposDoc = [
                33 => 'FACTURA ELECTRÓNICA',
                34 => 'FACTURA NO AFECTA O EXENTA ELECTRÓNICA',
                43 => 'LIQUIDACIÓN FACTURA ELECTRÓNICA',
                46 => 'FACTURA DE COMPRA ELECTRÓNICA',
                52 => 'GUÍA DE DESPACHO ELECTRÓNICA',
                56 => 'NOTA DE DÉBITO ELECTRÓNICA',
                61 => 'NOTA DE CRÉDITO ELECTRÓNICA',
                110 => 'FACTURA DE EXPORTACIÓN ELECTRÓNICA',
                111 => 'NOTA DE DÉBITO DE EXPORTACIÓN ELECTRÓNICA',
                112 => 'NOTA DE CRÉDITO DE EXPORTACIÓN ELECTRÓNICA'
            ];

            $tipoDoc = (int) ($idDoc->TipoDTE ?? 0);
            $nombreDoc = $tiposDoc[$tipoDoc] ?? 'DOCUMENTO TRIBUTARIO ELECTRÓNICO';

            $mostrarFactura = true;
        } catch (Exception $e) {
            $mostrarFactura = false;
        }
    @endphp

    @if($mostrarFactura && $documento)
        <!-- Tabs -->
        <div class="mb-4 border-b border-gray-200">
            <nav class="-mb-px flex gap-2">
                <button onclick="switchTab('vista')" id="tab-vista"
                    class="tab-btn active px-4 py-2 text-sm font-medium border-b-2 border-blue-500 text-blue-600">
                    Vista de Factura
                </button>
                <button onclick="switchTab('xml')" id="tab-xml"
                    class="tab-btn px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                    XML Original
                </button>
            </nav>
        </div>

        <!-- Vista de Factura -->
        <div id="content-vista" class="tab-content">
            <div class="bg-white border-2 border-gray-800 p-6 max-h-[70vh] overflow-auto font-mono text-sm">

                <!-- Header -->
                <div class="grid grid-cols-2 gap-6 mb-6 pb-6 border-b-2 border-gray-800">
                    <!-- Emisor -->
                    <div>
                        <h3 class="font-bold text-base mb-3">EMISOR</h3>
                        <div class="space-y-1">
                            <p class="font-bold">{{ $emisor->RznSoc ?? 'N/A' }}</p>
                            <p class="text-xs">{{ $emisor->GiroEmis ?? '' }}</p>
                            <p class="text-xs">{{ $emisor->DirOrigen ?? '' }}</p>
                            <p class="text-xs">{{ $emisor->CmnaOrigen ?? '' }}</p>
                            <p class="font-semibold mt-2">RUT: {{ $emisor->RUTEmisor ?? 'N/A' }}</p>
                        </div>
                    </div>

                    <!-- Timbre/Tipo Doc -->
                    <div class="border-2 border-red-600 p-4 text-center">
                        <p class="text-xs text-red-600 font-bold mb-1">R.U.T.: {{ $emisor->RUTEmisor ?? 'N/A' }}</p>
                        <h2 class="text-lg font-bold text-red-600 mb-2">{{ $nombreDoc }}</h2>
                        <p class="text-2xl font-bold">N° {{ $idDoc->Folio ?? 'N/A' }}</p>
                    </div>
                </div>

                <!-- Receptor y Datos -->
                <div class="mb-6 pb-6 border-b-2 border-gray-400">
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <h3 class="font-bold mb-2">RECEPTOR</h3>
                            <div class="space-y-1 text-xs">
                                <p><strong>Razón Social:</strong> {{ $receptor->RznSocRecep ?? 'N/A' }}</p>
                                <p><strong>RUT:</strong> {{ $receptor->RUTRecep ?? 'N/A' }}</p>
                                <p><strong>Giro:</strong> {{ $receptor->GiroRecep ?? 'N/A' }}</p>
                                <p><strong>Dirección:</strong> {{ $receptor->DirRecep ?? 'N/A' }}</p>
                                <p><strong>Comuna:</strong> {{ $receptor->CmnaRecep ?? 'N/A' }}</p>
                            </div>
                        </div>
                        <div>
                            <div class="space-y-1 text-xs">
                                <p><strong>Fecha Emisión:</strong>
                                    {{ \Carbon\Carbon::parse($idDoc->FchEmis ?? now())->format('d-m-Y') }}</p>
                                @if(isset($idDoc->FchVenc))
                                    <p><strong>Fecha Vencimiento:</strong>
                                        {{ \Carbon\Carbon::parse($idDoc->FchVenc)->format('d-m-Y') }}</p>
                                @endif
                                @if(isset($encabezado->ReferenciasDocumento))
                                    <p class="mt-3"><strong>Referencias:</strong></p>
                                    <p class="text-xs">{{ $encabezado->ReferenciasDocumento ?? '' }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detalle de Items -->
                <div class="mb-6">
                    <table class="w-full text-xs border-collapse">
                        <thead>
                            <tr class="bg-gray-200 border-y-2 border-gray-800">
                                <th class="text-left p-2 border-r border-gray-400">Item</th>
                                <th class="text-left p-2 border-r border-gray-400">Descripción</th>
                                <th class="text-right p-2 border-r border-gray-400">Cant.</th>
                                <th class="text-right p-2 border-r border-gray-400">P. Unit.</th>
                                <th class="text-right p-2">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($detalles as $index => $detalle)
                                <tr class="border-b border-gray-300">
                                    <td class="p-2 border-r border-gray-300">{{ $index + 1 }}</td>
                                    <td class="p-2 border-r border-gray-300">
                                        <p class="font-semibold">{{ $detalle->NmbItem ?? 'N/A' }}</p>
                                        @if(isset($detalle->DscItem))
                                            <p class="text-xs text-gray-600">{{ $detalle->DscItem }}</p>
                                        @endif
                                    </td>
                                    <td class="p-2 border-r border-gray-300 text-right">
                                      
                                        {{ number_format($detalle->QtyItem ?? 0, 2, ',', '.') }}
                                    </td>
                                    <td class="p-2 border-r border-gray-300 text-right">
                                        ${{ number_format($detalle->PrcItem ?? 0, 0, ',', '.') }}</td>
                                    <td class="p-2 text-right font-semibold">
                                        ${{ number_format($detalle->MontoItem ?? 0, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Totales -->
                <div class="flex justify-end">
                    <div class="w-80 border-2 border-gray-800 p-4">
                        <table class="w-full text-xs">
                            <tbody>
                                @if(isset($totales->MntNeto))
                                    <tr class="border-b border-gray-300">
                                        <td class="py-2 font-semibold">NETO $</td>
                                        <td class="py-2 text-right">${{ number_format($totales->MntNeto, 0, ',', '.') }}</td>
                                    </tr>
                                @endif
                                @if(isset($totales->MntExe))
                                    <tr class="border-b border-gray-300">
                                        <td class="py-2 font-semibold">EXENTO $</td>
                                        <td class="py-2 text-right">${{ number_format($totales->MntExe, 0, ',', '.') }}</td>
                                    </tr>
                                @endif
                                @if(isset($totales->TasaIVA))
                                    <tr class="border-b border-gray-300">
                                        <td class="py-2 font-semibold">IVA ({{ $totales->TasaIVA }}%) $</td>
                                        <td class="py-2 text-right">${{ number_format($totales->IVA ?? 0, 0, ',', '.') }}</td>
                                    </tr>
                                @endif
                                <tr class="border-t-2 border-gray-800">
                                    <td class="py-2 font-bold text-base">TOTAL $</td>
                                    <td class="py-2 text-right font-bold text-base">
                                        ${{ number_format($totales->MntTotal ?? 0, 0, ',', '.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Timbre Electrónico -->
                <div class="mt-6 pt-6 border-t-2 border-gray-400 text-center">
                    <p class="text-xs text-gray-600">TIMBRE ELECTRÓNICO SII</p>
                    <p class="text-xs text-gray-500 mt-1">Verifique documento: www.sii.cl</p>
                </div>

            </div>
        </div>

        <!-- Vista XML Original -->
        <div id="content-xml" class="tab-content hidden">
            <pre
                class="bg-gray-900 text-green-300 text-xs p-4 rounded max-h-[70vh] overflow-auto">{{ htmlspecialchars($xml) }}</pre>
        </div>

    @else
        <!-- Fallback si no se puede parsear -->
        <div class="mb-3 p-3 bg-yellow-50 border-l-4 border-yellow-500 text-yellow-700 rounded text-sm">
            ⚠ No se pudo interpretar el XML como factura electrónica. Mostrando XML original.
        </div>
        <pre
            class="bg-gray-900 text-green-300 text-xs p-4 rounded max-h-[60vh] overflow-auto">{{ htmlspecialchars($xml) }}</pre>
    @endif

</div>

<script>
    function switchTab(tab) {
        // Ocultar todos los contenidos
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('hidden');
        });

        // Remover clase active de todos los tabs
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active', 'border-blue-500', 'text-blue-600');
            btn.classList.add('border-transparent', 'text-gray-500');
        });

        // Mostrar contenido seleccionado
        document.getElementById('content-' + tab).classList.remove('hidden');

        // Activar tab seleccionado
        const activeTab = document.getElementById('tab-' + tab);
        activeTab.classList.add('active', 'border-blue-500', 'text-blue-600');
        activeTab.classList.remove('border-transparent', 'text-gray-500');
    }
</script>