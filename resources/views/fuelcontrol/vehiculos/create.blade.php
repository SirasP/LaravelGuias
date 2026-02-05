<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold">Nuevo Vehículo</h2>
    </x-slot>

    <div class="max-w-xl mx-auto p-6 bg-white rounded shadow">
        <form method="POST" action="{{ route('fuelcontrol.vehiculos.store') }}">
            @csrf

            <div class="mb-4">
                <label class="block text-sm font-medium">Patente</label>
                <input name="patente" class="w-full border rounded p-2" required>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium">Descripción</label>
                <input name="descripcion" class="w-full border rounded p-2">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium">Tipo</label>
                <select name="tipo" class="w-full border rounded p-2" required>
                    <option value="diesel">Diesel</option>
                    <option value="gasolina">Gasolina</option>
                </select>
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ route('fuelcontrol.vehiculos.index') }}" class="px-4 py-2 bg-gray-200 rounded">
                    Cancelar
                </a>
                <button class="px-4 py-2 bg-blue-600 text-white rounded">
                    Guardar
                </button>
            </div>
        </form>
    </div>
</x-app-layout>