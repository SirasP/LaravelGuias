@php $canSeeValues = auth()->user()?->canSeeValues() ?? true; @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl bg-amber-500 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Modificar Salida #{{ $movement->id }}</h2>
                <p class="text-xs text-gray-400 mt-0.5">
                    <a href="{{ route('gmail.inventory.exits') }}" class="hover:underline">Salidas</a>
                    <span class="mx-1">/</span> Editar movimiento existente
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
        .f-input:focus { border-color:#f59e0b; box-shadow:0 0 0 3px rgba(245,158,11,.12) }
        .dark .f-input { border-color:#1e2a3b; background:#0d1117; color:#f1f5f9 }
        .card { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:20px; }
        .dark .card { background:#161c2c; border-color:#1e2a3b }
        .prod-drop {
            position:fixed; z-index:9999; background:#fff; border:1px solid #e2e8f0;
            border-radius:12px; box-shadow:0 8px 32px rgba(0,0,0,.13); overflow-y:auto;
            max-height:220px;
        }
        .dark .prod-drop { background:#161c2c; border-color:#1e2a3b; box-shadow:0 8px 32px rgba(0,0,0,.4) }
        .prod-item {
            padding:9px 13px; cursor:pointer; font-size:13px; color:#334155;
            display:flex; align-items:center; justify-content:space-between; gap:8px;
        }
        .prod-item:hover { background:#fffbeb }
        .dark .prod-item { color:#cbd5e1 }
        .dark .prod-item:hover { background:rgba(245,158,11,.08) }
        .prod-item-sub { font-size:11px; color:#94a3b8; white-space:nowrap }
        .prod-item-empty { padding:9px 13px; font-size:12px; color:#94a3b8; font-style:italic }
    </style>

    <div class="page-bg"
         x-data="exitEditForm()"
         x-init="init()">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

            @if ($errors->any())
                <div class="mb-4 rounded-xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 px-4 py-3">
                    @foreach ($errors->all() as $error)
                        <p class="text-sm text-rose-700 dark:text-rose-400">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST"
                  action="{{ route('gmail.inventory.exits.update', ['id' => $movement->id]) }}"
                  @submit.prevent="submitForm($el)">
                @csrf
                @method('PUT')
                <input type="hidden" name="destinatario" :value="destinatario">
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

                        {{-- Product search --}}
                        <div class="card" style="overflow:visible; position:relative">
                            <label class="f-label">Agregar producto</label>
                            <div style="position:relative">
                                <input type="text"
                                    x-ref="prodInput"
                                    class="f-input"
                                    placeholder="Buscar por nombre o código para agregar..."
                                    x-model="search"
                                    @input.debounce.250ms="fetchProducts()"
                                    @focus="if(search.length >= 2) showDropdown = true"
                                    @blur="setTimeout(() => { showDropdown = false }, 150)"
                                    @keydown.escape="showDropdown = false"
                                    @keydown.enter.prevent
                                    autocomplete="off">

                                {{-- Dropdown --}}
                                <div x-show="showDropdown && searchResults.length > 0"
                                     x-cloak
                                     class="prod-drop"
                                     :style="dropStyle">
                                    <template x-for="p in searchResults" :key="p.id">
                                        <div class="prod-item" @mousedown.prevent="addProduct(p)">
                                            <div>
                                                <span class="font-semibold" x-text="p.nombre"></span>
                                                <span class="prod-item-sub ml-2" x-text="p.codigo ?? ''"></span>
                                            </div>
                                            <span class="prod-item-sub" x-text="formatNum(p.stock_actual, 2) + ' ' + (p.unidad ?? '')"></span>
                                        </div>
                                    </template>
                                </div>
                                <div x-show="showDropdown && search.length >= 2 && searchResults.length === 0"
                                     x-cloak
                                     class="prod-drop"
                                     :style="dropStyle">
                                    <p class="prod-item-empty">Sin resultados</p>
                                </div>
                            </div>
                        </div>

                        {{-- Items table --}}
                        <div class="card">
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                                Productos en esta salida
                                <span x-show="items.length > 0" class="ml-1 text-amber-600 dark:text-amber-400" x-text="'(' + items.length + ')'"></span>
                            </p>

                            <div x-show="items.length === 0" class="text-center py-10 text-gray-400 text-sm">
                                Agrega al menos un producto
                            </div>

                            <div x-show="items.length > 0" class="overflow-x-auto -mx-1">
                                <table class="w-full text-sm min-w-[500px]">
                                    <thead>
                                        <tr class="border-b border-gray-100 dark:border-gray-800">
                                            <th class="text-left text-xs font-semibold text-gray-400 pb-2 px-1">#</th>
                                            <th class="text-left text-xs font-semibold text-gray-400 pb-2 px-1">Producto</th>
                                            <th class="text-right text-xs font-semibold text-gray-400 pb-2 px-1">Disponible</th>
                                            <th class="text-right text-xs font-semibold text-gray-400 pb-2 px-1">Cantidad</th>
                                            <th class="pb-2 px-1 w-8"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(item, idx) in items" :key="item.product_id">
                                            <tr class="border-b border-gray-50 dark:border-gray-800/60 align-top">
                                                <td class="py-3 px-1 text-gray-400 text-xs pt-3.5" x-text="idx + 1"></td>
                                                <td class="py-3 px-1 min-w-[180px]">
                                                    <p class="font-semibold text-gray-900 dark:text-gray-100 leading-tight" x-text="item.nombre"></p>
                                                    <p class="text-xs text-gray-400" x-text="(item.codigo ?? 'Sin código') + ' · ' + (item.unidad ?? '')"></p>
                                                </td>
                                                <td class="py-3 px-1 text-right align-top pt-3.5">
                                                    <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 tabular-nums"
                                                          x-text="formatNum(item.stock_efectivo, 2)"></span>
                                                    <p class="text-[10px] text-gray-400">disp.*</p>
                                                </td>
                                                <td class="py-2 px-1 text-right align-top">
                                                    <input type="number"
                                                        x-model.number="item.quantity"
                                                        min="0.001"
                                                        step="any"
                                                        :max="item.stock_efectivo"
                                                        class="f-input text-right tabular-nums"
                                                        style="width:90px; padding:6px 8px"
                                                        :class="{ 'border-rose-400 dark:border-rose-600': item.quantity > item.stock_efectivo }">
                                                    <p x-show="item.quantity > item.stock_efectivo"
                                                       class="text-[10px] text-rose-500 mt-0.5">Excede disp.</p>
                                                </td>
                                                <td class="py-3 px-1 align-top pt-3.5 text-center">
                                                    <button type="button"
                                                        @click="removeItem(idx)"
                                                        class="w-6 h-6 rounded-lg flex items-center justify-center text-gray-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                    @if($canSeeValues)
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" class="pt-3 px-1 text-xs font-semibold text-gray-500 dark:text-gray-400">
                                                * Stock efectivo incluye la cantidad actualmente en este movimiento (se restaurará al guardar)
                                            </td>
                                        </tr>
                                    </tfoot>
                                    @endif
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- ── Right panel ── --}}
                    <div class="space-y-4">

                        {{-- Tipo --}}
                        <div class="card">
                            <label class="f-label">Tipo de salida</label>
                            <div class="flex gap-2 flex-wrap">
                                <template x-for="t in ['EPP', 'Venta', 'Salida']" :key="t">
                                    <button type="button"
                                        @click="tipoSalida = t"
                                        :class="{
                                            'bg-blue-600 text-white border-blue-600': tipoSalida === t && t === 'EPP',
                                            'bg-emerald-600 text-white border-emerald-600': tipoSalida === t && t === 'Venta',
                                            'bg-slate-600 text-white border-slate-600': tipoSalida === t && t === 'Salida',
                                            'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700': tipoSalida !== t
                                        }"
                                        class="flex-1 py-2 rounded-xl border text-sm font-bold transition">
                                        <span x-text="t === 'EPP' ? '🦺 EPP' : (t === 'Venta' ? '💰 Venta' : '📦 Salida')"></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        {{-- Destinatario --}}
                        <div class="card">
                            <label class="f-label">Destinatario</label>
                            <input type="text"
                                x-model="destinatario"
                                class="f-input"
                                placeholder="Nombre del destinatario"
                                required>
                        </div>

                        {{-- Fecha --}}
                        <div class="card">
                            <label class="f-label">Fecha del movimiento</label>
                            <input type="date"
                                name="ocurrio_el"
                                class="f-input"
                                value="{{ \Carbon\Carbon::parse($movement->ocurrio_el)->format('Y-m-d') }}"
                                required>
                        </div>

                        {{-- Notas --}}
                        <div class="card">
                            <label class="f-label">Notas <span class="font-normal normal-case opacity-60">(opcional)</span></label>
                            <textarea name="notas"
                                class="f-input"
                                rows="3"
                                placeholder="Observaciones, referencia interna...">{{ old('notas', $movement->notas) }}</textarea>
                        </div>

                        {{-- Submit --}}
                        <div class="card" style="padding:16px">
                            <div x-show="items.length === 0 || !destinatario.trim()"
                                 class="text-xs text-amber-600 dark:text-amber-400 font-semibold mb-3">
                                <span x-show="items.length === 0">Agrega al menos un producto.</span>
                                <span x-show="items.length > 0 && !destinatario.trim()">Completa el destinatario.</span>
                            </div>
                            <button type="submit"
                                :disabled="items.length === 0 || !destinatario.trim() || saving"
                                class="w-full py-3 rounded-xl text-sm font-bold text-white transition
                                       bg-amber-500 hover:bg-amber-600 disabled:opacity-40 disabled:cursor-not-allowed">
                                <span x-show="!saving">Guardar cambios</span>
                                <span x-show="saving" x-cloak>Guardando...</span>
                            </button>
                            <a href="{{ route('gmail.inventory.exits.show', ['id' => $movement->id]) }}"
                               class="mt-2 block text-center text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition">
                                Cancelar
                            </a>
                        </div>

                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
    function exitEditForm() {
        return {
            items: @json($items->values()),
            tipoSalida: @json($movement->tipo_salida ?? 'Salida'),
            destinatario: @json($movement->destinatario ?? ''),
            search: '',
            showDropdown: false,
            searchResults: [],
            dropStyle: '',
            saving: false,

            init() {
                // bind dropdown position to search input
            },

            formatNum(n, dec = 2) {
                return parseFloat(n || 0).toLocaleString('es-CL', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: dec,
                });
            },

            fetchProducts() {
                if (this.search.length < 2) {
                    this.searchResults = [];
                    this.showDropdown = false;
                    return;
                }
                const rect = this.$refs.prodInput.getBoundingClientRect();
                this.dropStyle = `top:${rect.bottom + window.scrollY + 4}px;left:${rect.left + window.scrollX}px;width:${rect.width}px`;

                fetch('{{ route('gmail.inventory.api.products') }}?q=' + encodeURIComponent(this.search))
                    .then(r => r.json())
                    .then(data => {
                        this.searchResults = data;
                        this.showDropdown = true;
                    })
                    .catch(() => {});
            },

            addProduct(p) {
                const exists = this.items.find(i => i.product_id === p.id);
                if (exists) {
                    exists.quantity = parseFloat((exists.quantity + 1).toFixed(4));
                    this.search = '';
                    this.showDropdown = false;
                    return;
                }
                this.items.push({
                    product_id:     p.id,
                    nombre:         p.nombre,
                    codigo:         p.codigo,
                    unidad:         p.unidad,
                    quantity:       1,
                    stock_efectivo: parseFloat(p.stock_actual || 0),
                });
                this.search = '';
                this.showDropdown = false;
            },

            removeItem(idx) {
                this.items.splice(idx, 1);
            },

            submitForm(formEl) {
                if (this.items.length === 0 || !this.destinatario.trim()) return;
                const over = this.items.find(i => i.quantity > i.stock_efectivo + 0.0001);
                if (over) {
                    alert('La cantidad de "' + over.nombre + '" excede el stock disponible.');
                    return;
                }
                this.saving = true;
                formEl.submit();
            },
        };
    }
    </script>
</x-app-layout>
