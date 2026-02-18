<x-app-layout>

    {{-- ═══════════════════════════════════════════════════
    HEADER
    ═══════════════════════════════════════════════════ --}}
    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-4">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-indigo-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Mi Perfil</h2>
                    <p class="text-xs text-gray-400 mt-0.5 hidden sm:block">Configuración de cuenta</p>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 space-y-5">

        {{-- ── Información del perfil ──────────────────────── --}}
        <div class="bg-white dark:bg-[#161c2c] border border-gray-200 dark:border-[#1e2a3b] rounded-2xl overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-[#1e2a3b] flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-indigo-500/20 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Información del Perfil</h3>
                    <p class="text-[11px] text-gray-400">Actualiza tu nombre y correo electrónico</p>
                </div>
            </div>
            <div class="p-5">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        {{-- ── Contraseña ──────────────────────────────────── --}}
        <div class="bg-white dark:bg-[#161c2c] border border-gray-200 dark:border-[#1e2a3b] rounded-2xl overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-[#1e2a3b] flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-amber-500/20 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Cambiar Contraseña</h3>
                    <p class="text-[11px] text-gray-400">Usa una contraseña segura y única</p>
                </div>
            </div>
            <div class="p-5">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        {{-- ── Eliminar cuenta ─────────────────────────────── --}}
        <div class="bg-white dark:bg-[#161c2c] border border-red-200 dark:border-red-900/40 rounded-2xl overflow-hidden">
            <div class="px-5 py-4 border-b border-red-100 dark:border-red-900/30 flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-red-500/20 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-red-700 dark:text-red-400">Zona de Peligro</h3>
                    <p class="text-[11px] text-gray-400">Acciones irreversibles sobre tu cuenta</p>
                </div>
            </div>
            <div class="p-5">
                @include('profile.partials.delete-user-form')
            </div>
        </div>

    </div>

</x-app-layout>
