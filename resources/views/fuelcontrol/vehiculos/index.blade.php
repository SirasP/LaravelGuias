<x-app-layout>
    <div x-data="{ openCreate: false, openDelete: false, deleteId: null,     openCreate: false,
    openDelete: false,
    openEdit: false,
    editVehiculo: {}
  }">
        <x-slot name="header">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                        Gestión de Vehículos
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Administra la flota de vehículos del sistema
                    </p>
                </div>

            </div>
        </x-slot>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

            <!-- ESTADÍSTICAS RÁPIDAS -->
            @if($vehiculos->isNotEmpty())
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div
                        class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-blue-600 rounded-lg">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs text-blue-600 dark:text-blue-400 font-medium">Total Flota</p>
                                <p class="text-lg font-bold text-blue-900 dark:text-blue-100">
                                    {{ $stats->total }}

                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 rounded-lg p-4 border border-green-200 dark:border-green-800">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-green-600 rounded-lg">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs text-green-600 dark:text-green-400 font-medium">Camiones</p>
                                <p class="text-lg font-bold text-green-900 dark:text-green-100">
                                    {{ $stats->camiones }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 rounded-lg p-4 border border-green-200 dark:border-green-800">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-green-600 rounded-lg">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs text-green-600 dark:text-green-400 font-medium">Motos y MotoBomba</p>
                                <p class="text-lg font-bold text-green-900 dark:text-green-100">
                                    {{ $stats->motos }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/20 rounded-lg p-4 border border-purple-200 dark:border-purple-800">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-purple-600 rounded-lg">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs text-purple-600 dark:text-purple-400 font-medium">Camionetas</p>
                                <p class="text-lg font-bold text-purple-900 dark:text-purple-100">
                                    {{ $stats->camionetas }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="bg-gradient-to-br from-orange-50 to-orange-100 dark:from-orange-900/20 dark:to-orange-800/20 rounded-lg p-4 border border-orange-200 dark:border-orange-800">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-orange-600 rounded-lg">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs text-orange-600 dark:text-orange-400 font-medium">Maquinaria</p>
                                <p class="text-lg font-bold text-orange-900 dark:text-orange-100">
                                    {{ $stats->maquinaria }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-blue-600 rounded-lg">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m5-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs text-blue-600 dark:text-blue-400 font-medium">Propio</p>
                                <p class="text-lg font-bold text-blue-900 dark:text-blue-100">
                                    {{ $stats->propios }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-blue-600 rounded-lg">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 4H7a2 2 0 01-2-2V6a2 2 0 012-2h5l5 5v9a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs text-blue-600 dark:text-blue-400 font-medium">Arrendado</p>
                                <p class="text-lg font-bold text-blue-900 dark:text-blue-100">
                                    {{ $stats->arrendados }}
                                </p>
                            </div>
                        </div>
                    </div>


                    <div
                        class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-blue-600 rounded-lg">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7h12l-4-4m4 4l-4 4M16 17H4l4 4m-4-4l4-4" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs text-blue-600 dark:text-blue-400 font-medium">Prestado</p>
                                <p class="text-lg font-bold text-blue-900 dark:text-blue-100">
                                    {{ $stats->prestados }}
                                </p>
                            </div>
                        </div>
                    </div>

                </div>
            @endif

            <!-- BARRA DE ACCIONES Y BÚSQUEDA -->
            <div class="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
                <div class="flex-1">
                    <form method="GET" action="{{ route('fuelcontrol.vehiculos.index') }}"
                        class="flex flex-wrap gap-2 items-center">

                        {{-- SEARCH --}}
                        <div class="relative flex-1 max-w-md">


                            <input type="text" name="search" value="{{ request('search') }}"
                                placeholder="Buscar por patente o descripción…" class="block w-full pl-10 pr-3 py-3
           border border-gray-300 dark:border-gray-600
           rounded-lg text-sm
           bg-white dark:bg-gray-700
           text-gray-900 dark:text-white
           placeholder-gray-500 dark:placeholder-gray-400
           focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>



                        {{-- BUSCAR --}}
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-3
           bg-blue-600 text-white
           rounded-lg text-sm font-medium
           hover:bg-blue-700
           focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1
           transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            Buscar
                        </button>

                        {{-- LIMPIAR --}}
                        @if(request('search') || request('tipo'))
                            <a href="{{ route('fuelcontrol.vehiculos.index') }}" class="inline-flex items-center gap-2 px-4 py-3
                                                                                   bg-gray-100 text-gray-700
                                                                                   border border-gray-300
                                                                                   rounded-lg text-sm font-medium
                                                                                   hover:bg-gray-200
                                                                                   focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-1
                                                                                   transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                Limpiar
                            </a>
                        @endif

                    </form>

                </div>


                <button @click="openCreate = true" class="flex items-center gap-2 px-4 py-3
           bg-green-600 text-white rounded-lg text-sm font-medium
           hover:bg-green-700 transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Nuevo Vehículo
                </button>
            </div>

            <!-- TABLA DE VEHÍCULOS -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Vehículo
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Tipo
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Descripción
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Registro
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Usuario
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Acciones
                                </th>
                            </tr>
                        </thead>

                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($vehiculos as $v)
                                @php

                                    $tipoConfig = [
                                        'maquinaria' => [
                                            'color' => 'text-green-600 dark:text-green-400',
                                            'bg' => 'bg-green-100 dark:bg-green-900/30',
                                            'border' => 'border-green-200 dark:border-green-800',
                                            'icon' => '🚜'
                                        ],
                                        'Camioneta' => [
                                            'color' => 'text-blue-600 dark:text-blue-400',
                                            'bg' => 'bg-blue-100 dark:bg-blue-900/30',
                                            'border' => 'border-blue-200 dark:border-blue-800',
                                            'icon' => '🚙'
                                        ],
                                        'moto' => [
                                            'color' => 'text-purple-600 dark:text-purple-400',
                                            'bg' => 'bg-purple-100 dark:bg-purple-900/30',
                                            'border' => 'border-purple-200 dark:border-purple-800',
                                            'icon' => '🏍️'

                                        ],
                                        'otro' => [
                                            'color' => 'text-gray-600 dark:text-gray-400',
                                            'bg' => 'bg-gray-100 dark:bg-gray-700',
                                            'border' => 'border-gray-200 dark:border-gray-600',
                                            'icon' => '⚙️'
                                        ]
                                    ];

                                    $desc = strtolower($v->descripcion);

                                    if (str_contains($desc, 'tractor') || str_contains($desc, 'excavadora') || str_contains($desc, 'pala') || str_contains($desc, 'fumigador')) {
                                        $config = $tipoConfig['maquinaria'];
                                    } elseif (str_contains($desc, 'camion') || str_contains($desc, 'camioneta') || str_contains($desc, 'minibus')) {
                                        $config = $tipoConfig['Camioneta'];
                                    } elseif (str_contains($desc, 'moto')) {
                                        $config = $tipoConfig['moto'];
                                    } else {
                                        $config = $tipoConfig['otro'];
                                    }

                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-4">
                                            <div
                                                class="h-10 w-10 flex-shrink-0 {{ $config['bg'] }} {{ $config['border'] }}
                                                                                                                                                                               rounded-lg flex items-center justify-center text-xl">
                                                <span class="{{ $config['color'] }}">
                                                    {{ $config['icon'] }}
                                                </span>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ $v->patente }}
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    ID: #{{ $v->id }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full {{ $config['bg'] }} {{ $config['color'] }} border {{ $config['border'] }}">
                                            {{ ucfirst($v->tipo) }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900 dark:text-white max-w-xs truncate">
                                            {{ $v->descripcion ?? 'Sin descripción' }}
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-white">
                                            {{ \Carbon\Carbon::parse($v->fecha_registro)->format('d/m/Y') }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ \Carbon\Carbon::parse($v->fecha_registro)->diffForHumans() }}
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="h-8 w-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                                <span class="text-xs font-medium text-gray-600 dark:text-gray-300">
                                                    {{ strtoupper(substr($v->usuario, 0, 2)) }}
                                                </span>
                                            </div>
                                            <div class="text-sm text-gray-900 dark:text-white">
                                                {{ $v->usuario }}
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <a href="{{ route('fuelcontrol.vehiculos.show', $v) }}"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 rounded-lg text-xs font-medium hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                                                title="Ver detalles">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                Ver
                                            </a>

                                            <button @click="
                                        openEdit = true;
                                        editVehiculo = {
                                            id: {{ $v->id }},
                                            patente: '{{ $v->patente }}',
                                            descripcion: '{{ $v->descripcion }}',
                                            tipo: '{{ $v->tipo }}'
                                        };
                                    " class="inline-flex items-center gap-1.5 px-3 py-1.5
                                           bg-blue-100 text-blue-700
                                           dark:bg-blue-900/30 dark:text-blue-400
                                           rounded-lg text-xs font-medium
                                           hover:bg-blue-200 dark:hover:bg-blue-900/50
                                           transition-colors" title="Editar vehículo">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                                Editar
                                            </button>



                                            <button @click="openDelete = true; deleteId = {{ $v->id }}" class="inline-flex items-center gap-1.5 px-3 py-1.5
                                                                                   bg-red-100 text-red-700
                                                                                   dark:bg-red-900/30 dark:text-red-400
                                                                                   rounded-lg text-xs font-medium
                                                                                   hover:bg-red-200 dark:hover:bg-red-900/50
                                                                                   transition-colors"
                                                title="Eliminar vehículo">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
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
                                    <td colspan="6" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center gap-3">
                                            <div class="p-4 bg-gray-100 dark:bg-gray-700 rounded-full">
                                                <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="text-gray-600 dark:text-gray-400 font-medium">
                                                    @if(request('search') || request('tipo'))
                                                        No se encontraron vehículos con los filtros aplicados
                                                    @else
                                                        No hay vehículos registrados
                                                    @endif
                                                </p>
                                                <p class="text-sm text-gray-500 dark:text-gray-500 mt-1">
                                                    @if(request('search') || request('tipo'))
                                                        Intenta ajustar los filtros de búsqueda
                                                    @else
                                                        Comienza agregando tu primer vehículo a la flota
                                                    @endif
                                                </p>
                                            </div>
                                            @if(!request('search') && !request('tipo'))
                                                <a href="{{ route('fuelcontrol.vehiculos.create') }}"
                                                    class="mt-2 inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition-colors">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M12 4v16m8-8H4" />
                                                    </svg>
                                                    Crear Primer Vehículo
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- PAGINACIÓN -->
            @if($vehiculos->hasPages())
                <div class="flex justify-end">
                    {{ $vehiculos->links() }}
                </div>
            @endif

        </div>


        <div x-show="openCreate" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">

            <div @click.outside="openCreate = false" x-transition
                class="bg-white dark:bg-gray-900 rounded-xl p-6 w-full max-w-lg shadow-2xl">

                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    Nuevo Vehículo
                </h2>

                <form method="POST" action="{{ route('fuelcontrol.vehiculos.store') }}" class="space-y-4">
                    @csrf

                    {{-- PATENTE --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Modelo Vehiculo
                        </label>
                        <input type="text" name="patente" required class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700
                           dark:bg-gray-800 dark:text-white
                           focus:ring-green-500 focus:border-green-500">
                    </div>

                    {{-- DESCRIPCIÓN --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Tipo Vehiculo
                        </label>
                        <input type="text" name="descripcion" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700
                           dark:bg-gray-800 dark:text-white">
                    </div>

                    {{-- TIPO --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            (Propio / Arrendado / Prestado)
                        </label>
                        <select name="tipo" required class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700
           dark:bg-gray-800 dark:text-white">

                            <option value="">Seleccionar</option>
                            <option value="Propio">Propio</option>
                            <option value="Arrendado">Arrendado</option>
                            <option value="Prestado">Prestado</option>

                        </select>
                    </div>

                    {{-- BOTONES --}}
                    <div class="flex justify-end gap-2 pt-4">
                        <button type="button" @click="openCreate = false" class="px-4 py-2 text-sm rounded-lg bg-gray-100 dark:bg-gray-700
                           text-gray-700 dark:text-gray-300">
                            Cancelar
                        </button>

                        <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-green-600 text-white
                           hover:bg-green-700">
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="openDelete" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
            <div @click.outside="openDelete = false" x-transition
                class="bg-white dark:bg-gray-900 rounded-xl p-6 w-full max-w-md shadow-2xl">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                    Confirmar eliminación
                </h2>

                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                    ¿Estás seguro de que deseas eliminar este vehículo?
                    <br>
                    <span class="font-medium text-red-600">Esta acción no se puede deshacer.</span>
                </p>

                <div class="flex justify-end gap-3">
                    <button type="button" @click="openDelete = false" class="px-4 py-2 rounded-lg text-sm
                       bg-gray-100 text-gray-700
                       dark:bg-gray-700 dark:text-gray-300
                       hover:bg-gray-200 dark:hover:bg-gray-600">
                        Cancelar
                    </button>

                    <form :action="`{{ route('fuelcontrol.vehiculos.destroy', '__id__') }}`.replace('__id__', deleteId)"
                        method="POST">
                        @csrf
                        @method('DELETE')

                        <button type="submit" class="px-4 py-2 rounded-lg text-sm
                           bg-red-600 text-white
                           hover:bg-red-700">
                            Sí, eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <div x-show="openEdit" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
            <div @click.outside="openEdit = false" x-transition
                class="bg-white dark:bg-gray-900 rounded-xl p-6 w-full max-w-lg shadow-2xl">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    Editar Vehículo
                </h2>

                <form method="POST"
                    :action="`{{ route('fuelcontrol.vehiculos.update', '__id__') }}`.replace('__id__', editVehiculo.id)"
                    class="space-y-4">
                    @csrf
                    @method('PUT')

                    {{-- PATENTE --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Patente
                        </label>
                        <input type="text" name="patente" x-model="editVehiculo.patente" required class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700
                           dark:bg-gray-800 dark:text-white
                           focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    {{-- DESCRIPCIÓN --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Descripción
                        </label>
                        <input type="text" name="descripcion" x-model="editVehiculo.descripcion" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700
                           dark:bg-gray-800 dark:text-white">
                    </div>

                    {{-- TIPO (estado: propio / arrendado / prestado) --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Tipo
                        </label>
                        <select name="tipo" x-model="editVehiculo.tipo" required class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700
                           dark:bg-gray-800 dark:text-white">
                            <option value="propio">Propio</option>
                            <option value="arrendado">Arrendado</option>
                            <option value="prestado">Prestado</option>
                        </select>
                    </div>

                    {{-- BOTONES --}}
                    <div class="flex justify-end gap-2 pt-4">
                        <button type="button" @click="openEdit = false" class="px-4 py-2 text-sm rounded-lg
                           bg-gray-100 dark:bg-gray-700
                           text-gray-700 dark:text-gray-300">
                            Cancelar
                        </button>

                        <button type="submit" class="px-4 py-2 text-sm rounded-lg
                           bg-blue-600 text-white
                           hover:bg-blue-700">
                            Guardar cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>


</x-app-layout>