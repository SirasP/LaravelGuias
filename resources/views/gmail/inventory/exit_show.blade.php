<x-app-layout>
    @php
        $isVenta = ($movement->tipo_salida ?? '') === 'Venta';
        $backParams = $isVenta ? ['tipo' => 'Venta'] : [];
    @endphp

    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-1.5 min-w-0 text-xs">
                <a href="{{ route('gmail.inventory.exits', $backParams) }}"
                    class="text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition font-medium truncate">
                    Salidas de inventario
                </a>
                <svg class="w-3 h-3 text-gray-300 dark:text-gray-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                </svg>
                <span class="font-bold text-gray-700 dark:text-gray-300 truncate">Movimiento #{{ $movement->id }}</span>
            </div>
            <a href="{{ route('gmail.inventory.exits', $backParams) }}"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 transition">
                Volver
            </a>
        </div>
    </x-slot>

    <style>
        .page-bg { background:#f1f5f9; min-height:100% }
        .dark .page-bg { background:#0d1117 }
        .panel { background:#fff; border:1px solid #e2e8f0; border-radius:16px; overflow:hidden }
        .dark .panel { background:#161c2c; border-color:#1e2a3b }
        .chip { display:inline-flex; align-items:center; border-radius:999px; padding:3px 10px; font-size:11px; font-weight:700 }
        .dt { width:100%; border-collapse:collapse; font-size:13px }
        .dt thead tr { background:#f8fafc; border-bottom:2px solid #f1f5f9 }
        .dark .dt thead tr { background:#0f1623; border-bottom-color:#1e2a3b }
        .dt th {
            padding:10px 14px; text-align:left; font-size:10px; font-weight:700;
            letter-spacing:.07em; text-transform:uppercase; color:#94a3b8;
        }
        .dt td { padding:12px 14px; border-bottom:1px solid #f1f5f9; color:#334155; vertical-align:middle }
        .dark .dt td { border-bottom-color:#1a2232; color:#cbd5e1 }
        .dt tbody tr:last-child td { border-bottom:none }
    </style>

    <div class="page-bg">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">

            <div class="panel">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs text-gray-400">Destinatario</p>
                        <h1 class="text-xl sm:text-2xl font-black text-gray-900 dark:text-gray-100 leading-tight">{{ $movement->destinatario ?? '—' }}</h1>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-400">Tipo</p>
                        <span class="chip {{ $isVenta ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' }}">
                            {{ $movement->tipo_salida ?? 'Salida' }}
                        </span>
                    </div>
                </div>

                <div class="px-5 py-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                    <div>
                        <p class="text-xs text-gray-400">Fecha</p>
                        <p class="font-bold text-gray-900 dark:text-gray-100">{{ \Carbon\Carbon::parse($movement->ocurrio_el)->format('d/m/Y') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Hora registro</p>
                        <p class="font-bold text-gray-900 dark:text-gray-100">{{ $movement->created_at ? \Carbon\Carbon::parse($movement->created_at)->format('H:i') : '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Costo total</p>
                        <p class="font-bold text-rose-600 dark:text-rose-400">$ {{ number_format((float)$movement->costo_total, 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Precio venta</p>
                        <p class="font-bold text-emerald-600 dark:text-emerald-400">
                            {{ ((float)($movement->precio_venta ?? 0)) > 0 ? '$ '.number_format((float)$movement->precio_venta, 0, ',', '.') : '—' }}
                        </p>
                    </div>
                </div>

                @if($movement->notas)
                    <div class="px-5 pb-4">
                        <p class="text-xs text-gray-400 mb-1">Notas</p>
                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $movement->notas }}</p>
                    </div>
                @endif
            </div>

            <div class="panel">
                <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between gap-3">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">Líneas del movimiento</h2>
                    <span class="text-xs text-gray-400">{{ $lines->count() }} {{ $lines->count() === 1 ? 'línea' : 'líneas' }}</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="dt">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Código</th>
                                <th>Unidad</th>
                                <th class="text-right">Cantidad</th>
                                <th class="text-right">Costo unit.</th>
                                <th class="text-right">Costo total</th>
                                <th>Fecha lote</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($lines as $line)
                                <tr>
                                    <td class="font-semibold text-gray-900 dark:text-gray-100">{{ $line->producto }}</td>
                                    <td class="text-gray-500 dark:text-gray-400">{{ $line->codigo ?? '—' }}</td>
                                    <td class="text-gray-500 dark:text-gray-400">{{ $line->unidad ?? '—' }}</td>
                                    <td class="text-right tabular-nums">{{ number_format((float)$line->cantidad, 2, ',', '.') }}</td>
                                    <td class="text-right tabular-nums">$ {{ number_format((float)$line->costo_unitario, 2, ',', '.') }}</td>
                                    <td class="text-right tabular-nums font-semibold">$ {{ number_format((float)$line->costo_total, 0, ',', '.') }}</td>
                                    <td class="text-gray-500 dark:text-gray-400">{{ $line->lote_fecha ? \Carbon\Carbon::parse($line->lote_fecha)->format('d/m/Y') : '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-10 text-gray-400">Sin líneas registradas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
