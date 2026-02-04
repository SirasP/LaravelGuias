<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between ">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                    </svg>
                    Gestión de Productos
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Administra el inventario de combustibles
                </p>
            </div>

        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6 ">

        <!-- ACCIONES Y BÚSQUEDA -->
        <div class="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center mb-4">


            <div class="flex gap-6 w-full sm:w-auto ">
                <a href="{{ route('fuelcontrol.productos.create') }}"
                    class="flex items-center justify-center gap-2 px-4 py-3.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors shadow-sm w-full sm:w-auto">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Nuevo Producto
                </a>
            </div>
        </div>

        <!-- ESTADÍSTICAS RÁPIDAS -->
        @if($productos->isNotEmpty())
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

                    @foreach ($productos as $p)
                        @php
                            // Colores según producto (opcional)
                            $isDiesel = str_contains(strtolower($p->nombre), 'diesel');
                            $bg = $isDiesel
                                ? 'from-yellow-50 to-yellow-100 dark:from-yellow-900/20 dark:to-yellow-800/20 border-yellow-200 dark:border-yellow-800'
                                : 'from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 border-blue-200 dark:border-blue-800';

                            $iconBg = $isDiesel ? 'bg-yellow-600' : 'bg-blue-600';
                            $text = $isDiesel ? 'text-yellow-900 dark:text-yellow-100' : 'text-blue-900 dark:text-blue-100';
                            $sub = $isDiesel ? 'text-yellow-600 dark:text-yellow-400' : 'text-blue-600 dark:text-blue-400';
                        @endphp

                        <div class="bg-gradient-to-br {{ $bg }} rounded-lg p-4 border">
                            <div class="flex items-center gap-3">
                                <div class="p-2 {{ $iconBg }} rounded-lg">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10" />
                                    </svg>
                                </div>

                                <div>
                                    <p class="text-xs font-medium {{ $sub }}">
                                        Stock {{ $p->nombre }}
                                    </p>
                                    <p class="text-lg font-bold {{ $text }}">
                                        {{ number_format($p->cantidad, 2) }} L
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endforeach

                </div>


                <div
                    class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 rounded-lg p-4 border border-green-200 dark:border-green-800">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-green-600 rounded-lg">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-green-600 dark:text-green-400 font-medium">Stock Normal</p>
                            <p class="text-lg font-bold text-green-900 dark:text-green-100">
                                {{ $productos->filter(fn($p) => $p->cantidad >= 50)->count() }}
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    class="bg-gradient-to-br from-red-50 to-red-100 dark:from-red-900/20 dark:to-red-800/20 rounded-lg p-4 border border-red-200 dark:border-red-800">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-red-600 rounded-lg">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-red-600 dark:text-red-400 font-medium">Stock Bajo</p>
                            <p class="text-lg font-bold text-red-900 dark:text-red-100">
                                {{ $productos->filter(fn($p) => $p->cantidad < 20)->count() }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- TABLA DE PRODUCTOS -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Producto
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Stock Actual
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Nivel
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Estado
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Acciones
                            </th>
                        </tr>
                    </thead>

                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($productos as $p)
                            @php
                                $porcentaje = min(100, max(0, $p->cantidad));
                                if ($porcentaje < 20) {
                                    $color = 'bg-red-500';
                                    $textColor = 'text-red-600 dark:text-red-400';
                                    $bgColor = 'bg-red-100 dark:bg-red-900/30';
                                    $borderColor = 'border-red-200 dark:border-red-800';
                                    $estado = 'Crítico';
                                } elseif ($porcentaje < 50) {
                                    $color = 'bg-yellow-500';
                                    $textColor = 'text-yellow-600 dark:text-yellow-400';
                                    $bgColor = 'bg-yellow-100 dark:bg-yellow-900/30';
                                    $borderColor = 'border-yellow-200 dark:border-yellow-800';
                                    $estado = 'Bajo';
                                } else {
                                    $color = 'bg-green-500';
                                    $textColor = 'text-green-600 dark:text-green-400';
                                    $bgColor = 'bg-green-100 dark:bg-green-900/30';
                                    $borderColor = 'border-green-200 dark:border-green-800';
                                    $estado = 'Normal';
                                }
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="h-10 w-10 flex-shrink-0 {{ $bgColor }} rounded-lg flex items-center justify-center border {{ $borderColor }}">
                                            <svg class="w-5 h-5 {{ $textColor }}" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                            </svg>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $p->nombre }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                ID: #{{ $p->id }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="text-sm font-bold text-gray-900 dark:text-white font-mono">
                                        {{ number_format($p->cantidad, 2) }} L
                                    </div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-2">
                                        <div
                                            class="w-full max-w-xs h-3 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                            <div class="{{ $color }} h-3 rounded-full transition-all duration-300"
                                                style="width: {{ $porcentaje }}%"></div>
                                        </div>
                                        <span
                                            class="text-xs font-medium text-gray-600 dark:text-gray-400 min-w-[45px] text-right">
                                            {{ number_format($porcentaje, 0) }}%
                                        </span>
                                    </div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span
                                        class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full {{ $bgColor }} {{ $textColor }} border {{ $borderColor }}">
                                        {{ $estado }}
                                    </span>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <a href="{{ route('fuelcontrol.productos.edit', $p->id) }}"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 rounded-lg text-xs font-medium hover:bg-blue-200 dark:hover:bg-blue-900/50 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            Editar
                                        </a>

                                        <button
                                            onclick="if(confirm('¿Estás seguro de eliminar este producto?')) document.getElementById('delete-form-{{ $p->id }}').submit()"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 rounded-lg text-xs font-medium hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                            Eliminar
                                        </button>


                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <div class="p-4 bg-gray-100 dark:bg-gray-700 rounded-full">
                                            <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-gray-600 dark:text-gray-400 font-medium">
                                                No hay productos registrados
                                            </p>
                                            <p class="text-sm text-gray-500 dark:text-gray-500 mt-1">
                                                Comienza agregando tu primer producto de combustible
                                            </p>
                                        </div>
                                        <a href="{{ route('fuelcontrol.productos.create') }}"
                                            class="mt-2 inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 4v16m8-8H4" />
                                            </svg>
                                            Crear Primer Producto
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PAGINACIÓN (si la usas) -->
        @if($productos instanceof \Illuminate\Pagination\LengthAwarePaginator && $productos->hasPages())
            <div class="flex justify-center">
                {{ $productos->links() }}
            </div>
        @endif

    </div>
</x-app-layout>