<x-app-layout>
    @php
        $tipoLabel = $tipo !== '' ? $tipo : 'Operación';
        $isVenta = $tipo === 'Venta';
        $backParams = array_filter([
            'tipo' => $isVenta ? 'Venta' : null,
            'q' => $destinatario,
        ]);
        $flatLines = $lines->flatten(1);
        $topProducts = $flatLines
            ->groupBy('producto')
            ->map(function ($g, $name) {
                return (object) [
                    'nombre' => $name,
                    'cantidad' => (float) $g->sum('cantidad'),
                    'costo' => (float) $g->sum('costo_total'),
                    'unidad' => (string) ($g->first()->unidad ?? ''),
                ];
            })
            ->sortByDesc('cantidad')
            ->take(6)
            ->values();
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
        .panel {
            background:#fff; border:1px solid #e2e8f0; border-radius:16px; overflow:hidden;
        }
        .dark .panel { background:#161c2c; border-color:#1e2a3b }
        .kpi-card {
            background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:14px;
        }
        .dark .kpi-card { background:#161c2c; border-color:#1e2a3b }
        .mov-card {
            background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:12px;
        }
        .dark .mov-card { background:#161c2c; border-color:#1e2a3b }
        .chip { display:inline-flex; align-items:center; border-radius:999px; padding:3px 10px; font-size:11px; font-weight:700 }
        .btn-main {
            display:inline-flex; align-items:center; justify-content:center; gap:6px;
            padding:8px 11px; border-radius:10px; font-size:11px; font-weight:700;
            color:#4f46e5; background:#eef2ff; transition:.15s;
        }
        .btn-main:hover { background:#e0e7ff; color:#4338ca }
        .dark .btn-main { background:rgba(99,102,241,.15); color:#a5b4fc }
        .dark .btn-main:hover { background:rgba(99,102,241,.25); color:#c7d2fe }
    </style>

    <div class="page-bg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">

            <div class="panel">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between gap-3 flex-wrap">
                    <div>
                        <p class="text-xs text-gray-400">Resumen consolidado</p>
                        <h1 class="text-xl sm:text-2xl font-black text-gray-900 dark:text-gray-100 leading-tight">
                            {{ $destinatario }}
                        </h1>
                    </div>
                    <span class="chip {{ $isVenta ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' }}">
                        {{ $tipoLabel }}
                    </span>
                </div>
                <div class="p-4 grid grid-cols-2 lg:grid-cols-5 gap-3">
                    <div class="kpi-card">
                        <p class="text-[11px] text-gray-400">Movimientos</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $summary->movimientos }}</p>
                    </div>
                    <div class="kpi-card">
                        <p class="text-[11px] text-gray-400">Unidades</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ number_format((float)$summary->cantidad_total, 2, ',', '.') }}</p>
                    </div>
                    <div class="kpi-card">
                        <p class="text-[11px] text-gray-400">Costo total</p>
                        <p class="text-lg font-bold text-rose-600 dark:text-rose-400">$ {{ number_format((float)$summary->costo_total, 0, ',', '.') }}</p>
                    </div>
                    <div class="kpi-card">
                        <p class="text-[11px] text-gray-400">Venta total</p>
                        <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400">
                            {{ (float)$summary->venta_total > 0 ? '$ '.number_format((float)$summary->venta_total, 0, ',', '.') : '—' }}
                        </p>
                    </div>
                    <div class="kpi-card col-span-2 lg:col-span-1">
                        <p class="text-[11px] text-gray-400">Sin precio venta</p>
                        <p class="text-lg font-bold text-amber-600 dark:text-amber-400">{{ $summary->sin_precio }}</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

                <div class="xl:col-span-2 panel">
                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between gap-3">
                        <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">Historial de movimientos</h2>
                        <span class="text-xs text-gray-400">{{ $movements->count() }} registros</span>
                    </div>

                    <div class="p-3 space-y-2">
                        @foreach($movements as $m)
                            <div class="mov-card">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-xs text-gray-400">Movimiento #{{ $m->id }}</p>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                            {{ \Carbon\Carbon::parse($m->ocurrio_el)->format('d/m/Y') }}
                                        </p>
                                    </div>
                                    <a href="{{ route('gmail.inventory.exits.show', ['id' => $m->id, 'from' => 'group', 'destinatario' => $destinatario, 'tipo' => $tipo]) }}" class="btn-main">
                                        Ver movimiento
                                    </a>
                                </div>

                                <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
                                    <div>
                                        <p class="text-gray-400">Costo</p>
                                        <p class="font-semibold text-rose-600 dark:text-rose-400">$ {{ number_format((float)$m->costo_total, 0, ',', '.') }}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-400">Venta</p>
                                        <p class="font-semibold text-emerald-600 dark:text-emerald-400">
                                            {{ ((float)($m->precio_venta ?? 0)) > 0 ? '$ '.number_format((float)$m->precio_venta, 0, ',', '.') : '—' }}
                                        </p>
                                    </div>
                                </div>

                                @if($m->notas)
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Str::limit($m->notas, 130) }}</p>
                                @endif

                                @if($lines->has($m->id))
                                    <details class="mt-2">
                                        <summary class="cursor-pointer text-xs font-semibold text-slate-600 dark:text-slate-300">
                                            Ver {{ $lines->get($m->id)->count() }} {{ $lines->get($m->id)->count() === 1 ? 'línea' : 'líneas' }}
                                        </summary>
                                        <div class="mt-1 text-xs text-gray-600 dark:text-gray-300 space-y-1">
                                            @foreach($lines->get($m->id) as $line)
                                                <div class="flex items-center justify-between gap-3 border-b border-gray-100 dark:border-gray-800 pb-1 last:border-0 last:pb-0">
                                                    <p class="truncate">{{ $line->producto }}</p>
                                                    <p class="whitespace-nowrap">
                                                        {{ number_format((float)$line->cantidad, 2, ',', '.') }} {{ $line->unidad }}
                                                        · $ {{ number_format((float)$line->costo_total, 0, ',', '.') }}
                                                    </p>
                                                </div>
                                            @endforeach
                                        </div>
                                    </details>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="panel">
                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Productos más retirados</h3>
                    </div>
                    <div class="p-3 space-y-2">
                        @forelse($topProducts as $p)
                            <div class="kpi-card !p-3">
                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">{{ $p->nombre }}</p>
                                <div class="mt-1 flex items-center justify-between gap-2 text-xs">
                                    <span class="text-gray-500 dark:text-gray-400">
                                        {{ number_format((float)$p->cantidad, 2, ',', '.') }} {{ $p->unidad }}
                                    </span>
                                    <span class="font-semibold text-gray-700 dark:text-gray-300">$ {{ number_format((float)$p->costo, 0, ',', '.') }}</span>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-400 text-center py-6">Sin líneas para mostrar.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
