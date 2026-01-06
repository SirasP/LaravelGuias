<x-app-layout>
    <div class="min-h-screen flex items-center justify-center px-6">

        <div class="max-w-md w-full">
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-8 shadow text-center">

                {{-- Icono --}}
                <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full
                            bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400">
                     403  || Permiso denegado
                </div>

                {{-- Título --}}
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Acceso denegado
                </h1>

                {{-- Mensaje --}}
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                    Tu usuario no tiene permisos para acceder a esta sección.
                </p>



            </div>
        </div>

    </div>
</x-app-layout>