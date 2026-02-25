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
        .page-bg { background: #f1f5f9; min-height: 100% }
        .dark .page-bg { background: #0d1117 }
        .f-input {
            width: 100%; border-radius: 12px; border: 1px solid #e2e8f0;
            background: #fff; padding: 9px 12px; font-size: 13px;
            color: #111827; outline: none;
        }
        .f-input:focus { border-color: #8b5cf6; box-shadow: 0 0 0 3px rgba(139,92,246,.12) }
        .dark .f-input { border-color: #1e2a3b; background: #0d1117; color: #f1f5f9 }
        .card { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:20px; }
        .dark .card { background:#161c2c; border-color:#1e2a3b }
        .stock-bar-bg { height:4px; border-radius:99px; background:#e2e8f0; overflow:hidden; margin-top:4px }
        .dark .stock-bar-bg { background:#1e2a3b }
        .stock-bar-fill { height:100%; border-radius:99px; transition:width .2s ease }
        .dd-item { display:flex; align-items:center; justify-content:space-between; gap:12px; width:100%; text-align:left; padding:10px 16px; transition:background .12s; cursor:pointer; }
        .dd-item:hover { background:rgba(139,92,246,.06) }
        .dark .dd-item:hover { background:rgba(139,92,246,.12) }
        .dd-create { display:flex; align-items:center; gap:8px; width:100%; text-align:left; padding:10px 16px; border-top:1px solid #e2e8f0; cursor:pointer; transition:background .12s; }
        .dark .dd-create { border-color:#1e2a3b }
        .dd-create:hover { background:rgba(16,185,129,.06) }
        .dark .dd-create:hover { background:rgba(16,185,129,.10) }
        .dd-more { display:flex; align-items:center; gap:8px; width:100%; text-align:left; padding:8px 16px; border-top:1px solid #e2e8f0; cursor:pointer; transition:background .12s; }
        .dark .dd-more { border-color:#1e2a3b }
        .dd-more:hover { background:#f8fafc }
        .dark .dd-more:hover { background:#0d1117 }
    </style>

    <div class="page-bg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6"
             x-data="exitForm(
                 '{{ route('gmail.inventory.api.products') }}',
                 '{{ route('gmail.inventory.api.lots', 0) }}',
                 '{{ route('gmail.inventory.api.destinatarios') }}'
             )"
             @keydown.escape="closeDropdowns()">

            @if ($errors->any())
                <div class="mb-4 rounded-xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 px-4 py-3">
                    @foreach ($errors->all() as $error)
                        <p class="text-sm text-rose-700 dark:text-rose-400">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('gmail.inventory.exit.store') }}" @submit.prevent="submitForm($el)">
                @csrf

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    {{-- ── Left panel ── --}}
                    <div class="lg:col-span-2 space-y-4">

                        {{-- Product search --}}
                        <div class="card">
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Agregar producto</p>
                            <div class="relative" @click.outside="showDropdown = false">
                                <input type="text"
                                    class="f-input pr-10"
                                    placeholder="Buscar por nombre o código..."
                                    x-model="search"
                                    @input.debounce.300ms="fetchProducts()"
                                    @focus="if(results.length || search.trim()) { fetchProducts(); }"
                                    autocomplete="off">
                                <div class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">
                                    <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                                    </svg>
                                    <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>

                                {{-- Product dropdown --}}
                                <div x-show="showDropdown && (visibleResults.length > 0 || noResults)"
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0"
                                    class="absolute z-30 w-full mt-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl overflow-hidden">

                                    {{-- No results --}}
                                    <div x-show="noResults" class="px-4 py-3 text-xs text-gray-400 text-center">
                                        Sin resultados para "<span x-text="search"></span>"
                                    </div>

                                    {{-- Result items (max 5) --}}
                                    <template x-for="p in visibleResults" :key="p.id">
                                        <button type="button" @click="addItem(p)" class="dd-item">
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate" x-text="p.nombre"></p>
                                                <p class="text-xs text-gray-400" x-text="(p.codigo ?? 'Sin código') + ' · ' + p.unidad"></p>
                                            </div>
                                            <div class="shrink-0 text-right">
                                                <p class="text-xs font-bold text-emerald-600 dark:text-emerald-400" x-text="'Stock: ' + formatNum(p.stock_actual, 4)"></p>
                                                <p class="text-xs text-gray-400" x-text="'$ ' + formatNum(p.costo_promedio, 2)"></p>
                                            </div>
                                        </button>
                                    </template>

                                    {{-- "Ver más" if results were truncated --}}
                                    <button type="button" x-show="hasMore && !loadingMore"
                                        @click="loadMore()"
                                        class="dd-more">
                                        <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                        <span class="text-xs text-gray-500">Buscar más resultados para "<span class="font-semibold" x-text="search"></span>"</span>
                                    </button>
                                    <div x-show="loadingMore" class="dd-more">
                                        <svg class="w-3.5 h-3.5 animate-spin text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                                        </svg>
                                        <span class="text-xs text-gray-400">Buscando más...</span>
                                    </div>
                                </div>
                            </div>
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
                                <table class="w-full text-sm min-w-[600px]">
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
                                                            }">
                                                        </div>
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
                                                            style="width:90px"
                                                            :class="item.quantity > item.stock_actual ? 'border-rose-400 dark:border-rose-600' : ''">
                                                        <button type="button" @click="item.quantity = item.stock_actual"
                                                            title="Usar todo el stock disponible"
                                                            class="shrink-0 px-1.5 py-1.5 rounded-lg text-[10px] font-bold
                                                                   bg-gray-100 hover:bg-emerald-100 text-gray-500 hover:text-emerald-700
                                                                   dark:bg-gray-800 dark:hover:bg-emerald-900/40 dark:text-gray-400 dark:hover:text-emerald-400
                                                                   transition-colors whitespace-nowrap">
                                                            Máx
                                                        </button>
                                                    </div>
                                                    <p x-show="item.quantity > item.stock_actual"
                                                        class="text-[10px] text-rose-600 dark:text-rose-400 mt-1 text-right"
                                                        x-text="'Máx: ' + formatNum(item.stock_actual, 4)"></p>
                                                </td>

                                                <td class="py-3 px-1 text-right text-gray-700 dark:text-gray-300 font-semibold text-xs whitespace-nowrap pt-3.5"
                                                    x-text="'$ ' + formatNum(item.quantity * item.costo_promedio, 2)">
                                                </td>

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
                            <div x-data="destinatarioField('{{ route('gmail.inventory.api.destinatarios') }}')" @click.outside="showDest = false">
                                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">
                                    Destinatario <span class="text-rose-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="text" name="destinatario"
                                        x-model="destValue"
                                        @input.debounce.250ms="fetchDest()"
                                        @focus="fetchDest()"
                                        placeholder="Nombre o área destino"
                                        required maxlength="200"
                                        autocomplete="off"
                                        class="f-input pr-8"
                                        value="{{ old('destinatario') }}">
                                    <div class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 pointer-events-none">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>

                                    {{-- Destinatario dropdown --}}
                                    <div x-show="showDest && (destSuggestions.length > 0 || destValue.trim().length > 0)"
                                        x-transition:enter="transition ease-out duration-100"
                                        x-transition:enter-start="opacity-0 translate-y-1"
                                        x-transition:enter-end="opacity-100 translate-y-0"
                                        class="absolute z-40 w-full mt-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl overflow-hidden">

                                        {{-- Existing suggestions --}}
                                        <template x-for="s in destSuggestions" :key="s">
                                            <button type="button" @click="selectDest(s)" class="dd-item">
                                                <div class="flex items-center gap-2 min-w-0">
                                                    <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                    </svg>
                                                    <span class="text-sm text-gray-800 dark:text-gray-200 truncate" x-text="s"></span>
                                                </div>
                                                <span class="text-[10px] text-gray-400 shrink-0">Reciente</span>
                                            </button>
                                        </template>

                                        {{-- "Crear nuevo" option shown when typed value doesn't match any suggestion --}}
                                        <button type="button"
                                            x-show="destValue.trim().length > 0 && !destSuggestions.includes(destValue.trim())"
                                            @click="selectDest(destValue.trim())"
                                            class="dd-create">
                                            <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14m-7-7h14" />
                                            </svg>
                                            <span class="text-xs text-emerald-700 dark:text-emerald-400">
                                                Usar "<strong x-text="destValue.trim()"></strong>"
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Notas (opcional)</label>
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
                                :disabled="items.length === 0 || hasErrors"
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

                {{-- Hidden inputs --}}
                <template x-for="(item, idx) in items" :key="item.product_id">
                    <div>
                        <input type="hidden" :name="'items[' + idx + '][product_id]'" :value="item.product_id">
                        <input type="hidden" :name="'items[' + idx + '][quantity]'" :value="item.quantity">
                    </div>
                </template>
            </form>
        </div>
    </div>

    <script>
        /* ── Product search combobox ── */
        function exitForm(apiUrl, lotsBaseUrl, destApiUrl) {
            return {
                search: '',
                results: [],         // all fetched results
                loading: false,
                loadingMore: false,
                showDropdown: false,
                expanded: false,     // true after clicking "Ver más"
                items: [],

                get visibleResults() {
                    return this.expanded ? this.results : this.results.slice(0, 5);
                },
                get hasMore() {
                    return !this.expanded && this.results.length > 5;
                },
                get noResults() {
                    return !this.loading && this.search.trim() !== '' && this.results.length === 0;
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

                async fetchProducts() {
                    const q = this.search.trim();
                    if (q === '') {
                        this.results = [];
                        this.showDropdown = false;
                        this.expanded = false;
                        return;
                    }
                    this.loading = true;
                    this.expanded = false;
                    try {
                        // Request 6 to detect if there are more than 5
                        const res = await fetch(apiUrl + '?q=' + encodeURIComponent(q) + '&limit=6');
                        this.results = await res.json();
                        this.showDropdown = true;
                    } catch (e) {
                        this.results = [];
                    } finally {
                        this.loading = false;
                    }
                },

                async loadMore() {
                    this.loadingMore = true;
                    try {
                        const res = await fetch(apiUrl + '?q=' + encodeURIComponent(this.search.trim()) + '&limit=50');
                        this.results = await res.json();
                        this.expanded = true;
                    } catch (e) {}
                    finally { this.loadingMore = false; }
                },

                async addItem(p) {
                    const exists = this.items.find(i => i.product_id === p.id);
                    if (exists) {
                        this.search = '';
                        this.results = [];
                        this.showDropdown = false;
                        return;
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
                    this.search = '';
                    this.results = [];
                    this.showDropdown = false;
                    this.expanded = false;

                    try {
                        const url = lotsBaseUrl.replace('/0', '/' + p.id);
                        const res = await fetch(url);
                        item.lots = await res.json();
                    } catch (e) {
                        item.lots = [];
                    } finally {
                        item.lotsLoading = false;
                    }
                },

                fifoPreview(item) {
                    const qty = item.quantity || 0;
                    if (!item.lots || item.lots.length === 0 || qty <= 0) return [];
                    let pending = qty;
                    const used = [];
                    for (const lot of item.lots) {
                        if (pending <= 0) break;
                        const take = Math.min(parseFloat(lot.cantidad_disponible), pending);
                        const date = lot.ingresado_el
                            ? new Date(lot.ingresado_el).toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric' })
                            : '—';
                        used.push({ label: 'Lote ' + date + ' (' + this.formatNum(take, 2) + ')' });
                        pending -= take;
                    }
                    return used;
                },

                removeItem(idx) { this.items.splice(idx, 1); },

                clampQuantity(item) {
                    if (item.quantity > item.stock_actual) item.quantity = item.stock_actual;
                    if (item.quantity < 0) item.quantity = 0;
                },

                closeDropdowns() { this.showDropdown = false; },

                submitForm(form) {
                    if (this.items.length === 0 || this.hasErrors) return;
                    form.submit();
                },

                formatNum(val, decimals) {
                    return (parseFloat(val) || 0).toLocaleString('es-CL', {
                        minimumFractionDigits: decimals, maximumFractionDigits: decimals,
                    });
                },
            };
        }

        /* ── Destinatario combobox ── */
        function destinatarioField(apiUrl) {
            return {
                destValue: '{{ old('destinatario') }}',
                destSuggestions: [],
                showDest: false,

                async fetchDest() {
                    this.showDest = true;
                    const q = this.destValue.trim();
                    try {
                        const res = await fetch(apiUrl + '?q=' + encodeURIComponent(q));
                        this.destSuggestions = await res.json();
                    } catch (e) {
                        this.destSuggestions = [];
                    }
                },

                selectDest(val) {
                    this.destValue = val;
                    this.showDest = false;
                },
            };
        }
    </script>
</x-app-layout>
