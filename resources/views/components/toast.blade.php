@php
    $toastType = null;
    $toastMessage = null;

    if (session('success')) {
        $toastType = 'success';
        $toastMessage = session('success');
    } elseif (session('warning')) {
        $toastType = 'warning';
        $toastMessage = session('warning');
    } elseif (session('info')) {
        $toastType = 'info';
        $toastMessage = session('info');
    } elseif (session('error')) {
        $toastType = 'error';
        $toastMessage = session('error');
    } elseif (session('status')) {
        $toastType = 'info';
        $toastMessage = match (session('status')) {
            'verification-link-sent' => 'Se envió un nuevo enlace de verificación a tu correo.',
            'profile-updated' => 'Perfil actualizado correctamente.',
            'password-updated' => 'Contraseña actualizada correctamente.',
            default => (string) session('status'),
        };
    } elseif (isset($errors) && $errors->any()) {
        $toastType = 'error';
        $toastMessage = $errors->first();
    }

    $containerClasses = match ($toastType) {
        'success' => 'bg-emerald-50 border-emerald-300 text-emerald-900 dark:bg-emerald-950 dark:border-emerald-700 dark:text-emerald-100',
        'warning' => 'bg-amber-50 border-amber-300 text-amber-900 dark:bg-amber-950 dark:border-amber-700 dark:text-amber-100',
        'info' => 'bg-sky-50 border-sky-300 text-sky-900 dark:bg-sky-950 dark:border-sky-700 dark:text-sky-100',
        default => 'bg-rose-50 border-rose-300 text-rose-900 dark:bg-rose-950 dark:border-rose-700 dark:text-rose-100',
    };

    $iconClasses = match ($toastType) {
        'success' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-800 dark:text-emerald-200',
        'warning' => 'bg-amber-100 text-amber-600 dark:bg-amber-800 dark:text-amber-200',
        'info' => 'bg-sky-100 text-sky-600 dark:bg-sky-800 dark:text-sky-200',
        default => 'bg-rose-100 text-rose-600 dark:bg-rose-800 dark:text-rose-200',
    };

    $closeClasses = match ($toastType) {
        'success' => 'text-emerald-700/70 hover:text-emerald-900 hover:bg-emerald-100 dark:text-emerald-200/70 dark:hover:text-white dark:hover:bg-emerald-800',
        'warning' => 'text-amber-700/70 hover:text-amber-900 hover:bg-amber-100 dark:text-amber-200/70 dark:hover:text-white dark:hover:bg-amber-800',
        'info' => 'text-sky-700/70 hover:text-sky-900 hover:bg-sky-100 dark:text-sky-200/70 dark:hover:text-white dark:hover:bg-sky-800',
        default => 'text-rose-700/70 hover:text-rose-900 hover:bg-rose-100 dark:text-rose-200/70 dark:hover:text-white dark:hover:bg-rose-800',
    };

    $title = match ($toastType) {
        'success' => 'Correcto',
        'warning' => 'Atención',
        'info' => 'Información',
        default => 'Error',
    };
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
        <div class="rounded-2xl border shadow-xl overflow-hidden {{ $containerClasses }}">
            <div class="flex items-start gap-3 px-4 py-3.5">
                <div class="mt-0.5 shrink-0 w-6 h-6 rounded-full flex items-center justify-center {{ $iconClasses }}">
                    @if($toastType === 'success')
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                    @elseif($toastType === 'warning')
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v4m0 4h.01M10.29 3.86l-8.5 14.72A2 2 0 003.53 21h16.94a2 2 0 001.74-2.42l-8.5-14.72a2 2 0 00-3.46 0z"/>
                        </svg>
                    @elseif($toastType === 'info')
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    @else
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <p class="text-xs font-bold uppercase tracking-wide opacity-70">
                        {{ $title }}
                    </p>
                    <p class="text-sm font-semibold leading-snug break-words mt-0.5">{{ $toastMessage }}</p>
                </div>

                <button
                    type="button"
                    @click="show = false"
                    class="shrink-0 rounded-lg p-1 transition {{ $closeClasses }}"
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
