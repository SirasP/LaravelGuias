<x-app-layout>
    <x-slot name="header">
        <div class="w-full grid grid-cols-1 lg:grid-cols-[auto,1fr,auto] items-center gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-xl bg-violet-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Inventario Valorizado</h2>
                    <p class="text-xs text-gray-400 mt-0.5 truncate">Stock × Costo promedio</p>
                </div>
            </div>
            <form method="GET" class="hidden lg:block w-full lg:max-w-xl lg:justify-self-center">
                <div class="flex gap-2">
                    <input type="text" name="q" value="{{ $q }}"
                           class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 px-4 py-2 text-sm focus:outline-none focus:border-violet-400"
                           placeholder="Buscar por nombre o código...">
                    <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-xl bg-violet-600 hover:bg-violet-700 text-white transition">Buscar</button>
                </div>
            </form>
            <div class="hidden lg:flex items-center gap-3">
                <span class="text-xs text-gray-400">{{ $totalProductos }} productos</span>
                <button onclick="window.print()"
                    class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400 hover:border-violet-400 hover:text-violet-600 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Imprimir
                </button>
            </div>
        </div>
    </x-slot>

    <div class="min-h-screen bg-gray-50 dark:bg-gray-950 py-6 px-4 sm:px-6 lg:px-8">
        <div class="max-w-8xl mx-auto space-y-5">

            {{-- KPIs --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Valor total --}}
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5">
                    <div class="flex items-start justify-between mb-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Valor total inventario</p>
                        <div class="w-7 h-7 rounded-lg bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center shrink-0">
                            <svg class="w-3.5 h-3.5 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                    <p class="text-2xl font-black text-violet-700 dark:text-violet-400">$ {{ number_format($totalValor, 0, ',', '.') }}</p>
                    <p class="text-xs text-gray-400 mt-1">Solo productos activos con stock</p>
                </div>

                {{-- Productos activos --}}
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5">
                    <div class="flex items-start justify-between mb-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Productos activos</p>
                        <div class="w-7 h-7 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center shrink-0">
                            <svg class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                        </div>
                    </div>
                    <p class="text-2xl font-black text-gray-800 dark:text-gray-100">{{ $totalProductos }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ $totalConStock }} con stock disponible</p>
                </div>

                {{-- Sin stock --}}
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5">
                    <div class="flex items-start justify-between mb-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Sin stock</p>
                        <div class="w-7 h-7 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center shrink-0">
                            <svg class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                            </svg>
                        </div>
                    </div>
                    <p class="text-2xl font-black text-amber-600 dark:text-amber-400">{{ $totalProductos - $totalConStock }}</p>
                    <p class="text-xs text-gray-400 mt-1">Productos con stock = 0</p>
                </div>

                {{-- Bajo mínimo --}}
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5">
                    <div class="flex items-start justify-between mb-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Bajo mínimo</p>
                        <div class="w-7 h-7 rounded-lg {{ $totalBajoMinimo > 0 ? 'bg-orange-100 dark:bg-orange-900/30' : 'bg-gray-100 dark:bg-gray-800' }} flex items-center justify-center shrink-0">
                            <svg class="w-3.5 h-3.5 {{ $totalBajoMinimo > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                    </div>
                    <p class="text-2xl font-black {{ $totalBajoMinimo > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-gray-400' }}">{{ $totalBajoMinimo }}</p>
                    <p class="text-xs text-gray-400 mt-1">Bajo stock mínimo configurado</p>
                </div>
            </div>

            {{-- Búsqueda mobile --}}
            <form method="GET" class="lg:hidden">
                <div class="flex gap-2">
                    <input type="text" name="q" value="{{ $q }}"
                           class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 px-4 py-2 text-sm focus:outline-none"
                           placeholder="Buscar...">
                    <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-xl bg-violet-600 text-white">Buscar</button>
                </div>
            </form>

            {{-- Tabla --}}
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200">Detalle por producto</h3>
                        @if($q)
                            <span class="text-xs bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300 px-2 py-0.5 rounded-full font-medium">
                                "{{ $q }}"
                            </span>
                        @endif
                    </div>
                    <span class="text-xs text-gray-400">Ordenado por valor total ↓</span>
                </div>

                @if($products->isEmpty())
                <div class="p-10 text-center">
                    <svg class="w-10 h-10 text-gray-300 dark:text-gray-700 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-sm text-gray-400">No se encontraron productos.</p>
                </div>
                @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800/60">
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400 w-10">#</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-400">Producto</th>
                                <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Stock</th>
                                <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Mínimo</th>
                                <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Costo prom.</th>
                                <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-gray-400">Valor total</th>
                                <th class="px-4 py-3 text-center text-[10px] font-bold uppercase tracking-wider text-gray-400">Participación</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @php $rank = 0; @endphp
                            @foreach($products as $p)
                            @php
                                $rank++;
                                $valor = (float)$p->stock_actual * (float)$p->costo_promedio;
                                $pct = $totalValor > 0 ? ($valor / $totalValor) * 100 : 0;
                                $bajoMin = $p->stock_minimo !== null && (float)$p->stock_actual < (float)$p->stock_minimo;
                                $sinStock = (float)$p->stock_actual <= 0;

                                // Color de la barra de participación según el rank
                                $barColor = match(true) {
                                    $rank === 1 => 'bg-violet-600',
                                    $rank === 2 => 'bg-violet-500',
                                    $rank === 3 => 'bg-violet-400',
                                    $pct >= 5   => 'bg-violet-300',
                                    default     => 'bg-gray-300 dark:bg-gray-600',
                                };
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition
                                       {{ $bajoMin ? 'bg-orange-50/40 dark:bg-orange-900/10' : '' }}
                                       {{ $sinStock && !$bajoMin ? 'opacity-50' : '' }}">

                                {{-- Rank con medallas para top 3 --}}
                                <td class="px-4 py-3 text-xs text-center">
                                    @if($rank === 1)
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-400 font-bold text-[10px]">1</span>
                                    @elseif($rank === 2)
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300 font-bold text-[10px]">2</span>
                                    @elseif($rank === 3)
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 font-bold text-[10px]">3</span>
                                    @else
                                        <span class="text-gray-400">{{ $rank }}</span>
                                    @endif
                                </td>

                                {{-- Nombre + código + alertas --}}
                                <td class="px-4 py-3">
                                    <a href="{{ route('gmail.inventory.product', $p->id) }}"
                                       class="hover:text-violet-600 dark:hover:text-violet-400 transition">
                                        <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $p->nombre }}</span>
                                        @if($p->codigo)
                                            <span class="text-xs text-gray-400 ml-1 font-mono">{{ $p->codigo }}</span>
                                        @endif
                                    </a>
                                    @if($bajoMin)
                                        <span class="ml-1 text-[9px] font-bold text-orange-600 dark:text-orange-400 bg-orange-100 dark:bg-orange-900/30 px-1.5 py-0.5 rounded border border-orange-200 dark:border-orange-800">
                                            ⚠ bajo mín.
                                        </span>
                                    @endif
                                    @if($sinStock)
                                        <span class="ml-1 text-[9px] font-bold text-gray-400 bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">
                                            sin stock
                                        </span>
                                    @endif
                                </td>

                                {{-- Stock --}}
                                <td class="px-4 py-3 text-right font-semibold tabular-nums
                                           {{ $sinStock ? 'text-gray-400' : ($bajoMin ? 'text-orange-600 dark:text-orange-400' : 'text-gray-700 dark:text-gray-300') }}">
                                    {{ number_format((float)$p->stock_actual, 2, ',', '.') }}
                                    <span class="text-xs text-gray-400 font-normal">{{ $p->unidad }}</span>
                                </td>

                                {{-- Mínimo --}}
                                <td class="px-4 py-3 text-right text-xs {{ $bajoMin ? 'text-orange-500 font-semibold' : 'text-gray-400' }}">
                                    {{ $p->stock_minimo !== null ? number_format((float)$p->stock_minimo, 2, ',', '.') : '—' }}
                                </td>

                                {{-- Costo promedio --}}
                                <td class="px-4 py-3 text-right tabular-nums text-gray-600 dark:text-gray-400">
                                    {{ $p->costo_promedio > 0 ? '$ '.number_format((float)$p->costo_promedio, 0, ',', '.') : '—' }}
                                </td>

                                {{-- Valor total --}}
                                <td class="px-4 py-3 text-right font-bold tabular-nums
                                           {{ $rank <= 3 ? 'text-violet-700 dark:text-violet-400' : 'text-gray-700 dark:text-gray-300' }}">
                                    {{ $valor > 0 ? '$ '.number_format($valor, 0, ',', '.') : '—' }}
                                </td>

                                {{-- Participación --}}
                                <td class="px-4 py-3">
                                    @if($pct > 0)
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 bg-gray-100 dark:bg-gray-800 rounded-full h-2 overflow-hidden">
                                            <div class="h-2 {{ $barColor }} rounded-full transition-all" style="width: {{ min($pct, 100) }}%"></div>
                                        </div>
                                        <span class="text-xs text-gray-500 w-10 text-right tabular-nums">{{ number_format($pct, 1) }}%</span>
                                    </div>
                                    @else
                                        <span class="text-xs text-gray-300">—</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-violet-50 dark:bg-violet-900/20 border-t-2 border-violet-200 dark:border-violet-800">
                                <td colspan="2" class="px-4 py-3 text-sm font-bold text-violet-700 dark:text-violet-300">
                                    Total inventario valorizado
                                </td>
                                <td colspan="3" class="px-4 py-3 text-right text-xs text-gray-500">
                                    {{ $totalConStock }} de {{ $totalProductos }} productos con stock
                                </td>
                                <td class="px-4 py-3 text-right text-lg font-black text-violet-700 dark:text-violet-300 tabular-nums">
                                    $ {{ number_format($totalValor, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-center text-xs font-bold text-violet-500">100%</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @endif
            </div>

            {{-- Nota al pie --}}
            <p class="text-center text-xs text-gray-400">
                Datos al {{ now()->format('d/m/Y H:i') }} &mdash; Solo productos activos &mdash; Valor calculado como Stock × Costo promedio
            </p>

        </div>
    </div>

    <style>
    @media print {
        nav, header, form, .print\:hidden { display: none !important; }
        body { background: white !important; }
        .rounded-2xl { border-radius: 0 !important; }
        a { color: inherit !important; text-decoration: none !important; }
    }
    </style>
</x-app-layout>
