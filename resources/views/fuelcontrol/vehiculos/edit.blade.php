<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold">Editar Vehículo</h2>
    </x-slot>

    <div class="max-w-xl mx-auto p-6 bg-white rounded shadow">
        <form method="POST" action="{{ route('fuelcontrol.vehiculos.update', $vehiculo->id) }}">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <label class="block text-sm font-medium">Patente</label>
                <input name="patente" value="{{ $vehiculo->patente }}" class="w-full border rounded p-2" required>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium">Descripción</label>
                <input name="descripcion" value="{{ $vehiculo->descripcion }}" class="w-full border rounded p-2">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium">Tipo</label>
                <select name="tipo" class="w-full border rounded p-2">
                    <option value="diesel" @selected($vehiculo->tipo === 'diesel')>Diesel</option>
                    <option value="gasolina" @selected($vehiculo->tipo === 'gasolina')>Gasolina</option>
                </select>
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ route('fuelcontrol.vehiculos.index') }}" class="px-4 py-2 bg-gray-200 rounded">
                    Cancelar
                </a>
                <button class="px-4 py-2 bg-blue-600 text-white rounded">
                    Actualizar
                </button>
            </div>
        </form>
    </div>
</x-app-layout>