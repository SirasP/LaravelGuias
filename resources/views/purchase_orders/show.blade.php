<x-app-layout>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">{{ $order->order_number }}</h2>
                <p class="text-xs text-gray-400 mt-0.5">Proveedor: {{ $order->supplier_name }} · {{ $order->currency }}</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('purchase_orders.index') }}" class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-xl bg-gray-200 hover:bg-gray-300 text-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-100 transition">Volver</a>
                <a href="{{ route('purchase_orders.create') }}" class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white transition">Nueva orden</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                <div>
                    <p class="text-xs text-gray-500">Estado</p>
                    <p class="font-semibold {{ $order->status === 'sent' ? 'text-emerald-600' : 'text-amber-600' }}">{{ $order->status === 'sent' ? 'Enviada' : 'Borrador' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Creación</p>
                    <p class="font-semibold text-gray-900 dark:text-gray-100">{{ \Carbon\Carbon::parse($order->created_at)->format('d/m/Y H:i') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Total</p>
                    <p class="font-bold text-gray-900 dark:text-gray-100">{{ $order->currency }} {{ number_format((float) $order->total, 2, ',', '.') }}</p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Productos</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800/70 text-xs uppercase text-gray-500 dark:text-gray-300">
                            <tr>
                                <th class="px-4 py-2 text-left">Producto</th>
                                <th class="px-4 py-2 text-left">UdM</th>
                                <th class="px-4 py-2 text-right">Cantidad</th>
                                <th class="px-4 py-2 text-right">Precio</th>
                                <th class="px-4 py-2 text-right">Importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $i)
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $i->product_name }}</td>
                                    <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $i->unit }}</td>
                                    <td class="px-4 py-2 text-right text-gray-900 dark:text-gray-100">{{ number_format((float) $i->quantity, 4, ',', '.') }}</td>
                                    <td class="px-4 py-2 text-right text-gray-900 dark:text-gray-100">{{ number_format((float) $i->unit_price, 2, ',', '.') }}</td>
                                    <td class="px-4 py-2 text-right font-bold text-gray-900 dark:text-gray-100">{{ number_format((float) $i->line_total, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Enviar por correo</h3>
                <form method="POST" action="{{ route('purchase_orders.send_email', $order->id) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Correos (coma/;)</label>
                        <input name="emails" value="{{ old('emails', $recipients->join(', ')) }}" class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" placeholder="compras@empresa.cl, gerente@empresa.cl">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Asunto</label>
                        <input name="subject" value="{{ old('subject', 'Orden de compra ' . $order->order_number) }}" class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Mensaje</label>
                        <textarea name="message" rows="3" class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" placeholder="Adjuntamos orden de compra...">{{ old('message') }}</textarea>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white transition">Enviar orden</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
