<section>
    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="space-y-5">
        @csrf
        @method('patch')

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="name" class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1.5">Nombre</label>
                <input id="name" name="name" type="text" required autofocus autocomplete="name"
                    value="{{ old('name', $user->name) }}"
                    class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50
                           text-sm text-gray-900 dark:text-gray-100
                           focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20
                           placeholder-gray-400 transition" />
                <x-input-error class="mt-1.5" :messages="$errors->get('name')" />
            </div>

            <div>
                <label for="email" class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1.5">Correo electrónico</label>
                <input id="email" name="email" type="email" required autocomplete="username"
                    value="{{ old('email', $user->email) }}"
                    class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50
                           text-sm text-gray-900 dark:text-gray-100
                           focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20
                           placeholder-gray-400 transition" />
                <x-input-error class="mt-1.5" :messages="$errors->get('email')" />

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                    <div class="mt-2">
                        <p class="text-xs text-amber-600 dark:text-amber-400">
                            Tu correo no está verificado.
                            <button form="send-verification" class="underline font-semibold hover:text-amber-700 dark:hover:text-amber-300 transition">
                                Reenviar verificación
                            </button>
                        </p>
                        @if (session('status') === 'verification-link-sent')
                            <p class="mt-1.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                Se ha enviado un nuevo enlace de verificación.
                            </p>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-3 pt-1">
            <button type="submit"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-xl
                       bg-indigo-600 hover:bg-indigo-700 active:scale-95
                       text-white transition shadow-sm shadow-indigo-200 dark:shadow-indigo-900/50">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Guardar cambios
            </button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)"
                    class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                    Guardado
                </p>
            @endif
        </div>
    </form>
</section>
