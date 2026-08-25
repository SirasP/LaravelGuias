<x-app-layout>
    <x-slot name="header">
        <div class="flex min-w-0 items-center gap-2">
            <a href="{{ route('purchase_requests.show', $purchaseRequest) }}" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-blue-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-blue-400" aria-label="Volver al detalle">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </a>
            <div class="min-w-0">
                <h1 class="truncate text-sm font-extrabold text-slate-900 dark:text-white">Editar solicitud {{ $purchaseRequest->folio ?: '#'.$purchaseRequest->id }}</h1>
                <p class="truncate text-xs text-slate-500 dark:text-slate-400">Los cambios se guardan en el borrador o corrección actual.</p>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-8xl px-4 py-6 sm:px-6 lg:px-8">
        @include('purchase_requests._module_nav')
        <div class="mt-5">
            @include('purchase_requests._form', ['purchaseRequest' => $purchaseRequest])
        </div>
    </div>
</x-app-layout>
