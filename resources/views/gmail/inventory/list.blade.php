<x-app-layout>
    <x-slot name="header">
        <div class="w-full grid grid-cols-1 lg:grid-cols-[auto,1fr,auto] items-center gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-xl bg-violet-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Inventario</h2>
                    <p class="text-xs text-gray-400 mt-0.5 truncate">FIFO modulo DTE</p>
                </div>
            </div>

            <form method="GET" class="hidden lg:block w-full lg:max-w-xl lg:justify-self-center">
                <div class="flex gap-2">
                    @if($bodegaId !== null)<input type="hidden" name="bodega_id" value="{{ $bodegaId }}">@endif
                    <input type="text" name="q" value="{{ $q }}" class="f-input"
                        placeholder="Buscar por producto, codigo o unidad...">
                    <button type="submit"
                        class="px-4 py-2 text-xs font-semibold rounded-xl bg-violet-600 hover:bg-violet-700 text-white transition">Buscar</button>
                </div>
            </form>

            <div class="hidden lg:flex items-center justify-end gap-2">
                <button type="button" @click="$store.invBodega.modalOpen = true; $store.invBodega.forcedOpen = true"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl
                           border transition
                           {{ $bodegaId !== null
                               ? 'bg-violet-600 text-white border-violet-600 shadow-sm'
                               : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-violet-400 hover:text-violet-600' }}">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7l9-4 9 4v10l-9 4-9-4V7z"/>
                    </svg>
                    <span x-text="$store.invBodega.label"></span>
                </button>
                <span class="text-xs text-gray-400">{{ $products->total() }} productos</span>
            </div>
        </div>
    </x-slot>

    <style>
        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .au {
            animation: fadeUp .35s ease both;
        }

        .d1 {
            animation-delay: .06s;
        }

        .d2 {
            animation-delay: .12s;
        }

        .d3 {
            animation-delay: .18s;
        }

        .d4 {
            animation-delay: .24s;
        }

        .d5 {
            animation-delay: .30s;
        }

        .page-bg {
            background: #f1f5f9;
            min-height: 100%
        }

        .dark .page-bg {
            background: #0d1117
        }

        .f-input {
            width: 100%;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: #fff;
            padding: 9px 12px;
            font-size: 13px;
            color: #111827;
            outline: none
        }

        .f-input:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, .12)
        }

        .dark .f-input {
            border-color: #1e2a3b;
            background: #0d1117;
            color: #f1f5f9
        }

        .card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 14px;
            position: relative;
            overflow: hidden;
            transition: transform .15s ease, box-shadow .15s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(15, 23, 42, .08)
        }

        .dark .card {
            background: #161c2c;
            border-color: #1e2a3b
        }

        /* Etiqueta transversal sin stock */
        .ribbon-sinstock {
            position: absolute;
            top: 14px;
            left: -30px;
            width: 110px;
            text-align: center;
            transform: rotate(-45deg);
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
            padding: 3px 0;
            background: #ef4444;
            color: #fff;
            z-index: 2;
            pointer-events: none;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .18);
        }

        .kv {
            font-size: 12px;
            color: #94a3b8
        }

        .vv {
            font-size: 14px;
            font-weight: 700;
            color: #334155
        }

        .dark .vv {
            color: #e2e8f0
        }

        .switch {
            position: relative;
            display: inline-flex;
            align-items: center;
            width: 38px;
            height: 22px;
            border-radius: 999px;
            background: #cbd5e1;
            transition: background .18s ease;
            cursor: pointer;
        }
        .dark .switch { background: #334155; }
        .switch-dot {
            width: 16px;
            height: 16px;
            border-radius: 999px;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,.22);
            transform: translateX(3px);
            transition: transform .18s ease;
        }
        input:checked + .switch {
            background: #10b981;
        }
        input:checked + .switch .switch-dot {
            transform: translateX(19px);
        }
    </style>

    @php
        $listaBodegaUrl = route('gmail.inventory.list');
    @endphp
    <script>
        window.__bodegaNames    = @json($bodegas->pluck('nombre', 'id'));
        window.__bodegaListUrl  = '{{ $listaBodegaUrl }}';
        document.addEventListener('alpine:init', () => {
            Alpine.store('invBodega', {
                modalOpen: false,
                selected: {{ $bodegaId !== null ? $bodegaId : 'null' }},
                forcedOpen: false,
                names: window.__bodegaNames,
                get label() {
                    if (this.selected === null) return 'Todas las bodegas';
                    return this.names[this.selected] ?? 'Bodega';
                }
            });
        });
    </script>

    <div class="page-bg"
         x-data="{
             onBodegaSelect(id) {
                 $store.invBodega.selected = id;
                 $store.invBodega.modalOpen = false;
                 const url = new URL(window.__bodegaListUrl, window.location.origin);
                 @if($q !== '') url.searchParams.set('q', '{{ addslashes($q) }}'); @endif
                 @if($estado !== '') url.searchParams.set('estado', '{{ $estado }}'); @endif
                 @if($stock !== '') url.searchParams.set('stock', '{{ $stock }}'); @endif
                 if (id !== null) url.searchParams.set('bodega_id', id);
                 window.location.href = url.toString();
             }
         }"
         @bodega-selected.window="onBodegaSelect($event.detail.id)">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">
            {{-- Búsqueda mobile --}}
            <form method="GET" class="lg:hidden">
                <div class="flex gap-2">
                    <input type="text" name="q" value="{{ $q }}" class="f-input"
                        placeholder="Buscar por producto, codigo o unidad...">
                    @if($bodegaId !== null)<input type="hidden" name="bodega_id" value="{{ $bodegaId }}">@endif
                    <button type="submit"
                        class="px-4 py-2 text-xs font-semibold rounded-xl bg-violet-600 hover:bg-violet-700 text-white transition">Buscar</button>
                </div>
            </form>

            {{-- Chips de filtro (Activos, Con stock, etc.) --}}
            @php
                $base = array_filter(['q' => $q, 'bodega_id' => $bodegaId]);
                $isNone = $estado === '' && $stock === '';
            @endphp
            <div class="flex flex-wrap gap-2 au d1">

                {{-- Todos: limpia todo --}}
                <a href="{{ route('gmail.inventory.list', $base) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl transition
                           {{ $isNone ? 'bg-violet-600 text-white' : 'bg-white text-gray-700 border border-gray-200 dark:bg-gray-900/40 dark:text-gray-300 dark:border-gray-700 hover:border-violet-400 hover:text-violet-600 dark:hover:text-violet-400' }}">
                    Todos ({{ $products->total() }})
                </a>

                {{-- Activos: solo estado=activos, limpia stock --}}
                @php $isActivos = $estado === 'activos' && $stock === ''; @endphp
                <a href="{{ $isActivos ? route('gmail.inventory.list', $base) : route('gmail.inventory.list', array_merge($base, ['estado' => 'activos'])) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl transition
                           {{ $isActivos ? 'bg-emerald-600 text-white' : 'bg-white text-gray-700 border border-gray-200 dark:bg-gray-900/40 dark:text-gray-300 dark:border-gray-700 hover:border-emerald-400 hover:text-emerald-600 dark:hover:text-emerald-400' }}">
                    Activos ({{ $totalActivos }})
                </a>

                {{-- Inactivos: solo estado=inactivos, limpia stock --}}
                @php $isInactivos = $estado === 'inactivos' && $stock === ''; @endphp
                <a href="{{ $isInactivos ? route('gmail.inventory.list', $base) : route('gmail.inventory.list', array_merge($base, ['estado' => 'inactivos'])) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl transition
                           {{ $isInactivos ? 'bg-gray-700 text-white' : 'bg-white text-gray-700 border border-gray-200 dark:bg-gray-900/40 dark:text-gray-300 dark:border-gray-700 hover:border-gray-500 hover:text-gray-600 dark:hover:text-gray-400' }}">
                    Inactivos ({{ $totalInactivos }})
                </a>

                {{-- Con stock: solo stock=con_stock, limpia estado --}}
                @php $isConStock = $stock === 'con_stock' && $estado === ''; @endphp
                <a href="{{ $isConStock ? route('gmail.inventory.list', $base) : route('gmail.inventory.list', array_merge($base, ['stock' => 'con_stock'])) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl transition
                           {{ $isConStock ? 'bg-cyan-600 text-white' : 'bg-white text-gray-700 border border-gray-200 dark:bg-gray-900/40 dark:text-gray-300 dark:border-gray-700 hover:border-cyan-400 hover:text-cyan-600 dark:hover:text-cyan-400' }}">
                    Con stock ({{ $totalConStock }})
                </a>

                {{-- Sin stock: solo stock=sin_stock, limpia estado --}}
                @php $isSinStock = $stock === 'sin_stock' && $estado === ''; @endphp
                <a href="{{ $isSinStock ? route('gmail.inventory.list', $base) : route('gmail.inventory.list', array_merge($base, ['stock' => 'sin_stock'])) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl transition
                           {{ $isSinStock ? 'bg-amber-600 text-white' : 'bg-white text-gray-700 border border-gray-200 dark:bg-gray-900/40 dark:text-gray-300 dark:border-gray-700 hover:border-amber-400 hover:text-amber-600 dark:hover:text-amber-400' }}">
                    Sin stock ({{ $totalSinStock }})
                </a>

                @if($totalBajoMinimo > 0)
                {{-- Bajo mínimo: solo stock=bajo_minimo, limpia estado --}}
                @php $isBajoMinimo = $stock === 'bajo_minimo' && $estado === ''; @endphp
                <a href="{{ $isBajoMinimo ? route('gmail.inventory.list', $base) : route('gmail.inventory.list', array_merge($base, ['stock' => 'bajo_minimo'])) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl transition
                           {{ $isBajoMinimo ? 'bg-orange-600 text-white' : 'bg-white text-orange-600 border border-orange-200 dark:bg-orange-900/10 dark:text-orange-400 dark:border-orange-800' }}">
                    ⚠ Bajo mínimo ({{ $totalBajoMinimo }})
                </a>
                @endif

            </div>

            @if($products->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4 au d2">
                    @foreach($products as $p)
                        <div class="card relative group">

                            <a href="{{ route('gmail.inventory.product', $p->id) }}" class="absolute inset-0 z-10"
                                aria-label="Ver producto"></a>

                            @if((float) $p->stock_actual <= 0)
                                <div class="ribbon-sinstock">Sin stock</div>
                            @endif
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-base font-bold text-gray-900 dark:text-gray-100 truncate">{{ $p->nombre }}
                                    </p>
                                    <p class="text-xs text-gray-400 mt-0.5">{{ $p->codigo ?? 'Sin codigo' }}</p>
                                </div>
                                <form method="POST"
                                    action="{{ route('inventario.productos.toggle', $p->id) }}"
                                    class="relative z-20 flex items-center gap-2"
                                    onclick="event.stopPropagation()">
                                    @csrf
                                    @method('PATCH')
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox"
                                            name="is_active"
                                            class="sr-only"
                                            {{ (int) $p->is_active === 1 ? 'checked' : '' }}
                                            onchange="this.form.submit()">
                                        <span class="switch">
                                            <span class="switch-dot"></span>
                                        </span>
                                        <span
                                            class="text-[11px] font-semibold {{ (int) $p->is_active === 1 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400' }}">
                                            {{ (int) $p->is_active === 1 ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </label>
                                </form>
                            </div>

                            @php
                                $bodegas_p       = $stockPorBodega->get($p->id, collect());
                                // Stock a mostrar en la tarjeta: de la bodega filtrada o global
                                $stockMostrar    = $bodegaId !== null
                                    ? (float) ($bodegas_p->firstWhere('bodega_id', $bodegaId)?->total ?? 0)
                                    : (float) $p->stock_actual;
                                $bajoMinimo      = $p->stock_minimo !== null && (float)$p->stock_actual < (float)$p->stock_minimo;
                                $stockLabel      = $bodegaId !== null
                                    ? 'Stock en bodega'
                                    : 'Stock total';
                            @endphp
                            <div class="mt-4 grid grid-cols-2 gap-3">
                                <div>
                                    <p class="kv">Unidad</p>
                                    <p class="vv">{{ $p->unidad }}</p>
                                </div>
                                <div>
                                    <p class="kv">{{ $stockLabel }}</p>
                                    <p class="vv flex items-center gap-1 {{ $stockMostrar <= 0 ? 'text-amber-600 dark:text-amber-400' : ($bajoMinimo && $bodegaId === null ? 'text-orange-500 dark:text-orange-400' : '') }}">
                                        {{ number_format($stockMostrar, 4, ',', '.') }}
                                        @if($bajoMinimo && $bodegaId === null)
                                            <span class="text-[9px] font-bold text-orange-500 bg-orange-50 dark:bg-orange-900/20 px-1 py-0.5 rounded border border-orange-200 dark:border-orange-800">min</span>
                                        @endif
                                    </p>
                                </div>
                                @if($p->stock_minimo !== null)
                                <div>
                                    <p class="kv">Stock mínimo</p>
                                    <p class="vv {{ $bajoMinimo ? 'text-orange-500 dark:text-orange-400' : 'text-gray-400 dark:text-gray-500' }}">
                                        {{ number_format((float) $p->stock_minimo, 4, ',', '.') }}
                                    </p>
                                </div>
                                @if(auth()->user()->canSeeValues())
                                <div>
                                    <p class="kv">Costo promedio</p>
                                    <p class="vv">$ {{ number_format((float) $p->costo_promedio, 2, ',', '.') }}</p>
                                </div>
                                @endif
                                @elseif(auth()->user()->canSeeValues())
                                <div class="col-span-2">
                                    <p class="kv">Costo promedio</p>
                                    <p class="vv">$ {{ number_format((float) $p->costo_promedio, 2, ',', '.') }}</p>
                                </div>
                                @endif
                            </div>

                            {{-- Desglose por bodega (solo cuando NO hay filtro activo) --}}
                            @if($bodegaId === null && $bodegas_p->isNotEmpty() && $bodegas_p->count() > 1)
                                <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-800 flex flex-col gap-1">
                                    @foreach($bodegas_p as $row)
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="flex items-center gap-1.5 text-[11px] text-gray-500 dark:text-gray-400 truncate">
                                                <svg class="w-3 h-3 shrink-0 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7l9-4 9 4v10l-9 4-9-4V7z"/>
                                                </svg>
                                                {{ $bodegaNames->get($row->bodega_id, 'Bodega #'.$row->bodega_id) }}
                                            </span>
                                            <span class="text-[11px] font-bold text-gray-700 dark:text-gray-300 shrink-0">
                                                {{ number_format((float) $row->total, 2, ',', '.') }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div
                    class="bg-white dark:bg-gray-900/40 border border-gray-200 dark:border-gray-800 rounded-2xl p-10 text-center au d2">
                    <svg class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                    <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">Aún no hay productos en inventario</p>
                    <p class="text-xs text-gray-400 mt-1">Los productos aparecerán aquí al agregar stock desde un DTE.</p>
                </div>
            @endif

            <div>{{ $products->links() }}</div>
        </div>

        @include('gmail.inventory._bodega_modal')
    </div>
</x-app-layout>
