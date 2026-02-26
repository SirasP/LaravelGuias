<x-app-layout>
    @php
        $tipoLabel = $tipo !== '' ? $tipo : 'Operación';
        $isVenta = $tipo === 'Venta';
        $isEpp = $tipo === 'EPP';
        $backParams = array_filter(['tipo' => $isVenta ? 'Venta' : null, 'q' => $destinatario]);

        $flatLines = $lines->flatten(1);

        // Top products by quantity
        $topProducts = $flatLines
            ->groupBy('producto')
            ->map(fn($g, $name) => (object) [
                'nombre' => $name,
                'cantidad' => (float) $g->sum('cantidad'),
                'costo' => (float) $g->sum('costo_total'),
                'unidad' => (string) ($g->first()->unidad ?? ''),
            ])
            ->sortByDesc('cantidad')
            ->take(8)
            ->values();
        $maxQty = $topProducts->max('cantidad') ?: 1;

        // Margen total
        $margenTotal = ($isVenta && $summary->costo_total > 0 && $summary->venta_total > 0)
            ? round((($summary->venta_total - $summary->costo_total) / $summary->costo_total) * 100, 1)
            : null;

        // Date range
        $fechas = $movements->pluck('ocurrio_el');
        $primeraMov = $fechas->min();
        $ultimaMov = $fechas->max();
    @endphp

    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 min-w-0 text-xs">
                <a href="{{ route('gmail.inventory.exits') }}"
                    class="flex items-center gap-1 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition font-medium shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                    </svg>
                    <span class="hidden sm:inline">Salidas de inventario</span>
                </a>
                <svg class="w-3 h-3 text-gray-300 dark:text-gray-600 shrink-0" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                </svg>
                <span class="font-bold text-gray-700 dark:text-gray-300 truncate">{{ $destinatario }}</span>
            </div>

            {{-- DERECHA --}}
            <div class="flex items-center gap-3 shrink-0">

                <a href="{{ route(
    'gmail.inventory.exits.group.pdf',
    array_filter(['destinatario' => $destinatario, 'tipo' => $tipo ?: null])
) }}" target="_blank" class="inline-flex gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold
                   bg-rose-50 hover:bg-rose-100
                   dark:bg-rose-900/20 dark:hover:bg-rose-900/40
                   text-rose-700 dark:text-rose-400
                   border border-rose-200 dark:border-rose-800 transition">

                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 13h4M10 17h4M12 9v4" />
                    </svg>
                    Descargar PDF
                </a>

                <span class="text-xs text-gray-400">
                    {{ $isVenta ? '💰' : ($isEpp ? '🦺' : '📦') }}
                    {{ $tipoLabel }}
                </span>

            </div>
        </div>
    </x-slot>

    <style>
        .page-bg {
            background: #f1f5f9;
            min-height: 100%
        }

        .dark .page-bg {
            background: #0d1117
        }

        .panel {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            overflow: hidden
        }

        .dark .panel {
            background: #161c2c;
            border-color: #1e2a3b
        }

        .kpi-box {
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            border-radius: 14px;
            padding: 14px 16px
        }

        .dark .kpi-box {
            background: #0f1623;
            border-color: #1e2a3b
        }

        .stat-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 3px
        }

        .mov-row {
            border-radius: 14px;
            border: 1px solid #f1f5f9;
            background: #fafafa;
            overflow: hidden;
            transition: .15s
        }

        .mov-row:hover {
            border-color: #e2e8f0
        }

        .dark .mov-row {
            background: #0f1623;
            border-color: #1e2a3b
        }

        .dark .mov-row:hover {
            border-color: #2d3a52
        }

        .bar-fill {
            height: 6px;
            border-radius: 3px;
            transition: width .3s
        }
    </style>

    <div class="page-bg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

            {{-- Hero panel --}}
            <div class="panel">
                <div class="h-1.5 {{ $isVenta ? 'bg-emerald-500' : ($isEpp ? 'bg-blue-500' : 'bg-slate-400') }}"></div>
                <div class="px-5 pt-5 pb-4">
                    <div class="flex items-start justify-between gap-4 mb-5">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 mb-2 flex-wrap">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold
                                    {{ $isVenta ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
    : ($isEpp ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'
        : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300') }}">
                                    {{ $isVenta ? '💰' : ($isEpp ? '🦺' : '📦') }} {{ $tipoLabel }}
                                </span>
                                <span class="text-xs text-gray-400">Resumen consolidado</span>
                            </div>
                            <h1
                                class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-gray-100 leading-tight break-words">
                                {{ $destinatario }}
                            </h1>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-xs text-gray-400">Período</p>
                            @if ($primeraMov && $ultimaMov && $primeraMov !== $ultimaMov)
                                <p class="text-sm font-bold text-gray-700 dark:text-gray-300 tabular-nums">
                                    {{ \Carbon\Carbon::parse($primeraMov)->format('d/m/Y') }}
                                </p>
                                <p class="text-xs text-gray-400">—</p>
                                <p class="text-sm font-bold text-gray-700 dark:text-gray-300 tabular-nums">
                                    {{ \Carbon\Carbon::parse($ultimaMov)->format('d/m/Y') }}
                                </p>
                            @elseif ($ultimaMov)
                                <p class="text-sm font-bold text-gray-700 dark:text-gray-300 tabular-nums">
                                    {{ \Carbon\Carbon::parse($ultimaMov)->format('d/m/Y') }}
                                </p>
                            @endif
                        </div>
                    </div>

                    {{-- KPI grid --}}
                    <div
                        class="grid grid-cols-2 {{ $isVenta ? 'sm:grid-cols-3 lg:grid-cols-5' : 'sm:grid-cols-3' }} gap-3">
                        <div class="kpi-box">
                            <p class="stat-label">Movimientos</p>
                            <p class="text-2xl font-black text-gray-900 dark:text-gray-100">{{ $summary->movimientos }}
                            </p>
                        </div>
                        <div class="kpi-box">
                            <p class="stat-label">Unidades totales</p>
                            <p class="text-2xl font-black text-gray-900 dark:text-gray-100">
                                {{ number_format((float) $summary->cantidad_total, 0, ',', '.') }}
                            </p>
                        </div>
                        <div class="kpi-box" style="background:#fff1f2; border-color:#fecdd3">
                            <p class="stat-label" style="color:#f43f5e">Costo total</p>
                            <p class="text-2xl font-black text-rose-600 dark:text-rose-400">$
                                {{ number_format((float) $summary->costo_total, 0, ',', '.') }}
                            </p>
                        </div>
                        @if ($isVenta)
                            <div class="kpi-box" style="background:#ecfdf5; border-color:#bbf7d0">
                                <p class="stat-label" style="color:#059669">Venta total</p>
                                <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400">
                                    {{ $summary->venta_total > 0 ? '$ ' . number_format((float) $summary->venta_total, 0, ',', '.') : '—' }}
                                </p>
                            </div>
                            <div
                                class="kpi-box col-span-2 sm:col-span-1 {{ $margenTotal !== null && $margenTotal >= 0 ? 'bg-emerald-50 dark:bg-emerald-900/20' : ($margenTotal !== null ? 'bg-rose-50 dark:bg-rose-900/20' : '') }}">
                                <p class="stat-label">Margen</p>
                                <p
                                    class="text-2xl font-black {{ $margenTotal !== null ? ($margenTotal >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600') : 'text-gray-400' }}">
                                    {{ $margenTotal !== null ? $margenTotal . '%' : '—' }}
                                </p>
                                @if ($isVenta && $summary->sin_precio > 0)
                                    <p class="text-[11px] text-amber-500 font-semibold mt-0.5">{{ $summary->sin_precio }} sin
                                        precio</p>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Main content: history + top products --}}
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

                {{-- Movement history --}}
                <div class="xl:col-span-2 panel">
                    <div
                        class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between gap-3">
                        <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">Historial de movimientos</h2>
                        <span class="text-xs text-gray-400 font-medium">{{ $movements->count() }} registros</span>
                    </div>

                    <div class="p-4 space-y-3">
                        @foreach($movements as $m)
                            @php
                                $mCosto = (float) $m->costo_total;
                                $mPv = (float) ($m->precio_venta ?? 0);
                                $mMg = ($isVenta && $mCosto > 0 && $mPv > 0) ? round((($mPv - $mCosto) / $mCosto) * 100, 1) : null;
                                $mLines = $lines->get($m->id, collect());
                                $showUrl = route('gmail.inventory.exits.show', ['id' => $m->id, 'from' => 'group', 'destinatario' => $destinatario, 'tipo' => $tipo]);
                            @endphp

                            <div class="mov-row" x-data="{ open: false }">
                                {{-- Card header --}}
                                <div class="px-4 py-3 flex items-center justify-between gap-3 cursor-pointer"
                                    @click="open = !open">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div
                                            class="shrink-0 w-9 h-9 rounded-xl {{ $isVenta ? 'bg-emerald-100 dark:bg-emerald-900/30' : ($isEpp ? 'bg-blue-100 dark:bg-blue-900/30' : 'bg-gray-100 dark:bg-gray-800') }} flex items-center justify-center">
                                            <span
                                                class="text-base leading-none">{{ $isVenta ? '💰' : ($isEpp ? '🦺' : '📦') }}</span>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                                {{ \Carbon\Carbon::parse($m->ocurrio_el)->format('d/m/Y') }}
                                            </p>
                                            <p class="text-xs text-gray-400">#{{ $m->id }} · {{ $mLines->count() }}
                                                {{ $mLines->count() === 1 ? 'producto' : 'productos' }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-4 shrink-0">
                                        <div class="text-right hidden sm:block">
                                            <p class="text-xs text-gray-400">Costo</p>
                                            <p class="text-sm font-bold text-rose-600 dark:text-rose-400 tabular-nums">$
                                                {{ number_format($mCosto, 0, ',', '.') }}
                                            </p>
                                        </div>
                                        @if ($isVenta && $mPv > 0)
                                            <div class="text-right hidden sm:block">
                                                <p class="text-xs text-gray-400">Venta</p>
                                                <p
                                                    class="text-sm font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">
                                                    $ {{ number_format($mPv, 0, ',', '.') }}</p>
                                            </div>
                                        @endif
                                        @if ($mMg !== null)
                                            <span
                                                class="hidden sm:inline text-xs font-bold px-2 py-0.5 rounded-full {{ $mMg >= 0 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-rose-100 text-rose-700' }}">
                                                {{ $mMg }}%
                                            </span>
                                        @endif
                                        <svg class="w-4 h-4 text-gray-400 transition-transform duration-200"
                                            :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </div>

                                {{-- Expandable detail --}}
                                <div x-show="open" x-collapse x-cloak class="border-t border-gray-100 dark:border-gray-800">
                                    {{-- Mobile cost row --}}
                                    <div class="sm:hidden px-4 pt-3 grid grid-cols-2 gap-2">
                                        <div class="bg-rose-50 dark:bg-rose-900/20 rounded-xl px-3 py-2">
                                            <p class="text-[10px] text-rose-500 uppercase tracking-wide font-bold">Costo</p>
                                            <p class="text-sm font-bold text-rose-600 dark:text-rose-400">$
                                                {{ number_format($mCosto, 0, ',', '.') }}
                                            </p>
                                        </div>
                                        @if ($isVenta)
                                            <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-xl px-3 py-2">
                                                <p class="text-[10px] text-emerald-500 uppercase tracking-wide font-bold">Venta
                                                </p>
                                                <p class="text-sm font-bold text-emerald-600 dark:text-emerald-400">
                                                    {{ $mPv > 0 ? '$ ' . number_format($mPv, 0, ',', '.') : '—' }}
                                                </p>
                                            </div>
                                        @endif
                                    </div>

                                    @if ($mLines->isNotEmpty())
                                        {{-- Desktop lines table --}}
                                        <div class="hidden sm:block px-4 pt-3">
                                            <table class="w-full text-xs">
                                                <thead>
                                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                                        <th class="text-left pb-2 text-gray-400 font-semibold">Producto</th>
                                                        <th class="text-right pb-2 text-gray-400 font-semibold pr-2">Cant.</th>
                                                        <th class="text-right pb-2 text-gray-400 font-semibold">Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-50 dark:divide-gray-800/50">
                                                    @foreach($mLines as $line)
                                                        <tr>
                                                            <td class="py-1.5 pr-2">
                                                                <p class="font-semibold text-gray-800 dark:text-gray-200">
                                                                    {{ $line->producto }}
                                                                </p>
                                                                <p class="text-gray-400">{{ $line->unidad }}</p>
                                                            </td>
                                                            <td
                                                                class="py-1.5 pr-2 text-right tabular-nums text-gray-600 dark:text-gray-300">
                                                                {{ number_format((float) $line->cantidad, 2, ',', '.') }}
                                                            </td>
                                                            <td
                                                                class="py-1.5 text-right tabular-nums font-semibold text-gray-800 dark:text-gray-200">
                                                                $ {{ number_format((float) $line->costo_total, 0, ',', '.') }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        {{-- Mobile lines --}}
                                        <div class="sm:hidden px-4 pt-2 space-y-1.5">
                                            @foreach($mLines as $line)
                                                <div class="flex items-center justify-between text-xs">
                                                    <span
                                                        class="text-gray-700 dark:text-gray-300 font-medium truncate mr-2">{{ $line->producto }}</span>
                                                    <span
                                                        class="tabular-nums text-gray-500 shrink-0">{{ number_format((float) $line->cantidad, 2, ',', '.') }}
                                                        · $ {{ number_format((float) $line->costo_total, 0, ',', '.') }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if ($m->notas)
                                        <p class="px-4 pt-2 text-xs text-gray-500 dark:text-gray-400 italic">{{ $m->notas }}</p>
                                    @endif

                                    <div class="px-4 py-3 flex justify-end">
                                        <a href="{{ $showUrl }}"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-900/20 dark:hover:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 transition">
                                            Ver detalle completo
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                                    d="M9 5l7 7-7 7" />
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Top products --}}
                <div class="panel">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Productos más retirados</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Acumulado histórico</p>
                    </div>
                    <div class="p-4 space-y-3">
                        @forelse($topProducts as $i => $p)
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="min-w-0 flex items-center gap-2">
                                        <span
                                            class="text-[10px] font-bold text-gray-400 w-4 text-right shrink-0">{{ $i + 1 }}</span>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                                            {{ $p->nombre }}
                                        </p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <p class="text-xs font-bold text-gray-700 dark:text-gray-300 tabular-nums">
                                            {{ number_format((float) $p->cantidad, 2, ',', '.') }} {{ $p->unidad }}
                                        </p>
                                        <p class="text-[10px] text-gray-400 tabular-nums">$
                                            {{ number_format((float) $p->costo, 0, ',', '.') }}
                                        </p>
                                    </div>
                                </div>
                                <div class="bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden h-1.5">
                                    <div class="bar-fill {{ $isVenta ? 'bg-emerald-500' : ($isEpp ? 'bg-blue-500' : 'bg-slate-400') }}"
                                        style="width:{{ round(($p->cantidad / $maxQty) * 100) }}%"></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-400 text-center py-6">Sin productos.</p>
                        @endforelse
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>