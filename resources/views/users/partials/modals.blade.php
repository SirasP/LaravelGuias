{{-- ══════════════════════════════════════════════════════
MODAL AGREGAR USUARIO
Condición: $store.ui.open === true
══════════════════════════════════════════════════════ --}}
<div x-cloak x-show="$store.ui.open" class="fixed inset-0 z-50 flex items-center justify-center p-4"
    @keydown.escape.window="$store.ui.open = false" aria-modal="true" role="dialog">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/60 backdrop-blur-[2px]" @click="$store.ui.open = false"></div>

    {{-- Panel --}}
    <div x-show="$store.ui.open" x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-2 scale-95" class="relative w-full max-w-lg overflow-hidden rounded-2xl
                bg-white shadow-2xl ring-1 ring-black/5
                dark:bg-gray-900 dark:ring-white/10">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4
                    border-b border-gray-200 dark:border-gray-800">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-900/30
                            flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Agregar usuario</h3>
                    <p class="text-xs text-gray-400">Completa los datos para registrar</p>
                </div>
            </div>
            <button type="button" @click="$store.ui.open = false" class="w-8 h-8 rounded-lg flex items-center justify-center
                           text-gray-400 hover:bg-gray-100 hover:text-gray-700
                           dark:hover:bg-gray-800 dark:hover:text-gray-200 transition" aria-label="Cerrar">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Body --}}
        <div class="px-6 py-5">
            <form method="POST" action="{{ route('users.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label
                        class="block text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1.5">
                        Nombre
                    </label>
                    <input name="name" value="{{ old('name') }}" required
                        class="w-full rounded-xl border border-gray-200 dark:border-gray-700
                                  bg-gray-50 dark:bg-gray-950 px-3 py-2 text-sm text-gray-900 dark:text-gray-100
                                  focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition" placeholder="Ej: Juan Pérez">
                    @error('name')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label
                        class="block text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1.5">
                        Email
                    </label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                        class="w-full rounded-xl border border-gray-200 dark:border-gray-700
                                  bg-gray-50 dark:bg-gray-950 px-3 py-2 text-sm text-gray-900 dark:text-gray-100
                                  focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition" placeholder="correo@empresa.cl">
                    @error('email')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label
                        class="block text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1.5">
                        Contraseña
                    </label>
                    <input type="password" name="password" required
                        class="w-full rounded-xl border border-gray-200 dark:border-gray-700
                                  bg-gray-50 dark:bg-gray-950 px-3 py-2 text-sm text-gray-900 dark:text-gray-100
                                  focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition" placeholder="••••••••">
                    @error('password')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-2 pt-2
                            border-t border-gray-100 dark:border-gray-800">
                    <button type="button" @click="$store.ui.open = false" class="px-4 py-2 rounded-xl text-sm font-semibold
                                   bg-gray-100 text-gray-700 hover:bg-gray-200
                                   dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 transition">
                        Cancelar
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl text-sm font-semibold
                                   bg-indigo-600 text-white hover:bg-indigo-700 transition active:scale-95">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


