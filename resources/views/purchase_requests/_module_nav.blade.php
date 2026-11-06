@php
    $currentStatus = $status ?? request()->query('status');
    if ($currentStatus instanceof \BackedEnum) {
        $currentStatus = $currentStatus->value;
    }

    // La pestaña apunta al grupo `por_revisar`; comparar contra 'submitted'
    // la dejaba sin marcar nunca.
    $reviewActive = request()->routeIs('purchase_requests.index')
        && $currentStatus === \App\Enums\PurchaseRequestStatus::GROUP_AWAITING_REVIEW;
    // Lo cerrado tiene su propia pestaña: sale de la lista de trabajo y se va
    // ahí, que es donde uno lo va a buscar cuando quiere consultar una compra
    // vieja, no cuando quiere ver qué falta.
    $terminadasActive = request()->routeIs('purchase_requests.index')
        && $currentStatus === \App\Enums\PurchaseRequestStatus::COMPLETED->value;
    $terminadasCuantas = auth()->user()
        ? \App\Models\PurchaseRequest::query()->visibleTo(auth()->user())
            ->where('status', \App\Enums\PurchaseRequestStatus::COMPLETED->value)->count()
        : 0;
    $catalogsActive = request()->routeIs('purchase_catalogs.*');
    $mineActive = request()->routeIs('purchase_requests.index', 'purchase_requests.show', 'purchase_requests.edit')
        && ! $reviewActive && ! $terminadasActive;
    $esAdministrador = auth()->user()?->isPurchaseReviewer() ?? false;
    $mantieneCatalogos = auth()->user()?->canAdministerPurchaseCatalogs() ?? false;
    $avisosActivo = request()->routeIs('purchase_notifications.*');
    $avisosSinLeer = auth()->user()?->unreadPurchaseNotificationsCount() ?? 0;
    $lectorActivo = request()->routeIs('purchase_requests.ingestions.*');
    $lectorDisponible = (bool) config('purchase_requests.reader.enabled');
    $odooActivo = request()->routeIs('purchase_requests.odoo', 'purchase_requests.odoo.*');
    // Las que el jefe hizo en Odoo sin avisar y siguen sin confirmar.
    $odooCotizaciones = $esAdministrador ? \App\Models\OdooPurchaseOrder::query()->pendingQuotations()->count() : 0;
    $porRevisarCuantas = $esAdministrador && auth()->user()
        ? \App\Models\PurchaseRequest::query()->visibleTo(auth()->user())->whereIn('status', \App\Enums\PurchaseRequestStatus::awaitingReviewValues())->count()
        : 0;
    $nuevaActiva = request()->routeIs('purchase_requests.create');

    // Las pestañas, en una lista: un solo dibujo para todas. «Nueva solicitud»
    // no es una pestaña sino una acción, y va aparte, como botón.
    $pestanas = array_values(array_filter([
        ['Mis solicitudes', route('purchase_requests.index'), $mineActive, 0, 'text-blue-600 dark:text-blue-400', 'bg-blue-600 text-white',
            'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', true, ''],
        ['Por revisar', route('purchase_requests.index', ['status' => \App\Enums\PurchaseRequestStatus::GROUP_AWAITING_REVIEW]), $reviewActive, $porRevisarCuantas, 'text-amber-600 dark:text-amber-400', 'bg-amber-500 text-white',
            'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', $esAdministrador, 'esperan decisión'],
        // Las que el jefe hizo en Odoo y siguen sin confirmar.
        ['Odoo', route('purchase_requests.odoo'), $odooActivo, $odooCotizaciones, 'text-violet-600 dark:text-violet-400', 'bg-violet-600 text-white',
            'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15', $esAdministrador, 'cotizaciones hechas en Odoo'],
        ['Terminadas', route('purchase_requests.index', ['status' => \App\Enums\PurchaseRequestStatus::COMPLETED->value]), $terminadasActive, $terminadasCuantas, 'text-emerald-600 dark:text-emerald-400', 'bg-emerald-600 text-white',
            'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', true, 'compras cerradas'],
        ['Avisos', route('purchase_notifications.index'), $avisosActivo, $avisosSinLeer, 'text-blue-600 dark:text-blue-400', 'bg-rose-500 text-white',
            'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9', true, 'avisos sin leer'],
        ['Leer cotización', route('purchase_requests.ingestions.index'), $lectorActivo, 0, 'text-violet-600 dark:text-violet-400', '',
            'M7 16a4 4 0 01-.88-7.9A5 5 0 1115.9 6H16a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12', $lectorDisponible, ''],
        ['Catálogos', route('purchase_catalogs.index'), $catalogsActive, 0, 'text-slate-700 dark:text-slate-200', '',
            'M4 6h16M4 12h16M4 18h7', $mantieneCatalogos, ''],
    ], fn (array $p): bool => $p[7]));
@endphp

<nav aria-label="Navegación de solicitudes de compra" class="flex items-center gap-2">
    {{-- Las pestañas sobre un riel gris; la activa, blanca y en relieve. En el teléfono el riel se desliza de lado. --}}
    <div class="min-w-0 flex-1 overflow-x-auto rounded-2xl bg-slate-100 p-1 [scrollbar-width:none] dark:bg-slate-800/70 [&::-webkit-scrollbar]:hidden">
        <div class="flex min-w-max items-center gap-0.5">
            @foreach($pestanas as [$titulo, $href, $activa, $cuantas, $tono, $insignia, $svg, , $leer])
                <a href="{{ $href }}" @if($activa) aria-current="page" @endif
                    class="relative inline-flex min-h-10 items-center gap-2 whitespace-nowrap rounded-xl px-3.5 text-sm font-semibold transition-all
                        {{ $activa
                            ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200/80 dark:bg-slate-900 dark:text-white dark:ring-slate-700'
                            : 'text-slate-500 hover:bg-white/60 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-900/40 dark:hover:text-white' }}">
                    <svg class="h-4 w-4 shrink-0 {{ $activa ? $tono : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $svg }}" />
                    </svg>
                    {{ $titulo }}
                    @if($cuantas > 0)
                        <span class="inline-flex min-w-5 justify-center rounded-full px-1.5 py-px text-[11px] font-black tabular-nums {{ $insignia ?: 'bg-slate-200 text-slate-700' }}">
                            {{ $cuantas > 99 ? '99+' : $cuantas }}
                            @if($leer)<span class="sr-only">{{ $leer }}</span>@endif
                        </span>
                    @endif
                    @if($activa)
                        <span class="absolute inset-x-3 -bottom-px h-0.5 rounded-full {{ str_replace(['text-', 'dark:text-'], ['bg-', 'dark:bg-'], $tono) }}" aria-hidden="true"></span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>

    <a href="{{ route('purchase_requests.create') }}" @if($nuevaActiva) aria-current="page" @endif
        class="inline-flex min-h-12 shrink-0 items-center gap-2 rounded-2xl bg-blue-600 px-3.5 text-sm font-extrabold text-white shadow-sm shadow-blue-200 transition hover:bg-blue-700 sm:px-4 dark:shadow-blue-950/60 {{ $nuevaActiva ? 'ring-2 ring-blue-300 ring-offset-2 dark:ring-offset-slate-950' : '' }}"
        title="Nueva solicitud">
        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" /></svg>
        <span class="hidden sm:inline">Nueva solicitud</span>
        <span class="sr-only sm:hidden">Nueva solicitud</span>
    </a>
</nav>
