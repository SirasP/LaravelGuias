@php
    $currentStatus = $status ?? request()->query('status');
    if ($currentStatus instanceof \BackedEnum) {
        $currentStatus = $currentStatus->value;
    }

    // La pestaña apunta al grupo `por_revisar`; comparar contra 'submitted'
    // la dejaba sin marcar nunca.
    $reviewActive = request()->routeIs('purchase_requests.index')
        && $currentStatus === \App\Enums\PurchaseRequestStatus::GROUP_AWAITING_REVIEW;
    $catalogsActive = request()->routeIs('purchase_catalogs.*');
    $mineActive = request()->routeIs('purchase_requests.index', 'purchase_requests.show', 'purchase_requests.edit')
        && ! $reviewActive;
    $esAdministrador = auth()->user()?->isPurchaseReviewer() ?? false;
    $mantieneCatalogos = auth()->user()?->canAdministerPurchaseCatalogs() ?? false;
    $avisosActivo = request()->routeIs('purchase_notifications.*');
    $avisosSinLeer = auth()->user()?->unreadPurchaseNotificationsCount() ?? 0;
    $lectorActivo = request()->routeIs('purchase_requests.ingestions.*');
    $lectorDisponible = (bool) config('purchase_requests.reader.enabled');
@endphp

<nav aria-label="Navegación de solicitudes de compra"
    class="overflow-x-auto rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <div class="flex min-w-max items-center gap-1">
        <a href="{{ route('purchase_requests.index') }}"
            @if($mineActive) aria-current="page" @endif
            class="inline-flex min-h-11 items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition-colors
                {{ $mineActive
                    ? 'bg-blue-600 text-white shadow-sm shadow-blue-200 dark:shadow-blue-950/60'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Mis solicitudes
        </a>

        <a href="{{ route('purchase_requests.create') }}"
            @if(request()->routeIs('purchase_requests.create')) aria-current="page" @endif
            class="inline-flex min-h-11 items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition-colors
                {{ request()->routeIs('purchase_requests.create')
                    ? 'bg-blue-600 text-white shadow-sm shadow-blue-200 dark:shadow-blue-950/60'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Nueva solicitud
        </a>

        @if($esAdministrador)
            <a href="{{ route('purchase_requests.index', ['status' => \App\Enums\PurchaseRequestStatus::GROUP_AWAITING_REVIEW]) }}"
                @if($reviewActive) aria-current="page" @endif
                class="inline-flex min-h-11 items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition-colors
                    {{ $reviewActive
                        ? 'bg-amber-500 text-white shadow-sm shadow-amber-200 dark:shadow-amber-950/60'
                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                Por revisar
            </a>
        @endif

        <a href="{{ route('purchase_notifications.index') }}"
            @if($avisosActivo) aria-current="page" @endif
            class="inline-flex min-h-11 items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition-colors
                {{ $avisosActivo
                    ? 'bg-blue-600 text-white shadow-sm shadow-blue-200 dark:shadow-blue-950/60'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            Avisos
            @if($avisosSinLeer > 0)
                <span class="rounded-full px-1.5 text-xs font-bold {{ $avisosActivo ? 'bg-white text-blue-700' : 'bg-blue-600 text-white' }}">
                    {{ $avisosSinLeer > 99 ? '99+' : $avisosSinLeer }}
                    <span class="sr-only">avisos sin leer</span>
                </span>
            @endif
        </a>

        @if($lectorDisponible)
            <a href="{{ route('purchase_requests.ingestions.index') }}"
                @if($lectorActivo) aria-current="page" @endif
                class="inline-flex min-h-11 items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition-colors
                    {{ $lectorActivo
                        ? 'bg-violet-600 text-white shadow-sm shadow-violet-200 dark:shadow-violet-950/60'
                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 16a4 4 0 01-.88-7.9A5 5 0 1115.9 6H16a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
                Leer cotización
            </a>
        @endif

        @if($mantieneCatalogos)
            <a href="{{ route('purchase_catalogs.index') }}"
                @if($catalogsActive) aria-current="page" @endif
                class="inline-flex min-h-11 items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition-colors
                    {{ $catalogsActive
                        ? 'bg-slate-800 text-white shadow-sm dark:bg-slate-100 dark:text-slate-900'
                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 6h16M4 12h16M4 18h7" />
                </svg>
                Catálogos
            </a>
        @endif
    </div>
</nav>
