<div class="text-left">

    @php
        $estado = $movimiento->estado ?? 'pendiente';

        $estadoConfig = match ($estado) {
            'pendiente' => [
                'bg' => 'bg-yellow-50 dark:bg-yellow-900/20',
                'border' => 'border-yellow-600',
                'text' => 'text-yellow-700 dark:text-yellow-300',
                'icon' => '⏳',
                'titulo' => 'Documento Pendiente de Revisión',
                'descripcion' => 'Debe aprobarse o rechazarse antes de afectar inventario'
            ],
            'aprobado' => [
                'bg' => 'bg-green-50 dark:bg-green-900/20',
                'border' => 'border-green-600',
                'text' => 'text-green-700 dark:text-green-300',
                'icon' => '✔',
                'titulo' => 'Documento Aprobado',
                'descripcion' => 'El stock fue ingresado correctamente'
            ],
            'rechazado' => [
                'bg' => 'bg-red-50 dark:bg-red-900/20',
                'border' => 'border-red-600',
                'text' => 'text-red-700 dark:text-red-300',
                'icon' => '✖',
                'titulo' => 'Documento Rechazado',
                'descripcion' => 'Este XML no afectó el inventario'
            ],
            default => [
                'bg' => 'bg-gray-50',
                'border' => 'border-gray-400',
                'text' => 'text-gray-700',
                'icon' => '•',
                'titulo' => 'Estado Desconocido',
                'descripcion' => ''
            ]
        };
    @endphp

    <!-- Badge de Estado -->
    <div
        class="mb-6 p-4 rounded-lg {{ $estadoConfig['bg'] }} border-l-4 {{ $estadoConfig['border'] }} {{ $estadoConfig['text'] }}">
        <div class="flex items-start gap-3">
            <span class="text-2xl flex-shrink-0">{{ $estadoConfig['icon'] }}</span>
            <div class="flex-1">
                <h3 class="font-bold text-base mb-1">{{ $estadoConfig['titulo'] }}</h3>
                <p class="text-sm opacity-90">{{ $estadoConfig['descripcion'] }}</p>
            </div>
        </div>
    </div>

    @php
        try {
            $xmlObj = simplexml_load_string($xml);
            $xmlObj->registerXPathNamespace('sii', 'http://www.sii.cl/SiiDte');

            // Extraer datos principales
            $documento = $xmlObj->xpath('//sii:Documento')[0] ?? null;
            $idDoc = $xmlObj->xpath('//sii:IdDoc')[0] ?? null;
            $emisor = $xmlObj->xpath('//sii:Emisor')[0] ?? null;
            $receptor = $xmlObj->xpath('//sii:Receptor')[0] ?? null;
            $totales = $xmlObj->xpath('//sii:Totales')[0] ?? null;
            $detalles = $xmlObj->xpath('//sii:Detalle') ?? [];

            // Catálogo de tipos de documento
            $tiposDoc = [
                33 => ['nombre' => 'FACTURA ELECTRÓNICA', 'color' => 'red'],
                34 => ['nombre' => 'FACTURA NO AFECTA O EXENTA ELECTRÓNICA', 'color' => 'blue'],
                43 => ['nombre' => 'LIQUIDACIÓN FACTURA ELECTRÓNICA', 'color' => 'purple'],
                46 => ['nombre' => 'FACTURA DE COMPRA ELECTRÓNICA', 'color' => 'orange'],
                52 => ['nombre' => 'GUÍA DE DESPACHO ELECTRÓNICA', 'color' => 'green'],
                56 => ['nombre' => 'NOTA DE DÉBITO ELECTRÓNICA', 'color' => 'pink'],
                61 => ['nombre' => 'NOTA DE CRÉDITO ELECTRÓNICA', 'color' => 'cyan'],
                110 => ['nombre' => 'FACTURA DE EXPORTACIÓN ELECTRÓNICA', 'color' => 'indigo'],
                111 => ['nombre' => 'NOTA DE DÉBITO DE EXPORTACIÓN ELECTRÓNICA', 'color' => 'purple'],
                112 => ['nombre' => 'NOTA DE CRÉDITO DE EXPORTACIÓN ELECTRÓNICA', 'color' => 'teal']
            ];

            $tipoDoc = (int) ($idDoc->TipoDTE ?? 0);
            $docInfo = $tiposDoc[$tipoDoc] ?? ['nombre' => 'DOCUMENTO TRIBUTARIO ELECTRÓNICO', 'color' => 'gray'];

            // Marcas comerciales
            $rutMandante = (string) ($xmlObj->xpath('//sii:RUTMandante')[0] ?? '');
            $companias = [
                '99520000-7' => ['nombre' => 'COPEC', 'color' => 'bg-red-600'],
                '76045459-6' => ['nombre' => 'ARAMCO', 'color' => 'bg-green-600'],
                '96505200-4' => ['nombre' => 'SHELL', 'color' => 'bg-yellow-500'],
            ];
            $marca = $companias[$rutMandante] ?? null;

            $mostrarFactura = true;
        } catch (Exception $e) {
            $mostrarFactura = false;
            $errorMsg = $e->getMessage();
        }
    @endphp

    @if($mostrarFactura && $documento)

        <!-- Tabs Navigation -->
        <div class="mb-6">
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="-mb-px flex gap-4" aria-label="Tabs">
                    <button onclick="switchTab('vista')" id="tab-vista"
                        class="tab-btn group inline-flex items-center gap-2 px-4 py-3 border-b-2 border-blue-500 text-sm font-semibold text-blue-600 dark:text-blue-400 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Vista de Factura
                    </button>
                    <button onclick="switchTab('xml')" id="tab-xml"
                        class="tab-btn group inline-flex items-center gap-2 px-4 py-3 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                        </svg>
                        XML Original
                    </button>
                    <button onclick="switchTab('resumen')" id="tab-resumen"
                        class="tab-btn group inline-flex items-center gap-2 px-4 py-3 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                        Resumen
                    </button>
                </nav>
            </div>
        </div>

        <!-- Vista de Factura -->
        <div id="content-vista" class="tab-content">
            <div
                class="bg-white dark:bg-gray-800 border-2 border-gray-800 dark:border-gray-600 rounded-lg shadow-xl p-8 max-h-[75vh] overflow-auto">

                <!-- Header con marca comercial -->
                <div
                    class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8 pb-8 border-b-2 border-gray-800 dark:border-gray-600">

                    <!-- Emisor -->
                    <div>
                        @if($marca)
                            <div class="mb-4 p-4 {{ $marca['color'] }} text-white rounded-lg shadow-md">
                                <div class="text-3xl font-black tracking-wider text-center">
                                    {{ $marca['nombre'] }}
                                </div>
                            </div>
                        @endif

                        <h3 class="text-lg font-bold mb-4 text-gray-900 dark:text-white flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            EMISOR
                        </h3>

                        <div class="space-y-2 bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg">
                            <div class="pb-2 border-b border-gray-200 dark:border-gray-600">
                                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Razón Social</p>
                                <p class="font-bold text-gray-900 dark:text-white">{{ $emisor->RznSoc ?? 'N/A' }}</p>
                            </div>
                            <p class="text-sm text-gray-700 dark:text-gray-300"><strong>Giro:</strong>
                                {{ $emisor->GiroEmis ?? 'N/A' }}</p>
                            <p class="text-sm text-gray-700 dark:text-gray-300"><strong>Dirección:</strong>
                                {{ $emisor->DirOrigen ?? 'N/A' }}</p>
                            <p class="text-sm text-gray-700 dark:text-gray-300"><strong>Comuna:</strong>
                                {{ $emisor->CmnaOrigen ?? 'N/A' }}</p>
                            <div class="pt-2 border-t border-gray-200 dark:border-gray-600">
                                <p class="text-sm font-bold text-gray-900 dark:text-white">RUT:
                                    {{ $emisor->RUTEmisor ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Timbre Documento -->
                    <div class="flex items-center justify-center">
                        <div
                            class="border-4 border-{{ $docInfo['color'] }}-600 p-6 text-center rounded-xl shadow-lg bg-white dark:bg-gray-800 w-full">
                            <p class="text-xs text-{{ $docInfo['color'] }}-600 font-bold mb-2">R.U.T.:
                                {{ $emisor->RUTEmisor ?? 'N/A' }}</p>
                            <h2 class="text-base md:text-lg font-bold text-{{ $docInfo['color'] }}-600 mb-3 leading-tight">
                                {{ $docInfo['nombre'] }}
                            </h2>
                            <div
                                class="bg-{{ $docInfo['color'] }}-50 dark:bg-{{ $docInfo['color'] }}-900/20 py-3 px-4 rounded-lg">
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Folio N°</p>
                                <p class="text-3xl font-black text-gray-900 dark:text-white">{{ $idDoc->Folio ?? 'N/A' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Receptor y Fechas -->
                <div class="mb-8 pb-8 border-b-2 border-gray-400 dark:border-gray-600">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                        <!-- Receptor -->
                        <div class="bg-blue-50 dark:bg-blue-900/20 p-5 rounded-lg">
                            <h3 class="font-bold text-lg mb-3 text-blue-900 dark:text-blue-300 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                RECEPTOR
                            </h3>
                            <div class="space-y-2 text-sm">
                                <p class="text-gray-700 dark:text-gray-300"><strong>Razón Social:</strong>
                                    {{ $receptor->RznSocRecep ?? 'N/A' }}</p>
                                <p class="text-gray-700 dark:text-gray-300"><strong>RUT:</strong>
                                    {{ $receptor->RUTRecep ?? 'N/A' }}</p>
                                <p class="text-gray-700 dark:text-gray-300"><strong>Giro:</strong>
                                    {{ $receptor->GiroRecep ?? 'N/A' }}</p>
                                <p class="text-gray-700 dark:text-gray-300"><strong>Dirección:</strong>
                                    {{ $receptor->DirRecep ?? 'N/A' }}</p>
                                <p class="text-gray-700 dark:text-gray-300"><strong>Comuna:</strong>
                                    {{ $receptor->CmnaRecep ?? 'N/A' }}</p>
                            </div>
                        </div>

                        <!-- Información del Documento -->
                        <div class="bg-gray-50 dark:bg-gray-700/50 p-5 rounded-lg">
                            <h3 class="font-bold text-lg mb-3 text-gray-900 dark:text-white flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                INFORMACIÓN
                            </h3>
                            <div class="space-y-3 text-sm">
                                <div
                                    class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-600">
                                    <span class="text-gray-600 dark:text-gray-400 font-medium">Fecha Emisión:</span>
                                    <span class="font-bold text-gray-900 dark:text-white">
                                        {{ \Carbon\Carbon::parse($idDoc->FchEmis ?? now())->format('d/m/Y') }}
                                    </span>
                                </div>
                                @if(isset($idDoc->FchVenc))
                                    <div
                                        class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-600">
                                        <span class="text-gray-600 dark:text-gray-400 font-medium">Vencimiento:</span>
                                        <span class="font-bold text-gray-900 dark:text-white">
                                            {{ \Carbon\Carbon::parse($idDoc->FchVenc)->format('d/m/Y') }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detalle de Items -->
                <div class="mb-8">
                    <h3 class="font-bold text-lg mb-4 text-gray-900 dark:text-white flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                        DETALLE DE PRODUCTOS
                    </h3>

                    <div class="overflow-x-auto rounded-lg border border-gray-300 dark:border-gray-600">
                        <table class="min-w-full divide-y divide-gray-300 dark:divide-gray-600">
                            <thead class="bg-gradient-to-r from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-600">
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-bold text-gray-700 dark:text-gray-200 uppercase tracking-wider">
                                        Item</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-bold text-gray-700 dark:text-gray-200 uppercase tracking-wider">
                                        Descripción</th>
                                    <th
                                        class="px-4 py-3 text-right text-xs font-bold text-gray-700 dark:text-gray-200 uppercase tracking-wider">
                                        Cantidad</th>
                                    <th
                                        class="px-4 py-3 text-right text-xs font-bold text-gray-700 dark:text-gray-200 uppercase tracking-wider">
                                        Precio Unit.</th>
                                    <th
                                        class="px-4 py-3 text-right text-xs font-bold text-gray-700 dark:text-gray-200 uppercase tracking-wider">
                                        Monto</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($detalles as $index => $detalle)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ $index + 1 }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                                {{ $detalle->NmbItem ?? 'N/A' }}</p>
                                            @if(isset($detalle->DscItem))
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $detalle->DscItem }}</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono text-sm text-gray-900 dark:text-white">
                                            {{ number_format((float) ($detalle->QtyItem ?? 0), 2, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono text-sm text-gray-900 dark:text-white">
                                            ${{ number_format((float) ($detalle->PrcItem ?? 0), 0, ',', '.') }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right font-mono text-sm font-bold text-gray-900 dark:text-white">
                                            ${{ number_format((float) ($detalle->MontoItem ?? 0), 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Totales -->
                <div class="flex justify-end">
                    <div
                        class="w-full md:w-96 border-2 border-gray-800 dark:border-gray-600 rounded-lg shadow-lg overflow-hidden">
                        <div
                            class="bg-gradient-to-r from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-600 px-4 py-2">
                            <h3 class="font-bold text-sm text-gray-900 dark:text-white uppercase tracking-wide">Resumen de
                                Totales</h3>
                        </div>
                        <div class="p-4 bg-white dark:bg-gray-800">
                            <table class="w-full">
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">

                                    @php
                                        // Cálculos de impuestos
                                        $iefTotal = 0;
                                        $impuestoTotal = 0;

                                        foreach ($detalles as $d) {
                                            $nsChildren = $d->children('http://www.sii.cl/SiiDte');
                                            if (isset($nsChildren->Subcantidad)) {
                                                foreach ($nsChildren->Subcantidad as $sub) {
                                                    $subNs = $sub->children('http://www.sii.cl/SiiDte');
                                                    if ((string) $subNs->SubCod === 'IEF') {
                                                        $qtyLitros = (float) $nsChildren->QtyItem;
                                                        $valorPorLitro = (float) $subNs->SubQty;
                                                        $iefTotal += ($qtyLitros * $valorPorLitro);
                                                    }
                                                }
                                            }
                                        }

                                        $imptoReten = $xmlObj->xpath('//sii:ImptoReten[sii:TipoImp=35]');
                                        if (!empty($imptoReten)) {
                                            $impuestoTotal = (float) $imptoReten[0]->MontoImp;
                                        }

                                        $ievFepp = $impuestoTotal - $iefTotal;
                                    @endphp

                                    @if(isset($totales->MntNeto))
                                        <tr>
                                            <td class="py-3 text-sm font-semibold text-gray-700 dark:text-gray-300">NETO</td>
                                            <td class="py-3 text-right font-mono text-sm text-gray-900 dark:text-white">
                                                ${{ number_format((float) $totales->MntNeto, 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    @endif

                                    @if(isset($totales->MntExe))
                                        <tr>
                                            <td class="py-3 text-sm font-semibold text-gray-700 dark:text-gray-300">EXENTO</td>
                                            <td class="py-3 text-right font-mono text-sm text-gray-900 dark:text-white">
                                                ${{ number_format((float) $totales->MntExe, 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    @endif

                                    @if(isset($totales->TasaIVA))
                                        <tr>
                                            <td class="py-3 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                                IVA ({{ $totales->TasaIVA }}%)
                                            </td>
                                            <td class="py-3 text-right font-mono text-sm text-gray-900 dark:text-white">
                                                ${{ number_format((float) ($totales->IVA ?? 0), 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    @endif

                                    @if($iefTotal > 0)
                                        <tr>
                                            <td class="py-3 text-sm font-semibold text-orange-700 dark:text-orange-400">
                                                IEF
                                                <span class="text-xs block text-gray-500">Imp. Esp. Combustibles</span>
                                            </td>
                                            <td
                                                class="py-3 text-right font-mono text-sm text-orange-700 dark:text-orange-400 font-bold">
                                                ${{ number_format($iefTotal, 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    @endif

                                    @if($ievFepp > 0)
                                        <tr>
                                            <td class="py-3 text-sm font-semibold text-purple-700 dark:text-purple-400">
                                                IEV / FEPP
                                                <span class="text-xs block text-gray-500">Fondo Est. Petróleo</span>
                                            </td>
                                            <td
                                                class="py-3 text-right font-mono text-sm text-purple-700 dark:text-purple-400 font-bold">
                                                ${{ number_format($ievFepp, 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    @endif

                                    <tr class="border-t-2 border-gray-800 dark:border-gray-600">
                                        <td class="py-4 text-base font-black text-gray-900 dark:text-white uppercase">TOTAL
                                        </td>
                                        <td
                                            class="py-4 text-right font-mono text-xl font-black text-gray-900 dark:text-white">
                                            ${{ number_format((float) ($totales->MntTotal ?? 0), 0, ',', '.') }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Timbre Electrónico -->
                <div class="mt-8 pt-8 border-t-2 border-gray-400 dark:border-gray-600 text-center">
                    <div class="inline-flex items-center gap-2 bg-blue-50 dark:bg-blue-900/20 px-6 py-3 rounded-lg">
                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        <div class="text-left">
                            <p class="text-xs font-bold text-blue-900 dark:text-blue-300 uppercase tracking-wide">Timbre
                                Electrónico SII</p>
                            <p class="text-xs text-gray-600 dark:text-gray-400">Verifique en www.sii.cl</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Vista XML Original -->
        <div id="content-xml" class="tab-content hidden">
            <div class="relative">
                <button onclick="copiarXML()"
                    class="absolute top-4 right-4 bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-2 transition-colors z-10">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                    Copiar XML
                </button>
                <pre id="xml-content"
                    class="bg-gray-900 text-green-300 text-xs p-6 rounded-lg max-h-[75vh] overflow-auto font-mono shadow-lg">{{ htmlspecialchars($xml) }}</pre>
            </div>
        </div>

        <!-- Vista Resumen -->
        <div id="content-resumen" class="tab-content hidden">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <!-- Card Info General -->
                <div
                    class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 p-6 rounded-xl shadow-lg border border-blue-200 dark:border-blue-800">
                    <h3 class="text-lg font-bold text-blue-900 dark:text-blue-300 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Información General
                    </h3>
                    <dl class="space-y-3">
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Tipo:</dt>
                            <dd class="text-sm font-bold text-gray-900 dark:text-white">{{ $docInfo['nombre'] }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Folio:</dt>
                            <dd class="text-sm font-bold text-gray-900 dark:text-white">{{ $idDoc->Folio ?? 'N/A' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Fecha:</dt>
                            <dd class="text-sm font-bold text-gray-900 dark:text-white">
                                {{ \Carbon\Carbon::parse($idDoc->FchEmis ?? now())->format('d/m/Y') }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Items:</dt>
                            <dd class="text-sm font-bold text-gray-900 dark:text-white">{{ count($detalles) }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Card Totales -->
                <div
                    class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 p-6 rounded-xl shadow-lg border border-green-200 dark:border-green-800">
                    <h3 class="text-lg font-bold text-green-900 dark:text-green-300 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Montos
                    </h3>
                    <dl class="space-y-3">
                        @if(isset($totales->MntNeto))
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Neto:</dt>
                                <dd class="text-sm font-bold text-gray-900 dark:text-white">
                                    ${{ number_format((float) $totales->MntNeto, 0, ',', '.') }}
                                </dd>
                            </div>
                        @endif
                        @if(isset($totales->IVA))
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">IVA:</dt>
                                <dd class="text-sm font-bold text-gray-900 dark:text-white">
                                    ${{ number_format((float) $totales->IVA, 0, ',', '.') }}
                                </dd>
                            </div>
                        @endif
                        @if($iefTotal > 0)
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">IEF:</dt>
                                <dd class="text-sm font-bold text-orange-600 dark:text-orange-400">
                                    ${{ number_format($iefTotal, 0, ',', '.') }}
                                </dd>
                            </div>
                        @endif
                        @if($ievFepp > 0)
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                    IEV / FEPP
                                </dt>
                                <dd class="text-sm font-bold text-purple-600 dark:text-purple-400">
                                    ${{ number_format($ievFepp, 0, ',', '.') }}
                                </dd>
                            </div>
                        @endif
                        <div class="flex justify-between pt-3 border-t-2 border-green-300 dark:border-green-700">
                            <dt class="text-base font-black text-gray-900 dark:text-white">TOTAL:</dt>
                            <dd class="text-base font-black text-green-700 dark:text-green-400">
                                ${{ number_format((float) ($totales->MntTotal ?? 0), 0, ',', '.') }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Card Emisor -->
                <div
                    class="bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/20 p-6 rounded-xl shadow-lg border border-purple-200 dark:border-purple-800">
                    <h3 class="text-lg font-bold text-purple-900 dark:text-purple-300 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        Emisor
                    </h3>
                    <dl class="space-y-2">
                        @if($marca)
                            <div class="mb-3 p-2 {{ $marca['color'] }} text-white rounded text-center font-bold">
                                {{ $marca['nombre'] }}
                            </div>
                        @endif
                        <div>
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Razón Social</dt>
                            <dd class="text-sm font-bold text-gray-900 dark:text-white">{{ $emisor->RznSoc ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">RUT</dt>
                            <dd class="text-sm font-bold text-gray-900 dark:text-white">{{ $emisor->RUTEmisor ?? 'N/A' }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Card Receptor -->
                <div
                    class="bg-gradient-to-br from-orange-50 to-orange-100 dark:from-orange-900/20 dark:to-orange-800/20 p-6 rounded-xl shadow-lg border border-orange-200 dark:border-orange-800">
                    <h3 class="text-lg font-bold text-orange-900 dark:text-orange-300 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Receptor
                    </h3>
                    <dl class="space-y-2">
                        <div>
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Razón Social</dt>
                            <dd class="text-sm font-bold text-gray-900 dark:text-white">
                                {{ $receptor->RznSocRecep ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">RUT</dt>
                            <dd class="text-sm font-bold text-gray-900 dark:text-white">{{ $receptor->RUTRecep ?? 'N/A' }}
                            </dd>
                        </div>
                    </dl>
                </div>

            </div>
        </div>

    @else
        <!-- Error al parsear XML -->
        <div class="p-6 bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 rounded-lg">
            <div class="flex items-start gap-3">
                <svg class="w-6 h-6 text-red-600 dark:text-red-400 flex-shrink-0" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div class="flex-1">
                    <h3 class="text-sm font-bold text-red-800 dark:text-red-300 mb-1">
                        Error al interpretar el XML
                    </h3>
                    <p class="text-sm text-red-700 dark:text-red-400">
                        No se pudo procesar el XML como documento tributario electrónico del SII. Mostrando contenido
                        original.
                    </p>
                    @if(isset($errorMsg))
                        <p class="text-xs text-red-600 dark:text-red-400 mt-2 font-mono">
                            {{ $errorMsg }}
                        </p>
                    @endif
                </div>
            </div>
        </div>
        <div class="mt-4">
            <pre
                class="bg-gray-900 text-green-300 text-xs p-4 rounded-lg max-h-[60vh] overflow-auto font-mono">{{ htmlspecialchars($xml) }}</pre>
        </div>
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
            btn.classList.remove('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
            btn.classList.add('border-transparent', 'text-gray-500', 'dark:text-gray-400');
        });

        // Mostrar contenido seleccionado
        document.getElementById('content-' + tab).classList.remove('hidden');

        // Activar tab seleccionado
        const activeTab = document.getElementById('tab-' + tab);
        activeTab.classList.remove('border-transparent', 'text-gray-500', 'dark:text-gray-400');
        activeTab.classList.add('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
    }

    function copiarXML() {
        const xmlContent = document.getElementById('xml-content').textContent;
        navigator.clipboard.writeText(xmlContent).then(() => {
            // Feedback visual
            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.innerHTML = `
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                ¡Copiado!
            `;
            btn.classList.remove('bg-green-600', 'hover:bg-green-700');
            btn.classList.add('bg-green-700');

            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.classList.add('bg-green-600', 'hover:bg-green-700');
                btn.classList.remove('bg-green-700');
            }, 2000);
        });
    }
</script>

<style>
    /* Animación suave para los tabs */
    .tab-content {
        animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Scrollbar personalizada */
    .overflow-auto::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    .overflow-auto::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    .overflow-auto::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
    }

    .overflow-auto::-webkit-scrollbar-thumb:hover {
        background: #555;
    }

    /* Dark mode scrollbar */
    .dark .overflow-auto::-webkit-scrollbar-track {
        background: #374151;
    }

    .dark .overflow-auto::-webkit-scrollbar-thumb {
        background: #6b7280;
    }

    .dark .overflow-auto::-webkit-scrollbar-thumb:hover {
        background: #9ca3af;
    }
</style>