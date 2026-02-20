<x-app-layout>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-xl bg-emerald-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Órdenes de compra</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Tablero</p>
                </div>
            </div>

            <a href="{{ route('purchase_orders.create') }}" class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white transition">
                Nueva orden
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800/70 text-gray-500 dark:text-gray-300 uppercase text-xs">
                            <tr>
                                <th class="px-4 py-3 text-left">Orden</th>
                                <th class="px-4 py-3 text-left">Proveedor</th>
                                <th class="px-4 py-3 text-left">Moneda</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3 text-left">Estado</th>
                                <th class="px-4 py-3 text-left">Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $o)
                                <tr class="border-t border-gray-100 dark:border-gray-800 hover:bg-gray-50/70 dark:hover:bg-gray-800/40 cursor-pointer" onclick="window.location='{{ route('purchase_orders.show', $o->id) }}'">
                                    <td class="px-4 py-3 font-semibold text-gray-900 dark:text-gray-100">{{ $o->order_number }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $o->supplier_name }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $o->currency }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-gray-100">{{ number_format((float) $o->total, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold {{ $o->status === 'sent' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' }}">
                                            {{ $o->status === 'sent' ? 'Enviada' : 'Borrador' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ \Carbon\Carbon::parse($o->created_at)->format('d/m/Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-gray-400">No hay órdenes de compra aún.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">{{ $orders->links() }}</div>
        </div>
    </div>
</x-app-layout>
