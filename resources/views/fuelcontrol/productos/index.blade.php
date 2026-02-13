<x-app-layout x-data="{
        open: false,
        deleteId: null,
        createOpen: false,
        loading: false,
        nombre: '',
        cantidad: ''
    }" class="min-h-screen">


    <!-- HEADER -->
    {{-- ═══════════════════════════════════════
    HEADER PRODUCTOS
    ═══════════════════════════════════════ --}}
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4 w-full">

            {{-- Título --}}
            <div class="flex items-center gap-3 min-w-0">


                {{-- Texto --}}
                <div>
                    <h2 class="flex items-center gap-2 text-xl font-bold text-gray-900 dark:text-white leading-none">

                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>

                        <span>Gestión de Productos</span>
                    </h2>

                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Inventario de combustibles
                    </p>
                </div>

            </div>


            {{-- Botón principal --}}
            <button @click="createOpen=true" class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-bold rounded-xl
           bg-emerald-600 hover:bg-emerald-700 active:scale-95
           text-white transition-all shadow-md shadow-emerald-300/40
           dark:shadow-emerald-900/40">

                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>

                Nuevo Producto
            </button>



        </div>
    </x-slot>

    <!-- MODAL CREAR -->
    <div x-show="createOpen" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">

        <div @click.outside="createOpen=false"
            class="bg-white dark:bg-gray-900 rounded-2xl p-6 w-full max-w-lg shadow-2xl">

            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">
                Nuevo Producto
            </h2>

            <form method="POST" action="{{ route('fuelcontrol.productos.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Producto
                    </label>

                    <select name="nombre" required
                        class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="">Selecciona un producto</option>
                        <option value="Diésel">Diésel</option>
                        <option value="Gasolina">Gasolina</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Cantidad (L)
                    </label>

                    <input type="number" step="0.01" name="cantidad" required
                        class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-emerald-500 focus:border-emerald-500">
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" @click="createOpen=false"
                        class="px-4 py-2 bg-gray-200 dark:bg-gray-700 rounded-lg text-sm">
                        Cancelar
                    </button>

                    <button type="submit"
                        class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700">
                        Guardar
                    </button>
                </div>
            </form>

        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 py-8 space-y-8">

        <!-- KPI CARDS -->
        @if($productos->isNotEmpty())
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">

                @foreach ($productos as $p)
                    @php
                        $isDiesel = str_contains(strtolower($p->nombre), 'diesel');
                        $bg = $isDiesel ? 'from-yellow-400 to-yellow-600' : 'from-blue-500 to-indigo-600';
                    @endphp

                    <div
                        class="relative bg-white dark:bg-gray-900 rounded-2xl shadow-lg p-6 overflow-hidden hover:shadow-xl transition">
                        <div
                            class="absolute -right-8 -top-8 w-32 h-32 bg-gradient-to-br {{ $bg }} opacity-10 rounded-full blur-2xl">
                        </div>

                        <p class="text-xs uppercase font-semibold text-gray-500 dark:text-gray-400">
                            {{ ucfirst($p->nombre) }}
                        </p>

                        <p class="text-3xl font-extrabold text-gray-900 dark:text-white mt-2">
                            {{ number_format($p->cantidad, 2) }}
                            <span class="text-sm text-gray-400">L</span>
                        </p>
                    </div>
                @endforeach

                <div class="bg-gradient-to-br from-emerald-500 to-green-600 rounded-2xl p-6 shadow-lg text-white">
                    <p class="text-xs uppercase font-semibold opacity-80">Stock Normal</p>
                    <p class="text-3xl font-bold mt-2">
                        {{ $productos->filter(fn($p) => $p->cantidad >= 50)->count() }}
                    </p>
                </div>

                <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-2xl p-6 shadow-lg text-white">
                    <p class="text-xs uppercase font-semibold opacity-80">Stock Bajo</p>
                    <p class="text-3xl font-bold mt-2">
                        {{ $productos->filter(fn($p) => $p->cantidad < 20)->count() }}
                    </p>
                </div>

            </div>
        @endif

        <!-- TABLA -->
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-lg overflow-hidden">

            <div class="px-6 py-4 border-b dark:border-gray-800">
                <h3 class="font-semibold text-gray-700 dark:text-gray-200">
                    Lista de Productos
                </h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 uppercase text-xs">
                        <tr>
                            <th class="px-6 py-3 text-left">Producto</th>
                            <th class="px-6 py-3 text-right">Stock</th>
                            <th class="px-6 py-3 text-center">Nivel</th>
                            <th class="px-6 py-3 text-center">Estado</th>
                            <th class="px-6 py-3 text-center">Acciones</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y dark:divide-gray-800">
                        @forelse ($productos as $p)

                            @php
                                $capacidad = strtolower($p->nombre) === 'diesel' ? 10000 : 100;
                                $porcentaje = min(100, round(($p->cantidad / $capacidad) * 100));
                            @endphp

                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                                <td class="px-6 py-4 font-semibold text-gray-900 dark:text-white">
                                    {{ ucfirst($p->nombre) }}
                                </td>

                                <td class="px-6 py-4 text-right font-mono font-bold">
                                    {{ number_format($p->cantidad, 2) }} L
                                </td>

                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 h-2 rounded-full overflow-hidden">
                                            <div class="bg-emerald-500 h-2 rounded-full" style="width: {{ $porcentaje }}%">
                                            </div>
                                        </div>
                                        <span class="text-xs">{{ $porcentaje }}%</span>
                                    </div>
                                </td>

                                <td class="px-6 py-4 text-center">
                                    @if($porcentaje < 20)
                                        <span class="px-3 py-1 text-xs rounded-full bg-red-100 text-red-600">Crítico</span>
                                    @elseif($porcentaje < 50)
                                        <span class="px-3 py-1 text-xs rounded-full bg-yellow-100 text-yellow-600">Bajo</span>
                                    @else
                                        <span class="px-3 py-1 text-xs rounded-full bg-green-100 text-green-600">Normal</span>
                                    @endif
                                </td>

                                <td class="px-6 py-4 text-center">
                                    <div class="flex justify-center gap-2">
                                        <a href="{{ route('fuelcontrol.productos.edit', $p->id) }}"
                                            class="px-3 py-1 bg-blue-100 text-blue-600 rounded-lg text-xs hover:bg-blue-200 transition">
                                            Editar
                                        </a>

                                        <button @click="confirmDelete({{ $p->id }})"
                                            class="px-3 py-1 bg-red-100 text-red-600 rounded-lg text-xs hover:bg-red-200 transition">
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
                                <td colspan="5" class="py-10 text-center text-gray-400">
                                    No hay productos registrados
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- MODAL ELIMINAR -->
    <div x-show="open" x-cloak class="fixed inset-0 flex items-center justify-center bg-black/50 backdrop-blur-sm">

        <div class="bg-white dark:bg-gray-900 rounded-2xl p-6 w-full max-w-md shadow-2xl">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                Confirmar eliminación
            </h2>

            <p class="text-sm text-gray-500 mt-2">
                Esta acción no se puede deshacer.
            </p>

            <div class="flex justify-end gap-3 mt-6">
                <button @click="open=false" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 rounded-lg text-sm">
                    Cancelar
                </button>

                <button @click="deleteNow()"
                    class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700">
                    Eliminar
                </button>
            </div>
        </div>
    </div>

    </div>


</x-app-layout>