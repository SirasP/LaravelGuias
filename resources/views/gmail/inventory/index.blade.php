<x-app-layout>
    <x-slot name="header">
        <div class="w-full grid grid-cols-1 lg:grid-cols-[auto,1fr,auto] items-center gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-xl bg-violet-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Inventario</h2>
                    <p class="text-xs text-gray-400 mt-0.5 truncate">FIFO modulo DTE</p>
                </div>
            </div>

            <form method="GET" class="hidden lg:block w-full lg:max-w-xl lg:justify-self-center">
                <div class="flex gap-2">
                    <input type="text" name="q" value="{{ $q }}" class="f-input" placeholder="Buscar por producto, codigo o unidad...">
                    <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-xl bg-violet-600 hover:bg-violet-700 text-white transition">Buscar</button>
                </div>
            </form>

            <div class="hidden lg:flex items-center justify-end text-xs text-gray-400">
                {{ $products->total() }} productos
            </div>
        </div>
    </x-slot>

    <style>
        .page-bg { background:#f1f5f9; min-height:100% }
        .dark .page-bg { background:#0d1117 }
        .f-input { width:100%; border-radius:12px; border:1px solid #e2e8f0; background:#fff; padding:9px 12px; font-size:13px; color:#111827; outline:none }
        .f-input:focus { border-color:#8b5cf6; box-shadow:0 0 0 3px rgba(139,92,246,.12) }
        .dark .f-input { border-color:#1e2a3b; background:#0d1117; color:#f1f5f9 }
        .card {
            background:#fff;
            border:1px solid #e2e8f0;
            border-radius:16px;
            padding:14px;
            transition:transform .15s ease, box-shadow .15s ease;
        }
        .card:hover { transform:translateY(-2px); box-shadow:0 10px 25px rgba(15,23,42,.08) }
        .dark .card { background:#161c2c; border-color:#1e2a3b }
        .kv { font-size:12px; color:#94a3b8 }
        .vv { font-size:14px; font-weight:700; color:#334155 }
        .dark .vv { color:#e2e8f0 }
    </style>

    <div class="page-bg">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">
            <form method="GET" class="lg:hidden">
                <div class="flex gap-2">
                    <input type="text" name="q" value="{{ $q }}" class="f-input" placeholder="Buscar por producto, codigo o unidad...">
                    <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-xl bg-violet-600 hover:bg-violet-700 text-white transition">Buscar</button>
                </div>
            </form>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('gmail.inventory.index', array_filter(['q' => $q])) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl transition {{ $estado === '' && $stock === '' ? 'bg-violet-600 text-white' : 'bg-white text-gray-700 border border-gray-200 dark:bg-gray-900/40 dark:text-gray-300 dark:border-gray-700' }}">
                    Todos ({{ $products->total() }})
                </a>
                <a href="{{ route('gmail.inventory.index', array_filter(['q' => $q, 'estado' => 'activos', 'stock' => $stock])) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl transition {{ $estado === 'activos' ? 'bg-emerald-600 text-white' : 'bg-white text-gray-700 border border-gray-200 dark:bg-gray-900/40 dark:text-gray-300 dark:border-gray-700' }}">
                    Activos ({{ $totalActivos }})
                </a>
                <a href="{{ route('gmail.inventory.index', array_filter(['q' => $q, 'estado' => 'inactivos', 'stock' => $stock])) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl transition {{ $estado === 'inactivos' ? 'bg-gray-700 text-white' : 'bg-white text-gray-700 border border-gray-200 dark:bg-gray-900/40 dark:text-gray-300 dark:border-gray-700' }}">
                    Inactivos ({{ $totalInactivos }})
                </a>
                <a href="{{ route('gmail.inventory.index', array_filter(['q' => $q, 'estado' => $estado, 'stock' => 'con_stock'])) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl transition {{ $stock === 'con_stock' ? 'bg-cyan-600 text-white' : 'bg-white text-gray-700 border border-gray-200 dark:bg-gray-900/40 dark:text-gray-300 dark:border-gray-700' }}">
                    Con stock ({{ $totalConStock }})
                </a>
                <a href="{{ route('gmail.inventory.index', array_filter(['q' => $q, 'estado' => $estado, 'stock' => 'sin_stock'])) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl transition {{ $stock === 'sin_stock' ? 'bg-amber-600 text-white' : 'bg-white text-gray-700 border border-gray-200 dark:bg-gray-900/40 dark:text-gray-300 dark:border-gray-700' }}">
                    Sin stock ({{ $totalSinStock }})
                </a>
            </div>

            @if($products->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4">
                    @foreach($products as $p)
                        <div class="card">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-base font-bold text-gray-900 dark:text-gray-100 truncate">{{ $p->nombre }}</p>
                                    <p class="text-xs text-gray-400 mt-0.5">{{ $p->codigo ?? 'Sin codigo' }}</p>
                                </div>
                                <span class="inline-flex px-2.5 py-1 text-[11px] font-semibold rounded-full {{ (int) $p->is_active === 1 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300' : 'bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">
                                    {{ (int) $p->is_active === 1 ? 'Activo' : 'Inactivo' }}
                                </span>
                            </div>

                            <div class="mt-4 grid grid-cols-2 gap-3">
                                <div>
                                    <p class="kv">Unidad</p>
                                    <p class="vv">{{ $p->unidad }}</p>
                                </div>
                                <div>
                                    <p class="kv">Stock actual</p>
                                    <p class="vv">{{ number_format((float) $p->stock_actual, 4, ',', '.') }}</p>
                                </div>
                                <div class="col-span-2">
                                    <p class="kv">Costo promedio</p>
                                    <p class="vv">$ {{ number_format((float) $p->costo_promedio, 2, ',', '.') }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-white dark:bg-gray-900/40 border border-gray-200 dark:border-gray-800 rounded-2xl p-10 text-center text-gray-400">
                    Aun no hay productos en inventario.
                </div>
            @endif

            <div>{{ $products->links() }}</div>
        </div>
    </div>
</x-app-layout>
