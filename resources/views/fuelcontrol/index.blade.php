<x-app-layout>

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <!-- ICONO DASHBOARD -->
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 3v18m4-10v10m4-6v6M7 13v8M3 9v12" />
                    </svg>

                    Dashboard
                </h2>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Resumen general del sistema de control de combustible
                </p>
            </div>
        </div>
    </x-slot>



    {{-- CONTENIDO PRINCIPAL --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8 gap-3 ">


        <!-- TARJETAS DE RESUMEN -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-4">
            <!-- Productos -->
            <div
                class="bg-white dark:bg-gray-800 rounded-xl shadow-md hover:shadow-lg transition-shadow duration-300 overflow-hidden mb-4">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                            <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                        </div>
                    </div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                        Total Productos
                    </h3>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white">
                        {{ $resumen['total_productos'] }}
                    </p>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Tipos de combustible registrados
                    </p>
                </div>
            </div>

            <!-- Vehículos -->
            <div
                class="bg-white dark:bg-gray-800 rounded-xl shadow-md hover:shadow-lg transition-shadow duration-300 overflow-hidden mb-4">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg">
                            <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                            </svg>
                        </div>
                    </div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                        Flota de Vehículos
                    </h3>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white">
                        {{ $resumen['total_vehiculos'] }}
                    </p>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Vehículos activos en el sistema
                    </p>
                </div>
            </div>

            <!-- Movimientos -->
            <div
                class="bg-white dark:bg-gray-800 mb-4 rounded-xl shadow-md hover:shadow-lg transition-shadow duration-300 overflow-hidden">
                <div class="p-6 ">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                            <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </div>
                    </div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                        Movimientos Hoy
                    </h3>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white">
                        {{ $resumen['movimientos_hoy'] }}
                    </p>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Transacciones del día
                    </p>
                </div>
            </div>
        </div>

        <!-- STOCK ACTUAL -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md overflow-hidden mb-4">

            <div class="px-6 py-4  border-b border-gray-200 dark:border-gray-700 ">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white ">
                            Inventario Actual
                        </h2>
                    </div>
                    <span
                        class="px-3 py-1 text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-full">
                        {{ count($productos) }} productos
                    </span>
                </div>
            </div>

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
                                Nivel de Inventario
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Estado
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
                                            class="h-10 w-10 flex-shrink-0 {{ $bgColor }} rounded-lg flex items-center justify-center">
                                            <svg class="w-5 h-5 {{ $textColor }}" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                            </svg>
                                        </div>

                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $p->nombre }}
                                        </div>
                                    </div>

                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white font-mono">
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
                                        class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full {{ $bgColor }} {{ $textColor }}">
                                        {{ $estado }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center gap-2">
                                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                        </svg>
                                        <p class="text-gray-500 dark:text-gray-400 text-sm">
                                            No hay productos registrados
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ÚLTIMOS MOVIMIENTOS -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md overflow-hidden mb-4">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Últimos Movimientos
                        </h2>
                    </div>

                </div>
            </div>

            <div class="divide-y divide-gray-200 dark:divide-gray-700 mb-4">
                @forelse ($movimientos as $m)
                    <div class="px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4 flex-1">
                                <div class="flex-shrink-0">
                                    <div
                                        class="h-10 w-10 rounded-lg {{ $m->cantidad < 0 ? 'bg-red-100 dark:bg-red-900/30' : 'bg-green-100 dark:bg-green-900/30' }} flex items-center justify-center">
                                        @if($m->cantidad < 0)
                                            <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
                                            </svg>
                                        @else
                                            <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                            </svg>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                        {{ ucfirst($m->producto_nombre) ?? 'Producto #' . $m->producto_id }}
                                    </p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $m->tipo === 'ingreso' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' }}">
                                            {{ ucfirst($m->tipo) }}
                                        </span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            • {{ \Carbon\Carbon::parse($m->fecha_movimiento)->format('d/m/Y ') }}
                                        </span>

                                    </div>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p
                                    class="text-lg font-bold font-mono {{ $m->cantidad < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                    {{ $m->cantidad > 0 ? '+' : '' }}{{ number_format($m->cantidad, 2) }} L
                                </p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-12 mb-4">
                        <div class="flex flex-col items-center gap-2 ">
                            <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <p class="text-gray-500 dark:text-gray-400 text-sm ">
                                No hay movimientos registrados
                            </p>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- GASOLINA -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
                <h3 class="font-semibold mb-3">⛽ Gasolina — últimos 30 días</h3>
                <div class="relative h-48">
                    <canvas id="gasolinaChart"></canvas>
                </div>
            </div>

            <!-- DIESEL -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
                <h3 class="font-semibold mb-3">🛢️ Diésel — últimos 30 días</h3>
                <div class="relative h-48">
                    <canvas id="dieselChart"></canvas>
                </div>
            </div>

        </div>
    </div>

</x-app-layout>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {

        const gasCtx = document.getElementById('gasolinaChart');
        const dieCtx = document.getElementById('dieselChart');

        if (gasCtx) {
            new Chart(gasCtx, {
                type: 'bar',
                data: {
                    labels: @json($labelsGasolina),
                    datasets: [{
                        label: 'Gasolina (L)',
                        data: @json($dataGasolina),
                        backgroundColor: '#3b82f6',
                        borderRadius: 6
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }

        if (dieCtx) {
            new Chart(dieCtx, {
                type: 'bar',
                data: {
                    labels: @json($labelsDiesel),
                    datasets: [{
                        label: 'Diésel (L)',
                        data: @json($dataDiesel),
                        backgroundColor: '#16a34a',
                        borderRadius: 6
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }

    });
</script>