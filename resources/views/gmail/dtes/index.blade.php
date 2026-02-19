<x-app-layout>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-xl bg-cyan-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Facturas proveedor</h2>
                    <p class="text-xs text-gray-400 mt-0.5 truncate">Tablero resumen</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('gmail.dtes.list') }}"
                    class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-xl bg-cyan-600 hover:bg-cyan-700 text-white transition">
                    Ver listado
                </a>
                <a href="{{ route('gmail.inventory.index') }}"
                    class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-xl bg-violet-600 hover:bg-violet-700 text-white transition">
                    Inventario DTE
                </a>
            </div>
        </div>
    </x-slot>

    <style>
        .page-bg { background:#f1f5f9; min-height:100% }
        .dark .page-bg { background:#0d1117 }
        .panel { background:#fff; border:1px solid #e2e8f0; border-radius:18px; overflow:hidden }
        .dark .panel { background:#161c2c; border-color:#1e2a3b }
        .card { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:16px }
        .dark .card { background:#161c2c; border-color:#1e2a3b }

        .mini-grid {
            display:grid;
            grid-template-columns:repeat(6,minmax(90px,1fr));
            gap:10px;
            align-items:end;
        }
        .mini-col { text-align:center }
        .mini-bar-wrap {
            height:120px;
            border-left:1px solid #e5e7eb;
            border-right:1px solid #e5e7eb;
            display:flex;
            align-items:flex-end;
            justify-content:center;
            padding:0 8px;
        }
        .dark .mini-bar-wrap { border-color:#273244 }
        .mini-bar {
            width:100%;
            max-width:72px;
            border-radius:8px 8px 0 0;
            background:#cbd5e1;
            min-height:4px;
        }
        .mini-label { margin-top:8px; font-size:12px; color:#64748b; font-weight:600 }
        .dark .mini-label { color:#94a3b8 }

        @media (max-width: 900px) {
            .mini-grid { grid-template-columns:repeat(3,minmax(90px,1fr)); }
            .mini-bar-wrap { height:90px; }
        }
    </style>

    <div class="page-bg">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                <div class="panel">
                    <div class="p-4 sm:p-5 space-y-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-2xl font-bold text-cyan-700 dark:text-cyan-300">Facturas de proveedores</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Resumen general de DTE cargados</p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs uppercase tracking-wide text-gray-400">Total documentos</p>
                                <p class="text-2xl font-extrabold text-gray-900 dark:text-gray-100">{{ number_format($summary['total_docs'], 0, ',', '.') }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="card">
                                <p class="text-xs uppercase tracking-wide text-gray-400">Por validar</p>
                                <p class="text-lg font-bold text-cyan-700 dark:text-cyan-300">{{ number_format($summary['por_validar_count'], 0, ',', '.') }}</p>
                                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">$ {{ number_format($summary['por_validar_monto'], 0, ',', '.') }}</p>
                            </div>
                            <div class="card">
                                <p class="text-xs uppercase tracking-wide text-gray-400">Por pagar</p>
                                <p class="text-lg font-bold text-cyan-700 dark:text-cyan-300">{{ number_format($summary['por_pagar_count'], 0, ',', '.') }}</p>
                                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">$ {{ number_format($summary['por_pagar_monto'], 0, ',', '.') }}</p>
                            </div>
                            <div class="card">
                                <p class="text-xs uppercase tracking-wide text-gray-400">Atrasado</p>
                                <p class="text-lg font-bold text-cyan-700 dark:text-cyan-300">{{ number_format($summary['atrasado_count'], 0, ',', '.') }}</p>
                                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">$ {{ number_format($summary['atrasado_monto'], 0, ',', '.') }}</p>
                            </div>
                        </div>

                        @php
                            $maxAge = max(1, ...array_values($aging));
                            $bars = [
                                ['label' => 'Debido', 'key' => 'vencido', 'color' => '#fda4af'],
                                ['label' => '1 - 7 días', 'key' => 'd1_7', 'color' => '#fde68a'],
                                ['label' => '8 - 15 días', 'key' => 'd8_15', 'color' => '#fcd34d'],
                                ['label' => '16 - 30 días', 'key' => 'd16_30', 'color' => '#f59e0b'],
                                ['label' => '+30 días', 'key' => 'd31_plus', 'color' => '#ef4444'],
                                ['label' => 'No adeudado', 'key' => 'no_adeudado', 'color' => '#99f6e4'],
                            ];
                        @endphp

                        <div class="mini-grid">
                            @foreach($bars as $bar)
                                @php
                                    $value = (int) ($aging[$bar['key']] ?? 0);
                                    $height = (int) round(($value / $maxAge) * 100);
                                @endphp
                                <div class="mini-col">
                                    <div class="mini-bar-wrap">
                                        <div class="mini-bar" style="height: {{ max(4, $height) }}%; background: {{ $bar['color'] }}"></div>
                                    </div>
                                    <p class="mini-label">{{ $bar['label'] }}</p>
                                    <p class="text-xs text-gray-400">{{ number_format($value, 0, ',', '.') }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="p-4 sm:p-5 space-y-3">
                        <p class="text-sm font-bold text-gray-900 dark:text-gray-100">Accesos rápidos</p>

                        <a href="{{ route('gmail.dtes.list') }}" class="card block hover:border-cyan-300 dark:hover:border-cyan-700 transition">
                            <p class="text-sm font-bold text-gray-900 dark:text-gray-100">Listado de facturas</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Ver tabla completa con filtros y búsqueda</p>
                        </a>

                        <a href="{{ route('gmail.inventory.index') }}" class="card block hover:border-violet-300 dark:hover:border-violet-700 transition">
                            <p class="text-sm font-bold text-gray-900 dark:text-gray-100">Inventario DTE</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Revisar productos ingresados por facturas</p>
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
