<x-app-layout>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Nueva orden de compra</h2>
                <p class="text-xs text-gray-400 mt-0.5">Puedes usar productos de inventario o crear líneas manuales</p>
            </div>
            <a href="{{ route('purchase_orders.index') }}" class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-xl bg-gray-200 hover:bg-gray-300 text-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-100 transition">Volver</a>
        </div>
    </x-slot>

    <div class="py-6" x-data="purchaseOrderForm(@js($products))">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <form method="POST" action="{{ route('purchase_orders.store') }}" @submit="beforeSubmit()" class="space-y-4">
                @csrf

                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Proveedor</label>
                        <input name="supplier_name" value="{{ old('supplier_name') }}" required class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" placeholder="Nombre proveedor">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Moneda</label>
                        <select name="currency" class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                            @foreach(['CLP', 'USD', 'EUR'] as $cur)
                                <option value="{{ $cur }}" @selected(old('currency', 'CLP') === $cur)>{{ $cur }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs text-gray-500 mb-1">Correos destinatarios (separados por coma)</label>
                        <input name="emails" value="{{ old('emails') }}" class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" placeholder="compras@empresa.cl, gerente@empresa.cl">
                    </div>
                    <div class="xl:col-span-4">
                        <label class="block text-xs text-gray-500 mb-1">Notas</label>
                        <textarea name="notes" rows="2" class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" placeholder="Condiciones, plazos, observaciones...">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Líneas de productos</h3>
                        <button type="button" @click="addLine()" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white transition">Agregar línea</button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-800/70 text-xs uppercase text-gray-500 dark:text-gray-300">
                                <tr>
                                    <th class="px-3 py-2 text-left">Producto inventario</th>
                                    <th class="px-3 py-2 text-left">Producto manual</th>
                                    <th class="px-3 py-2 text-left">UdM</th>
                                    <th class="px-3 py-2 text-right">Cantidad</th>
                                    <th class="px-3 py-2 text-right">Precio</th>
                                    <th class="px-3 py-2 text-right">Importe</th>
                                    <th class="px-3 py-2 text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(line, i) in lines" :key="line.uid">
                                    <tr class="border-t border-gray-100 dark:border-gray-800">
                                        <td class="px-3 py-2 min-w-[250px]">
                                            <select :name="`items[${i}][inventory_product_id]`" x-model="line.inventory_product_id" @change="fillFromInventory(line)" class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 text-sm">
                                                <option value="">-- Manual --</option>
                                                <template x-for="p in products" :key="p.id">
                                                    <option :value="String(p.id)" x-text="`${p.nombre}${p.codigo ? ' (' + p.codigo + ')' : ''}`"></option>
                                                </template>
                                            </select>
                                        </td>
                                        <td class="px-3 py-2 min-w-[220px]">
                                            <input :name="`items[${i}][product_name]`" x-model="line.product_name" class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 text-sm" placeholder="Nombre producto">
                                            <label class="mt-1 inline-flex items-center gap-1 text-[11px] text-gray-500" x-show="!line.inventory_product_id">
                                                <input type="checkbox" :name="`items[${i}][save_as_inventory]`" value="1" class="rounded border-gray-300 dark:border-gray-700">
                                                Guardar en inventario
                                            </label>
                                        </td>
                                        <td class="px-3 py-2 min-w-[90px]"><input :name="`items[${i}][unit]`" x-model="line.unit" class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 text-sm" placeholder="UN"></td>
                                        <td class="px-3 py-2 min-w-[110px]"><input type="number" step="0.0001" min="0" :name="`items[${i}][quantity]`" x-model.number="line.quantity" @input="recalc(line)" class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 text-sm text-right"></td>
                                        <td class="px-3 py-2 min-w-[120px]"><input type="number" step="0.0001" min="0" :name="`items[${i}][unit_price]`" x-model.number="line.unit_price" @input="recalc(line)" class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 text-sm text-right"></td>
                                        <td class="px-3 py-2 min-w-[120px] text-right font-semibold text-gray-900 dark:text-gray-100" x-text="money(line.line_total)"></td>
                                        <td class="px-3 py-2 text-center">
                                            <button type="button" @click="removeLine(i)" class="px-2 py-1 text-xs rounded-lg bg-rose-100 text-rose-700 hover:bg-rose-200">Quitar</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <div class="text-sm text-gray-500">Subtotal: <span class="font-bold text-gray-900 dark:text-gray-100" x-text="money(grandTotal())"></span></div>
                    <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white transition">Crear orden</button>
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
