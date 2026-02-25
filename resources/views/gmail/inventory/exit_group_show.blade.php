<x-app-layout>
    @php
        $tipoLabel = $tipo !== '' ? $tipo : 'Operación';
        $backParams = array_filter([
            'tipo' => $tipo === 'Venta' ? 'Venta' : null,
            'q' => $destinatario,
        ]);
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
                <span class="font-bold text-gray-700 dark:text-gray-300 truncate">{{ $destinatario }}</span>
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
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">

            <div class="panel">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs text-gray-400">Resumen de {{ $tipoLabel }}</p>
                        <h1 class="text-xl sm:text-2xl font-black text-gray-900 dark:text-gray-100 leading-tight">Ficha consolidada · {{ $destinatario }}</h1>
                    </div>
                    <span class="chip {{ $tipo === 'Venta' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' }}">
                        {{ $tipoLabel }}
                    </span>
                </div>

                <div class="px-5 py-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 text-sm">
                    <div>
                        <p class="text-xs text-gray-400">Movimientos</p>
                        <p class="font-bold text-gray-900 dark:text-gray-100">{{ $summary->movimientos }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Unidades</p>
                        <p class="font-bold text-gray-900 dark:text-gray-100">{{ number_format((float)$summary->cantidad_total, 2, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Costo total</p>
                        <p class="font-bold text-rose-600 dark:text-rose-400">$ {{ number_format((float)$summary->costo_total, 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Venta total</p>
                        <p class="font-bold text-emerald-600 dark:text-emerald-400">{{ (float)$summary->venta_total > 0 ? '$ '.number_format((float)$summary->venta_total, 0, ',', '.') : '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Sin precio venta</p>
                        <p class="font-bold text-amber-600 dark:text-amber-400">{{ $summary->sin_precio }}</p>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between gap-3">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">Movimientos del grupo</h2>
                    <span class="text-xs text-gray-400">{{ $movements->count() }} {{ $movements->count() === 1 ? 'registro' : 'registros' }}</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="dt">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Fecha</th>
                                <th>Notas</th>
                                <th class="text-right">Costo</th>
                                <th class="text-right">Venta</th>
                                <th>Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($movements as $m)
                                <tr>
                                    <td class="font-semibold">{{ $m->id }}</td>
                                    <td>{{ \Carbon\Carbon::parse($m->ocurrio_el)->format('d/m/Y') }}</td>
                                    <td class="text-gray-500 dark:text-gray-400">{{ $m->notas ? \Illuminate\Support\Str::limit($m->notas, 60) : '—' }}</td>
                                    <td class="text-right tabular-nums">$ {{ number_format((float)$m->costo_total, 0, ',', '.') }}</td>
                                    <td class="text-right tabular-nums">{{ ((float)($m->precio_venta ?? 0)) > 0 ? '$ '.number_format((float)$m->precio_venta, 0, ',', '.') : '—' }}</td>
                                    <td>
                                        <a href="{{ route('gmail.inventory.exits.show', $m->id) }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                                            Ver movimiento
                                        </a>
                                    </td>
                                </tr>
                                @if($lines->has($m->id))
                                    <tr>
                                        <td colspan="6" class="bg-gray-50/70 dark:bg-gray-900/30">
                                            <div class="text-xs text-gray-600 dark:text-gray-300 leading-6">
                                                @foreach($lines->get($m->id) as $line)
                                                    <span class="inline-block mr-4">
                                                        {{ $line->producto }} · {{ number_format((float)$line->cantidad, 2, ',', '.') }} {{ $line->unidad }} · $ {{ number_format((float)$line->costo_total, 0, ',', '.') }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
