<x-app-layout>

   <x-slot name="header">
    <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white ">
            📦 Movimientos de Inventario
        </h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Historial completo de ingresos y salidas de productos
        </p>
    </div>
</x-slot>

<!-- Card de Filtros -->
<div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-200 dark:border-gray-700 overflow-hidden">
        
        <!-- Header del Card -->
        <div class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-800 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                            Filtros de Búsqueda
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Refina los resultados según tus necesidades
                        </p>
                    </div>
                </div>
                
                <!-- Botón limpiar filtros -->
                <button 
                    onclick="limpiarFiltros()"
                    id="btnLimpiarFiltros"
                    class="hidden items-center gap-2 px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-300 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors duration-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Limpiar filtros
                </button>
            </div>
        </div>

        <!-- Contenido del Card -->
        <div class="p-6">
            <form id="formFiltros" method="GET" action="{{ route('fuelcontrol.movimientos') }}">

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    
                    <!-- Filtro por Estado -->
                    <div class="space-y-2">
                        <label for="filtroEstado" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Estado
                            </div>
                        </label>
                        <select 
                            id="filtroEstado"
                            name="estado"
                            onchange="aplicarFiltros()"
                            class="w-full text-sm border-gray-300 dark:border-gray-600 
                                   dark:bg-gray-700 dark:text-white 
                                   rounded-lg shadow-sm
                                   focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                   transition-all duration-200
                                   cursor-pointer">
                            <option value="">📋 Todos los estados</option>
                            <option value="pendiente" {{ request('estado') == 'pendiente' ? 'selected' : '' }}>
                                ⏳ Pendiente
                            </option>
                            <option value="aprobado" {{ request('estado') == 'aprobado' ? 'selected' : '' }}>
                                ✅ Aprobado
                            </option>
                            <option value="rechazado" {{ request('estado') == 'rechazado' ? 'selected' : '' }}>
                                ❌ Rechazado
                            </option>
                        </select>
                    </div>

                    <!-- Filtro por Tipo -->
                    <div class="space-y-2">
                        <label for="filtroTipo" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                </svg>
                                Tipo de Movimiento
                            </div>
                        </label>
                        <select 
                            id="filtroTipo"
                            name="tipo"
                            onchange="aplicarFiltros()"
                            class="w-full text-sm border-gray-300 dark:border-gray-600 
                                   dark:bg-gray-700 dark:text-white 
                                   rounded-lg shadow-sm
                                   focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                   transition-all duration-200
                                   cursor-pointer">
                            <option value="">🔄 Todos los tipos</option>
                            <option value="entrada" {{ request('tipo') == 'entrada' ? 'selected' : '' }}>
                                ⬇️ Entrada
                            </option>
                            <option value="salida" {{ request('tipo') == 'salida' ? 'selected' : '' }}>
                                ⬆️ Salidas
                            </option>
                        </select>
                    </div>

                    <!-- Filtro por Producto -->
                    <div class="space-y-2">
                        <label for="filtroProducto" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                                Producto
                            </div>
                        </label>
                        <select 
                            id="filtroProducto"
                            name="producto_id"
                            onchange="aplicarFiltros()"
                            class="w-full text-sm border-gray-300 dark:border-gray-600 
                                   dark:bg-gray-700 dark:text-white 
                                   rounded-lg shadow-sm
                                   focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                   transition-all duration-200
                                   cursor-pointer">
                            <option value="">📦 Todos los productos</option>
                            @foreach($productos ?? [] as $producto)
                                <option value="{{ $producto->id }}" {{ request('producto_id') == $producto->id ? 'selected' : '' }}>
                                    {{ ucfirst($producto->nombre) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Filtro por Fecha -->
                    <div class="space-y-2">
                        <label for="filtroFecha" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                Rango de Fecha
                            </div>
                        </label>
                        <select 
                            id="filtroFecha"
                            name="fecha"
                            onchange="aplicarFiltros()"
                            class="w-full text-sm border-gray-300 dark:border-gray-600 
                                   dark:bg-gray-700 dark:text-white 
                                   rounded-lg shadow-sm
                                   focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                   transition-all duration-200
                                   cursor-pointer">
                            <option value="">📅 Todas las fechas</option>
                            <option value="hoy" {{ request('fecha') == 'hoy' ? 'selected' : '' }}>
                                Hoy
                            </option>
                            <option value="semana" {{ request('fecha') == 'semana' ? 'selected' : '' }}>
                                Esta semana
                            </option>
                            <option value="mes" {{ request('fecha') == 'mes' ? 'selected' : '' }}>
                                Este mes
                            </option>
                            <option value="trimestre" {{ request('fecha') == 'trimestre' ? 'selected' : '' }}>
                                Este trimestre
                            </option>
                        </select>
                    </div>

                </div>

                <!-- Indicador de filtros activos -->
                <div id="filtrosActivos" class="hidden mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                            <span class="font-medium">Filtros activos:</span>
                            <span id="contadorFiltros" class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded-full text-xs font-bold">
                                0
                            </span>
                        </div>
                        <div id="tagsFiltros" class="flex flex-wrap gap-2">
                            <!-- Tags de filtros activos se generan aquí -->
                        </div>
                    </div>
                </div>

            </form>
        </div>

    </div>

</div>

<script>
    function aplicarFiltros() {
        document.getElementById('formFiltros').submit();
    }

    function limpiarFiltros() {
        // Resetear todos los selects
        document.getElementById('filtroEstado').value = '';
        document.getElementById('filtroTipo').value = '';
        document.getElementById('filtroProducto').value = '';
        document.getElementById('filtroFecha').value = '';
        
        // Enviar formulario limpio
        aplicarFiltros();
    }

    function actualizarIndicadorFiltros() {
        const estado = document.getElementById('filtroEstado').value;
        const tipo = document.getElementById('filtroTipo').value;
        const producto = document.getElementById('filtroProducto').value;
        const fecha = document.getElementById('filtroFecha').value;
        
        const filtrosActivos = [estado, tipo, producto, fecha].filter(f => f !== '');
        const count = filtrosActivos.length;
        
        const contenedor = document.getElementById('filtrosActivos');
        const btnLimpiar = document.getElementById('btnLimpiarFiltros');
        const contador = document.getElementById('contadorFiltros');
        const tagsContainer = document.getElementById('tagsFiltros');
        
        if (count > 0) {
            contenedor.classList.remove('hidden');
            btnLimpiar.classList.remove('hidden');
            btnLimpiar.classList.add('flex');
            contador.textContent = count;
            
            // Generar tags
            tagsContainer.innerHTML = '';
            
            if (estado) {
                const estadoTexto = document.querySelector(`#filtroEstado option[value="${estado}"]`).textContent;
                tagsContainer.innerHTML += `
                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300 rounded-lg text-xs font-medium">
                        ${estadoTexto}
                    </span>
                `;
            }
            
            if (tipo) {
                const tipoTexto = document.querySelector(`#filtroTipo option[value="${tipo}"]`).textContent;
                tagsContainer.innerHTML += `
                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded-lg text-xs font-medium">
                        ${tipoTexto}
                    </span>
                `;
            }
            
            if (producto) {
                const productoTexto = document.querySelector(`#filtroProducto option[value="${producto}"]`).textContent;
                tagsContainer.innerHTML += `
                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 rounded-lg text-xs font-medium">
                        ${productoTexto}
                    </span>
                `;
            }
            
            if (fecha) {
                const fechaTexto = document.querySelector(`#filtroFecha option[value="${fecha}"]`).textContent;
                tagsContainer.innerHTML += `
                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded-lg text-xs font-medium">
                        ${fechaTexto}
                    </span>
                `;
            }
        } else {
            contenedor.classList.add('hidden');
            btnLimpiar.classList.add('hidden');
            btnLimpiar.classList.remove('flex');
        }
    }

    // Ejecutar al cargar la página
    document.addEventListener('DOMContentLoaded', actualizarIndicadorFiltros);
</script>

    <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

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
                                               onclick="abrirMovimiento('{{ route('fuelcontrol.xml.show', $m->id) }}')"


                                                class="group relative inline-flex items-center gap-2 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-black text-xs font-semibold px-4 py-2 rounded-lg transition-all duration-200 shadow-sm hover:shadow-md">
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
<script>
window.abrirMovimiento = async function (url) {
    try {
        const response = await fetch(url);
        const html = await response.text();

        await Swal.fire({
            width: '85%',
            showCloseButton: true,
            showConfirmButton: false,
            html: html,
            background: '#f9fafb'
        });

    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No se pudo cargar el XML'
        });
    }
};
</script>
<script>
    window.switchTab = function (tab) {

        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('hidden');
        });

        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active', 'border-blue-500', 'text-blue-600');
            btn.classList.add('border-transparent', 'text-gray-500');
        });

        const content = document.getElementById('content-' + tab);
        if (content) content.classList.remove('hidden');

        const activeTab = document.getElementById('tab-' + tab);
        if (activeTab) {
            activeTab.classList.add('active', 'border-blue-500', 'text-blue-600');
            activeTab.classList.remove('border-transparent', 'text-gray-500');
        }
    };
</script>