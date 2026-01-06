<x-app-layout>
    <x-slot name="header">
        <div class="text-sm text-gray-600 dark:text-gray-400">
            Registrar camión
        </div>
    </x-slot>

    <div class="py-6 max-w-xl mx-auto">
        <form method="POST" action="{{ route('agrak.store') }}"
            class="space-y-6 bg-white dark:bg-gray-900 p-6 rounded-xl border border-gray-200 dark:border-gray-800">
            @csrf

            {{-- ===============================
                 Valor detectado
            =============================== --}}
            <div>
                <input type="hidden" name="patente_detectada" value="{{ $patente }}">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Valor detectado en el archivo
                </label>

                <input value="{{ $patente }}" readonly
                       class="mt-1 w-full rounded-lg border px-3 py-2 bg-gray-100 dark:bg-gray-800
                              text-gray-600 dark:text-gray-400">

                <p class="mt-1 text-xs text-gray-500">
                    Este valor venía en el Excel y puede no ser una patente (sello, bin, error humano, etc.).
                </p>
            </div>

            {{-- ===============================
                 Seleccionar patente existente
            =============================== --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Patente correcta (existente)
                </label>

                <select name="patente_norm"
                        class="mt-1 w-full rounded-lg border px-3 py-2 bg-white dark:bg-gray-900">
                    <option value="">— Seleccionar patente existente —</option>

                    @foreach($camiones as $camion)
                        <option value="{{ $camion->patente_norm }}">
                            {{ $camion->patente_norm }}
                            @if($camion->alias)
                                — {{ $camion->alias }}
                            @endif
                        </option>
                    @endforeach
                </select>

                <p class="mt-1 text-xs text-gray-500">
                    Usa esta opción si el camión ya existe en el sistema.
                </p>
            </div>

            {{-- ===============================
                 Crear patente nueva
            =============================== --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Nueva patente (si no existe)
                </label>

                <input name="patente_nueva"
                       placeholder="Ej: JJ7382"
                       class="mt-1 w-full rounded-lg border px-3 py-2 uppercase">

                <p class="mt-1 text-xs text-gray-500">
                    Solo completa este campo si la patente correcta aún no está registrada.
                </p>
            </div>

            <hr class="border-gray-200 dark:border-gray-800">

            {{-- ===============================
                 Datos opcionales del camión
            =============================== --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Alias (opcional)
                </label>

                <input name="alias"
                       placeholder="Ej: Camión Sergio / Camión rojo / Vitafoods 1"
                       class="mt-1 w-full rounded-lg border px-3 py-2">

                <p class="mt-1 text-xs text-gray-500">
                    Nombre interno para identificar fácilmente el camión.
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Observaciones
                </label>

                <textarea name="observaciones" rows="3"
                          class="mt-1 w-full rounded-lg border px-3 py-2"></textarea>
            </div>

            {{-- ===============================
                 Acción
            =============================== --}}
            <div class="flex justify-end">
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 rounded-lg
                               bg-indigo-600 hover:bg-indigo-700
                                text-sm font-medium">
                    Guardar camión
                </button>
            </div>

        </form>
    </div>
</x-app-layout>
