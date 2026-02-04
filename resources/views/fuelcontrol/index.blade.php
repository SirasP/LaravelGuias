@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

    <!-- TÍTULO -->
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">
            ⛽ FuelControl
        </h1>
    </div>

    <!-- RESUMEN -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow p-5">
            <p class="text-sm text-gray-500">Productos</p>
            <p class="text-2xl font-bold">{{ $resumen['total_productos'] }}</p>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-xl shadow p-5">
            <p class="text-sm text-gray-500">Vehículos</p>
            <p class="text-2xl font-bold">{{ $resumen['total_vehiculos'] }}</p>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-xl shadow p-5">
            <p class="text-sm text-gray-500">Movimientos hoy</p>
            <p class="text-2xl font-bold">{{ $resumen['movimientos_hoy'] }}</p>
        </div>
    </div>

    <!-- STOCK ACTUAL -->
    <div class="bg-white dark:bg-gray-900 rounded-xl shadow overflow-hidden">
        <div class="px-6 py-4 border-b dark:border-gray-700 font-semibold">
            🛢️ Stock actual
        </div>

        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 dark:bg-gray-800">
                <tr>
                    <th class="px-6 py-3 text-left">Producto</th>
                    <th class="px-6 py-3 text-right">Cantidad</th>
                </tr>
            </thead>
            <tbody class="divide-y dark:divide-gray-700">
                @foreach ($productos as $p)
                    <tr>
                        <td class="px-6 py-3">{{ $p->nombre }}</td>
                        <td class="px-6 py-3 text-right font-mono">
                            {{ number_format($p->cantidad, 2) }} L
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- ÚLTIMOS MOVIMIENTOS -->
    <div class="bg-white dark:bg-gray-900 rounded-xl shadow">
        <div class="px-6 py-4 border-b dark:border-gray-700 font-semibold">
            🔄 Últimos movimientos
        </div>

        <div class="divide-y dark:divide-gray-700">
            @forelse ($movimientos as $m)
                <div class="px-6 py-3 flex justify-between text-sm">
                    <div>
                        <p class="font-medium">
                            Producto #{{ $m->producto_id }}
                        </p>
                        <p class="text-gray-500">
                            {{ $m->tipo }} — {{ $m->usuario }}
                        </p>
                    </div>
                    <div class="text-right font-mono">
                        {{ number_format($m->cantidad, 2) }} L
                    </div>
                </div>
            @empty
                <p class="px-6 py-4 text-gray-500">
                    No hay movimientos registrados.
                </p>
            @endforelse
        </div>
    </div>

</div>
@endsection