{{-- ══════════════════════════════════════════════════════
MODAL VER USUARIO
Condición: $store.ui.openView === true
══════════════════════════════════════════════════════ --}}
<div x-cloak x-show="$store.ui.openView" class="fixed inset-0 z-50 flex items-center justify-center p-4"
    @keydown.escape.window="$store.ui.openView = false; $store.ui.selectedUser = null" aria-modal="true" role="dialog">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/60 backdrop-blur-[2px]"
        @click="$store.ui.openView = false; $store.ui.selectedUser = null"></div>

    {{-- Panel --}}
    <div x-show="$store.ui.openView" x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-2 scale-95" class="relative w-full max-w-md overflow-hidden rounded-2xl
                bg-white shadow-2xl ring-1 ring-black/5
                dark:bg-gray-900 dark:ring-white/10">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4
                    border-b border-gray-200 dark:border-gray-800">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-900/30
                            flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Detalle del usuario</h3>
                    <p class="text-xs text-gray-400">Información registrada</p>
                </div>
            </div>
            <button type="button" @click="$store.ui.openView = false; $store.ui.selectedUser = null" class="w-8 h-8 rounded-lg flex items-center justify-center
                           text-gray-400 hover:bg-gray-100 hover:text-gray-700
                           dark:hover:bg-gray-800 dark:hover:text-gray-200 transition" aria-label="Cerrar">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Body --}}
        <div class="px-6 py-5">

            {{-- Skeleton si selectedUser aún es null --}}
            <template x-if="!$store.ui.selectedUser">
                <div class="space-y-3 animate-pulse">
                    <div class="h-3 w-24 rounded-full bg-gray-200 dark:bg-gray-800"></div>
                    <div class="h-3 w-full rounded-full bg-gray-200 dark:bg-gray-800"></div>
                    <div class="h-3 w-2/3 rounded-full bg-gray-200 dark:bg-gray-800"></div>
                </div>
            </template>

            {{-- Contenido real --}}
            <template x-if="$store.ui.selectedUser">
                <div class="space-y-4">

                    {{-- ID + Toggle activo --}}
                    <div class="flex items-center justify-between">
                        <span
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg
                                     bg-gray-100 dark:bg-gray-800 text-xs font-bold text-gray-500 dark:text-gray-400 font-mono">
                            # <span x-text="$store.ui.selectedUser.id"></span>
                        </span>

                        <div class="flex items-center gap-2.5">
                            <span class="text-xs font-semibold" :class="$store.ui.selectedUser.is_active
                                      ? 'text-emerald-600 dark:text-emerald-400'
                                      : 'text-gray-400'"
                                x-text="$store.ui.selectedUser.is_active ? 'Activo' : 'Inactivo'">
                            </span>

                            {{-- Toggle switch --}}
                            <button type="button" role="switch" :aria-checked="$store.ui.selectedUser.is_active" class="relative inline-flex h-6 w-11 items-center rounded-full
                                           transition-colors focus:outline-none focus:ring-2
                                           focus:ring-indigo-500 focus:ring-offset-2" :class="$store.ui.selectedUser.is_active
                                        ? 'bg-emerald-500'
                                        : 'bg-gray-200 dark:bg-gray-700'" @click="toggleActive($store.ui.selectedUser)">
                                <span
                                    class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                                    :class="$store.ui.selectedUser.is_active ? 'translate-x-6' : 'translate-x-1'">
                                </span>
                            </button>
                        </div>
                    </div>

                    {{-- Avatar + nombre --}}
                    <div class="flex items-center gap-3 p-4 rounded-xl
                                bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-900/40">
                        <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center
                                    text-white text-sm font-bold shrink-0"
                            x-text="$store.ui.selectedUser.name.charAt(0).toUpperCase()">
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-gray-900 dark:text-gray-100"
                                x-text="$store.ui.selectedUser.name"></p>
                            <p class="text-xs text-indigo-600 dark:text-indigo-400 font-medium truncate"
                                x-text="$store.ui.selectedUser.email"></p>
                        </div>
                    </div>

                    {{-- Campos --}}
                    <div class="grid gap-2.5">
                        <div class="rounded-xl border border-gray-200 dark:border-gray-800
                                    bg-gray-50 dark:bg-gray-950 px-4 py-3">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400 mb-0.5">Nombre</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100"
                                x-text="$store.ui.selectedUser.name"></p>
                        </div>
                        <div class="rounded-xl border border-gray-200 dark:border-gray-800
                                    bg-gray-50 dark:bg-gray-950 px-4 py-3">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400 mb-0.5">Email</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 break-all"
                                x-text="$store.ui.selectedUser.email"></p>
                        </div>
                        <div class="rounded-xl border border-gray-200 dark:border-gray-800
                                    bg-gray-50 dark:bg-gray-950 px-4 py-3">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400 mb-1">Rol</p>
                            <select class="w-full rounded-xl border border-gray-200 dark:border-gray-700
                                           bg-white dark:bg-gray-900 px-3 py-2 text-sm text-gray-900 dark:text-gray-100
                                           focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                :value="$store.ui.selectedUser.role || 'viewer'"
                                @change="updateRole($store.ui.selectedUser, $event.target.value)">
                                <option value="viewer">viewer</option>
                                <option value="admin">admin</option>
                                <option value="bodeguero">bodeguero</option>
                            </select>
                        </div>
                    </div>

                </div>
            </template>
        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-end px-6 py-4
                    border-t border-gray-100 dark:border-gray-800
                    bg-gray-50/60 dark:bg-gray-950/40">
            <button type="button" @click="$store.ui.openView = false; $store.ui.selectedUser = null" class="px-4 py-2 rounded-xl text-sm font-semibold
                           bg-gray-100 text-gray-700 hover:bg-gray-200
                           dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 transition">
                Cerrar
            </button>
        </div>
    </div>
</div>
