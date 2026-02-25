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
            text-transform:uppercase; letter-spacing:.05em; color:#64748b; margin-bottom:5px;
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

        /* Dropdowns */
        .combo-drop, .prod-drop {
            position:fixed; z-index:9999; background:#fff; border:1px solid #e2e8f0;
            border-radius:12px; box-shadow:0 8px 32px rgba(0,0,0,.13); overflow-y:auto;
        }
        .dark .combo-drop, .dark .prod-drop { background:#161c2c; border-color:#1e2a3b; box-shadow:0 8px 32px rgba(0,0,0,.4) }
        .combo-drop { max-height:280px; }
        .prod-drop  { max-height:220px; border-radius:10px; }

        .combo-item, .prod-item {
            padding:9px 13px; cursor:pointer; font-size:13px; color:#334155;
            display:flex; align-items:center; justify-content:space-between; gap:8px;
        }
        .combo-item:hover, .prod-item:hover { background:#f5f3ff }
        .dark .combo-item, .dark .prod-item { color:#cbd5e1 }
        .dark .combo-item:hover, .dark .prod-item:hover { background:rgba(139,92,246,.08) }
        .combo-sub, .prod-item-sub { font-size:11px; color:#94a3b8; white-space:nowrap }
        .combo-empty, .prod-item-empty { padding:9px 13px; font-size:12px; color:#94a3b8; font-style:italic }
        .combo-create {
            display:flex; align-items:center; gap:7px; padding:10px 13px; cursor:pointer;
            font-size:12px; font-weight:700; color:#8b5cf6; border-top:1px solid #f1f5f9;
        }
        .combo-create:hover { background:#faf5ff }
        .dark .combo-create { color:#a78bfa; border-top-color:#1a2232 }
        .dark .combo-create:hover { background:rgba(139,92,246,.08) }
        .prod-item-extra {
            padding:7px 13px; font-size:10px; color:#94a3b8; font-style:italic;
            border-top:1px solid #f1f5f9; text-align:center; cursor:pointer;
        }
        .dark .prod-item-extra { border-top-color:#1a2232; }

        /* Chips */
        .dest-chip {
            display:inline-flex; align-items:center; gap:4px; padding:4px 10px 4px 12px;
            border-radius:999px; border:1.5px solid #c4b5fd; background:#ede9fe;
            font-size:12px; font-weight:700; color:#5b21b6;
        }
        .dark .dest-chip { background:rgba(139,92,246,.15); border-color:rgba(139,92,246,.4); color:#a78bfa; }
        .dest-chip-sub { font-size:10px; font-weight:400; color:#7c3aed; margin-left:3px }
        .dark .dest-chip-sub { color:#a78bfa }
        .dest-chip-x {
            margin-left:4px; width:15px; height:15px; border-radius:999px; cursor:pointer;
            font-size:13px; line-height:1; color:#7c3aed; background:none; border:none; padding:0;
            display:inline-flex; align-items:center; justify-content:center; transition:.1s;
        }
        .dest-chip-x:hover { background:rgba(239,68,68,.15); color:#dc2626; }
        .tipo-chip {
            display:inline-flex; align-items:center; gap:5px; padding:3px 10px 3px 8px;
            border-radius:999px; font-size:11px; font-weight:700; cursor:pointer; border:1.5px solid; transition:.15s;
        }

        /* Stock bar */
        .stock-bar-bg { height:4px; border-radius:99px; background:#e2e8f0; overflow:hidden; margin-top:4px }
        .dark .stock-bar-bg { background:#1e2a3b }
        .stock-bar-fill { height:100%; border-radius:99px; transition:width .2s ease }
    </style>

    <div class="page-bg"
         x-data="exitForm(
             '{{ route('gmail.inventory.api.products') }}',
             '{{ route('gmail.inventory.api.lots', 0) }}',
             '{{ route('gmail.inventory.api.contacts') }}',
             '{{ route('gmail.inventory.api.contact.store') }}'
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
                <input type="hidden" name="destinatario" :value="destValue">
                <input type="hidden" name="tipo_salida"  :value="tipoSalida">
                <template x-for="(item, idx) in items" :key="item.product_id">
                    <div>
                        <input type="hidden" :name="'items[' + idx + '][product_id]'" :value="item.product_id">
                        <input type="hidden" :name="'items[' + idx + '][quantity]'"    :value="item.quantity">
                    </div>
                </template>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    {{-- ── Left panel ── --}}
                    <div class="lg:col-span-2 space-y-4">

                        <div class="card" style="overflow:visible">
                            <label class="f-label">Agregar producto</label>
                            <input type="text"
                                x-ref="prodInput"
                                class="f-input"
                                placeholder="Buscar por nombre o código..."
                                x-model="search"
                                @focus="openProdDrop()"
                                @input.debounce.250ms="fetchProducts()"
                                @blur="setTimeout(() => { showDropdown = false }, 150)"
                                @keydown.escape="showDropdown = false"
                                @keydown.enter.prevent
                                autocomplete="off">
                        </div>

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
                                                    <div x-show="item.lots && item.lots.length > 0" class="mt-1">
                                                        <template x-for="(lot, li) in fifoPreview(item)" :key="li">
                                                            <span class="inline-flex items-center gap-1 mr-1 mb-0.5 px-1.5 py-0.5 text-[10px] font-medium rounded-md bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400 border border-amber-200 dark:border-amber-800/60">
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
                                                                background: (item.quantity / item.stock_actual) >= 1 ? '#ef4444' : (item.quantity / item.stock_actual) > 0.7 ? '#f59e0b' : '#10b981'
                                                            }"></div>
                                                    </div>
                                                    <p class="text-[10px] text-gray-400 mt-0.5 text-right"
                                                        x-text="item.stock_actual > 0 ? Math.round(item.quantity / item.stock_actual * 100) + '%' : '—'"></p>
                                                </td>
                                                <td class="py-3 px-1 align-top">
                                                    <div class="flex items-center gap-1 justify-end">
                                                        <input type="number" :max="item.stock_actual" min="0.0001" step="any"
                                                            x-model.number="item.quantity" @input="clampQuantity(item)"
                                                            class="f-input text-right py-1.5 px-2" style="width:88px"
                                                            :class="item.quantity > item.stock_actual ? 'border-rose-400 dark:border-rose-600' : ''">
                                                        <button type="button" @click="item.quantity = item.stock_actual" title="Usar todo el stock"
                                                            class="shrink-0 px-1.5 py-1.5 rounded-lg text-[10px] font-bold bg-gray-100 hover:bg-emerald-100 text-gray-500 hover:text-emerald-700 dark:bg-gray-800 dark:hover:bg-emerald-900/40 dark:text-gray-400 dark:hover:text-emerald-400 transition-colors whitespace-nowrap">Máx</button>
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

                            {{-- Tipo chip --}}
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300"
                                   x-text="tipoSalida === 'Venta' ? 'Venta comercial' : tipoSalida === 'EPP' ? 'Entrega de EPP' : 'Salida de stock'"></p>
                                <button type="button" @click="tipoModalOpen = true" class="tipo-chip"
                                    :class="tipoSalida === 'Venta'
                                        ? 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-300 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400'
                                        : tipoSalida === 'EPP'
                                        ? 'bg-blue-50 dark:bg-blue-900/20 border-blue-300 dark:border-blue-700 text-blue-700 dark:text-blue-400'
                                        : 'bg-violet-50 dark:bg-violet-900/20 border-violet-300 dark:border-violet-700 text-violet-700 dark:text-violet-400'">
                                    <span x-text="tipoSalida ? tipoOpciones.find(o => o.value === tipoSalida)?.emoji : '?'"></span>
                                    <span x-text="tipoSalida || 'Sin tipo'"></span>
                                    <svg class="w-2.5 h-2.5 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                </button>
                            </div>

                            {{-- Contact combobox --}}
                            <div>
                                <label class="f-label">
                                    <span x-text="destLabel"></span>
                                    <span class="text-rose-500 normal-case tracking-normal">*</span>
                                </label>

                                {{-- Chip when selected --}}
                                <div x-show="destValue" class="flex items-center gap-2 mb-1">
                                    <span class="dest-chip">
                                        <span x-text="destValue"></span>
                                        <span class="dest-chip-sub" x-show="destContact && destContact.rut" x-text="destContact?.rut"></span>
                                        <button type="button" class="dest-chip-x"
                                            @click="destValue = ''; destContact = null; destSearch = ''; $nextTick(() => $refs.destInput?.focus())">&times;</button>
                                    </span>
                                </div>

                                {{-- Input when not selected --}}
                                <div x-show="!destValue" class="relative">
                                    <input type="text"
                                        x-ref="destInput"
                                        class="f-input pr-7"
                                        x-model="destSearch"
                                        :placeholder="destPlaceholder"
                                        @focus="openDestDrop()"
                                        @input.debounce.200ms="fetchContacts()"
                                        @blur="setTimeout(() => { destDropOpen = false }, 150)"
                                        @keydown.escape="destDropOpen = false"
                                        autocomplete="off">
                                    <div class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 pointer-events-none">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            {{-- Resumen Venta --}}
                            <template x-if="tipoSalida === 'Venta'">
                                <div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/10 border border-emerald-100 dark:border-emerald-900/40 px-4 py-3 space-y-1.5">
                                    <div class="flex justify-between text-xs text-gray-500"><span>Productos</span><span x-text="items.length"></span></div>
                                    <div class="border-t border-emerald-100 dark:border-emerald-800/40 pt-1.5 flex justify-between text-sm font-bold">
                                        <span class="text-gray-700 dark:text-gray-300">Costo total</span>
                                        <span class="text-rose-600 dark:text-rose-400" x-text="'$ ' + formatNum(totalCost, 2)"></span>
                                    </div>
                                    <p class="text-[10px] text-gray-400">El precio de venta se registra desde el historial de salidas.</p>
                                </div>
                            </template>

                            {{-- Resumen EPP --}}
                            <template x-if="tipoSalida === 'EPP'">
                                <div class="rounded-xl bg-blue-50 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-900/40 px-4 py-3 space-y-1.5">
                                    <div class="flex justify-between text-xs text-gray-500"><span>Productos</span><span x-text="items.length"></span></div>
                                    <div class="flex justify-between text-xs text-gray-500"><span>Unidades</span><span x-text="formatNum(totalQty, 2)"></span></div>
                                    <div class="border-t border-blue-100 dark:border-blue-800/40 pt-1.5 flex justify-between text-sm font-bold">
                                        <span class="text-gray-700 dark:text-gray-300">Costo EPP</span>
                                        <span class="text-blue-600 dark:text-blue-400" x-text="'$ ' + formatNum(totalCost, 2)"></span>
                                    </div>
                                </div>
                            </template>

                            {{-- Resumen Salida --}}
                            <template x-if="tipoSalida === 'Salida' || !tipoSalida">
                                <div class="rounded-xl bg-gray-50 dark:bg-gray-800/60 px-4 py-3 space-y-1.5">
                                    <div class="flex justify-between text-xs text-gray-500"><span>Productos</span><span x-text="items.length"></span></div>
                                    <div class="flex justify-between text-xs text-gray-500"><span>Unidades</span><span x-text="formatNum(totalQty, 4)"></span></div>
                                    <div class="border-t border-gray-200 dark:border-gray-700 pt-1.5 flex justify-between text-sm font-bold">
                                        <span class="text-gray-700 dark:text-gray-300">Costo estimado</span>
                                        <span class="text-gray-900 dark:text-gray-100" x-text="'$ ' + formatNum(totalCost, 2)"></span>
                                    </div>
                                </div>
                            </template>

                            <div x-show="hasErrors" class="rounded-xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 px-3 py-2">
                                <p class="text-xs text-rose-700 dark:text-rose-400 font-medium">Corrige las cantidades antes de continuar.</p>
                            </div>

                            <button type="submit"
                                :disabled="items.length === 0 || hasErrors || !destValue.trim() || !tipoSalida"
                                class="w-full py-2.5 px-4 rounded-xl text-sm font-semibold text-white transition disabled:opacity-40 disabled:cursor-not-allowed"
                                :class="tipoSalida === 'Venta' ? 'bg-emerald-600 hover:bg-emerald-700' : tipoSalida === 'EPP' ? 'bg-blue-600 hover:bg-blue-700' : 'bg-rose-600 hover:bg-rose-700'">
                                <span x-text="tipoSalida === 'Venta' ? 'Registrar Venta' : tipoSalida === 'EPP' ? 'Registrar Entrega EPP' : 'Registrar Salida'"></span>
                            </button>

                            <a href="{{ route('gmail.inventory.exits') }}"
                                class="block text-center text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition">Cancelar</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        {{-- ── Product dropdown ── --}}
        <div x-show="showDropdown" x-cloak class="prod-drop"
             :style="`top:${prodDropTop}px; left:${prodDropLeft}px; width:${prodDropWidth}px`">
            <div x-show="loading" class="prod-item-empty">Buscando...</div>
            <template x-if="!loading && results.length === 0 && search.trim()">
                <div class="prod-item-empty">Sin resultados — el producto debe tener stock disponible.</div>
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
                <div class="prod-item-extra" @mousedown.prevent="loadMore()">+ más resultados — haz clic para ver todos</div>
            </template>
        </div>

        {{-- ── Contact dropdown ── --}}
        <div x-show="destDropOpen" x-cloak class="combo-drop"
             :style="`top:${destDropTop}px; left:${destDropLeft}px; width:${destDropWidth}px`">

            <div x-show="destLoading" class="combo-empty">Buscando...</div>

            <template x-for="c in destSuggestions" :key="c.id">
                <div class="combo-item" @mousedown.prevent="selectContact(c)">
                    <div class="min-w-0">
                        <p class="font-semibold truncate" x-text="c.nombre"></p>
                        <p class="combo-sub"
                           x-text="[c.empresa, c.cargo, c.area].filter(Boolean).join(' · ') || ''"></p>
                    </div>
                    <div class="shrink-0 text-right" x-show="c.rut || c.telefono">
                        <p class="combo-sub" x-text="c.rut ?? ''"></p>
                        <p class="combo-sub" x-text="c.telefono ?? ''"></p>
                    </div>
                </div>
            </template>

            <div class="combo-empty" x-show="!destLoading && destSuggestions.length === 0 && destSearch.trim()">
                Sin coincidencias — crea uno nuevo.
            </div>
            <div class="combo-empty" x-show="!destLoading && destSuggestions.length === 0 && !destSearch.trim()">
                No hay registros aún — crea el primero.
            </div>

            <div class="combo-create" @mousedown.prevent="openContactModal()">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                <span x-text="'Crear nuevo ' + destLabel.toLowerCase()"></span>
            </div>
        </div>

        {{-- ── Modal: Crear contacto (max-w-3xl, multi-field) ── --}}
        <div x-show="contactModalOpen" x-cloak
             class="fixed inset-0 flex items-center justify-center p-4"
             style="z-index:300"
             @keydown.escape.window="contactModalOpen = false">
            <div class="absolute inset-0 bg-black/50" @click="contactModalOpen = false"></div>
            <div class="relative w-full max-w-3xl max-h-[90vh] overflow-y-auto"
                 style="background:#fff; border:1px solid #e2e8f0; border-radius:18px;"
                 x-on:click.stop>

                {{-- Header --}}
                <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between sticky top-0 bg-white z-10">
                    <div class="flex items-center gap-2.5">
                        <span class="w-8 h-8 rounded-xl flex items-center justify-center text-lg"
                            :class="tipoSalida === 'Venta' ? 'bg-emerald-100' : tipoSalida === 'EPP' ? 'bg-blue-100' : 'bg-violet-100'">
                            <span x-text="tipoSalida ? tipoOpciones.find(o => o.value === tipoSalida)?.emoji : '📋'"></span>
                        </span>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900" x-text="'Nuevo ' + destLabel.toLowerCase()"></h3>
                            <p class="text-[11px] text-gray-400"
                               x-text="tipoSalida === 'Venta' ? 'Registra los datos del cliente' :
                                        tipoSalida === 'EPP'   ? 'Registra al trabajador que recibe el equipo' :
                                        'Registra el punto o responsable de la salida'"></p>
                        </div>
                    </div>
                    <button type="button" @click="contactModalOpen = false"
                        class="w-7 h-7 flex items-center justify-center rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-500 text-xl leading-none transition">&times;</button>
                </div>

                {{-- Body --}}
                <div class="px-5 py-5 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">

                    {{-- NOMBRE: siempre visible, span 2 --}}
                    <div class="sm:col-span-2">
                        <label class="f-label">
                            <span x-text="tipoSalida === 'Venta' ? 'Nombre / Razón social' : tipoSalida === 'EPP' ? 'Nombre completo' : 'Nombre / Área'"></span>
                            <span class="text-rose-500 normal-case tracking-normal"> *</span>
                        </label>
                        <input type="text" x-model="contactForm.nombre" class="f-input"
                            :placeholder="tipoSalida === 'Venta' ? 'Ej: Comercial ABC Ltda.' : tipoSalida === 'EPP' ? 'Ej: Pedro Soto González' : 'Ej: Bodega central'"
                            maxlength="200">
                    </div>

                    {{-- RUT --}}
                    <div>
                        <label class="f-label">RUT</label>
                        <input type="text" x-model="contactForm.rut" class="f-input"
                            :placeholder="tipoSalida === 'EPP' ? '12.345.678-9' : '77.071.100-2'" maxlength="30">
                    </div>

                    {{-- EMPRESA (solo Venta) --}}
                    <template x-if="tipoSalida === 'Venta'">
                        <div class="sm:col-span-2">
                            <label class="f-label">Empresa / Razón social</label>
                            <input type="text" x-model="contactForm.empresa" class="f-input"
                                placeholder="Ej: Comercial ABC SpA" maxlength="200">
                        </div>
                    </template>

                    {{-- CARGO (EPP y Salida) --}}
                    <template x-if="tipoSalida !== 'Venta'">
                        <div>
                            <label class="f-label" x-text="tipoSalida === 'EPP' ? 'Cargo / Función' : 'Responsable'"></label>
                            <input type="text" x-model="contactForm.cargo" class="f-input"
                                :placeholder="tipoSalida === 'EPP' ? 'Ej: Operario, Supervisor...' : 'Ej: Juan Pérez'" maxlength="100">
                        </div>
                    </template>

                    {{-- ÁREA --}}
                    <div>
                        <label class="f-label" x-text="tipoSalida === 'Venta' ? 'Área / Contacto' : 'Área / Departamento'"></label>
                        <input type="text" x-model="contactForm.area" class="f-input"
                            :placeholder="tipoSalida === 'Venta' ? 'Ej: Compras, Gerencia...' : 'Ej: Producción, Bodega...'" maxlength="100">
                    </div>

                    {{-- TELÉFONO --}}
                    <div>
                        <label class="f-label">Teléfono</label>
                        <input type="text" x-model="contactForm.telefono" class="f-input" placeholder="+56 9 1234 5678" maxlength="50">
                    </div>

                    {{-- EMAIL --}}
                    <div>
                        <label class="f-label">Correo electrónico</label>
                        <input type="email" x-model="contactForm.email" class="f-input"
                            :placeholder="tipoSalida === 'Venta' ? 'compras@empresa.cl' : 'trabajador@empresa.cl'" maxlength="200">
                    </div>

                    {{-- NOTAS (col-span-full) --}}
                    <div class="sm:col-span-2 xl:col-span-3">
                        <label class="f-label">Notas / Observaciones</label>
                        <textarea x-model="contactForm.notas" rows="2" class="f-input resize-none"
                            :placeholder="tipoSalida === 'Venta' ? 'Condiciones de pago, notas del cliente...' :
                                          tipoSalida === 'EPP'   ? 'Observaciones sobre el trabajador o el equipo entregado...' :
                                          'Instrucciones de entrega, referencias...'"
                            maxlength="1000"></textarea>
                    </div>
                </div>

                {{-- Error --}}
                <div x-show="contactErr" class="mx-5 mb-2 rounded-xl bg-rose-50 border border-rose-200 px-4 py-2">
                    <p class="text-xs text-rose-700" x-text="contactErr"></p>
                </div>

                {{-- Footer --}}
                <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-end gap-2 sticky bottom-0 bg-white">
                    <button type="button" @click="contactModalOpen = false"
                        class="px-4 py-2 text-xs font-bold rounded-xl bg-gray-100 text-gray-700 hover:bg-gray-200 transition">Cancelar</button>
                    <button type="button" @click="saveContact()" :disabled="savingContact || !contactForm.nombre.trim()"
                        class="px-5 py-2 text-xs font-bold rounded-xl text-white transition disabled:opacity-50"
                        :class="tipoSalida === 'Venta' ? 'bg-emerald-600 hover:bg-emerald-700' : tipoSalida === 'EPP' ? 'bg-blue-600 hover:bg-blue-700' : 'bg-violet-600 hover:bg-violet-700'">
                        <span x-show="!savingContact" x-text="'Guardar ' + destLabel.toLowerCase()"></span>
                        <span x-show="savingContact">Guardando...</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Modal: Tipo de salida (bottom sheet) ── --}}
        <div x-show="tipoModalOpen" x-cloak
             class="fixed inset-0 flex items-end sm:items-center justify-center p-0 sm:p-4"
             style="z-index:400"
             @keydown.escape.window="if(tipoSalida) tipoModalOpen = false">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="if(tipoSalida) tipoModalOpen = false"></div>
            <div class="relative w-full sm:max-w-sm rounded-t-3xl sm:rounded-2xl overflow-hidden bg-white" x-on:click.stop>
                <div class="flex justify-center pt-3 pb-1 sm:hidden">
                    <div class="w-10 h-1 rounded-full bg-gray-200"></div>
                </div>
                <div class="px-6 pt-5 pb-2 text-center">
                    <div class="w-12 h-12 rounded-2xl bg-rose-100 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-gray-900">¿Tipo de salida?</h3>
                    <p class="text-xs text-gray-500 mt-1">Selecciona el motivo para adaptar el formulario</p>
                </div>
                <div class="px-6 py-4 grid grid-cols-3 gap-3">
                    <template x-for="opt in tipoOpciones" :key="opt.value">
                        <button type="button" @click="changeTipo(opt.value)"
                            class="rounded-2xl border-2 p-4 text-center transition focus:outline-none"
                            :class="tipoSalida === opt.value
                                ? (opt.value === 'Venta'  ? 'border-emerald-500 bg-emerald-50 ring-2 ring-emerald-200' :
                                   opt.value === 'EPP'   ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-200' :
                                   'border-violet-500 bg-violet-50 ring-2 ring-violet-200')
                                : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'">
                            <div class="text-3xl mb-1.5" x-text="opt.emoji"></div>
                            <div class="text-xs font-bold text-gray-900" x-text="opt.value"></div>
                            <div class="text-[10px] text-gray-400 mt-0.5 leading-tight" x-text="opt.desc"></div>
                        </button>
                    </template>
                </div>
                <div class="px-6 pb-6">
                    <p x-show="!tipoSalida" class="text-center text-xs text-gray-400">Selecciona una opción para continuar</p>
                </div>
            </div>
        </div>

    </div>{{-- end x-data --}}

    <script>
        function exitForm(apiUrl, lotsBaseUrl, contactsApiUrl, contactStoreUrl) {
            return {
                /* ── Products ── */
                search: '', results: [], loading: false, showDropdown: false, expanded: false,
                prodDropTop: 0, prodDropLeft: 0, prodDropWidth: 300,
                items: [],

                /* ── Contact combobox ── */
                destSearch: '', destSuggestions: [], destLoading: false,
                destDropOpen: false, destDropTop: 0, destDropLeft: 0, destDropWidth: 280,
                destValue: '{{ old('destinatario') }}',
                destContact: null,

                /* ── Contact modal ── */
                contactModalOpen: false,
                savingContact: false,
                contactErr: '',
                contactForm: { nombre:'', rut:'', empresa:'', cargo:'', area:'', telefono:'', email:'', notas:'' },

                /* ── Tipo ── */
                tipoSalida: '{{ old('tipo_salida') }}',
                tipoModalOpen: false,
                tipoOpciones: [
                    { value:'Venta',  emoji:'💰', desc:'Producto vendido a cliente' },
                    { value:'EPP',   emoji:'🦺', desc:'Equipo de protección personal' },
                    { value:'Salida', emoji:'📦', desc:'Uso interno u otro motivo' },
                ],

                /* ── Computed ── */
                get destLabel()       { return this.tipoSalida==='Venta' ? 'Cliente' : this.tipoSalida==='EPP' ? 'Trabajador' : 'Destinatario'; },
                get destPlaceholder() { return this.tipoSalida==='Venta' ? 'Buscar o crear cliente...' : this.tipoSalida==='EPP' ? 'Buscar o crear trabajador...' : 'Buscar o crear destinatario...'; },
                get contactTipo()     { return this.tipoSalida==='Venta' ? 'cliente' : this.tipoSalida==='EPP' ? 'trabajador' : 'destinatario'; },
                get visibleResults()  { return this.expanded ? this.results : this.results.slice(0,5); },
                get hasMore()         { return !this.expanded && this.results.length > 5; },
                get totalCost()       { return this.items.reduce((s,i) => s + (i.quantity * i.costo_promedio), 0); },
                get totalQty()        { return this.items.reduce((s,i) => s + (i.quantity||0), 0); },
                get hasErrors()       { return this.items.some(i => i.quantity <= 0 || i.quantity > i.stock_actual); },

                init() {
                    if (!this.tipoSalida) this.$nextTick(() => { this.tipoModalOpen = true; });
                    window.addEventListener('scroll', () => {
                        this.showDropdown = false;
                        this.destDropOpen = false;
                    }, { passive: true });
                },

                changeTipo(val) {
                    const prev = this.tipoSalida;
                    this.tipoSalida = val;
                    if (prev !== val) { this.destValue = ''; this.destContact = null; this.destSearch = ''; }
                    this.tipoModalOpen = false;
                },

                /* ── Product methods ── */
                openProdDrop() {
                    const rect = this.$refs.prodInput.getBoundingClientRect();
                    this.prodDropTop = rect.bottom + 4; this.prodDropLeft = rect.left; this.prodDropWidth = rect.width;
                    this.showDropdown = true;
                    if (this.search.trim()) this.fetchProducts();
                },
                async fetchProducts() {
                    const q = this.search.trim();
                    if (!q) { this.results=[]; this.showDropdown=false; this.expanded=false; return; }
                    this.loading=true; this.expanded=false;
                    try { this.results = await (await fetch(apiUrl+'?q='+encodeURIComponent(q)+'&limit=6')).json(); this.showDropdown=true; }
                    catch(e) { this.results=[]; } finally { this.loading=false; }
                },
                async loadMore() {
                    try { this.results = await (await fetch(apiUrl+'?q='+encodeURIComponent(this.search.trim())+'&limit=50')).json(); this.expanded=true; } catch(e){}
                },
                async addItem(p) {
                    if (this.items.find(i => i.product_id===p.id)) { this.search=''; this.results=[]; this.showDropdown=false; return; }
                    const item = { product_id:p.id, nombre:p.nombre, codigo:p.codigo, unidad:p.unidad, stock_actual:parseFloat(p.stock_actual), costo_promedio:parseFloat(p.costo_promedio), quantity:1, lots:[], lotsLoading:true };
                    this.items.push(item);
                    this.search=''; this.results=[]; this.showDropdown=false; this.expanded=false;
                    try { item.lots = await (await fetch(lotsBaseUrl.replace('/0','/'+p.id))).json(); } catch(e){ item.lots=[]; } finally { item.lotsLoading=false; }
                },
                fifoPreview(item) {
                    const qty=item.quantity||0;
                    if (!item.lots?.length || qty<=0) return [];
                    let pending=qty; const used=[];
                    for (const lot of item.lots) {
                        if (pending<=0) break;
                        const take=Math.min(parseFloat(lot.cantidad_disponible),pending);
                        const d=lot.ingresado_el ? new Date(lot.ingresado_el).toLocaleDateString('es-CL') : '—';
                        used.push({label:'Lote '+d+' ('+this.formatNum(take,2)+')'});
                        pending-=take;
                    }
                    return used;
                },
                removeItem(idx) { this.items.splice(idx,1); },
                clampQuantity(item) {
                    if (item.quantity>item.stock_actual) item.quantity=item.stock_actual;
                    if (item.quantity<0) item.quantity=0;
                },

                /* ── Contact combobox ── */
                openDestDrop() {
                    const rect = this.$refs.destInput.getBoundingClientRect();
                    this.destDropTop=rect.bottom+4; this.destDropLeft=rect.left; this.destDropWidth=rect.width;
                    this.destDropOpen=true;
                    this.fetchContacts();
                },
                async fetchContacts() {
                    this.destLoading=true;
                    try {
                        const q = encodeURIComponent(this.destSearch.trim());
                        const t = encodeURIComponent(this.contactTipo);
                        this.destSuggestions = await (await fetch(contactsApiUrl+'?q='+q+'&tipo='+t)).json();
                    } catch(e){ this.destSuggestions=[]; } finally { this.destLoading=false; }
                },
                selectContact(c) {
                    this.destValue   = c.nombre;
                    this.destContact = c;
                    this.destSearch  = '';
                    this.destDropOpen = false;
                },

                /* ── Contact modal ── */
                openContactModal() {
                    this.contactForm = { nombre:this.destSearch.trim(), rut:'', empresa:'', cargo:'', area:'', telefono:'', email:'', notas:'' };
                    this.contactErr  = '';
                    this.destDropOpen = false;
                    this.contactModalOpen = true;
                    this.$nextTick(() => document.querySelector('[x-model="contactForm.nombre"]')?.focus());
                },
                async saveContact() {
                    if (!this.contactForm.nombre.trim()) return;
                    this.savingContact=true; this.contactErr='';
                    try {
                        const res = await fetch(contactStoreUrl, {
                            method: 'POST',
                            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                            body: JSON.stringify({ tipo: this.contactTipo, ...this.contactForm })
                        });
                        if (!res.ok) {
                            const err = await res.json().catch(()=>null);
                            this.contactErr = err?.message ?? 'Error al guardar. Verifica los datos.';
                            return;
                        }
                        const contact = await res.json();
                        this.destValue   = contact.nombre;
                        this.destContact = contact;
                        this.destSearch  = '';
                        this.contactModalOpen = false;
                    } catch(e) {
                        this.contactErr = 'Error de conexión.';
                    } finally {
                        this.savingContact = false;
                    }
                },

                /* ── Submit ── */
                submitForm(form) {
                    if (this.items.length===0 || this.hasErrors || !this.destValue.trim() || !this.tipoSalida) return;
                    form.submit();
                },

                formatNum(val, decimals) {
                    return (parseFloat(val)||0).toLocaleString('es-CL',{minimumFractionDigits:decimals,maximumFractionDigits:decimals});
                },
            };
        }
    </script>
</x-app-layout>
