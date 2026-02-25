<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl bg-rose-600 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Nueva Salida</h2>
                <p class="text-xs text-gray-400 mt-0.5">
                    <a href="{{ route('gmail.inventory.list') }}" class="hover:underline">Inventario</a>
                    <span class="mx-1">/</span> Registrar salida de stock
                </p>
            </div>
        </div>
    </x-slot>

    <style>
        [x-cloak] { display:none !important; }

        .page-bg { background:#f1f5f9; min-height:100% }
        .dark .page-bg { background:#0d1117 }

        .f-label {
            display:block; font-size:11px; font-weight:700;
            text-transform:uppercase; letter-spacing:.05em;
            color:#64748b; margin-bottom:5px;
        }
        .dark .f-label { color:#94a3b8 }
        .f-input {
            width:100%; border-radius:10px; border:1px solid #e2e8f0; background:#fff;
            padding:8px 11px; font-size:13px; color:#111827; outline:none;
            transition:border-color .15s, box-shadow .15s;
        }
        .f-input:focus { border-color:#8b5cf6; box-shadow:0 0 0 3px rgba(139,92,246,.12) }
        .dark .f-input { border-color:#1e2a3b; background:#0d1117; color:#f1f5f9 }

        .card { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:20px; }
        .dark .card { background:#161c2c; border-color:#1e2a3b }

        /* ── Destinatario combobox (combo-drop pattern from cotizaciones) ── */
        .combo-wrap { position:relative; }
        .combo-drop {
            position:fixed; z-index:9999;
            background:#fff; border:1px solid #e2e8f0; border-radius:12px;
            box-shadow:0 8px 32px rgba(0,0,0,.12); max-height:280px; overflow-y:auto;
        }
        .dark .combo-drop { background:#161c2c; border-color:#1e2a3b; box-shadow:0 8px 32px rgba(0,0,0,.4) }
        .combo-item {
            display:flex; align-items:center; justify-content:space-between;
            padding:9px 13px; cursor:pointer; font-size:13px; color:#334155; gap:8px;
        }
        .combo-item:hover { background:#f5f3ff }
        .dark .combo-item { color:#cbd5e1 }
        .dark .combo-item:hover { background:rgba(139,92,246,.08) }
        .combo-item-sub { font-size:11px; color:#94a3b8; white-space:nowrap }
        .combo-empty  { padding:9px 13px; font-size:12px; color:#94a3b8; font-style:italic }
        .combo-create {
            display:flex; align-items:center; gap:7px;
            padding:10px 13px; cursor:pointer; font-size:12px; font-weight:700;
            color:#8b5cf6; border-top:1px solid #f1f5f9;
        }
        .combo-create:hover { background:#faf5ff }
        .dark .combo-create { color:#a78bfa; border-top-color:#1a2232 }
        .dark .combo-create:hover { background:rgba(139,92,246,.08) }

        /* ── Product dropdown (prod-drop pattern from cotizaciones) ── */
        .prod-drop {
            position:fixed; z-index:9999;
            background:#fff; border:1px solid #e2e8f0; border-radius:10px;
            box-shadow:0 8px 24px rgba(0,0,0,.10); max-height:220px; overflow-y:auto;
        }
        .dark .prod-drop { background:#161c2c; border-color:#1e2a3b; }
        .prod-item {
            padding:8px 12px; cursor:pointer; font-size:13px; color:#334155;
            display:flex; align-items:center; justify-content:space-between; gap:6px;
        }
        .prod-item:hover { background:#f5f3ff; }
        .dark .prod-item { color:#cbd5e1; }
        .dark .prod-item:hover { background:rgba(139,92,246,.08); }
        .prod-item-sub { font-size:10px; color:#94a3b8; white-space:nowrap; }
        .prod-item-empty { padding:9px 12px; font-size:11px; color:#94a3b8; font-style:italic; }
        .prod-item-extra {
            padding:6px 12px; font-size:10px; color:#94a3b8; font-style:italic;
            border-top:1px solid #f1f5f9; text-align:center;
        }
        .dark .prod-item-extra { border-top-color:#1a2232; }

        /* Destinatario chip */
        .dest-chip {
            display:inline-flex; align-items:center; gap:4px;
            padding:3px 8px 3px 10px; border-radius:999px;
            background:#ede9fe; border:1.5px solid #c4b5fd;
            font-size:12px; font-weight:700; color:#5b21b6; white-space:nowrap;
        }
        .dark .dest-chip { background:rgba(139,92,246,.15); border-color:rgba(139,92,246,.4); color:#a78bfa; }
        .dest-chip-x {
            display:inline-flex; align-items:center; justify-content:center;
            width:14px; height:14px; border-radius:999px; cursor:pointer;
            font-size:13px; line-height:1; color:#7c3aed; background:none; border:none; padding:0; transition:.1s;
        }
        .dest-chip-x:hover { background:rgba(239,68,68,.15); color:#dc2626; }

        /* Stock bar */
        .stock-bar-bg { height:4px; border-radius:99px; background:#e2e8f0; overflow:hidden; margin-top:4px }
        .dark .stock-bar-bg { background:#1e2a3b }
        .stock-bar-fill { height:100%; border-radius:99px; transition:width .2s ease }
    </style>

    <div class="page-bg"
         x-data="exitForm(
             '{{ route('gmail.inventory.api.products') }}',
             '{{ route('gmail.inventory.api.lots', 0) }}',
             '{{ route('gmail.inventory.api.destinatarios') }}'
         )">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

            @if ($errors->any())
                <div class="mb-4 rounded-xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 px-4 py-3">
                    @foreach ($errors->all() as $error)
                        <p class="text-sm text-rose-700 dark:text-rose-400">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('gmail.inventory.exit.store') }}" @submit.prevent="submitForm($el)">
                @csrf
                {{-- Hidden inputs (generated by Alpine) --}}
                <input type="hidden" name="destinatario" :value="destValue">
                <template x-for="(item, idx) in items" :key="item.product_id">
                    <div>
                        <input type="hidden" :name="'items[' + idx + '][product_id]'" :value="item.product_id">
                        <input type="hidden" :name="'items[' + idx + '][quantity]'" :value="item.quantity">
                    </div>
                </template>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    {{-- ── Left panel ── --}}
                    <div class="lg:col-span-2 space-y-4">

                        {{-- Product search --}}
                        <div class="card" style="overflow:visible">
                            <label class="f-label">Agregar producto</label>
                            <input type="text"
                                x-ref="prodInput"
                                class="f-input"
                                placeholder="Buscar por nombre o código..."
                                x-model="search"
                                @focus="openProdDrop()"
                                @input.debounce.250ms="fetchProducts()"
                                @keydown.escape="showDropdown = false"
                                @keydown.enter.prevent
                                autocomplete="off">
                        </div>

                        {{-- Item table --}}
                        <div class="card">
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                                Productos a retirar
                                <span x-show="items.length > 0" class="ml-1 text-violet-600 dark:text-violet-400" x-text="'(' + items.length + ')'"></span>
                            </p>

                            <div x-show="items.length === 0" class="text-center py-10 text-gray-400 text-sm">
                                Usa el buscador para agregar productos
                            </div>

                            <div x-show="items.length > 0" class="overflow-x-auto -mx-1">
                                <table class="w-full text-sm min-w-[580px]">
                                    <thead>
                                        <tr class="border-b border-gray-100 dark:border-gray-800">
                                            <th class="text-left text-xs font-semibold text-gray-400 pb-2 px-1">#</th>
                                            <th class="text-left text-xs font-semibold text-gray-400 pb-2 px-1">Producto</th>
                                            <th class="text-right text-xs font-semibold text-gray-400 pb-2 px-1">Stock disp.</th>
                                            <th class="text-right text-xs font-semibold text-gray-400 pb-2 px-1">Cantidad</th>
                                            <th class="text-right text-xs font-semibold text-gray-400 pb-2 px-1">Costo est.</th>
                                            <th class="pb-2 px-1"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(item, idx) in items" :key="item.product_id">
                                            <tr class="border-b border-gray-50 dark:border-gray-800/60 align-top">
                                                <td class="py-3 px-1 text-gray-400 text-xs pt-3.5" x-text="idx + 1"></td>

                                                <td class="py-3 px-1 min-w-[180px]">
                                                    <p class="font-semibold text-gray-900 dark:text-gray-100 truncate max-w-[200px]" x-text="item.nombre"></p>
                                                    <p class="text-xs text-gray-400" x-text="(item.codigo ?? 'Sin código') + ' · ' + item.unidad"></p>
                                                    {{-- FIFO lot preview --}}
                                                    <div x-show="item.lots && item.lots.length > 0" class="mt-1">
                                                        <template x-for="(lot, li) in fifoPreview(item)" :key="li">
                                                            <span class="inline-flex items-center gap-1 mr-1 mb-0.5 px-1.5 py-0.5 text-[10px] font-medium rounded-md
                                                                bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400 border border-amber-200 dark:border-amber-800/60">
                                                                <svg class="w-2.5 h-2.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                                </svg>
                                                                <span x-text="lot.label"></span>
                                                            </span>
                                                        </template>
                                                    </div>
                                                    <p x-show="item.lotsLoading" class="text-[10px] text-gray-400 mt-1 animate-pulse">cargando lotes...</p>
                                                </td>

                                                <td class="py-3 px-1 text-right align-top pt-3.5">
                                                    <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400" x-text="formatNum(item.stock_actual, 4)"></span>
                                                    <div class="stock-bar-bg w-20 ml-auto mt-1">
                                                        <div class="stock-bar-fill"
                                                            :style="{
                                                                width: Math.min(100, item.stock_actual > 0 ? (item.quantity / item.stock_actual * 100) : 0) + '%',
                                                                background: (item.quantity / item.stock_actual) >= 1 ? '#ef4444' :
                                                                            (item.quantity / item.stock_actual) > 0.7 ? '#f59e0b' : '#10b981'
                                                            }"></div>
                                                    </div>
                                                    <p class="text-[10px] text-gray-400 mt-0.5 text-right"
                                                        x-text="item.stock_actual > 0 ? Math.round(item.quantity / item.stock_actual * 100) + '%' : '—'"></p>
                                                </td>

                                                <td class="py-3 px-1 align-top">
                                                    <div class="flex items-center gap-1 justify-end">
                                                        <input type="number"
                                                            :max="item.stock_actual" min="0.0001" step="any"
                                                            x-model.number="item.quantity"
                                                            @input="clampQuantity(item)"
                                                            class="f-input text-right py-1.5 px-2"
                                                            style="width:88px"
                                                            :class="item.quantity > item.stock_actual ? 'border-rose-400 dark:border-rose-600' : ''">
                                                        <button type="button" @click="item.quantity = item.stock_actual"
                                                            title="Usar todo el stock"
                                                            class="shrink-0 px-1.5 py-1.5 rounded-lg text-[10px] font-bold
                                                                   bg-gray-100 hover:bg-emerald-100 text-gray-500 hover:text-emerald-700
                                                                   dark:bg-gray-800 dark:hover:bg-emerald-900/40 dark:text-gray-400 dark:hover:text-emerald-400
                                                                   transition-colors whitespace-nowrap">Máx</button>
                                                    </div>
                                                    <p x-show="item.quantity > item.stock_actual"
                                                        class="text-[10px] text-rose-600 dark:text-rose-400 mt-1 text-right"
                                                        x-text="'Máx: ' + formatNum(item.stock_actual, 4)"></p>
                                                </td>

                                                <td class="py-3 px-1 text-right text-gray-700 dark:text-gray-300 font-semibold text-xs whitespace-nowrap pt-3.5"
                                                    x-text="'$ ' + formatNum(item.quantity * item.costo_promedio, 2)"></td>

                                                <td class="py-3 px-1 pt-3.5">
                                                    <button type="button" @click="removeItem(idx)"
                                                        class="text-gray-300 hover:text-rose-500 dark:hover:text-rose-400 transition-colors">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- ── Right panel ── --}}
                    <div class="space-y-4">
                        <div class="card space-y-4">
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Resumen</p>

                            {{-- Destinatario combobox --}}
                            <div>
                                <label class="f-label">Destinatario <span class="text-rose-500 normal-case tracking-normal">*</span></label>

                                {{-- Chip when selected --}}
                                <div x-show="destValue" class="flex items-center gap-2 mb-2">
                                    <span class="dest-chip">
                                        <span x-text="destValue"></span>
                                        <button type="button" class="dest-chip-x" @click="destValue = ''; destSearch = ''; setTimeout(() => $refs.destInput.focus(), 50)">&times;</button>
                                    </span>
                                </div>

                                {{-- Input (hidden when value is set) --}}
                                <div x-show="!destValue" class="relative">
                                    <input type="text"
                                        x-ref="destInput"
                                        class="f-input pr-7"
                                        x-model="destSearch"
                                        @focus="openDestDrop()"
                                        @input.debounce.200ms="fetchDest()"
                                        @keydown.escape="destDropOpen = false"
                                        placeholder="Escribe para buscar o crear..."
                                        autocomplete="off">
                                    <div class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 pointer-events-none">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </div>
                                <p x-show="!destValue" class="text-[11px] text-gray-400 mt-1">Selecciona uno reciente o crea uno nuevo.</p>
                            </div>

                            <div>
                                <label class="f-label">Notas (opcional)</label>
                                <textarea name="notas" rows="3" maxlength="1000"
                                    placeholder="Observaciones..."
                                    class="f-input resize-none">{{ old('notas') }}</textarea>
                            </div>

                            <div class="rounded-xl bg-gray-50 dark:bg-gray-800/60 px-4 py-3 space-y-1.5">
                                <div class="flex justify-between text-xs text-gray-500">
                                    <span>Productos</span>
                                    <span x-text="items.length"></span>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500">
                                    <span>Unidades totales</span>
                                    <span x-text="formatNum(totalQty, 4)"></span>
                                </div>
                                <div class="border-t border-gray-200 dark:border-gray-700 pt-1.5 flex justify-between text-sm font-bold text-gray-900 dark:text-gray-100">
                                    <span>Costo estimado</span>
                                    <span x-text="'$ ' + formatNum(totalCost, 2)"></span>
                                </div>
                            </div>

                            <div x-show="hasErrors" class="rounded-xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 px-3 py-2">
                                <p class="text-xs text-rose-700 dark:text-rose-400 font-medium">Corrige las cantidades antes de continuar.</p>
                            </div>

                            <button type="submit"
                                :disabled="items.length === 0 || hasErrors || !destValue.trim()"
                                class="w-full py-2.5 px-4 rounded-xl text-sm font-semibold text-white transition
                                    bg-rose-600 hover:bg-rose-700
                                    disabled:opacity-40 disabled:cursor-not-allowed">
                                Registrar Salida
                            </button>

                            <a href="{{ route('gmail.inventory.exits') }}"
                                class="block text-center text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition">
                                Cancelar
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        {{-- ── Product dropdown (position:fixed, outside panels) ── --}}
        <div x-show="showDropdown" x-cloak class="prod-drop"
             :style="`top:${prodDropTop}px; left:${prodDropLeft}px; width:${prodDropWidth}px`">

            <div x-show="loading" class="prod-item-empty">Buscando...</div>

            <template x-if="!loading && results.length === 0 && search.trim()">
                <div class="prod-item-empty">Sin resultados — el producto debe existir en inventario con stock disponible.</div>
            </template>

            <template x-for="p in visibleResults" :key="p.id">
                <div class="prod-item" @mousedown.prevent="addItem(p)">
                    <div class="min-w-0">
                        <p class="font-semibold truncate" x-text="p.nombre"></p>
                        <p class="prod-item-sub" x-text="(p.codigo ?? 'Sin código') + ' · ' + p.unidad"></p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-xs font-bold text-emerald-600 dark:text-emerald-400" x-text="'Stock: ' + formatNum(p.stock_actual, 4)"></p>
                        <p class="prod-item-sub" x-text="'$ ' + formatNum(p.costo_promedio, 2)"></p>
                    </div>
                </div>
            </template>

            <template x-if="hasMore">
                <div class="prod-item-extra" @mousedown.prevent="loadMore()" style="cursor:pointer">
                    + más resultados — haz clic o escribe para filtrar
                </div>
            </template>
        </div>

        {{-- ── Destinatario dropdown (position:fixed, outside panels) ── --}}
        <div x-show="destDropOpen" x-cloak class="combo-drop"
             :style="`top:${destDropTop}px; left:${destDropLeft}px; width:${destDropWidth}px`">

            <template x-for="s in destSuggestions" :key="s">
                <div class="combo-item" @mousedown.prevent="selectDest(s)">
                    <div class="flex items-center gap-2 min-w-0">
                        <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span class="font-semibold truncate" x-text="s"></span>
                    </div>
                    <span class="combo-item-sub">Reciente</span>
                </div>
            </template>

            <div class="combo-empty"
                x-show="destSuggestions.length === 0 && destSearch.trim()">
                Sin coincidencias — crea uno nuevo.
            </div>

            <div class="combo-create" @mousedown.prevent="openDestModal()">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                Crear nuevo destinatario
            </div>
        </div>

        {{-- ── Modal: Nuevo Destinatario ── --}}
        <div x-show="destModalOpen" x-cloak
             class="fixed inset-0 flex items-center justify-center p-4"
             style="z-index:300"
             @keydown.escape.window="destModalOpen = false">
            <div class="absolute inset-0 bg-black/50" @click="destModalOpen = false"></div>
            <div class="relative w-full max-w-md"
                 style="background:#fff; border:1px solid #e2e8f0; border-radius:18px; overflow:hidden;"
                 x-on:click.stop>

                {{-- Header --}}
                <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between"
                     style="background:#fff">
                    <div class="flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg bg-violet-100 flex items-center justify-center">
                            <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </span>
                        <h3 class="text-sm font-bold text-gray-900">Nuevo destinatario</h3>
                    </div>
                    <button type="button" @click="destModalOpen = false"
                        class="w-7 h-7 flex items-center justify-center rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-500 text-xl leading-none transition">&times;</button>
                </div>

                {{-- Body --}}
                <div class="px-5 py-5" style="background:#fff">
                    <label class="f-label">Nombre del destinatario *</label>
                    <input type="text"
                        x-ref="destModalInput"
                        x-model="destModalName"
                        @keydown.enter.prevent="confirmDestModal()"
                        class="f-input"
                        placeholder="Ej: Bodega central, Juan Pérez, Área producción..."
                        maxlength="200">
                    <p class="text-[11px] text-gray-400 mt-2">
                        Puede ser una persona, área o punto de entrega. Se guardará en el historial para reutilizarlo.
                    </p>
                </div>

                {{-- Footer --}}
                <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-end gap-2"
                     style="background:#fff">
                    <button type="button" @click="destModalOpen = false"
                        class="px-4 py-2 text-xs font-bold rounded-xl bg-gray-100 text-gray-700 hover:bg-gray-200 transition">
                        Cancelar
                    </button>
                    <button type="button" @click="confirmDestModal()"
                        :disabled="!destModalName.trim()"
                        class="px-4 py-2 text-xs font-bold rounded-xl bg-violet-600 text-white hover:bg-violet-700 transition disabled:opacity-50">
                        Usar este destinatario
                    </button>
                </div>
            </div>
        </div>

    </div>{{-- end x-data --}}

    <script>
        function exitForm(apiUrl, lotsBaseUrl, destApiUrl) {
            return {
                /* ── Product search ── */
                search: '',
                results: [],
                loading: false,
                loadingMore: false,
                showDropdown: false,
                expanded: false,
                prodDropTop: 0, prodDropLeft: 0, prodDropWidth: 300,
                items: [],

                /* ── Destinatario ── */
                destSearch: '',
                destSuggestions: [],
                destDropOpen: false,
                destDropTop: 0, destDropLeft: 0, destDropWidth: 280,
                destValue: '{{ old('destinatario') }}',

                /* ── Destinatario modal ── */
                destModalOpen: false,
                destModalName: '',

                /* ── Computed ── */
                get visibleResults() {
                    return this.expanded ? this.results : this.results.slice(0, 5);
                },
                get hasMore() {
                    return !this.expanded && this.results.length > 5;
                },
                get totalCost() {
                    return this.items.reduce((s, i) => s + (i.quantity * i.costo_promedio), 0);
                },
                get totalQty() {
                    return this.items.reduce((s, i) => s + (i.quantity || 0), 0);
                },
                get hasErrors() {
                    return this.items.some(i => i.quantity <= 0 || i.quantity > i.stock_actual);
                },

                /* ── Product methods ── */
                openProdDrop() {
                    const rect = this.$refs.prodInput.getBoundingClientRect();
                    this.prodDropTop   = rect.bottom + 4;
                    this.prodDropLeft  = rect.left;
                    this.prodDropWidth = rect.width;
                    this.showDropdown  = true;
                    if (this.search.trim()) this.fetchProducts();
                },

                async fetchProducts() {
                    const q = this.search.trim();
                    if (q === '') { this.results = []; this.showDropdown = false; this.expanded = false; return; }
                    this.loading  = true;
                    this.expanded = false;
                    try {
                        // request 6 so we know if there are more than 5
                        const res = await fetch(apiUrl + '?q=' + encodeURIComponent(q) + '&limit=6');
                        this.results = await res.json();
                        this.showDropdown = true;
                    } catch(e) { this.results = []; }
                    finally { this.loading = false; }
                },

                async loadMore() {
                    this.loadingMore = true;
                    try {
                        const res = await fetch(apiUrl + '?q=' + encodeURIComponent(this.search.trim()) + '&limit=50');
                        this.results = await res.json();
                        this.expanded = true;
                    } catch(e) {}
                    finally { this.loadingMore = false; }
                },

                async addItem(p) {
                    if (this.items.find(i => i.product_id === p.id)) {
                        this.search = ''; this.results = []; this.showDropdown = false; return;
                    }
                    const item = {
                        product_id:     p.id,
                        nombre:         p.nombre,
                        codigo:         p.codigo,
                        unidad:         p.unidad,
                        stock_actual:   parseFloat(p.stock_actual),
                        costo_promedio: parseFloat(p.costo_promedio),
                        quantity:       1,
                        lots:           [],
                        lotsLoading:    true,
                    };
                    this.items.push(item);
                    this.search = ''; this.results = []; this.showDropdown = false; this.expanded = false;

                    try {
                        const url = lotsBaseUrl.replace('/0', '/' + p.id);
                        item.lots = await (await fetch(url)).json();
                    } catch(e) { item.lots = []; }
                    finally { item.lotsLoading = false; }
                },

                fifoPreview(item) {
                    const qty = item.quantity || 0;
                    if (!item.lots || !item.lots.length || qty <= 0) return [];
                    let pending = qty;
                    const used = [];
                    for (const lot of item.lots) {
                        if (pending <= 0) break;
                        const take = Math.min(parseFloat(lot.cantidad_disponible), pending);
                        const d = lot.ingresado_el ? new Date(lot.ingresado_el).toLocaleDateString('es-CL') : '—';
                        used.push({ label: 'Lote ' + d + ' (' + this.formatNum(take, 2) + ')' });
                        pending -= take;
                    }
                    return used;
                },

                removeItem(idx) { this.items.splice(idx, 1); },

                clampQuantity(item) {
                    if (item.quantity > item.stock_actual) item.quantity = item.stock_actual;
                    if (item.quantity < 0) item.quantity = 0;
                },

                /* ── Destinatario methods ── */
                openDestDrop() {
                    const rect = this.$refs.destInput.getBoundingClientRect();
                    this.destDropTop   = rect.bottom + 4;
                    this.destDropLeft  = rect.left;
                    this.destDropWidth = rect.width;
                    this.destDropOpen  = true;
                    this.fetchDest();
                },

                async fetchDest() {
                    try {
                        const q = this.destSearch.trim();
                        const res = await fetch(destApiUrl + '?q=' + encodeURIComponent(q));
                        this.destSuggestions = await res.json();
                    } catch(e) { this.destSuggestions = []; }
                },

                selectDest(val) {
                    this.destValue    = val;
                    this.destSearch   = '';
                    this.destDropOpen = false;
                },

                openDestModal() {
                    this.destModalName = this.destSearch.trim();
                    this.destDropOpen  = false;
                    this.destModalOpen = true;
                    this.$nextTick(() => this.$refs.destModalInput?.focus());
                },

                confirmDestModal() {
                    const name = this.destModalName.trim();
                    if (!name) return;
                    this.destValue    = name;
                    this.destSearch   = '';
                    this.destModalOpen = false;
                    this.destModalName = '';
                },

                /* ── Form submit ── */
                submitForm(form) {
                    if (this.items.length === 0 || this.hasErrors || !this.destValue.trim()) return;
                    form.submit();
                },

                formatNum(val, decimals) {
                    return (parseFloat(val) || 0).toLocaleString('es-CL', {
                        minimumFractionDigits: decimals, maximumFractionDigits: decimals,
                    });
                },
            };
        }

        /* Close dropdowns on outside click */
        document.addEventListener('click', (e) => {
            const root = document.querySelector('[x-data]');
            if (!root || !root.__x) return;
            const data = root.__x.$data;
            if (data.showDropdown && !e.target.closest('.prod-drop') && !e.target.closest('[x-ref="prodInput"]'))
                data.showDropdown = false;
            if (data.destDropOpen && !e.target.closest('.combo-drop') && !e.target.closest('[x-ref="destInput"]'))
                data.destDropOpen = false;
        });
    </script>
</x-app-layout>
