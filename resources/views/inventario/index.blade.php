<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
            Inventario
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex gap-6">

                <a href="{{ route('inventario.productos') }}"
                    class="flex-1 bg-white dark:bg-gray-800 shadow-sm rounded-xl p-6 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    <h3 class="text-lg font-semibold">Productos</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Crear y administrar productos del inventario.
                    </p>
                </a>

                <a href="{{ route('inventario.categorias') }}"
                    class="flex-1 bg-white dark:bg-gray-800 shadow-sm rounded-xl p-6 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    <h3 class="text-lg font-semibold">Categorías</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Organiza los productos por categoría.
                    </p>
                </a>

                <a href="{{ route('inventario.movimientos') }}"
                    class="flex-1 bg-white dark:bg-gray-800 shadow-sm rounded-xl p-6 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    <h3 class="text-lg font-semibold">Movimientos</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Entradas y salidas de inventario.
                    </p>
                </a>

                <a href="{{ route('inventario.stock') }}"
                    class="flex-1 bg-white dark:bg-gray-800 shadow-sm rounded-xl p-6 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    <h3 class="text-lg font-semibold">Stock</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Ver stock actual por producto.
                    </p>
                </a>

            </div>

        </div>
    </div>
</x-app-layout>