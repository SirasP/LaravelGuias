<x-app-layout>

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                    📦 Movimientos de Inventario
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Historial completo de ingresos y salidas de productos
                </p>
            </div>
            
            <!-- Filtros rápidos (opcional) -->
            <div class="flex gap-2">
                <select class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos los estados</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="aprobado">Aprobado</option>
                    <option value="rechazado">Rechazado</option>
                </select>
                
                <select class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos los tipos</option>
                    <option value="ingreso">Ingresos</option>
                    <option value="salida">Salidas</option>
                </select>
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <!-- Total Movimientos -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $movimientos->total() }}</p>
                    </div>
                    <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Pendientes -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Pendientes</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $movimientos->where('estado', 'pendiente')->count() }}</p>
                    </div>
                    <div class="p-3 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg">
                        <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Aprobados -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Aprobados</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $movimientos->where('estado', 'aprobado')->count() }}</p>
                    </div>
                    <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Rechazados -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 border-l-4 border-red-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Rechazados</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $movimientos->where('estado', 'rechazado')->count() }}</p>
                    </div>
                    <div class="p-3 bg-red-100 dark:bg-red-900/30 rounded-lg">
                        <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de Movimientos -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">

                    <thead class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-800">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                Producto
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                Tipo
                            </th>
                            <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                Cantidad
                            </th>
                            <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                Estado
                            </th>
                            <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                Fecha
                            </th>
                            <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                Usuario
                            </th>
                            <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                Acciones
                            </th>
                        </tr>
                    </thead>

                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">

                        @forelse ($movimientos as $m)

                            @php
                                $estadoConfig = match ($m->estado) {
                                    'pendiente' => [
                                        'bg' => 'bg-yellow-100 dark:bg-yellow-900/30',
                                        'text' => 'text-yellow-800 dark:text-yellow-300',
                                        'icon' => '⏳'
                                    ],
                                    'aprobado' => [
                                        'bg' => 'bg-green-100 dark:bg-green-900/30',
                                        'text' => 'text-green-800 dark:text-green-300',
                                        'icon' => '✓'
                                    ],
                                    'rechazado' => [
                                        'bg' => 'bg-red-100 dark:bg-red-900/30',
                                        'text' => 'text-red-800 dark:text-red-300',
                                        'icon' => '✗'
                                    ],
                                    default => [
                                        'bg' => 'bg-gray-100 dark:bg-gray-700',
                                        'text' => 'text-gray-800 dark:text-gray-300',
                                        'icon' => '•'
                                    ]
                                };
                            @endphp

                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">

                                <!-- Producto -->
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex-shrink-0 w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center">
                                            <span class="text-red font-bold text-lg">
                                                {{ strtoupper(substr($m->producto_nombre ?? 'N', 0, 1)) }}
                                            </span>
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                                {{ ucfirst($m->producto_nombre ?? 'N/A') }}
                                            </p>
                                            @if($m->producto->codigo ?? false)
                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    Código: {{ $m->producto->codigo }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                <!-- Tipo -->
                                <td class="px-6 py-4">
                                   @if(strtolower(trim($m->tipo)) == 'entrada')

                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z" clip-rule="evenodd"/>
                                            </svg>
                                            Ingreso
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586l-1.293-1.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z" clip-rule="evenodd" transform="rotate(180 10 10)"/>
                                            </svg>
                                            Salida
                                        </span>
                                    @endif
                                </td>

                                <!-- Cantidad -->
                                <td class="px-6 py-4 text-right">
                                    <span class="font-mono text-lg font-bold {{ $m->tipo === 'ingreso' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ $m->tipo === 'ingreso' ? '+' : '-' }}{{ number_format(abs($m->cantidad), 2) }}
                                    </span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">L</span>
                                </td>

                                <!-- Estado -->
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold {{ $estadoConfig['bg'] }} {{ $estadoConfig['text'] }}">
                                        <span>{{ $estadoConfig['icon'] }}</span>
                                        {{ ucfirst($m->estado) }}
                                    </span>
                                </td>

                                <!-- Fecha -->
                                <td class="px-6 py-4 text-center">
                                    <div class="text-sm text-gray-900 dark:text-white font-medium">
                                        {{ \Carbon\Carbon::parse($m->fecha_movimiento)->format('d/m/Y') }}

                                    </div>
                                 
                                </td>

                                <!-- Usuario -->
                                 @php
    $nombreUsuario = $m->usuario;

    if(in_array($nombreUsuario, ['gmail', 'gmail_historico'])) {
        $nombreUsuario = 'Carga Automática';
    }
@endphp
                                <td class="px-6 py-4 text-center">
                                    @if($m->usuario ?? false)
                                        <div class="flex items-center justify-center gap-2">
                                            <div class="w-8 h-8 bg-gradient-to-br from-purple-500 to-purple-600 rounded-full flex items-center justify-center">
                                                <span class="text-black text-xs font-bold">
                                                    {{ strtoupper(substr($m->usuario, 0, 1)) }}
                                                </span>
                                            </div>
                                            <div class="text-left">
                                                <p class="text-xs font-medium text-gray-900 dark:text-white">
                                                    {{ $nombreUsuario }}
                                                </p>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
                                    @endif
                                </td>

                                <!-- Acciones -->
                                <td class="px-6 py-4 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        @if($m->xml_path)
                                            <button 
                                                onclick="abrirMovimiento({{ $m->id }})"
                                                class="group relative inline-flex items-center gap-2 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white text-xs font-semibold px-4 py-2 rounded-lg transition-all duration-200 shadow-sm hover:shadow-md">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                                Ver XML
                                            </button>
                                            
                                            @if($m->requiere_revision)
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300" title="Requiere revisión">
                                                    ⚠
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-xs text-gray-400 dark:text-gray-500">Sin XML</span>
                                        @endif
                                    </div>
                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td colspan="7" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="w-20 h-20 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mb-4">
                                            <svg class="w-10 h-10 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                            </svg>
                                        </div>
                                        <p class="text-gray-500 dark:text-gray-400 font-medium text-lg">
                                            No hay movimientos registrados
                                        </p>
                                        <p class="text-gray-400 dark:text-gray-500 text-sm mt-1">
                                            Los movimientos aparecerán aquí una vez que se registren
                                        </p>
                                    </div>
                                </td>
                            </tr>

                        @endforelse

                    </tbody>
                </table>
            </div>

            <!-- Paginación mejorada -->
            @if($movimientos->hasPages())
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700">
                    
                        <div>
                            {{ $movimientos->links() }}
                        </div>
                    </div>
                </div>
            @endif

        </div>

    </div>

</x-app-layout>