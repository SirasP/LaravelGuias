<x-app-layout>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-xl bg-violet-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Inventario DTE</h2>
                    <p class="text-xs text-gray-400 mt-0.5 truncate">Tablero resumen</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('gmail.inventory.list') }}"
                    class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-xl bg-violet-600 hover:bg-violet-700 text-white transition">
                    Ver listado
                </a>
                <a href="{{ route('gmail.dtes.index') }}"
                    class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-xl bg-cyan-600 hover:bg-cyan-700 text-white transition">
                    Facturas proveedor
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
            min-height:4px;
            background:#cbd5e1;
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
            <div class="panel">
                <div class="p-4 sm:p-5 space-y-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-2xl font-bold text-violet-700 dark:text-violet-300">Inventario de productos</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Resumen general del inventario DTE</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs uppercase tracking-wide text-gray-400">Total productos</p>
                            <p class="text-2xl font-extrabold text-gray-900 dark:text-gray-100">{{ number_format($totalProducts, 0, ',', '.') }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
                        <div class="card">
                            <p class="text-xs uppercase tracking-wide text-gray-400">Activos</p>
                            <p class="text-lg font-bold text-emerald-700 dark:text-emerald-300">{{ number_format($totalActivos, 0, ',', '.') }}</p>
                        </div>
                        <div class="card">
                            <p class="text-xs uppercase tracking-wide text-gray-400">Inactivos</p>
                            <p class="text-lg font-bold text-gray-700 dark:text-gray-300">{{ number_format($totalInactivos, 0, ',', '.') }}</p>
                        </div>
                        <div class="card">
                            <p class="text-xs uppercase tracking-wide text-gray-400">Con stock</p>
                            <p class="text-lg font-bold text-cyan-700 dark:text-cyan-300">{{ number_format($totalConStock, 0, ',', '.') }}</p>
                        </div>
                        <div class="card">
                            <p class="text-xs uppercase tracking-wide text-gray-400">Sin stock</p>
                            <p class="text-lg font-bold text-amber-700 dark:text-amber-300">{{ number_format($totalSinStock, 0, ',', '.') }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                        <div class="card">
                            <p class="text-xs uppercase tracking-wide text-gray-400">Stock total acumulado</p>
                            <p class="text-2xl font-extrabold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($stockTotalUnidades, 4, ',', '.') }}</p>
                        </div>
                        <div class="card">
                            <p class="text-xs uppercase tracking-wide text-gray-400">Valor inventario (referencial)</p>
                            <p class="text-2xl font-extrabold text-gray-900 dark:text-gray-100 mt-1">$ {{ number_format($valorInventario, 0, ',', '.') }}</p>
                        </div>
                    </div>

                    @php
                        $maxUnidad = max(1, ...collect($unidadResumen)->pluck('cantidad')->all());
                    @endphp
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-400 mb-2">Distribución por unidad</p>
                        <div class="mini-grid">
                            @foreach($unidadResumen as $unit)
                                @php
                                    $count = (int) ($unit['cantidad'] ?? 0);
                                    $height = (int) round(($count / $maxUnidad) * 100);
                                @endphp
                                <div class="mini-col">
                                    <div class="mini-bar-wrap">
                                        <div class="mini-bar" style="height: {{ max(4, $height) }}%; background: #a78bfa"></div>
                                    </div>
                                    <p class="mini-label">{{ $unit['unidad'] }}</p>
                                    <p class="text-xs text-gray-400">{{ number_format($count, 0, ',', '.') }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
