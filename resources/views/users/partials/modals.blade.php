{{-- ================= MODAL AGREGAR (PRO) ================= --}}
<div x-cloak x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4"
    @keydown.escape.window="open=false" aria-modal="true" role="dialog">
    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/60 backdrop-blur-[2px]" @click="open=false"></div>

    {{-- Panel --}}
    <div x-show="open" x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-2 scale-95"
        class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-800">
            <div class="flex items-center gap-3">
                <div class="grid h-9 w-9 place-items-center rounded-xl bg-blue-600/10 text-blue-700 dark:text-blue-300">
                    ➕
                </div>
                <div class="leading-tight">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                        Agregar usuario
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Completa los datos para registrar
                    </p>
                </div>
            </div>

            <button type="button" @click="open=false" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700
                       dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-gray-100 transition"
                aria-label="Cerrar">
                ✕
            </button>
        </div>

        {{-- Body --}}
        <div class="px-6 py-5">
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

                {{-- Footer --}}
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-200 dark:border-gray-800">
                    <button type="button" @click="$store.ui.open=false" class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-medium
                               bg-gray-200 text-gray-800 hover:bg-gray-300
                               dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700 transition">
                        Cancelar
                    </button>

                    <button type="submit" class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold
                               bg-blue-600 text-white hover:bg-blue-700 transition">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ================= MODAL VER (PRO + GUARDAR is_active) ================= --}}

<div x-cloak x-show="open" class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
    @keydown.escape.window="open=false; selectedUser=null" aria-modal="true" role="dialog">
    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/60 backdrop-blur-[2px]"
        @click="open=false; selectedUser=null"></div>

    {{-- Panel --}}
    <div x-show="openView" x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-2 scale-95"
        class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-800">
            <div class="flex items-center gap-3">
                <div class="grid h-9 w-9 place-items-center rounded-xl bg-blue-600/10 text-blue-700 dark:text-blue-300">
                    👁
                </div>
                <div class="leading-tight">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                        Detalle del usuario
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Información registrada
                    </p>
                </div>
            </div>

            <button type="button" @click="openView=false; selectedUser=null" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700
                       dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-gray-100 transition"
                aria-label="Cerrar">
                ✕
            </button>
        </div>

        {{-- Body --}}
        <div class="px-6 py-5">
            {{-- Estado "cargando" por si selectedUser llega null --}}
            <template x-if="!selectedUser">
                <div class="space-y-3">
                    <div class="h-4 w-32 rounded bg-gray-200 dark:bg-gray-800"></div>
                    <div class="h-4 w-full rounded bg-gray-200 dark:bg-gray-800"></div>
                    <div class="h-4 w-2/3 rounded bg-gray-200 dark:bg-gray-800"></div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Cargando usuario...</p>
                </div>
            </template>

            <template x-if="$store.ui.selectedUser">
                <div class="space-y-4">
                    {{-- Badge arriba --}}
                    <div class="flex items-center justify-between">
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700
                                     dark:bg-gray-800 dark:text-gray-200">
                            ID: <span class="ml-1 font-semibold" x-text="$store.ui.selectedUser.id"></span>
                        </span>

                        {{-- Estado Activo / Inactivo + Switch (GUARDA EN BD) --}}
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-medium" :class="$store.ui.selectedUser?.is_active
                                    ? 'text-green-700 dark:text-green-300'
                                    : 'text-gray-500 dark:text-gray-400'"
                                x-text="$store.ui.selectedUser?.is_active ? 'Activo' : 'Inactivo'"></span>

                            <button type="button" role="switch" :aria-checked="$store.ui.selectedUser?.is_active" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none
         focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                :class="$store.ui.selectedUser?.is_active ? 'bg-green-600' : 'bg-gray-300 dark:bg-gray-700'"
                                @click="
    const prev = $store.ui.selectedUser.is_active;

    // Cambio visual inmediato
    $store.ui.selectedUser.is_active = !prev;

    const url = '{{ route('users.toggleActive', '__ID__') }}'.replace('__ID__', $store.ui.selectedUser.id);

    fetch(url, {
      method: 'PATCH',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
        'Accept': 'application/json'
      },
      body: JSON.stringify({ is_active: $store.ui.selectedUser.is_active })
    })
    .then(async (r) => {
      const ct = r.headers.get('content-type') || '';
      if (!ct.includes('application/json')) {
        const text = await r.text();
        console.error('Respuesta NO JSON:', text);
        throw new Error('Respuesta NO JSON');
      }
      const data = await r.json();
      if (!r.ok || !data.ok) throw data;
      return data;
    })
    .then((data) => {
      $store.ui.selectedUser.is_active = data.is_active;

      Swal.fire({
        icon: 'success',
        title: 'Estado actualizado',
        text: data.message,
        timer: 1200,
        showConfirmButton: false
      });
    })
    .catch(() => {
      $store.ui.selectedUser.is_active = prev;

      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'No se pudo actualizar el estado del usuario'
      });
    });
  ">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                    :class="$store.ui.selectedUser?.is_active ? 'translate-x-6' : 'translate-x-1'"></span>
                            </button>

                        </div>
                    </div>

                    {{-- Campos en cards --}}
                    <div class="grid gap-3">
                        <div
                            class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Nombre</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100"
                                x-text="$store.ui.selectedUser.name"></p>
                        </div>

                        <div
                            class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Email</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100 break-all"
                                x-text="$store.ui.selectedUser.email"></p>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- Footer --}}
        <div
            class="flex items-center justify-end gap-2 px-6 py-4 border-t border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-950/40">
            <button type="button" @click="$store.ui.openView=false; $store.ui.selectedUser=null" class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-medium
                       bg-gray-200 text-gray-800 hover:bg-gray-300
                       dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700 transition">
                Cerrar
            </button>
        </div>
    </div>
</div>