{{--
    Modal de selección de bodega (bottom-sheet / center).
    Usa Alpine.store('invBodega') — accesible desde cualquier componente Alpine.
    El botón que selecciona dispatch('bodega-selected', {id}) que la página escucha.
    Requiere que se pase $bodegas (colección de bodegas).
--}}
<div x-show="$store.invBodega.modalOpen" x-cloak
     class="fixed inset-0 flex items-end sm:items-center justify-center p-0 sm:p-4"
     style="z-index:400"
     @keydown.escape.window="if($store.invBodega.selected !== null) $store.invBodega.modalOpen = false">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"
         @click="if($store.invBodega.selected !== null) $store.invBodega.modalOpen = false"></div>

    {{-- Sheet --}}
    <div class="relative w-full sm:max-w-sm rounded-t-3xl sm:rounded-2xl overflow-hidden bg-white dark:bg-gray-900"
         style="box-shadow:0 -8px 40px rgba(0,0,0,.22)"
         @click.stop>

        {{-- Drag handle (mobile) --}}
        <div class="flex justify-center pt-3 pb-1 sm:hidden">
            <div class="w-10 h-1 rounded-full bg-gray-200 dark:bg-gray-700"></div>
        </div>

        {{-- Header --}}
        <div class="px-6 pt-5 pb-2 text-center">
            <div class="w-12 h-12 rounded-2xl bg-violet-100 dark:bg-violet-900/40 flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 7l9-4 9 4v10l-9 4-9-4V7z"/>
                </svg>
            </div>
            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">¿Qué bodega quieres ver?</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Selecciona para filtrar el inventario</p>
        </div>

        {{-- Opciones --}}
        <div class="px-6 py-4 flex flex-col gap-2">

            {{-- Todas las bodegas --}}
            <button type="button" @click="$dispatch('bodega-selected', {id: null})"
                class="w-full flex items-center gap-3 px-4 py-3 rounded-2xl border-2 text-left transition focus:outline-none"
                :class="$store.invBodega.selected === null
                    ? 'border-violet-500 bg-violet-50 dark:bg-violet-900/30 ring-2 ring-violet-200 dark:ring-violet-800'
                    : 'border-gray-200 dark:border-gray-700 hover:border-violet-300 dark:hover:border-violet-700 hover:bg-gray-50 dark:hover:bg-gray-800'">
                <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 text-xl
                             bg-gray-100 dark:bg-gray-800">🏭</span>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100">Todas las bodegas</p>
                    <p class="text-xs text-gray-400 mt-0.5">Ver inventario consolidado</p>
                </div>
                <svg x-show="$store.invBodega.selected === null" class="w-4 h-4 text-violet-600 dark:text-violet-400 ml-auto shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </button>

            {{-- Cada bodega --}}
            @foreach($bodegas as $bod)
            <button type="button" @click="$dispatch('bodega-selected', {id: {{ $bod->id }}})"
                class="w-full flex items-center gap-3 px-4 py-3 rounded-2xl border-2 text-left transition focus:outline-none"
                :class="$store.invBodega.selected === {{ $bod->id }}
                    ? 'border-violet-500 bg-violet-50 dark:bg-violet-900/30 ring-2 ring-violet-200 dark:ring-violet-800'
                    : 'border-gray-200 dark:border-gray-700 hover:border-violet-300 dark:hover:border-violet-700 hover:bg-gray-50 dark:hover:bg-gray-800'">
                <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 text-xl
                             {{ $bod->es_principal ? 'bg-violet-100 dark:bg-violet-900/40' : 'bg-sky-100 dark:bg-sky-900/30' }}">
                    {{ $bod->es_principal ? '🏪' : '🔧' }}
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ $bod->nombre }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ $bod->codigo }}</p>
                </div>
                <svg x-show="$store.invBodega.selected === {{ $bod->id }}" class="w-4 h-4 text-violet-600 dark:text-violet-400 ml-auto shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </button>
            @endforeach
        </div>

        <div class="px-6 pb-6">
            <p x-show="$store.invBodega.selected === null && !$store.invBodega.forcedOpen"
               class="text-center text-xs text-gray-400">
                Puedes cambiar la bodega en cualquier momento desde el listado
            </p>
        </div>
    </div>
</div>
