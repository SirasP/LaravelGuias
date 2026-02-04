<x-app-layout>

    {{-- HEADER --}}
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h2 class="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-gray-100">
                    ⛽ FuelControl
                </h2>
                <p class="text-sm text-gray-500">
                    Control y trazabilidad de combustible
                </p>
            </div>

            <span class="inline-flex items-center gap-2 text-xs px-3 py-1 rounded-full
                bg-green-100 text-green-700
                dark:bg-green-900/30 dark:text-green-300">
                ● Sistema activo
            </span>
        </div>
    </x-slot>

    {{-- CONTENIDO --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

        {{-- =========================
        RESUMEN
        ========================= --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">

            {{-- Productos --}}
            <div class="rounded-2xl bg-gradient-to-br from-blue-500 to-blue-600 text-white shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <span class="text-sm opacity-90">Productos</span>
                    <span class="text-3xl">🛢️</span>
                </div>
                <p class="mt-4 text-4xl font-extrabold">
                    {{ $resumen['total_productos'] }}
                </p>
            </div>

            {{-- Vehículos --}}
            <div class="rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-600 text-white shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <span class="text-sm opacity-90">Vehículos</span>
                    <span class="text-3xl">🚗</span>
                </div>
                <p class="mt-4 text-4xl font-extrabold">
                    {{ $resumen['total_vehiculos'] }}
                </p>
            </div>

            {{-- Movimientos --}}
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

        {{-- =========================
        STOCK ACTUAL
        ========================= --}}
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow overflow-hidden">

            <div class="px-6 py-4 border-b dark:border-gray-700 flex items-center gap-2">
                <span class="text-xl">🛢️</span>
                <h3 class="font-semibold text-gray-800 dark:text-gray-100">
                    Stock actual
                </h3>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-6 py-3 text-left font-semibold">Producto</th>
                            <th class="px-6 py-3 text-right font-semibold">Cantidad</th>
                            <th class="px-6 py-3 text-center font-semibold">Nivel</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-700">
                        @foreach ($productos as $p)
                            @php
                                // indicador visual simple
                                $porcentaje = min(100, max(5, $p->cantidad));
                                $color = $porcentaje < 20
                                    ? 'bg-red-500'
                                    : ($porcentaje < 50 ? 'bg-yellow-400' : 'bg-green-500');
                            @endphp

                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                                <td class="px-6 py-4 font-medium">
                                    {{ $p->nombre }}
                                </td>

                                <td class="px-6 py-4 text-right font-mono">
                                    {{ number_format($p->cantidad, 2) }} L
                                </td>

                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3 justify-center">
                                        <div class="w-32 h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                            <div class="{{ $color }} h-2" style="width: {{ $porcentaje }}%"></div>
                                        </div>
                                        <span class="text-xs text-gray-500 w-10 text-right">
                                            {{ $porcentaje }}%
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- =========================
        ÚLTIMOS MOVIMIENTOS
        ========================= --}}
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow">

            <div class="px-6 py-4 border-b dark:border-gray-700 flex items-center gap-2">
                <span class="text-xl">🔄</span>
                <h3 class="font-semibold text-gray-800 dark:text-gray-100">
                    Últimos movimientos
                </h3>
            </div>

            <div class="divide-y dark:divide-gray-700">
                @forelse ($movimientos as $m)
                    <div class="px-6 py-4 flex items-center justify-between">
                        <div>
                            <p class="font-medium">
                                Producto #{{ $m->producto_id }}
                            </p>
                            <p class="text-xs text-gray-500">
                                {{ ucfirst($m->tipo) }} · {{ $m->usuario }}
                            </p>
                        </div>

                        <span class="font-mono font-semibold
                                {{ $m->cantidad < 0 ? 'text-red-600' : 'text-green-600' }}">
                            {{ number_format($m->cantidad, 2) }} L
                        </span>
                    </div>
                @empty
                    <p class="px-6 py-6 text-center text-gray-500">
                        No hay movimientos registrados.
                    </p>
                @endforelse
            </div>
        </div>

    </div>

</x-app-layout>