<section>
    <form method="post" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        @method('put')

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label for="update_password_current_password" class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1.5">Contraseña actual</label>
                <input id="update_password_current_password" name="current_password" type="password" autocomplete="current-password"
                    class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50
                           text-sm text-gray-900 dark:text-gray-100
                           focus:border-amber-400 focus:ring-2 focus:ring-amber-500/20
                           placeholder-gray-400 transition"
                    placeholder="********" />
                <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-1.5" />
            </div>

            <div>
                <label for="update_password_password" class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1.5">Nueva contraseña</label>
                <input id="update_password_password" name="password" type="password" autocomplete="new-password"
                    class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50
                           text-sm text-gray-900 dark:text-gray-100
                           focus:border-amber-400 focus:ring-2 focus:ring-amber-500/20
                           placeholder-gray-400 transition"
                    placeholder="********" />
                <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-1.5" />
            </div>

            <div>
                <label for="update_password_password_confirmation" class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1.5">Confirmar contraseña</label>
                <input id="update_password_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                    class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50
                           text-sm text-gray-900 dark:text-gray-100
                           focus:border-amber-400 focus:ring-2 focus:ring-amber-500/20
                           placeholder-gray-400 transition"
                    placeholder="********" />
                <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-1.5" />
            </div>
        </div>

        <div class="flex items-center gap-3 pt-1">
            <button type="submit"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-xl
                       bg-amber-600 hover:bg-amber-700 active:scale-95
                       text-white transition shadow-sm shadow-amber-200 dark:shadow-amber-900/50">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                Actualizar contraseña
            </button>

            @if (session('status') === 'password-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)"
                    class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                    Contraseña actualizada
                </p>
            @endif
        </div>
    </form>
</section>
