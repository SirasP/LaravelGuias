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
        .kv { font-size:12px; color:#94a3b8 }
        .vv { font-size:14px; font-weight:700; color:#334155 }
        .dark .vv { color:#e2e8f0 }
    </style>

    <div class="page-bg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6"
             x-data="exitForm('{{ route('gmail.inventory.api.products') }}')"
             @keydown.escape="closeDropdown()">

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

                    {{-- ── Left panel: product search + item table ── --}}
                    <div class="lg:col-span-2 space-y-4">

                        {{-- Search box --}}
                        <div class="card">
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Agregar producto</p>
                            <div class="relative">
                                <input type="text"
                                    class="f-input pr-10"
                                    placeholder="Buscar por nombre o código..."
                                    x-model="search"
                                    @input.debounce.300ms="fetchProducts()"
                                    @focus="if(results.length) showDropdown = true"
                                    @click.stop
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

                                {{-- Dropdown --}}
                                <div x-show="showDropdown && results.length > 0"
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0"
                                    @click.stop
                                    class="absolute z-30 w-full mt-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl overflow-hidden">
                                    <template x-for="p in results" :key="p.id">
                                        <button type="button"
                                            @click="addItem(p)"
                                            class="w-full text-left flex items-center justify-between gap-3 px-4 py-2.5 hover:bg-violet-50 dark:hover:bg-violet-900/20 transition-colors">
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
                                </div>
                            </div>
                        </div>

                        {{-- Item table --}}
                        <div class="card overflow-x-auto">
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                                Productos a retirar
                                <span x-show="items.length > 0" class="ml-1 text-violet-600 dark:text-violet-400" x-text="'(' + items.length + ')'"></span>
                            </p>

                            <div x-show="items.length === 0" class="text-center py-10 text-gray-400 text-sm">
                                Usa el buscador para agregar productos
                            </div>

                            <table x-show="items.length > 0" class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <th class="text-left text-xs font-semibold text-gray-400 pb-2 pr-3">#</th>
                                        <th class="text-left text-xs font-semibold text-gray-400 pb-2 pr-3">Producto</th>
                                        <th class="text-right text-xs font-semibold text-gray-400 pb-2 pr-3">Stock disp.</th>
                                        <th class="text-right text-xs font-semibold text-gray-400 pb-2 pr-3">Cantidad</th>
                                        <th class="text-right text-xs font-semibold text-gray-400 pb-2 pr-3">Costo est.</th>
                                        <th class="pb-2"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(item, idx) in items" :key="item.product_id">
                                        <tr class="border-b border-gray-50 dark:border-gray-800/60">
                                            <td class="py-2 pr-3 text-gray-400 text-xs" x-text="idx + 1"></td>
                                            <td class="py-2 pr-3">
                                                <p class="font-semibold text-gray-900 dark:text-gray-100 truncate max-w-[180px]" x-text="item.nombre"></p>
                                                <p class="text-xs text-gray-400" x-text="(item.codigo ?? 'Sin código') + ' · ' + item.unidad"></p>
                                            </td>
                                            <td class="py-2 pr-3 text-right">
                                                <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400" x-text="formatNum(item.stock_actual, 4)"></span>
                                            </td>
                                            <td class="py-2 pr-3">
                                                <input type="number"
                                                    :max="item.stock_actual"
                                                    min="0.0001"
                                                    step="any"
                                                    x-model.number="item.quantity"
                                                    @input="clampQuantity(item)"
                                                    class="f-input text-right w-28 py-1.5 px-2"
                                                    :class="item.quantity > item.stock_actual ? 'border-rose-400' : ''">
                                            </td>
                                            <td class="py-2 pr-3 text-right text-gray-700 dark:text-gray-300 font-semibold text-xs whitespace-nowrap"
                                                x-text="'$ ' + formatNum(item.quantity * item.costo_promedio, 2)">
                                            </td>
                                            <td class="py-2">
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

                    {{-- ── Right panel: summary + submit ── --}}
                    <div class="space-y-4">
                        <div class="card space-y-4">
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Resumen</p>

                            <div>
                                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">
                                    Destinatario <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" name="destinatario"
                                    value="{{ old('destinatario') }}"
                                    placeholder="Nombre o área destino"
                                    required maxlength="200"
                                    class="f-input">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Notas (opcional)</label>
                                <textarea name="notas" rows="3" maxlength="1000"
                                    placeholder="Observaciones..."
                                    class="f-input resize-none">{{ old('notas') }}</textarea>
                            </div>

                            <div class="rounded-xl bg-gray-50 dark:bg-gray-800/60 px-4 py-3 space-y-1">
                                <div class="flex justify-between text-xs text-gray-500">
                                    <span>Productos</span>
                                    <span x-text="items.length"></span>
                                </div>
                                <div class="flex justify-between text-sm font-bold text-gray-900 dark:text-gray-100">
                                    <span>Costo estimado</span>
                                    <span x-text="'$ ' + formatNum(totalCost, 2)"></span>
                                </div>
                            </div>

                            <button type="submit"
                                :disabled="items.length === 0 || hasErrors"
                                class="w-full py-2.5 px-4 rounded-xl text-sm font-semibold text-white transition
                                    bg-rose-600 hover:bg-rose-700
                                    disabled:opacity-40 disabled:cursor-not-allowed">
                                Registrar Salida
                            </button>

                            <a href="{{ route('gmail.inventory.list') }}"
                                class="block text-center text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition">
                                Cancelar
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Hidden inputs generated by Alpine --}}
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
        function exitForm(apiUrl) {
            return {
                search: '',
                results: [],
                loading: false,
                showDropdown: false,
                items: [],

                get totalCost() {
                    return this.items.reduce((s, i) => s + (i.quantity * i.costo_promedio), 0);
                },

                get hasErrors() {
                    return this.items.some(i => i.quantity <= 0 || i.quantity > i.stock_actual);
                },

                async fetchProducts() {
                    if (this.search.trim() === '') {
                        this.results = [];
                        this.showDropdown = false;
                        return;
                    }
                    this.loading = true;
                    try {
                        const res = await fetch(apiUrl + '?q=' + encodeURIComponent(this.search));
                        this.results = await res.json();
                        this.showDropdown = this.results.length > 0;
                    } catch (e) {
                        this.results = [];
                    } finally {
                        this.loading = false;
                    }
                },

                addItem(p) {
                    const exists = this.items.find(i => i.product_id === p.id);
                    if (!exists) {
                        this.items.push({
                            product_id:    p.id,
                            nombre:        p.nombre,
                            codigo:        p.codigo,
                            unidad:        p.unidad,
                            stock_actual:  parseFloat(p.stock_actual),
                            costo_promedio: parseFloat(p.costo_promedio),
                            quantity:      1,
                        });
                    }
                    this.search = '';
                    this.results = [];
                    this.showDropdown = false;
                },

                removeItem(idx) {
                    this.items.splice(idx, 1);
                },

                clampQuantity(item) {
                    if (item.quantity > item.stock_actual) {
                        item.quantity = item.stock_actual;
                    }
                    if (item.quantity < 0) {
                        item.quantity = 0;
                    }
                },

                closeDropdown() {
                    this.showDropdown = false;
                },

                submitForm(form) {
                    if (this.items.length === 0) return;
                    if (this.hasErrors) return;
                    form.submit();
                },

                formatNum(val, decimals) {
                    const n = parseFloat(val) || 0;
                    return n.toLocaleString('es-CL', {
                        minimumFractionDigits: decimals,
                        maximumFractionDigits: decimals,
                    });
                },
            };
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', () => {
            const comp = document.querySelector('[x-data]')?.__x?.$data;
            if (comp) comp.showDropdown = false;
        });
    </script>
</x-app-layout>
