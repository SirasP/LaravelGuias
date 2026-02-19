<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-4">
            <div>
                <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Detalle DTE XML</h2>
                <p class="text-xs text-gray-400 mt-0.5 hidden sm:block">Folio {{ $document->folio ?? '—' }}</p>
            </div>
            <a href="{{ route('gmail.dtes.index') }}"
                class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                Volver
            </a>
        </div>
    </x-slot>

    <style>
        .page-bg { background:#f1f5f9; min-height:100% }
        .dark .page-bg { background:#0d1117 }
        .panel { background:#fff; border:1px solid #e2e8f0; border-radius:16px; overflow:hidden }
        .dark .panel { background:#161c2c; border-color:#1e2a3b }
        .dt { width:100%; border-collapse:collapse; font-size:13px }
        .dt thead tr { background:#f8fafc; border-bottom:1px solid #f1f5f9 }
        .dark .dt thead tr { background:#111827; border-bottom-color:#1e2a3b }
        .dt th { padding:10px 12px; text-align:left; font-size:10px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#94a3b8 }
        .dt td { padding:12px; border-bottom:1px solid #f8fafc; color:#334155 }
        .dark .dt td { border-bottom-color:#1a2232; color:#cbd5e1 }
    </style>

    <div class="page-bg">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">
            <div class="panel p-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div><span class="text-gray-400 text-xs">Proveedor</span><div class="font-semibold">{{ $document->proveedor_nombre ?? '—' }}</div></div>
                    <div><span class="text-gray-400 text-xs">RUT</span><div class="font-semibold">{{ $document->proveedor_rut ?? '—' }}</div></div>
                    <div><span class="text-gray-400 text-xs">Folio</span><div class="font-semibold">{{ $document->folio ?? '—' }}</div></div>
                    <div><span class="text-gray-400 text-xs">Fecha factura</span><div class="font-semibold">{{ $document->fecha_factura ?? '—' }}</div></div>
                    <div><span class="text-gray-400 text-xs">Fecha contable</span><div class="font-semibold">{{ $document->fecha_contable ?? '—' }}</div></div>
                    <div><span class="text-gray-400 text-xs">Vencimiento</span><div class="font-semibold">{{ $document->fecha_vencimiento ?? '—' }}</div></div>
                    <div class="md:col-span-2"><span class="text-gray-400 text-xs">Referencia</span><div class="font-semibold">{{ $document->referencia ?? '—' }}</div></div>
                    <div><span class="text-gray-400 text-xs">XML</span><div class="font-semibold">{{ $document->xml_filename ?? '—' }}</div></div>
                </div>
            </div>

            <div class="panel">
                <div class="overflow-x-auto">
                    <table class="dt">
                        <thead>
                            <tr>
                                <th>Línea</th>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>Cantidad</th>
                                <th>Unidad</th>
                                <th>P. Unitario</th>
                                <th>Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($lines as $l)
                                <tr>
                                    <td>{{ $l->nro_linea ?? '—' }}</td>
                                    <td>{{ $l->codigo ?? '—' }}</td>
                                    <td>{{ $l->descripcion ?? '—' }}</td>
                                    <td>{{ number_format((float) $l->cantidad, 2, ',', '.') }}</td>
                                    <td>{{ $l->unidad ?? '—' }}</td>
                                    <td>{{ number_format((float) $l->precio_unitario, 2, ',', '.') }}</td>
                                    <td>{{ number_format((float) $l->monto_item, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-10 text-gray-400">Sin líneas de detalle.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

