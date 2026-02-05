<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">
            🚗 Vehículos
        </h2>
    </x-slot>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

        <!-- HEADER -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">
                🚗 Vehículos
            </h1>

            <a href="{{ route('vehiculos.create') }}"
                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                + Nuevo vehículo
            </a>
        </div>

        <!-- TABLA -->
        <div class="overflow-x-auto bg-white shadow rounded-lg">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">ID</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Patente</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Descripción</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Tipo</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Fecha registro</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Usuario</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">Acciones</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200">
                    @forelse ($vehiculos as $v)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-gray-700">
                                {{ $v->id }}
                            </td>

                            <td class="px-4 py-2 font-medium text-gray-800">
                                {{ $v->patente }}
                            </td>

                            <td class="px-4 py-2 text-gray-700">
                                {{ $v->descripcion }}
                            </td>

                            <td class="px-4 py-2 capitalize">
                                {{ $v->tipo }}
                            </td>

                            <td class="px-4 py-2 text-gray-600">
                                {{ \Carbon\Carbon::parse($v->fecha_registro)->format('d-m-Y') }}
                            </td>

                            <td class="px-4 py-2 text-gray-700">
                                {{ $v->usuario }}
                            </td>

                            <td class="px-4 py-2 text-right space-x-2">
                                <a href="{{ route('vehiculos.show', $v) }}" class="text-blue-600 hover:underline">
                                    Ver
                                </a>

                                <a href="{{ route('vehiculos.edit', $v) }}" class="text-yellow-600 hover:underline">
                                    Editar
                                </a>

                                <form action="{{ route('vehiculos.destroy', $v) }}" method="POST" class="inline"
                                    onsubmit="return confirm('¿Eliminar vehículo?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-red-600 hover:underline">
                                        Eliminar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-gray-500">
                                No hay vehículos registrados
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- PAGINACIÓN -->
        <div class="mt-4">
            {{ $vehiculos->links() }}
        </div>

    </div>

</x-app-layout>