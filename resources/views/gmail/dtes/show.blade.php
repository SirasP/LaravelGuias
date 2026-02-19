<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-xl bg-indigo-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Facturas proveedor</h2>
                    <p class="text-xs text-gray-400 mt-0.5 truncate">Detalle XML</p>
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <button onclick="window.print()"
                    class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white transition">
                    Imprimir
                </button>
                <a href="{{ route('gmail.dtes.index') }}"
                    class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    Volver
                </a>
            </div>
        </div>
    </x-slot>

    @php
        $tipoMap = [
            33 => ['sigla' => 'FAC', 'nombre' => 'Factura electronica'],
            34 => ['sigla' => 'FEX', 'nombre' => 'Factura exenta'],
            56 => ['sigla' => 'ND', 'nombre' => 'Nota de debito'],
            61 => ['sigla' => 'NC', 'nombre' => 'Nota de credito'],
        ];

        $tipo = $tipoMap[(int) ($document->tipo_dte ?? 0)] ?? ['sigla' => 'DTE', 'nombre' => 'Documento tributario'];

        $vencDate = $document->fecha_vencimiento ? \Carbon\Carbon::parse($document->fecha_vencimiento)->startOfDay() : null;
        $hoy = now()->startOfDay();
        $estadoPago = ($vencDate && $vencDate->lt($hoy)) ? 'Sin pagar' : 'Pagado';

        $estadoClass = $estadoPago === 'Pagado'
            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
            : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300';
    @endphp

    <style>
        .page-bg { background:#f1f5f9; min-height:100% }
        .dark .page-bg { background:#0d1117 }

        .panel {
            background:#fff;
            border:1px solid #e2e8f0;
            border-radius:16px;
            overflow:hidden;
        }
        .dark .panel { background:#161c2c; border-color:#1e2a3b }

        .panel-head {
            padding:14px 18px;
            border-bottom:1px solid #f1f5f9;
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:12px;
        }
        .dark .panel-head { border-bottom-color:#1e2a3b }

        .chip {
            display:inline-flex;
            align-items:center;
            border-radius:999px;
            font-size:11px;
            font-weight:700;
            padding:4px 10px;
        }

        .kv {
            display:grid;
            grid-template-columns: 170px 1fr;
            gap:8px 14px;
            font-size:13px;
        }

        .kv-k { color:#64748b; font-weight:700 }
        .dark .kv-k { color:#94a3b8 }

        .kv-v { color:#1f2937; font-weight:600 }
        .dark .kv-v { color:#e5e7eb }

        .dt { width:100%; border-collapse:collapse; font-size:13px }
        .dt thead tr { background:#f8fafc; border-top:1px solid #f1f5f9; border-bottom:1px solid #f1f5f9 }
        .dark .dt thead tr { background:#111827; border-color:#1e2a3b }

        .dt th {
            padding:10px 12px;
            text-align:left;
            font-size:10px;
            letter-spacing:.08em;
            text-transform:uppercase;
            color:#94a3b8;
            white-space:nowrap;
            font-weight:700;
        }

        .dt td {
            padding:12px;
            border-bottom:1px solid #f8fafc;
            color:#334155;
            vertical-align:middle;
        }
        .dark .dt td { border-bottom-color:#1a2232; color:#cbd5e1 }

        .tabs {
            display:flex;
            flex-wrap:wrap;
            gap:6px;
            padding:10px 12px 0;
            border-bottom:1px solid #e2e8f0;
        }
        .dark .tabs { border-bottom-color:#1e2a3b }

        .tab {
            padding:8px 12px;
            border:1px solid #e2e8f0;
            border-bottom:none;
            border-radius:10px 10px 0 0;
            background:#f8fafc;
            color:#64748b;
            font-size:12px;
            font-weight:700;
        }
        .dark .tab { border-color:#1e2a3b; background:#111827; color:#94a3b8 }

        .tab.active { background:#fff; color:#111827 }
        .dark .tab.active { background:#161c2c; color:#e5e7eb }

        @media (max-width: 768px) {
            .kv { grid-template-columns: 1fr; gap:2px 0 }
        }
    </style>

    <div class="page-bg">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">
            <div class="panel">
                <div class="panel-head">
                    <div class="min-w-0">
                        <p class="text-xs text-gray-400">Factura de proveedor</p>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-gray-100 leading-tight truncate">
                            {{ $tipo['sigla'] }} {{ $document->folio ?? '—' }}
                        </h1>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="chip bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300">{{ $tipo['nombre'] }}</span>
                        <span class="chip {{ $estadoClass }}">{{ $estadoPago }}</span>
                    </div>
                </div>

                <div class="p-4 sm:p-5 grid grid-cols-1 xl:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wide">Proveedor</p>
                            <p class="text-base font-bold text-gray-900 dark:text-gray-100">{{ $document->proveedor_nombre ?? '—' }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $document->proveedor_rut ?? '—' }}</p>
                        </div>

                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wide">Referencia</p>
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $document->referencia ?? '—' }}</p>
                        </div>

                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wide">Archivo XML</p>
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 break-all">{{ $document->xml_filename ?? '—' }}</p>
                        </div>
                    </div>

                    <div class="kv">
                        <div class="kv-k">Fecha factura</div>
                        <div class="kv-v">{{ $document->fecha_factura ?? '—' }}</div>

                        <div class="kv-k">Fecha contable</div>
                        <div class="kv-v">{{ $document->fecha_contable ?? '—' }}</div>

                        <div class="kv-k">Fecha vencimiento</div>
                        <div class="kv-v">{{ $document->fecha_vencimiento ?? '—' }}</div>

                        <div class="kv-k">Tipo DTE</div>
                        <div class="kv-v">{{ $document->tipo_dte ?? '—' }}</div>

                        <div class="kv-k">Numero documento</div>
                        <div class="kv-v">{{ $document->folio ?? '—' }}</div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="tabs">
                    <button class="tab active">Lineas de factura</button>
                    <button class="tab">Apuntes contables</button>
                    <button class="tab">Otra informacion</button>
                </div>

                <div class="overflow-x-auto">
                    <table class="dt">
                        <thead>
                            <tr>
                                <th>Linea</th>
                                <th>Codigo</th>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>UdM</th>
                                <th>Precio</th>
                                <th>Importe</th>
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
                                    <td>{{ number_format((float) $l->precio_unitario, 0, ',', '.') }}</td>
                                    <td class="font-semibold">$ {{ number_format((float) $l->monto_item, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-10 text-gray-400">Sin lineas de detalle.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="p-4 border-t border-gray-100 dark:border-gray-800 flex justify-end">
                    <div class="w-full sm:w-80 text-sm space-y-1">
                        <div class="flex justify-between text-gray-600 dark:text-gray-300">
                            <span>Monto neto</span>
                            <span class="font-semibold">$ {{ number_format((float) $document->monto_neto, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-gray-600 dark:text-gray-300">
                            <span>IVA</span>
                            <span class="font-semibold">$ {{ number_format((float) $document->monto_iva, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-base text-gray-900 dark:text-gray-100">
                            <span>Total</span>
                            <span class="font-bold">$ {{ number_format((float) $document->monto_total, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
