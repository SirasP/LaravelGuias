<x-app-layout>

    {{-- ================= HEADER ================= --}}
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Usuarios registrados
                </p>
            </div>

            <button type="button" class="lg:hidden fixed bottom-6 right-6 z-[10000]
           h-14 w-14 rounded-full bg-blue-600 text-white text-3xl
           flex items-center justify-center shadow-lg
           hover:bg-blue-700 transition"
                @click="$store.ui.open = true; $store.ui.openView = false; $store.ui.selectedUser = null"
                aria-label="Agregar usuario">
                +
            </button>

        </div>
    </x-slot>

    {{-- ================= MODALES ================= --}}
    @include('users.partials.modals')

    {{-- ================= CONTENIDO ================= --}}
    <div class="py-6">
        <div class="max-w-full mx-auto px-4">

            {{-- GRID: TABLA + TARJETA DERECHA --}}
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                {{-- ================= TARJETA IZQUIERDA (TABLA) ================= --}}
                <div class="lg:col-span-10">
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-xl overflow-hidden">
                        <div class="p-6 text-gray-900 dark:text-gray-100">

                            @php($success = session()->pull('success'))
                            @if ($success && request()->isMethod('GET'))
                                <script>
                                    document.addEventListener('DOMContentLoaded', function () {
                                        Swal.fire({
                                            icon: 'success',
                                            title: '¡Listo!',
                                            text: @json($success),
                                            timer: 2000,
                                            showConfirmButton: false
                                        });
                                    });
                                </script>
                            @endif

                            <div x-data="{ users: @js($movimientos) }"
                                class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-900/60 text-gray-700 dark:text-gray-200">
                                        <tr class="[&>th]:px-4 [&>th]:py-3 [&>th]:font-semibold [&>th]:text-left">
                                            <th>ID</th>
                                            <th>Nombre</th>
                                            <th>Email</th>
                                            <th>Estado</th>
                                            <th class="text-right">Acciones</th>
                                        </tr>
                                    </thead>

                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        <template x-for="user in users" :key="user.id">

                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/40 transition">
                                                <td class="px-4 py-3">#<span x-text="user.id"></span></td>

                                                <td class="px-4 py-3 font-medium" x-text="user.name"></td>

                                                <td class="px-4 py-3">
                                                    <span
                                                        class="inline-flex rounded-md bg-gray-100 dark:bg-gray-700 px-2 py-1 text-xs"
                                                        x-text="user.email"></span>
                                                </td>

                                                <td class="px-4 py-3">
                                                    <span
                                                        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium"
                                                        :class="user.is_active
                                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                                            : 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300'"
                                                        x-text="user.is_active ? 'Activo' : 'Inactivo'">
                                                    </span>
                                                </td>

                                                <td class="px-4 py-3 text-left">
                                                    <div class="flex items-center gap-3">

                                                        {{-- VER --}}
                                                        <button type="button" @click="
                $store.ui.selectedUser = user;
                $store.ui.open = false;
                $store.ui.openView = true;
            " class="p-2 rounded-lg
                   text-gray-600 hover:text-blue-600
                   hover:bg-gray-100 dark:text-gray-300 dark:hover:text-blue-400 dark:hover:bg-gray-800
                   transition" title="Ver usuario">

                                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg"
                                                                fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                                                stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-7.5 9.75-7.5
                         9.75 7.5 9.75 7.5
                         -3.75 7.5 -9.75 7.5
                         -9.75 -7.5 -9.75 -7.5z" />
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15.75a3.75 3.75 0 100-7.5
                         3.75 3.75 0 000 7.5z" />
                                                            </svg>
                                                        </button>

                                                        {{-- ELIMINAR --}}
                                                        <form method="POST" :action="`/users/${user.id}`">
                                                            @csrf
                                                            @method('DELETE')

                                                            <button type="button" @click="
                    const form = $el.closest('form');
                    Swal.fire({
                        title: '¿Eliminar usuario?',
                        text: 'Esta acción no se puede deshacer',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) form.submit();
                    });
                " class="p-2 rounded-lg
                       text-red-600 hover:text-red-700
                       hover:bg-red-100 dark:hover:bg-red-900/30
                       transition" title="Eliminar usuario">

                                                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg"
                                                                    fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                                                    stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        d="M6 7h12M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m-7 0h6m-8 0v12a2 2 0 002 2h6a2 2 0 002-2V7" />
                                                                </svg>
                                                            </button>
                                                        </form>

                                                    </div>
                                                </td>


                                            </tr>
                                        </template>

                                        <tr x-show="users.length === 0">
                                            <td colspan="5" class="text-center py-12 text-gray-500">
                                                No hay usuarios para mostrar.
                                            </td>
                                        </tr>
                                    </tbody>

                                </table>
                            </div>

                        </div>
                    </div>
                </div>
                {{-- ================= TARJETA DERECHA (CREAR USUARIO) ================= --}}
                <div class="hidden lg:block lg:col-span-2">
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-xl lg:sticky lg:top-6">

                        <div class="p-6 text-gray-900 dark:text-gray-100">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h3 class="text-base font-semibold">Crear usuario</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        Completa los datos para registrar
                                    </p>
                                </div>
                            </div>

                            <form method="POST" action="{{ route('users.store') }}" class="space-y-4">
                                @csrf

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Nombre
                                    </label>
                                    <input name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                               dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" placeholder="Ej: Juan Pérez">
                                    @error('name')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Email
                                    </label>
                                    <input type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                               dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                                        placeholder="correo@ejemplo.com">
                                    @error('email')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Contraseña
                                    </label>
                                    <input type="password" name="password" required class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                               dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" placeholder="••••••••">
                                    @error('password')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                                    <button type="submit" class="w-full inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold
                               bg-blue-600 text-white hover:bg-blue-700 transition">
                                        Guardar
                                    </button>
                                </div>
                            </form>

                        </div>
                    </div>
                </div>


            </div>
        </div>

</x-app-layout>