<x-app-layout>

    {{-- ================= HEADER ================= --}}
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Stock actual por producto
                </p>
            </div>
        </div>
    </x-slot>

    {{-- ================= CONTENIDO ================= --}}
    <div class="py-6">
        <div class="max-w-full mx-auto px-4">

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-xl overflow-hidden">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <a href="{{ route('inventario.stock.entrada') }}" class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold
          bg-blue-600 text-white hover:bg-blue-700 transition mb-4">
                        + Entrada de stock
                    </a>
                    {{-- Buscador --}}
                    <form method="GET" action="{{ route('inventario.stock') }}" class="flex gap-4 mb-4">
                        <input name="q" value="{{ $q }}" placeholder="Buscar producto..." class="w-full md:w-96 rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm text-gray-900
                                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                   dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">

                        <button type="submit"
                            class="px-6 py-2 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 hover:opacity-90 transition">
                            Buscar
                        </button>
                    </form>

                    {{-- Tabla --}}
                    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900/60 text-gray-700 dark:text-gray-200">
                                <tr class="[&>th]:px-4 [&>th]:py-3 [&>th]:font-semibold [&>th]:text-left">
                                    <th>ID</th>
                                    <th>SKU</th>
                                    <th>Producto</th>
                                    <th>Stock actual</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse ($stock as $s)
                                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/40 transition">
                                                                <td class="px-4 py-3">#{{ $s->id }}</td>

                                                                <td class="px-4 py-3">
                                                                    <span
                                                                        class="inline-flex rounded-md bg-gray-100 dark:bg-gray-700 px-2 py-1 text-xs">
                                                                        {{ $s->sku ?: '—' }}
                                                                    </span>
                                                                </td>

                                                                <td class="px-4 py-3 font-medium">
                                                                    {{ $s->nombre }}
                                                                </td>

                                                                <td class="px-4 py-3">
                                                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium
                                                                                                                                                                {{ $s->stock_actual > 0
                                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                    : 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                                                                        {{ $s->stock_actual }}
                                                                    </span>
                                                                </td>
                                                            </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-12 text-gray-500">
                                            No hay productos.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>

        </div>
    </div>

</x-app-layout>