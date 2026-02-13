<x-app-layout>
    <div x-data="{ open: false, deleteId: null, createOpen: false, loading: false, nombre: '', cantidad: '' }">

        <x-slot name="header">
            <div class="flex items-center justify-between ">
                <div>
                    <h2 class="text-xl font-bold text-green-900 dark:text-white flex items-center gap-2">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor"
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
                    <button @click="createOpen = true"
                        class="flex items-center justify-center gap-2 px-4 py-3.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors shadow-sm w-full sm:w-auto">

                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Nuevo Producto
                    </button>
                </div>
            </div>

            <!-- ESTADÍSTICAS RÁPIDAS -->
            @if($productos->isNotEmpty())

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">

                    @foreach ($productos as $p)
                        @php
                            $isDiesel = str_contains(strtolower($p->nombre), 'diesel');

                            $bgGradient = $isDiesel
                                ? 'from-yellow-400 to-yellow-600'
                                : 'from-blue-500 to-indigo-600';

                            $accent = $isDiesel
                                ? 'text-yellow-600'
                                : 'text-blue-600';
                        @endphp

                        <div
                            class="relative overflow-hidden rounded-2xl bg-white shadow-lg border border-gray-200 p-6 hover:shadow-xl transition-all duration-300">

                            <!-- Decorativo fondo -->
                            <div
                                class="absolute -top-10 -right-10 w-28 h-28 bg-gradient-to-br {{ $bgGradient }} opacity-10 rounded-full blur-2xl">
                            </div>

                            <div class="relative flex items-center justify-between">

                                <!-- Texto -->
                                <div>
                                    <p class="text-xs uppercase tracking-wider font-semibold {{ $accent }}">
                                        Stock {{ ucfirst($p->nombre) }}
                                    </p>

                                    <p class="text-3xl font-black tracking-tight text-gray-900">
                                        {{ number_format($p->cantidad, 2) }}
                                        <span class="text-base font-semibold text-gray-400">L</span>
                                    </p>
                                </div>

                                <!-- Icono -->
                                <div class="p-4 rounded-2xl bg-gradient-to-br {{ $bgGradient }} shadow-md">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10" />
                                    </svg>
                                </div>

                            </div>
                        </div>

                    @endforeach


                    <!-- STOCK NORMAL -->
                    <div
                        class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-500 to-green-600 p-6 shadow-lg hover:shadow-xl transition-all duration-300">

                        <div class="absolute -top-10 -right-10 w-28 h-28 bg-white/20 rounded-full blur-2xl"></div>

                        <div class="relative flex items-center justify-between">

                            <div>
                                <p class="text-xs uppercase tracking-wider font-semibold text-white/80">
                                    Stock Normal
                                </p>

                                <p class="text-3xl font-black tracking-tight text-white">
                                    {{ $productos->filter(fn($p) => $p->cantidad >= 50)->count() }}
                                </p>
                            </div>

                            <div class="p-4 rounded-2xl bg-white/20 backdrop-blur-sm">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>

                        </div>
                    </div>


                    <!-- 🔥 STOCK BAJO ACTUALIZADO -->
                    <div
                        class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-red-500 to-red-600 p-6 shadow-lg hover:shadow-xl transition-all duration-300">

                        <div class="absolute -top-10 -right-10 w-28 h-28 bg-white/20 rounded-full blur-2xl"></div>

                        <div class="relative flex items-center justify-between">

                            <div>
                                <p class="text-xs uppercase tracking-wider font-semibold text-white/80">
                                    Stock Bajo
                                </p>

                                <p class="text-3xl font-black tracking-tight text-white">
                                    {{ $productos->filter(fn($p) => $p->cantidad < 20)->count() }}
                                </p>

                                <p class="text-xs text-white/70 mt-1">
                                    Requiere atención
                                </p>
                            </div>

                            <div class="p-4 rounded-2xl bg-white/20 backdrop-blur-sm">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
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
                                    $capacidades = [
                                        'diesel' => 10000,
                                        'gasolina' => 100,
                                    ];

                                    $nombre = strtolower($p->nombre);
                                    $capacidad = $capacidades[$nombre] ?? 100; // fallback seguro

                                    $porcentaje = ($p->cantidad / $capacidad) * 100;
                                    $porcentaje = min(100, max(0, round($porcentaje)));

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
                                                    {{ ucfirst($p->nombre) }}
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
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                                Editar
                                            </a>



                                            <button @click="open = true; deleteId = {{ $p->id }}"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-100 text-red-700 rounded-lg text-xs font-medium">
                                                Eliminar
                                            </button>



                                            <form id="delete-form-{{ $p->id }}"
                                                action="{{ route('fuelcontrol.productos.destroy', $p->id) }}" method="POST"
                                                class="hidden">
                                                @csrf
                                                @method('DELETE')
                                            </form>

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
                                            <button @click="createOpen = true"
                                                class="mt-2 inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">

                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M12 4v16m8-8H4" />
                                                </svg>
                                                Nuevo Producto
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div x-show="open" x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">

                <div x-transition @click.outside="open = false"
                    class="bg-white dark:bg-gray-900 rounded-xl p-5 w-full max-w-md shadow-2xl">

                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Eliminar producto
                    </h2>

                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                        Esta acción no se puede deshacer
                    </p>

                    <div class="mt-6 flex justify-end gap-2">
                        <button @click="open = false"
                            class="px-3 py-1.5 text-sm rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                            Cancelar
                        </button>

                        <button @click="document.getElementById(`delete-form-${deleteId}`).submit()"
                            class="px-3 py-1.5 text-sm rounded-lg bg-red-600 text-white hover:bg-red-700">
                            Eliminar
                        </button>
                    </div>
                </div>
            </div>


            <div x-show="createOpen" x-cloak @keydown.escape.window="createOpen = false"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">

                <div x-transition @click.outside="createOpen = false"
                    class="bg-white dark:bg-gray-900 rounded-xl p-6 w-full max-w-lg shadow-2xl">

                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        Nuevo Producto
                    </h2>

                    <form method="POST" action="{{ route('fuelcontrol.productos.store') }}" @submit="loading = true"
                        class="space-y-4">
                        @csrf

                        <!-- Nombre -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Producto
                            </label>

                            <select name="nombre" x-model="nombre" required
                                class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-blue-500 focus:border-blue-500">

                                <option value="">Selecciona un producto</option>
                                <option value="Diésel">Diésel</option>
                                <option value="Gasolina">Gasolina</option>
                            </select>

                            <p x-show="nombre === ''" class="text-xs text-red-500 mt-1">
                                Debes seleccionar un producto
                            </p>
                        </div>

                        <!-- Cantidad -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Cantidad (L)
                            </label>
                            <input type="number" step="0.01" name="cantidad" x-model="cantidad" required
                                class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-blue-500 focus:border-blue-500"
                                :class="cantidad === '' || cantidad < 0 ? 'border-red-500' : ''">

                            <p x-show="cantidad === '' || cantidad < 0" class="text-xs text-red-500 mt-1">
                                Ingresa una cantidad válida
                            </p>
                        </div>

                        <!-- Acciones -->
                        <div class="flex justify-end gap-2 pt-4">
                            <button type="button" @click="createOpen = false" :disabled="loading"
                                class="px-4 py-2 text-sm rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                Cancelar
                            </button>

                            <button type="submit" :disabled="loading || nombre === '' || cantidad === ''"
                                class="px-4 py-2 text-sm rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50">

                                <span x-show="!loading">Guardar</span>
                                <span x-show="loading">Guardando...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>





            <!-- PAGINACIÓN (si la usas) -->
            @if($productos instanceof \Illuminate\Pagination\LengthAwarePaginator && $productos->hasPages())
                <div class="flex justify-center">
                    {{ $productos->links() }}
                </div>
            @endif

        </div>
    </div>
</x-app-layout>