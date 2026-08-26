<x-app-layout>
    <x-slot name="header">
        <div class="flex min-w-0 items-center gap-2">
            <a href="{{ route('purchase_requests.index') }}" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-blue-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-blue-400" aria-label="Volver a solicitudes">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </a>
            <div class="min-w-0">
                <h1 class="truncate text-sm font-extrabold text-slate-900 dark:text-white">Nueva solicitud de compra</h1>
                <p class="truncate text-xs text-slate-500 dark:text-slate-400">Guarda primero como borrador y envíala cuando esté completa.</p>
            </div>
        </div>
    </x-slot>

    @php
        // El asistente sólo tiene sentido al crear: editando ya hay partidas
        // escritas y rehacerlas desde texto sería pisar el trabajo hecho.
        $asistenteDisponible = (bool) config('purchase_requests.reader.enabled');
    @endphp

    <div class="mx-auto max-w-8xl px-4 py-6 sm:px-6 lg:px-8" x-data="{ modo: 'manual' }">
        @include('purchase_requests._module_nav')

        @if($asistenteDisponible)
            {{-- Dos maneras de llegar a lo mismo: llenar las partidas a mano, o
                 escribirlo de corrido y que el asistente las arme. --}}
            <nav aria-label="Cómo quieres escribir la solicitud"
                class="mt-3 inline-flex rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <button type="button" @click="modo = 'manual'" :aria-current="modo === 'manual' ? 'page' : false"
                    class="inline-flex min-h-11 items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition-colors"
                    :class="modo === 'manual'
                        ? 'bg-blue-600 text-white shadow-sm shadow-blue-200 dark:shadow-blue-950/60'
                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'">
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Manual
                </button>

                <button type="button" @click="modo = 'ia'" :aria-current="modo === 'ia' ? 'page' : false"
                    class="inline-flex min-h-11 items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition-colors"
                    :class="modo === 'ia'
                        ? 'bg-violet-600 text-white shadow-sm shadow-violet-200 dark:shadow-violet-950/60'
                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'">
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                    </svg>
                    IA
                </button>
            </nav>
        @endif

        <div class="mt-5">
            @include('purchase_requests._form', ['purchaseRequest' => $purchaseRequest ?? null])
        </div>
    </div>
</x-app-layout>
