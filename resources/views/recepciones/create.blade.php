<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-1.5 min-w-0 text-xs">
            <a href="{{ route('purchase_orders.index') }}"
               class="text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition font-medium">Cotizaciones</a>
            <svg class="w-3 h-3 text-gray-300 dark:text-gray-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
            </svg>
            <a href="{{ route('purchase_orders.show', $order->id) }}"
               class="text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition font-medium font-mono">{{ $order->order_number }}</a>
            <svg class="w-3 h-3 text-gray-300 dark:text-gray-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
            </svg>
            <span class="font-bold text-gray-700 dark:text-gray-300">Recibir mercadería</span>
        </div>
    </x-slot>

    <div class="py-6 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

        @if(session('error'))
            <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-300">
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                <h1 class="text-lg font-black text-gray-900 dark:text-gray-100">Recepción de mercadería</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                    OC <span class="font-mono font-semibold">{{ $order->order_number }}</span> · {{ $order->supplier_name }}
                </p>
            </div>

            <form method="POST" action="{{ route('purchase_orders.receptions.store', $order->id) }}" class="p-5 space-y-5">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Bodega destino</label>
                        <select name="bodega_id" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                            <option value="">— Sin bodega —</option>
                            @foreach($bodegas as $b)
                                <option value="{{ $b->id }}">{{ $b->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Fecha recepción</label>
                        <input type="date" name="fecha_recepcion" value="{{ now()->toDateString() }}"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Notas</label>
                        <input type="text" name="notas" placeholder="Opcional"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                    </div>
                </div>

                <div class="overflow-x-auto border border-gray-100 dark:border-gray-800 rounded-lg">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800/50 text-xs text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-3 py-2 text-left font-bold">Producto</th>
                                <th class="px-3 py-2 text-right font-bold">Pedido</th>
                                <th class="px-3 py-2 text-right font-bold">Ya recibido</th>
                                <th class="px-3 py-2 text-right font-bold">Pendiente</th>
                                <th class="px-3 py-2 text-right font-bold">Recibir ahora</th>
                                <th class="px-3 py-2 text-right font-bold">Costo unit.</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($items as $idx => $i)
                                <tr>
                                    <td class="px-3 py-2">
                                        <div class="font-semibold text-gray-800 dark:text-gray-200">{{ $i->product_name }}</div>
                                        <div class="text-xs text-gray-400">{{ $i->unit }}</div>
                                        <input type="hidden" name="lineas[{{ $idx }}][item_id]" value="{{ $i->id }}">
                                        <input type="hidden" name="lineas[{{ $idx }}][product_name]" value="{{ $i->product_name }}">
                                        <input type="hidden" name="lineas[{{ $idx }}][unidad]" value="{{ $i->unit }}">
                                        <input type="hidden" name="lineas[{{ $idx }}][cantidad_pedida]" value="{{ $i->quantity }}">
                                        <input type="hidden" name="lineas[{{ $idx }}][inventory_product_id]" value="{{ $i->inventory_product_id }}">
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-500">{{ rtrim(rtrim(number_format((float)$i->quantity, 4, '.', ''), '0'), '.') }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-500">{{ rtrim(rtrim(number_format((float)$i->ya_recibido, 4, '.', ''), '0'), '.') }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums font-semibold {{ $i->pendiente > 0 ? 'text-amber-600' : 'text-emerald-600' }}">{{ rtrim(rtrim(number_format((float)$i->pendiente, 4, '.', ''), '0'), '.') }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <input type="number" step="any" min="0" name="lineas[{{ $idx }}][cantidad_recibida]"
                                               value="{{ $i->pendiente > 0 ? rtrim(rtrim(number_format((float)$i->pendiente, 4, '.', ''), '0'), '.') : 0 }}"
                                               class="w-24 text-right rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm tabular-nums">
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <input type="number" step="any" min="0" name="lineas[{{ $idx }}][costo_unitario]"
                                               value="{{ $i->unit_price }}"
                                               class="w-24 text-right rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm tabular-nums">
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-3 py-6 text-center text-gray-400">Esta OC no tiene ítems.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <a href="{{ route('purchase_orders.show', $order->id) }}"
                       class="px-4 py-2 text-sm font-semibold text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200">Cancelar</a>
                    <button type="submit"
                            class="px-5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold shadow-sm">
                        Crear recepción (borrador)
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
