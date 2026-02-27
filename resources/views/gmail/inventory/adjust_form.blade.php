<x-app-layout>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 min-w-0 text-xs">
                <a href="{{ route('gmail.inventory.list') }}"
                    class="flex items-center gap-1 text-gray-400 hover:text-orange-600 dark:hover:text-orange-400 transition font-medium shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                    </svg>
                    <span class="hidden sm:inline">Inventario</span>
                </a>
                <svg class="w-3 h-3 text-gray-300 dark:text-gray-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                </svg>
                <span class="font-bold text-gray-700 dark:text-gray-300">Ajuste de Inventario</span>
            </div>
        </div>
    </x-slot>

    <div class="min-h-screen bg-gray-50 dark:bg-gray-950 py-6 px-4 sm:px-6 lg:px-8">
        <div class="max-w-8xl mx-auto" x-data="adjustForm()">

            {{-- ¿Qué es esto? --}}
            <div class="mb-5 bg-orange-50 dark:bg-orange-900/15 border border-orange-200 dark:border-orange-800 rounded-2xl p-5">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-xl bg-orange-100 dark:bg-orange-900/40 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-4 h-4 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-orange-800 dark:text-orange-300 mb-1">¿Cuándo usar un Ajuste de Stock?</p>
                        <p class="text-xs text-orange-700 dark:text-orange-400 leading-relaxed mb-3">
                            Úsalo cuando el stock del sistema <strong>no coincide con la realidad física</strong> de la bodega y necesitas corregirlo sin registrar una venta ni una entrega a alguien.
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div class="bg-white dark:bg-gray-900 rounded-xl p-3 border border-orange-100 dark:border-orange-900">
                                <p class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wide mb-1.5">Incremento (+)</p>
                                <ul class="text-[11px] text-gray-600 dark:text-gray-400 space-y-1">
                                    <li>• Encontraste stock que estaba mal ingresado</li>
                                    <li>• Un producto fue devuelto a bodega</li>
                                    <li>• Conteo físico arroja más de lo que dice el sistema</li>
                                </ul>
                            </div>
                            <div class="bg-white dark:bg-gray-900 rounded-xl p-3 border border-orange-100 dark:border-orange-900">
                                <p class="text-[11px] font-bold text-rose-600 dark:text-rose-400 uppercase tracking-wide mb-1.5">Decremento (−)</p>
                                <ul class="text-[11px] text-gray-600 dark:text-gray-400 space-y-1">
                                    <li>• Merma o pérdida sin destinatario</li>
                                    <li>• Producto roto o dañado que se descarta</li>
                                    <li>• Conteo físico arroja menos de lo que dice el sistema</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if($errors->any())
            <div class="mb-4 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-sm text-red-700 dark:text-red-300">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
            @endif

            <form method="POST" action="{{ route('gmail.inventory.adjust.store') }}" @submit.prevent="submitForm">
                @csrf

                {{-- Selector de dirección --}}
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 mb-4">
                    <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-4">Tipo de ajuste</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" @click="direccion='POSITIVO'"
                            :class="direccion==='POSITIVO' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700'"
                            class="flex flex-col items-center gap-2 px-4 py-4 rounded-xl border-2 text-sm font-bold transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Incremento
                            <span class="text-[11px] font-normal opacity-75">Devolución, conteo al alza</span>
                        </button>
                        <button type="button" @click="direccion='NEGATIVO'"
                            :class="direccion==='NEGATIVO' ? 'bg-rose-600 text-white border-rose-600' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700'"
                            class="flex flex-col items-center gap-2 px-4 py-4 rounded-xl border-2 text-sm font-bold transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                            </svg>
                            Decremento
                            <span class="text-[11px] font-normal opacity-75">Merma, pérdida, conteo a la baja</span>
                        </button>
                    </div>
                    <input type="hidden" name="direccion" :value="direccion">
                </div>

                {{-- Búsqueda de producto --}}
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 mb-4">
                    <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-4">Producto</h3>

                    <div class="relative" x-show="!selectedProduct">
                        <input type="text" x-model="search" @input.debounce.300ms="fetchProducts"
                            @focus="if(search.length>1) showDropdown=true"
                            placeholder="Buscar producto por nombre o código..."
                            class="w-full text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 px-4 py-2.5 focus:outline-none focus:border-orange-400 transition">
                        <div x-show="showDropdown && searchResults.length > 0" x-cloak
                            class="absolute z-20 mt-1 w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg overflow-auto max-h-60">
                            <template x-for="p in searchResults" :key="p.id">
                                <button type="button" @click="selectProduct(p)"
                                    class="w-full text-left px-4 py-2.5 hover:bg-orange-50 dark:hover:bg-orange-900/20 transition border-b border-gray-100 dark:border-gray-800 last:border-0">
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200" x-text="p.nombre"></span>
                                    <span class="text-xs text-gray-400 ml-2" x-text="p.codigo ?? ''"></span>
                                    <span class="block text-xs text-gray-400">Stock: <span x-text="p.stock_actual"></span> <span x-text="p.unidad"></span></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div x-show="selectedProduct" x-cloak class="flex items-center justify-between p-3 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-xl">
                        <div>
                            <p class="text-sm font-bold text-gray-800 dark:text-gray-200" x-text="selectedProduct?.nombre"></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Stock actual: <span class="font-semibold" x-text="selectedProduct?.stock_actual"></span>
                                <span x-text="selectedProduct?.unidad"></span>
                            </p>
                        </div>
                        <button type="button" @click="clearProduct"
                            class="text-xs text-gray-400 hover:text-rose-600 transition font-semibold">Cambiar</button>
                    </div>
                    <input type="hidden" name="product_id" :value="selectedProduct?.id ?? ''">
                </div>

                {{-- Cantidad, motivo, notas --}}
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 mb-4 space-y-4">
                    <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200">Detalle del ajuste</h3>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">
                            Cantidad a ajustar
                        </label>
                        <input type="number" name="quantity" x-model="quantity" step="0.01" min="0.01" required
                            :max="direccion==='NEGATIVO' && selectedProduct ? selectedProduct.stock_actual : undefined"
                            class="w-full text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 px-4 py-2.5 focus:outline-none focus:border-orange-400 transition"
                            placeholder="Ej: 5">
                        <p x-show="direccion==='NEGATIVO' && selectedProduct" class="text-xs text-gray-400 mt-1">
                            Máximo disponible: <span class="font-semibold" x-text="selectedProduct?.stock_actual"></span>
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">
                            Motivo
                        </label>
                        <select name="motivo" required
                            class="w-full text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 px-4 py-2.5 focus:outline-none focus:border-orange-400 transition">
                            <optgroup label="Decremento">
                                <option value="Merma">Merma</option>
                                <option value="Pérdida">Pérdida</option>
                                <option value="Rotura / Daño">Rotura / Daño</option>
                                <option value="Conteo físico (baja)">Conteo físico (baja)</option>
                            </optgroup>
                            <optgroup label="Incremento">
                                <option value="Devolución">Devolución</option>
                                <option value="Conteo físico (alza)">Conteo físico (alza)</option>
                                <option value="Corrección de ingreso">Corrección de ingreso</option>
                            </optgroup>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">
                            Notas adicionales <span class="font-normal">(opcional)</span>
                        </label>
                        <textarea name="notas" rows="2"
                            class="w-full text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 px-4 py-2.5 focus:outline-none focus:border-orange-400 transition resize-none"
                            placeholder="Descripción del ajuste..."></textarea>
                    </div>
                </div>

                {{-- Resumen y confirmar --}}
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6">
                    <div x-show="selectedProduct && quantity > 0" x-cloak class="mb-4 p-3 rounded-xl border"
                        :class="direccion==='POSITIVO' ? 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800' : 'bg-rose-50 dark:bg-rose-900/20 border-rose-200 dark:border-rose-800'">
                        <p class="text-xs font-semibold" :class="direccion==='POSITIVO' ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300'">
                            Resultado esperado:
                            <span x-text="selectedProduct?.nombre"></span>
                            <span x-text="direccion==='POSITIVO' ? '→ +' + quantity + ' unidades' : '→ -' + quantity + ' unidades'"></span>
                        </p>
                        <p class="text-xs text-gray-500 mt-0.5" x-show="selectedProduct">
                            Stock: <span x-text="selectedProduct?.stock_actual"></span>
                            <span x-text="direccion==='POSITIVO' ? '→ ' + (parseFloat(selectedProduct?.stock_actual||0)+parseFloat(quantity||0)).toFixed(2) : '→ ' + (parseFloat(selectedProduct?.stock_actual||0)-parseFloat(quantity||0)).toFixed(2)"></span>
                        </p>
                    </div>

                    <button type="submit" :disabled="saving || !selectedProduct || !quantity"
                        :class="direccion==='POSITIVO' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-rose-600 hover:bg-rose-700'"
                        class="w-full py-3 rounded-xl text-white font-bold text-sm transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <span x-show="!saving" x-text="direccion==='POSITIVO' ? 'Registrar Incremento' : 'Registrar Decremento'"></span>
                        <span x-show="saving" x-cloak>Procesando...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function adjustForm() {
        return {
            direccion: 'NEGATIVO',
            search: '',
            showDropdown: false,
            searchResults: [],
            selectedProduct: null,
            quantity: '',
            saving: false,

            async fetchProducts() {
                if (this.search.length < 2) { this.showDropdown = false; return; }
                try {
                    const res = await fetch(`{{ route('gmail.inventory.api.products') }}?q=${encodeURIComponent(this.search)}&with_stock=0`);
                    this.searchResults = await res.json();
                    this.showDropdown = this.searchResults.length > 0;
                } catch(e) { this.showDropdown = false; }
            },

            selectProduct(p) {
                this.selectedProduct = p;
                this.showDropdown = false;
                this.search = '';
            },

            clearProduct() {
                this.selectedProduct = null;
                this.search = '';
                this.quantity = '';
            },

            submitForm(e) {
                if (!this.selectedProduct) { alert('Selecciona un producto.'); return; }
                if (!this.quantity || this.quantity <= 0) { alert('Ingresa una cantidad válida.'); return; }
                if (this.direccion === 'NEGATIVO' && parseFloat(this.quantity) > parseFloat(this.selectedProduct.stock_actual)) {
                    alert('La cantidad supera el stock disponible.'); return;
                }
                this.saving = true;
                e.target.submit();
            }
        };
    }
    </script>
</x-app-layout>
