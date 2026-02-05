<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold">Detalle Vehículo</h2>
    </x-slot>

    <div class="max-w-xl mx-auto p-6 bg-white rounded shadow space-y-2">
        <p><strong>Patente:</strong> {{ $vehiculo->patente }}</p>
        <p><strong>Descripción:</strong> {{ $vehiculo->descripcion }}</p>
        <p><strong>Tipo:</strong> {{ ucfirst($vehiculo->tipo) }}</p>
        <p><strong>Usuario:</strong> {{ $vehiculo->usuario }}</p>
        <p><strong>Fecha:</strong> {{ $vehiculo->fecha_registro }}</p>

        <a href="{{ route('fuelcontrol.vehiculos.index') }}"
            class="inline-block mt-4 px-4 py-2 bg-blue-600 text-white rounded">
            Volver
        </a>
    </div>
</x-app-layout>