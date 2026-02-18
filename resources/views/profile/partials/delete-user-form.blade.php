<section>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
        Una vez eliminada tu cuenta, todos los recursos y datos serán eliminados permanentemente. Descarga cualquier información que desees conservar antes de continuar.
    </p>

    <button type="button"
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
        class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-xl
               bg-red-600 hover:bg-red-700 active:scale-95
               text-white transition shadow-sm shadow-red-200 dark:shadow-red-900/50">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
        </svg>
        Eliminar cuenta
    </button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-xl bg-red-500/20 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Confirmar eliminación</h3>
                    <p class="text-[11px] text-gray-400">Esta acción no se puede deshacer</p>
                </div>
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                Ingresa tu contraseña para confirmar que deseas eliminar permanentemente tu cuenta.
            </p>

            <div class="mb-5">
                <label for="password" class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1.5">Contraseña</label>
                <input id="password" name="password" type="password"
                    class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50
                           text-sm text-gray-900 dark:text-gray-100
                           focus:border-red-400 focus:ring-2 focus:ring-red-500/20
                           placeholder-gray-400 transition"
                    placeholder="Tu contraseña actual" />
                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-1.5" />
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" x-on:click="$dispatch('close')"
                    class="px-4 py-2 text-xs font-semibold rounded-xl
                           text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-800
                           hover:bg-gray-200 dark:hover:bg-gray-700 transition">
                    Cancelar
                </button>
                <button type="submit"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-xl
                           bg-red-600 hover:bg-red-700 active:scale-95
                           text-white transition shadow-sm shadow-red-200 dark:shadow-red-900/50">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    Eliminar cuenta
                </button>
            </div>
        </form>
    </x-modal>
</section>
