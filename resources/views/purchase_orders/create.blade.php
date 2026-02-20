<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-3 flex-wrap">
            <div class="flex items-center gap-1.5 min-w-0 text-xs">
                <a href="{{ route('purchase_orders.index') }}"
                    class="text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition font-medium truncate">
                    Órdenes de compra
                </a>
                <svg class="w-3 h-3 text-gray-300 dark:text-gray-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                </svg>
                <span class="font-bold text-gray-700 dark:text-gray-300">Nueva orden</span>
            </div>
        </div>
    </x-slot>

    <style>
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(8px) }
            to   { opacity:1; transform:translateY(0) }
        }
        .au { animation:fadeUp .35s ease both }
        .d1 { animation-delay:.06s }
        .d2 { animation-delay:.12s }
        .d3 { animation-delay:.18s }

        .page-bg { background:#f1f5f9; min-height:100% }
        .dark .page-bg { background:#0d1117 }

        .panel { background:#fff; border:1px solid #e2e8f0; border-radius:18px; overflow:hidden }
        .dark .panel { background:#161c2c; border-color:#1e2a3b }

        /* Form labels & inputs */
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
        .f-input:focus { border-color:#10b981; box-shadow:0 0 0 3px rgba(16,185,129,.12) }
        .dark .f-input { border-color:#1e2a3b; background:#0d1117; color:#f1f5f9 }
        .dark .f-input:focus { border-color:#10b981 }

        /* Compact input for table cells */
        .f-cell {
            width:100%; border-radius:8px; border:1px solid #e2e8f0; background:#fff;
            padding:6px 9px; font-size:12px; color:#111827; outline:none;
            transition:border-color .15s;
        }
        .f-cell:focus { border-color:#10b981; box-shadow:0 0 0 2px rgba(16,185,129,.1) }
        .dark .f-cell { border-color:#1e2a3b; background:#0d1117; color:#f1f5f9 }

        /* Line items table */
        .dt { width:100%; border-collapse:collapse; font-size:13px; min-width:960px }
        .dt thead { position:sticky; top:0; z-index:1 }
        .dt thead tr { background:#f8fafc }
        .dark .dt thead tr { background:#0f1623 }
        .dt th {
            padding:9px 12px; text-align:left; font-size:10px; font-weight:700;
            letter-spacing:.07em; text-transform:uppercase; color:#94a3b8; white-space:nowrap;
            box-shadow:inset 0 -2px 0 #e2e8f0;
        }
        .dark .dt th { box-shadow:inset 0 -2px 0 #1e2a3b }
        .dt td { padding:8px 10px; border-bottom:1px solid #f1f5f9; color:#334155; vertical-align:middle }
        .dark .dt td { border-bottom-color:#1a2232; color:#cbd5e1 }
        .dt tbody tr:last-child td { border-bottom:none }
        .dt tbody tr:hover td { background:#f5fffb }
        .dark .dt tbody tr:hover td { background:rgba(16,185,129,.02) }

        .amt-cell {
            background:rgba(16,185,129,.04); border-left:1px solid #ecfdf5;
            font-weight:700; font-size:13px; color:#065f46;
            text-align:right; padding-right:14px; white-space:nowrap;
        }
        .dark .amt-cell { background:rgba(16,185,129,.07); border-left-color:#1e2a3b; color:#6ee7b7 }
        .dt tbody tr:hover .amt-cell { background:rgba(16,185,129,.09) }

        .line-num {
            display:inline-flex; width:20px; height:20px; align-items:center; justify-content:center;
            border-radius:999px; background:#f1f5f9; color:#94a3b8; font-size:10px; font-weight:700;
        }
        .dark .line-num { background:#1e2a3b; color:#64748b }
    </style>

    <div class="page-bg" x-data="purchaseOrderForm(@js($products))">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-5 space-y-4">

            @if($errors->any())
                <div class="px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700
                        dark:bg-rose-900/20 dark:border-rose-800 dark:text-rose-400 text-sm au d1">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('purchase_orders.store') }}" @submit="beforeSubmit()">
                @csrf

                {{-- Información de la orden --}}
                <div class="panel au d1">
                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Información de la orden</h3>
                    </div>
                    <div class="px-5 py-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                        <div>
                            <label class="f-label">
                                Proveedor <span class="text-rose-500 font-bold">*</span>
                            </label>
                            <input name="supplier_name" value="{{ old('supplier_name') }}" required
                                class="f-input" placeholder="Nombre del proveedor">
                        </div>
                        <div>
                            <label class="f-label">Moneda</label>
                            <select name="currency" class="f-input">
                                @foreach(['CLP', 'USD', 'EUR'] as $cur)
                                    <option value="{{ $cur }}" @selected(old('currency', 'CLP') === $cur)>{{ $cur }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="f-label">
                                Correos destinatarios
                                <span class="text-gray-400 font-normal normal-case tracking-normal">(separar con coma)</span>
                            </label>
                            <input name="emails" value="{{ old('emails') }}"
                                class="f-input" placeholder="compras@empresa.cl, gerente@empresa.cl">
                        </div>
                        <div class="xl:col-span-4">
                            <label class="f-label">Notas / observaciones</label>
                            <textarea name="notes" rows="2" class="f-input" style="resize:vertical"
                                placeholder="Condiciones de entrega, plazos de pago, observaciones...">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- Líneas de productos --}}
                <div class="panel au d2 mt-4">
                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Líneas de productos</h3>
                        <button type="button" @click="addLine()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Agregar línea
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="dt">
                            <thead>
                                <tr>
                                    <th class="w-10 text-center">#</th>
                                    <th>Producto de inventario</th>
                                    <th>Descripción / nombre</th>
                                    <th>UdM</th>
                                    <th class="text-right">Cantidad</th>
                                    <th class="text-right">Precio unit.</th>
                                    <th class="text-right pr-5">Importe</th>
                                    <th class="w-10"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(line, i) in lines" :key="line.uid">
                                    <tr>
                                        <td class="text-center">
                                            <span class="line-num" x-text="i + 1"></span>
                                        </td>
                                        <td class="min-w-[220px]">
                                            <select :name="`items[${i}][inventory_product_id]`"
                                                x-model="line.inventory_product_id"
                                                @change="fillFromInventory(line)"
                                                class="f-cell">
                                                <option value="">— Manual —</option>
                                                <template x-for="p in products" :key="p.id">
                                                    <option :value="String(p.id)"
                                                        x-text="`${p.nombre}${p.codigo ? ' (' + p.codigo + ')' : ''}`">
                                                    </option>
                                                </template>
                                            </select>
                                        </td>
                                        <td class="min-w-[200px]">
                                            <input :name="`items[${i}][product_name]`"
                                                x-model="line.product_name"
                                                class="f-cell" placeholder="Nombre del producto">
                                            <label class="mt-1 inline-flex items-center gap-1 text-[11px] text-gray-500 cursor-pointer"
                                                x-show="!line.inventory_product_id">
                                                <input type="checkbox"
                                                    :name="`items[${i}][save_as_inventory]`"
                                                    value="1"
                                                    class="rounded border-gray-300 dark:border-gray-700 w-3 h-3 text-emerald-600">
                                                <span>Guardar en inventario</span>
                                            </label>
                                        </td>
                                        <td class="min-w-[80px]">
                                            <input :name="`items[${i}][unit]`"
                                                x-model="line.unit"
                                                class="f-cell" placeholder="UN">
                                        </td>
                                        <td class="min-w-[100px]">
                                            <input type="number" step="0.0001" min="0"
                                                :name="`items[${i}][quantity]`"
                                                x-model.number="line.quantity"
                                                @input="recalc(line)"
                                                class="f-cell text-right">
                                        </td>
                                        <td class="min-w-[120px]">
                                            <input type="number" step="0.0001" min="0"
                                                :name="`items[${i}][unit_price]`"
                                                x-model.number="line.unit_price"
                                                @input="recalc(line)"
                                                class="f-cell text-right">
                                        </td>
                                        <td class="amt-cell min-w-[120px]" x-text="money(line.line_total)"></td>
                                        <td class="text-center px-2">
                                            <button type="button" @click="removeLine(i)"
                                                x-show="lines.length > 1"
                                                class="w-7 h-7 inline-flex items-center justify-center rounded-lg
                                                       bg-rose-50 hover:bg-rose-100 text-rose-500 dark:bg-rose-900/20 dark:hover:bg-rose-900/40 dark:text-rose-400 transition">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    {{-- Footer: total + submit --}}
                    <div class="border-t-2 border-gray-100 dark:border-gray-800 px-5 py-4 bg-gray-50/60 dark:bg-gray-900/20 flex items-center justify-between gap-4 flex-wrap">
                        <p class="text-xs text-gray-400">
                            <span x-text="lines.length"></span> línea<span x-show="lines.length !== 1">s</span>
                        </p>
                        <div class="flex items-center gap-6">
                            <div class="text-right">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Total</p>
                                <p class="text-2xl font-black tabular-nums text-emerald-600 dark:text-emerald-400"
                                    x-text="money(grandTotal())"></p>
                            </div>
                            <button type="submit"
                                class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-bold rounded-xl
                                       bg-emerald-600 hover:bg-emerald-700 text-white transition shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Crear orden
                            </button>
                        </div>
                    </div>
                </div>

            </form>
        </div>
    </div>

    <script>
        function purchaseOrderForm(products) {
            return {
                products,
                lines: [{ uid: Date.now(), inventory_product_id: '', product_name: '', unit: 'UN', quantity: 1, unit_price: 0, line_total: 0 }],
                addLine() {
                    this.lines.push({ uid: Date.now() + Math.random(), inventory_product_id: '', product_name: '', unit: 'UN', quantity: 1, unit_price: 0, line_total: 0 });
                },
                removeLine(index) {
                    if (this.lines.length === 1) return;
                    this.lines.splice(index, 1);
                },
                fillFromInventory(line) {
                    if (!line.inventory_product_id) return;
                    const p = this.products.find(x => String(x.id) === String(line.inventory_product_id));
                    if (!p) return;
                    line.product_name = p.nombre || line.product_name;
                    line.unit = p.unidad || line.unit || 'UN';
                    if ((!line.unit_price || Number(line.unit_price) === 0) && Number(p.costo_promedio || 0) > 0) {
                        line.unit_price = Number(p.costo_promedio);
                    }
                    this.recalc(line);
                },
                recalc(line) {
                    const q = Number(line.quantity || 0);
                    const u = Number(line.unit_price || 0);
                    line.line_total = q * u;
                },
                grandTotal() {
                    return this.lines.reduce((acc, l) => acc + Number(l.line_total || 0), 0);
                },
                money(v) {
                    return new Intl.NumberFormat('es-CL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(v || 0));
                },
                beforeSubmit() {
                    this.lines.forEach(l => this.recalc(l));
                }
            }
        }
    </script>
</x-app-layout>
