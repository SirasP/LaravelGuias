<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Facturas proveedor</h2>
                <p class="text-xs text-gray-400 mt-0.5 hidden sm:block">Detalle del XML</p>
            </div>
            <a href="{{ route('gmail.dtes.index') }}"
                class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                Volver al tablero
            </a>
        </div>
    </x-slot>

    @php
        $tipoMap = [
            33 => ['sigla' => 'FAC', 'nombre' => 'Factura Electronica'],
            34 => ['sigla' => 'FEX', 'nombre' => 'Factura Exenta'],
            56 => ['sigla' => 'ND', 'nombre' => 'Nota de Debito'],
            61 => ['sigla' => 'NC', 'nombre' => 'Nota de Credito'],
        ];

        $tipo = $tipoMap[(int) ($document->tipo_dte ?? 0)] ?? ['sigla' => 'DTE', 'nombre' => 'Documento Tributario'];

        $vencDate = $document->fecha_vencimiento ? \Carbon\Carbon::parse($document->fecha_vencimiento)->startOfDay() : null;
        $hoy = now()->startOfDay();
        $estadoPago = ($vencDate && $vencDate->lt($hoy)) ? 'Sin pagar' : 'Pagado';
    @endphp

    <style>
        .page-bg { background:#f3f4f6; min-height:100% }
        .dark .page-bg { background:#0d1117 }
        .card { background:#fff; border:1px solid #e5e7eb; border-radius:12px }
        .dark .card { background:#141a28; border-color:#253041 }
        .chip { border-radius:999px; font-size:11px; font-weight:700; padding:4px 10px; display:inline-flex; align-items:center }
        .dt { width:100%; border-collapse:collapse; font-size:13px }
        .dt thead tr { background:#f8fafc; border-top:1px solid #e5e7eb; border-bottom:1px solid #e5e7eb }
        .dark .dt thead tr { background:#111827; border-color:#243041 }
        .dt th { padding:10px 12px; text-align:left; font-size:11px; font-weight:700; color:#374151; white-space:nowrap }
        .dark .dt th { color:#cbd5e1 }
        .dt td { padding:12px; border-bottom:1px solid #eef2f7; color:#334155; vertical-align:middle }
        .dark .dt td { border-bottom-color:#1f2a3c; color:#cbd5e1 }
        .kv { display:grid; grid-template-columns: 180px 1fr; gap:8px 16px; align-items:start }
        .kv-k { color:#4b5563; font-weight:700 }
        .dark .kv-k { color:#9ca3af }
        .kv-v { color:#1f2937 }
        .dark .kv-v { color:#e5e7eb }
        .tab { border:1px solid #d1d5db; border-bottom:none; background:#f9fafb; padding:10px 14px; font-weight:600; color:#374151 }
        .tab.active { background:#fff; border-top:2px solid #7c3aed; color:#111827 }
        .dark .tab { background:#0f172a; border-color:#273449; color:#cbd5e1 }
        .dark .tab.active { background:#141a28; color:#f8fafc }
    </style>

    <div class="page-bg">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-5 space-y-4">
            <div class="flex flex-wrap gap-2">
                <button class="px-3 py-1.5 text-xs font-semibold rounded-md bg-violet-800 text-white">Imprimir</button>
                <button class="px-3 py-1.5 text-xs font-semibold rounded-md bg-violet-800 text-white">Pagar</button>
                <button class="px-3 py-1.5 text-xs font-semibold rounded-md bg-gray-200 text-gray-700">Nota de credito</button>
                <button class="px-3 py-1.5 text-xs font-semibold rounded-md bg-gray-200 text-gray-700">Restablecer a borrador</button>
                <button class="px-3 py-1.5 text-xs font-semibold rounded-md bg-gray-200 text-gray-700">Aceptar documento</button>
                <span class="ml-auto chip {{ $estadoPago === 'Pagado' ? 'bg-green-600 text-white' : 'bg-rose-600 text-white' }}">{{ $estadoPago }}</span>
            </div>

            <div class="card p-5">
                <p class="text-lg font-semibold text-gray-800 dark:text-gray-200">Factura de proveedor</p>
                <h1 class="text-5xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ $tipo['sigla'] }} {{ $document->folio ?? '—' }}</h1>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-6">
                    <div>
                        <div class="grid grid-cols-[140px,1fr] gap-2">
                            <div class="text-2xl font-bold text-gray-700 dark:text-gray-300">Proveedor</div>
                            <div>
                                <div class="text-2xl text-cyan-700 dark:text-cyan-400 font-semibold">{{ $document->proveedor_nombre ?? '—' }}</div>
                                <div class="text-xl text-gray-700 dark:text-gray-300">{{ $document->proveedor_rut ?? '—' }}</div>
                            </div>
                        </div>

                        <div class="mt-8">
                            <p class="text-3xl font-semibold text-gray-800 dark:text-gray-200">Referencia de factura</p>
                            <p class="mt-2 text-xl text-gray-600 dark:text-gray-300">{{ $document->referencia ?? '—' }}</p>
                        </div>
                    </div>

                    <div class="kv text-2xl">
                        <div class="kv-k">Fecha de la factura</div>
                        <div class="kv-v">{{ $document->fecha_factura ?? '—' }}</div>

                        <div class="kv-k">Fecha contable</div>
                        <div class="kv-v">{{ $document->fecha_contable ?? '—' }}</div>

                        <div class="kv-k">Fecha de vencimiento</div>
                        <div class="kv-v">{{ $document->fecha_vencimiento ?? '—' }}</div>

                        <div class="kv-k">Tipo de documento</div>
                        <div class="kv-v">({{ $document->tipo_dte ?? '—' }}) {{ $tipo['nombre'] }}</div>

                        <div class="kv-k">Numero de documento</div>
                        <div class="kv-v">{{ $document->folio ?? '—' }}</div>

                        <div class="kv-k">Estado DTE</div>
                        <div class="kv-v">{{ $estadoPago }}</div>

                        <div class="kv-k">Archivo XML</div>
                        <div class="kv-v">{{ $document->xml_filename ?? '—' }}</div>
                    </div>
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="px-4 pt-4 flex flex-wrap gap-2 border-b border-gray-200 dark:border-gray-700">
                    <button class="tab active">Lineas de factura</button>
                    <button class="tab">Apuntes contables</button>
                    <button class="tab">Otra informacion</button>
                    <button class="tab">Referencias cruzadas</button>
                </div>

                <div class="overflow-x-auto">
                    <table class="dt">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Cuenta</th>
                                <th>Analitica</th>
                                <th>Cantidad</th>
                                <th>UdM</th>
                                <th>Precio</th>
                                <th>Impuestos</th>
                                <th>Importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($lines as $l)
                                <tr>
                                    <td>{{ $l->descripcion ?? '—' }}</td>
                                    <td class="text-gray-400">—</td>
                                    <td class="text-gray-400">—</td>
                                    <td>{{ number_format((float) $l->cantidad, 2, ',', '.') }}</td>
                                    <td>{{ $l->unidad ?? '—' }}</td>
                                    <td>{{ number_format((float) $l->precio_unitario, 0, ',', '.') }}</td>
                                    <td><span class="chip bg-gray-200 text-gray-700">IVA 19%</span></td>
                                    <td class="font-semibold">$ {{ number_format((float) $l->monto_item, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-10 text-gray-400">Sin lineas de detalle.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end p-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="w-full sm:w-[340px] text-sm space-y-1">
                        <div class="flex justify-between text-gray-600 dark:text-gray-300"><span>Monto neto:</span><span class="font-semibold">$ {{ number_format((float) $document->monto_neto, 0, ',', '.') }}</span></div>
                        <div class="flex justify-between text-gray-600 dark:text-gray-300"><span>IVA 19%:</span><span class="font-semibold">$ {{ number_format((float) $document->monto_iva, 0, ',', '.') }}</span></div>
                        <div class="flex justify-between text-lg text-gray-900 dark:text-gray-100"><span>Total:</span><span class="font-bold">$ {{ number_format((float) $document->monto_total, 0, ',', '.') }}</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
