@php
    $toastType = null;
    $toastMessage = null;

    if (session('success')) {
        $toastType = 'success';
        $toastMessage = session('success');
    } elseif (session('error')) {
        $toastType = 'error';
        $toastMessage = session('error');
    } elseif ($errors->any()) {
        $toastType = 'error';
        $toastMessage = $errors->first();
    }
@endphp

@if($toastMessage)
    <div
        x-data="{ show: true }"
        x-init="setTimeout(() => show = false, 4500)"
        x-show="show"
        x-transition:enter="transition ease-out duration-250"
        x-transition:enter-start="opacity-0 translate-y-[-8px] scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-180"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-[-8px] scale-95"
        class="fixed top-5 right-5 z-[999999] w-[calc(100vw-2rem)] max-w-sm"
        role="status"
        aria-live="polite"
    >
        <div class="rounded-2xl border shadow-2xl backdrop-blur-sm overflow-hidden
            {{ $toastType === 'success'
                ? 'bg-emerald-50/95 border-emerald-200 text-emerald-900 dark:bg-emerald-900/90 dark:border-emerald-700 dark:text-emerald-100'
                : 'bg-rose-50/95 border-rose-200 text-rose-900 dark:bg-rose-900/90 dark:border-rose-700 dark:text-rose-100' }}">
            <div class="flex items-start gap-3 px-4 py-3.5">
                <div class="mt-0.5 shrink-0 w-6 h-6 rounded-full flex items-center justify-center
                    {{ $toastType === 'success'
                        ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-800 dark:text-emerald-200'
                        : 'bg-rose-100 text-rose-600 dark:bg-rose-800 dark:text-rose-200' }}">
                    @if($toastType === 'success')
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                    @else
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <p class="text-xs font-bold uppercase tracking-wide opacity-70">
                        {{ $toastType === 'success' ? 'Correcto' : 'Error' }}
                    </p>
                    <p class="text-sm font-semibold leading-snug break-words mt-0.5">{{ $toastMessage }}</p>
                </div>

                <button
                    type="button"
                    @click="show = false"
                    class="shrink-0 rounded-lg p-1 transition
                        {{ $toastType === 'success'
                            ? 'text-emerald-700/70 hover:text-emerald-900 hover:bg-emerald-100 dark:text-emerald-200/70 dark:hover:text-white dark:hover:bg-emerald-800'
                            : 'text-rose-700/70 hover:text-rose-900 hover:bg-rose-100 dark:text-rose-200/70 dark:hover:text-white dark:hover:bg-rose-800' }}"
                    aria-label="Cerrar notificación"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
@endif
