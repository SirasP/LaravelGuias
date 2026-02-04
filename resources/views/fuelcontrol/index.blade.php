<x-app-layout>

    {{-- HEADER SUPERIOR --}}
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-6">
            <div>
                <h1 class="text-3xl font-extrabold tracking-tight text-gray-900 dark:text-gray-100">
                    ⛽ FuelControl
                </h1>
                <p class="text-sm text-gray-500">
                    Control de combustible en tiempo real
                </p>
            </div>

            <span class="inline-flex items-center gap-2 text-xs px-3 py-1 rounded-full
        bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">
                ● Sistema activo
            </span>
        </div>

    </x-slot>

    {{-- CONTENIDO PRINCIPAL --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

        <!-- HERO / TÍTULO GRANDE -->
        <div class="flex items-center justify-between">
            <h1 class="text-3xl font-extrabold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                FuelControl
            </h1>
            <span class="text-sm text-gray-500">
                Control de combustible
            </span>
        </div>

        <!-- RESUMEN -->
        <div class="flex flex-col sm:flex-row gap-4">

            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow p-5 flex items-center gap-4">
                <div class="text-3xl">🛢️</div>
                <div>
                    <p class="text-sm text-gray-500">Productos</p>
                    <p class="text-2xl font-bold">{{ $resumen['total_productos'] }}</p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow p-5 flex items-center gap-4">
                <div class="text-3xl">🚗</div>
                <div>
                    <p class="text-sm text-gray-500">Vehículos</p>
                    <p class="text-2xl font-bold">{{ $resumen['total_vehiculos'] }}</p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow p-5 flex items-center gap-4">
                <div class="text-3xl">🔄</div>
                <div>
                    <p class="text-sm text-gray-500">Movimientos hoy</p>
                    <p class="text-2xl font-bold">{{ $resumen['movimientos_hoy'] }}</p>
                </div>
            </div>
        </div>

        <!-- STOCK -->
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow overflow-hidden">
            <div class="px-6 py-4 gap-4 border-b dark:border-gray-700 font-semibold ">
                🛢️ Stock actual
            </div>

            <div class="overflow-x-auto gap-4">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-800">
                        <tr>
                            <th class="px-6 py-3 text-left">Producto</th>
                            <th class="px-6 py-3 text-right">Cantidad</th>
                            <th class="px-6 py-3 text-center">Nivel</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-700">
                        @foreach ($productos as $p)
                            @php
                                $porcentaje = min(100, max(5, $p->cantidad));
                                $color = $porcentaje < 20
                                    ? 'bg-red-500'
                                    : ($porcentaje < 50 ? 'bg-yellow-400' : 'bg-green-500');
                            @endphp
                            <tr>
                                <td class="px-6 py-3 font-medium">{{ $p->nombre }}</td>
                                <td class="px-6 py-3 text-right font-mono">
                                    {{ number_format($p->cantidad, 2) }} L
                                </td>
                                <td class="px-6 py-3">
                                    <div class="w-32 h-2 rounded-full bg-gray-200 dark:bg-gray-700 mx-auto overflow-hidden">
                                        <div class="{{ $color }} h-2" style="width: {{ $porcentaje }}%"></div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Movimientos -->
        <div class="rounded-2xl bg-gradient-to-br from-orange-500 to-orange-600 text-white shadow-lg p-6">
            <div class="flex items-center justify-between">
                <span class="text-sm opacity-90">Movimientos hoy</span>
                <span class="text-3xl">🔄</span>
            </div>
            <p class="mt-4 text-4xl font-extrabold">
                {{ $resumen['movimientos_hoy'] }}
            </p>
        </div>

    </div>

</x-app-layout>