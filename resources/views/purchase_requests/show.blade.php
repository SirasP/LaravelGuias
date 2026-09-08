<x-app-layout>
    @php
        $rawStatus = $purchaseRequest->status instanceof \BackedEnum ? $purchaseRequest->status->value : (string) $purchaseRequest->status;
        $statusLabel = is_object($purchaseRequest->status) && method_exists($purchaseRequest->status, 'label')
            ? $purchaseRequest->status->label()
            : \Illuminate\Support\Str::headline($rawStatus);
        $statusClasses = is_object($purchaseRequest->status) && method_exists($purchaseRequest->status, 'badgeClasses')
            ? $purchaseRequest->status->badgeClasses()
            : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200';
        $statusIcon = is_object($purchaseRequest->status) && method_exists($purchaseRequest->status, 'icon')
            ? $purchaseRequest->status->icon()
            : '•';

        $isEditable = auth()->user()?->can('update', $purchaseRequest) ?? false;
        $canReview = auth()->user()?->can('approve', $purchaseRequest) ?? false;

        $formatDate = static function ($date): string {
            if (blank($date)) {
                return '—';
            }
            try {
                return $date instanceof \Carbon\CarbonInterface
                    ? $date->format('d-m-Y')
                    : \Illuminate\Support\Carbon::parse($date)->format('d-m-Y');
            } catch (\Throwable) {
                return (string) $date;
            }
        };

        $formatDateTime = static function ($date): string {
            if (blank($date)) {
                return '—';
            }
            try {
                return $date instanceof \Carbon\CarbonInterface
                    ? $date->format('d-m-Y H:i')
                    : \Illuminate\Support\Carbon::parse($date)->format('d-m-Y H:i');
            } catch (\Throwable) {
                return (string) $date;
            }
        };

        $priority = $purchaseRequest->priority === 'urgent'
            ? ['Urgente', 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-950/40 dark:text-rose-300 dark:ring-rose-500/30']
            : ['Normal', 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700/50'];

        $suggestedSuppliers = $purchaseRequest->suggested_suppliers ?? [];
        $suggestedSuppliers = is_array($suggestedSuppliers) ? array_filter($suggestedSuppliers) : [];

        $requesterName = $purchaseRequest->requester_name_snapshot ?: data_get($purchaseRequest, 'requester.name', '—');
        $requesterInitials = collect(explode(' ', trim((string) $requesterName)))
            ->filter()
            ->take(2)
            ->map(fn($seg) => mb_substr($seg, 0, 1))
            ->implode('');
        if (empty($requesterInitials)) {
            $requesterInitials = 'SC';
        }

        $itemsCount = $purchaseRequest->items->count();
        $totalAmount = $purchaseRequest->total();
        $hasTotal = filled($totalAmount);
        $tasa = (float) config('purchase_requests.tax_rate', 0.19);
        $simbolo = $purchaseRequest->currency === 'CLP' ? '$' : $purchaseRequest->currency.' ';
        $neto = $hasTotal
            ? ($purchaseRequest->prices_include_tax ? $totalAmount / (1 + $tasa) : $totalAmount)
            : 0;

        $comparables = collect($comparaciones)->reject(function ($comparacion) {
            $enCurso = in_array($comparacion['ingestion']->status, [
                \App\Models\PurchaseRequestIngestion::PENDING,
                \App\Models\PurchaseRequestIngestion::PROCESSING,
            ], true);

            return $enCurso || $comparacion['resultado']->elDocumentoNoAporto();
        })->values();

        $hayEspecificacion = $purchaseRequest->items->contains(fn ($linea) => filled($linea->specification));
        $hayPrecio = $purchaseRequest->items->contains(fn ($linea) => filled($linea->unit_price));
        $hayDestino = $purchaseRequest->items->contains(fn ($linea) => filled($linea->destination));

        $odooActivo = (bool) config('purchase_requests.odoo.enabled');
        $yaEnOdoo = filled($purchaseRequest->odoo_order_id);
        $candidatos = session('odoo_candidates', []);

        // A quién comprarle. Se resuelve contra el catálogo local, sin llamar a
        // Odoo al dibujar la página, sólo para saber si hay que preguntarlo:
        // esperar a que fallara el envío para pedirlo era hacerlo al revés.
        $proveedorEscrito = collect($purchaseRequest->suggested_suppliers ?? [])
            ->map(fn ($s) => trim((string) $s))->filter()->first();
        $exportador = app(\App\Services\PurchaseRequests\Odoo\PurchaseRequestExporter::class);
        $conOdooDeVerdad = $exportador instanceof \App\Services\PurchaseRequests\Odoo\OdooPurchaseRequestExporter;
        $proveedorDeOdoo = $conOdooDeVerdad ? $exportador->proveedorConocido($purchaseRequest) : null;
        $faltaProveedor = $conOdooDeVerdad && ! $yaEnOdoo && $proveedorDeOdoo === null;
    @endphp

    {{-- ── CONTEXT HEADER STICKY ────────────────────────────────────────── --}}
    <x-slot name="header">
        <div class="flex w-full items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-3">
                <a href="{{ route('purchase_requests.index') }}"
                   class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600 transition hover:bg-blue-50 hover:text-blue-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-blue-950/50 dark:hover:text-blue-400"
                   title="Volver a solicitudes">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-sm font-black tracking-tight text-blue-600 dark:text-blue-400">
                            {{ $purchaseRequest->folio ?: '#'.$purchaseRequest->id }}
                        </span>
                        <span class="text-slate-300 dark:text-slate-600">·</span>
                        <span class="truncate text-xs font-semibold text-slate-500 dark:text-slate-400">
                            {{ $purchaseRequest->department ?: 'Solicitud de compra' }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-extrabold shadow-sm ring-1 {{ $statusClasses }}">
                    <span>{{ $statusIcon }}</span>
                    <span>{{ $statusLabel }}</span>
                </span>
                <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $priority[1] }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $purchaseRequest->priority === 'urgent' ? 'bg-rose-500 animate-ping' : 'bg-slate-400' }}"></span>
                    {{ $priority[0] }}
                </span>
            </div>
        </div>
    </x-slot>

    {{-- ── FULL WIDTH CONTAINER (ocupa el 100% de la pantalla sin cortes) ── --}}
    <div class="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">

        {{-- Alertas de sesión con animación y estilo premium --}}
        @if(session('success'))
            <div class="flex items-center gap-3 rounded-2xl border border-emerald-300/80 bg-gradient-to-r from-emerald-50 via-teal-50/50 to-white p-4 text-sm font-medium text-emerald-950 shadow-sm backdrop-blur-md dark:border-emerald-800/80 dark:from-emerald-950/50 dark:via-slate-900 dark:to-slate-900 dark:text-emerald-200">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-500 text-white shadow-sm shadow-emerald-500/30">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                </div>
                <div class="flex-1 font-semibold">{{ session('success') }}</div>
            </div>
        @endif

        @if(session('error'))
            <div class="flex items-center gap-3 rounded-2xl border border-rose-300/80 bg-gradient-to-r from-rose-50 via-red-50/50 to-white p-4 text-sm font-medium text-rose-950 shadow-sm backdrop-blur-md dark:border-rose-800/80 dark:from-rose-950/50 dark:via-slate-900 dark:to-slate-900 dark:text-rose-200">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-rose-500 text-white shadow-sm shadow-rose-500/30">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                </div>
                <div class="flex-1 font-semibold">{{ session('error') }}</div>
            </div>
        @endif

        {{-- ── HERO COMMAND CARD (Full Width, Modern Web App Header) ───── --}}
        <section x-data="{ panel: null }" class="relative w-full overflow-hidden rounded-3xl border border-slate-200/90 bg-gradient-to-b from-white via-slate-50/40 to-white shadow-sm transition-all dark:border-slate-800 dark:from-slate-900 dark:via-slate-900/90 dark:to-slate-900">
            {{-- Accent gradients decorativos --}}
            <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-blue-500/10 blur-3xl dark:bg-blue-600/15"></div>
            <div class="pointer-events-none absolute -bottom-24 -left-24 h-96 w-96 rounded-full bg-indigo-500/10 blur-3xl dark:bg-indigo-600/15"></div>

            <div class="relative p-6 sm:p-8">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                    
                    {{-- Título y Metadatos principales --}}
                    <div class="min-w-0 flex-1 space-y-4">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <span class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-3 py-1 font-mono text-xs font-black tracking-wider text-white shadow-sm shadow-blue-500/25">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" /></svg>
                                {{ $purchaseRequest->folio ?: 'BOR-'.$purchaseRequest->id }}
                            </span>
                            
                            <span class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1 text-xs font-extrabold ring-1 shadow-sm {{ $statusClasses }}">
                                <span>{{ $statusIcon }}</span>
                                <span>{{ $statusLabel }}</span>
                            </span>

                            <span class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1 text-xs font-extrabold ring-1 {{ $priority[1] }}">
                                <span class="h-2 w-2 rounded-full {{ $purchaseRequest->priority === 'urgent' ? 'bg-rose-500 animate-ping' : 'bg-slate-400' }}"></span>
                                {{ $priority[0] }}
                            </span>

                            @if($purchaseRequest->department)
                                <span class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white/80 px-3 py-1 text-xs font-bold text-slate-700 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                    <svg class="h-3.5 w-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                    {{ $purchaseRequest->department }}
                                </span>
                            @endif

                            <span class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white/80 px-3 py-1 text-xs font-bold text-slate-600 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                <svg class="h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                {{ $itemsCount }} {{ \Illuminate\Support\Str::plural('partida', $itemsCount) }}
                            </span>
                        </div>

                        {{-- Nombre / Motivo de la solicitud --}}
                        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white sm:text-3xl lg:text-4xl leading-tight">
                            {{ $purchaseRequest->reason }}
                        </h1>

                        {{-- Fila de detalles del solicitante y fechas --}}
                        <div class="flex flex-wrap items-center gap-x-6 gap-y-2.5 pt-1 text-xs text-slate-500 dark:text-slate-400">
                            <div class="flex items-center gap-2">
                                <div class="flex h-7 w-7 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600 font-mono text-xs font-black text-white shadow-sm shadow-blue-500/25">
                                    {{ $requesterInitials }}
                                </div>
                                <span>Solicitante: <strong class="font-bold text-slate-900 dark:text-white">{{ $requesterName }}</strong></span>
                            </div>

                            <span class="text-slate-300 dark:text-slate-700">·</span>

                            <div class="flex items-center gap-1.5">
                                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                <span>Creada el <strong class="font-bold text-slate-800 dark:text-slate-200">{{ $formatDate($purchaseRequest->created_at) }}</strong></span>
                            </div>

                            <span class="text-slate-300 dark:text-slate-700">·</span>

                            <div class="flex items-center gap-1.5">
                                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a3 3 0 006 0M9 5a3 3 0 016 0m-7 7h8m-8 4h5" /></svg>
                                <span>Revisión {{ $purchaseRequest->revision_number ?: 0 }}</span>
                            </div>

                            @if(filled($purchaseRequest->required_date))
                                <span class="text-slate-300 dark:text-slate-700">·</span>
                                <div class="flex items-center gap-1.5 text-amber-700 dark:text-amber-400">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    <span>Fecha requerida: <strong class="font-black">{{ $formatDate($purchaseRequest->required_date) }}</strong></span>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Toolbar de Acciones (Desktop & Mobile) --}}
                    <div class="flex flex-wrap items-center gap-2.5 lg:justify-end">
                        {{-- Botón Descargar PDF --}}
                        <a href="{{ route('purchase_requests.pdf', $purchaseRequest) }}"
                           class="inline-flex min-h-11 items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-800 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:shadow active:scale-95 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700">
                            <svg class="h-4 w-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 10v6m0 0l-3-3m3 3l3-3M5 20h14a2 2 0 002-2v-1a2 2 0 00-2-2H5a2 2 0 00-2 2v1a2 2 0 002 2zM7 4h10v6H7z" />
                            </svg>
                            <span>Descargar PDF</span>
                        </a>

                        @if($isEditable)
                            <a href="{{ route('purchase_requests.edit', $purchaseRequest) }}"
                               class="inline-flex min-h-11 items-center justify-center gap-2 rounded-2xl bg-blue-600 px-5 text-sm font-black text-white shadow-md shadow-blue-500/25 transition hover:bg-blue-700 active:scale-95">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                <span>Editar</span>
                            </a>
                        @endif

                        @can('requestChanges', $purchaseRequest)
                            @if($purchaseRequest->status === \App\Enums\PurchaseRequestStatus::APPROVED)
                                <button type="button" @click="panel = panel === 'devolver' ? null : 'devolver'"
                                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-2xl border border-amber-300/80 bg-amber-50 px-4 text-sm font-bold text-amber-900 transition hover:bg-amber-100 active:scale-95 dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-300 dark:hover:bg-amber-900/60">
                                    <svg class="h-4 w-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" /></svg>
                                    <span>Devolver</span>
                                </button>
                            @endif
                        @endcan

                        {{-- Terminada: lo que faltaba para saber qué sigue en
                             marcha y qué ya llegó. Va en verde y primero,
                             porque es el final normal de una compra. --}}
                        @can('complete', $purchaseRequest)
                            <form method="POST" action="{{ route('purchase_requests.complete', $purchaseRequest) }}">
                                @csrf
                                <button type="submit"
                                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-5 text-sm font-black text-white shadow-md shadow-emerald-500/25 transition hover:bg-emerald-700 active:scale-95">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    <span>Terminada</span>
                                </button>
                            </form>
                        @endcan

                        @can('reopen', $purchaseRequest)
                            <form method="POST" action="{{ route('purchase_requests.reopen', $purchaseRequest) }}">
                                @csrf
                                <button type="submit"
                                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-2xl border border-violet-300/80 bg-violet-50 px-4 text-sm font-bold text-violet-900 transition hover:bg-violet-100 active:scale-95 dark:border-violet-700/60 dark:bg-violet-950/40 dark:text-violet-300 dark:hover:bg-violet-900/60">
                                    <svg class="h-4 w-4 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                    <span>Volver a abrir</span>
                                </button>
                            </form>
                        @endcan

                        @can('cancel', $purchaseRequest)
                            <button type="button" @click="panel = panel === 'anular' ? null : 'anular'"
                                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-2xl border border-rose-300/80 bg-rose-50 px-4 text-sm font-bold text-rose-800 transition hover:bg-rose-100 active:scale-95 dark:border-rose-800/60 dark:bg-rose-950/40 dark:text-rose-300 dark:hover:bg-rose-900/60">
                                <svg class="h-4 w-4 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                <span>Anular</span>
                            </button>
                        @endcan

                        @can('requestCancellation', $purchaseRequest)
                            @if(! $purchaseRequest->cancellation_requested_at)
                                <button type="button" @click="panel = panel === 'pedir' ? null : 'pedir'"
                                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-2xl border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 transition hover:bg-slate-100 active:scale-95 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                                    <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    <span>Pedir anulación</span>
                                </button>
                            @endif
                        @endcan

                        @if($purchaseRequest->revisions->count() > 1)
                            <button type="button" @click="panel = panel === 'revisiones' ? null : 'revisiones'"
                                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 active:scale-95 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                                <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <span>Revisiones</span>
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-black text-slate-700 dark:bg-slate-700 dark:text-slate-300">{{ $purchaseRequest->revisions->count() }}</span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Panel de Revisiones expandible --}}
            @if($purchaseRequest->revisions->count() > 1)
                <div x-show="panel === 'revisiones'" x-cloak x-transition.opacity
                     class="border-t border-slate-200/80 bg-slate-50/80 dark:border-slate-800 dark:bg-slate-950/50">
                    <div class="px-6 py-3.5">
                        <p class="text-xs font-semibold text-slate-600 dark:text-slate-400">
                            Historial de versiones: cada revisión guarda el documento tal como se envió y no se regenera con datos nuevos.
                        </p>
                    </div>
                    <ul class="divide-y divide-slate-200/80 border-t border-slate-200/80 dark:divide-slate-800 dark:border-slate-800">
                        @foreach ($purchaseRequest->revisions as $rev)
                            <li class="flex items-center justify-between gap-4 px-6 py-3.5 transition hover:bg-white/80 dark:hover:bg-slate-900/60">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2.5">
                                        <p class="text-sm font-black text-slate-900 dark:text-white">
                                            Revisión {{ $rev->revision_number }}
                                        </p>
                                        @if($rev->revision_number === $purchaseRequest->revision_number)
                                            <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-blue-700 dark:bg-blue-950/70 dark:text-blue-300">vigente</span>
                                        @endif
                                    </div>
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                        {{ $rev->submitted_by_name_snapshot }} · {{ optional($rev->submitted_at)->format('d-m-Y H:i') }}
                                        · {{ $rev->item_count }} {{ \Illuminate\Support\Str::plural('partida', $rev->item_count) }}
                                    </p>
                                </div>
                                @can('downloadPdf', $purchaseRequest)
                                    <a href="{{ route('purchase_requests.pdf', ['purchaseRequest' => $purchaseRequest, 'revision' => $rev->revision_number]) }}"
                                        class="inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                                        <svg class="h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M5 20h14a2 2 0 002-2v-1a2 2 0 00-2-2H5a2 2 0 00-2 2v1a2 2 0 002 2zM7 4h10v6H7z" /></svg>
                                        PDF
                                    </a>
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Banner si tiene cambios solicitados --}}
            @if($rawStatus === 'changes_requested')
                @php $lastChange = $purchaseRequest->events?->first(fn ($event) => data_get($event, 'event_type') === 'changes_requested'); @endphp
                <div class="border-t border-amber-300/80 bg-gradient-to-r from-amber-50 to-orange-50/60 px-6 py-4 text-sm text-amber-950 backdrop-blur-md dark:border-amber-900/60 dark:from-amber-950/40 dark:to-slate-900 dark:text-amber-100">
                    <div class="flex items-start gap-3.5">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-white shadow-sm shadow-amber-500/30">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        </div>
                        <div class="space-y-1">
                            <p class="font-black text-amber-950 dark:text-amber-200">Esta solicitud requiere correcciones.</p>
                            @if(filled(data_get($lastChange, 'comment')))
                                <p class="text-xs text-amber-800 dark:text-amber-300">{{ data_get($lastChange, 'comment') }}</p>
                            @endif

                            @if(filled($purchaseRequest->requested_corrections))
                                <div class="pt-2">
                                    <p class="text-[11px] font-black uppercase tracking-wider text-amber-700 dark:text-amber-400">Puntos a corregir:</p>
                                    <ul class="mt-1.5 flex flex-wrap gap-1.5">
                                        @foreach($purchaseRequest->requested_corrections as $punto)
                                            <li class="rounded-full bg-amber-200/80 px-3 py-1 text-xs font-bold text-amber-950 dark:bg-amber-900/80 dark:text-amber-100">
                                                {{ \App\Enums\PurchaseRequestCorrection::labelFor($punto) }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            {{-- ── MODALES TELEPORTADOS AL BODY (Devolver, Anular, Pedir) ────── --}}
            @can('requestChanges', $purchaseRequest)
                @if($purchaseRequest->status === \App\Enums\PurchaseRequestStatus::APPROVED)
                    <template x-teleport="body">
                        <div x-show="panel === 'devolver'" x-cloak role="dialog" aria-modal="true" aria-labelledby="titulo-devolver"
                            @keydown.escape.window="panel = null"
                            class="fixed inset-0 z-[100] flex items-end justify-center p-4 sm:items-center">
                            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="panel = null" x-show="panel === 'devolver'"
                                x-transition.opacity></div>
                            <form method="POST" action="{{ route('purchase_requests.request_changes', $purchaseRequest) }}"
                                x-show="panel === 'devolver'"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
                                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                                class="relative w-full max-w-lg space-y-4 rounded-3xl bg-white p-6 shadow-2xl dark:bg-slate-900 dark:border dark:border-slate-800">
                                @csrf
                                <input type="hidden" name="lock_version" value="{{ $purchaseRequest->lock_version }}">
                                
                                <div class="flex items-start gap-4">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400">
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" /></svg>
                                    </div>
                                    <div>
                                        <h2 id="titulo-devolver" class="text-lg font-black text-slate-900 dark:text-white">Devolver para corregir</h2>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                            Todavía no se envía a Odoo, así que aún se puede arreglar. Vuelve a estado editable y hay que aprobarla de nuevo antes de enviarla.
                                        </p>
                                    </div>
                                </div>

                                <div class="space-y-1.5">
                                    <label for="reopen-comment" class="block text-xs font-bold text-slate-700 dark:text-slate-200">
                                        ¿Qué hay que corregir? <span class="text-rose-600">*</span>
                                    </label>
                                    <textarea id="reopen-comment" name="comment" rows="3" required
                                        class="w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                        placeholder="Ej.: la unidad de la partida 3 no corresponde."></textarea>
                                    @error('comment') <p class="text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
                                </div>

                                <div class="flex flex-wrap justify-end gap-2.5 pt-2">
                                    <button type="button" @click="panel = null" class="min-h-11 rounded-xl px-4 text-sm font-bold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Cancelar</button>
                                    <button type="submit" class="min-h-11 rounded-xl bg-amber-600 px-5 text-sm font-extrabold text-white shadow-sm hover:bg-amber-700">Devolver para corregir</button>
                                </div>
                            </form>
                        </div>
                    </template>
                @endif
            @endcan

            @can('cancel', $purchaseRequest)
                <template x-teleport="body">
                    <div x-show="panel === 'anular'" x-cloak role="dialog" aria-modal="true" aria-labelledby="titulo-anular"
                        @keydown.escape.window="panel = null"
                        class="fixed inset-0 z-[100] flex items-end justify-center p-4 sm:items-center">
                        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="panel = null" x-show="panel === 'anular'"
                            x-transition.opacity></div>
                        <form method="POST" action="{{ route('purchase_requests.cancel', $purchaseRequest) }}"
                            x-show="panel === 'anular'"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
                            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                            class="relative w-full max-w-lg space-y-4 rounded-3xl bg-white p-6 shadow-2xl dark:bg-slate-900 dark:border dark:border-slate-800">
                            @csrf
                            <input type="hidden" name="lock_version" value="{{ $purchaseRequest->lock_version }}">

                            <div class="flex items-start gap-4">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-rose-100 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                </div>
                                <div>
                                    <h2 id="titulo-anular" class="text-lg font-black text-slate-900 dark:text-white">Anular solicitud</h2>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        Queda registrada en el historial con su motivo. No se elimina.
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <label for="cancel-comment" class="block text-xs font-bold text-slate-700 dark:text-slate-200">
                                    Motivo <span class="text-rose-600">*</span>
                                </label>
                                <textarea id="cancel-comment" name="comment" rows="3" required
                                    class="w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-rose-500 focus:ring-rose-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                    placeholder="Explica por qué se anula."></textarea>
                                @error('comment') <p class="text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="flex flex-wrap justify-end gap-2.5 pt-2">
                                <button type="button" @click="panel = null" class="min-h-11 rounded-xl px-4 text-sm font-bold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Cancelar</button>
                                <button type="submit" class="min-h-11 rounded-xl bg-rose-600 px-5 text-sm font-extrabold text-white shadow-sm hover:bg-rose-700">Confirmar anulación</button>
                            </div>
                        </form>
                    </div>
                </template>
            @endcan

            @can('requestCancellation', $purchaseRequest)
                @if(! $purchaseRequest->cancellation_requested_at)
                    <template x-teleport="body">
                        <div x-show="panel === 'pedir'" x-cloak role="dialog" aria-modal="true" aria-labelledby="titulo-pedir"
                            @keydown.escape.window="panel = null"
                            class="fixed inset-0 z-[100] flex items-end justify-center p-4 sm:items-center">
                            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="panel = null" x-show="panel === 'pedir'"
                                x-transition.opacity></div>
                            <form method="POST" action="{{ route('purchase_requests.request_cancellation', $purchaseRequest) }}"
                                x-show="panel === 'pedir'"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
                                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                                class="relative w-full max-w-lg space-y-4 rounded-3xl bg-white p-6 shadow-2xl dark:bg-slate-900 dark:border dark:border-slate-800">
                                @csrf
                                <div class="flex items-start gap-4">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                    </div>
                                    <div>
                                        <h2 id="titulo-pedir" class="text-lg font-black text-slate-900 dark:text-white">Pedir anulación</h2>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                            Como ya fue enviada, la anulación la decide Compras. Tu petición queda registrada.
                                        </p>
                                    </div>
                                </div>

                                <div class="space-y-1.5">
                                    <label for="request-cancel-comment" class="block text-xs font-bold text-slate-700 dark:text-slate-200">
                                        Motivo <span class="text-rose-600">*</span>
                                    </label>
                                    <textarea id="request-cancel-comment" name="comment" rows="3" required
                                        class="w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                        placeholder="Explica por qué ya no se necesita."></textarea>
                                    @error('comment') <p class="text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
                                </div>

                                <div class="flex flex-wrap justify-end gap-2.5 pt-2">
                                    <button type="button" @click="panel = null" class="min-h-11 rounded-xl px-4 text-sm font-bold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Cancelar</button>
                                    <button type="submit" class="min-h-11 rounded-xl bg-slate-900 px-5 text-sm font-extrabold text-white shadow-sm hover:bg-black dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white">Pedir anulación</button>
                                </div>
                            </form>
                        </div>
                    </template>
                @endif
            @endcan
        </section>

        {{-- ── FULL WIDTH METRICS RIBBON (4 KPI Cards) ──────────────────── --}}
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumen ejecutivo">
            {{-- KPI 1: Estado del flujo --}}
            <div class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-black uppercase tracking-widest text-slate-400">Estado del flujo</span>
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl shadow-sm {{ $statusClasses }}">{{ $statusIcon }}</span>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">{{ $statusLabel }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        @if($rawStatus === 'approved')
                            Autorizada · Lista para cotizaciones y Odoo
                        @elseif($rawStatus === 'draft')
                            Borrador interno · No enviada a revisión
                        @elseif($rawStatus === 'submitted')
                            Esperando resolución de Compras
                        @elseif($rawStatus === 'completed')
                            Compra cerrada · No queda nada pendiente
                        @elseif($rawStatus === 'cancelled')
                            Anulada · No sigue su curso
                        @elseif($rawStatus === 'rejected')
                            Rechazada · No sigue su curso
                        @else
                            Ciclo de revisión en curso (v{{ $purchaseRequest->revision_number }})
                        @endif
                    </p>
                </div>
            </div>

            {{-- KPI 2: Total partidas --}}
            <div class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-black uppercase tracking-widest text-slate-400">Volumen de ítems</span>
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600 shadow-sm dark:bg-blue-950/60 dark:text-blue-400">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-black tracking-tight tabular-nums text-slate-900 dark:text-white">{{ $itemsCount }} {{ \Illuminate\Support\Str::plural('partida', $itemsCount) }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ $hayPrecio ? 'Partidas con valorización ingresada' : 'Líneas pendientes de precio del proveedor' }}
                    </p>
                </div>
            </div>

            {{-- KPI 3: Área / Solicitante --}}
            <div class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-black uppercase tracking-widest text-slate-400">Área requirente</span>
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-violet-50 text-violet-600 shadow-sm dark:bg-violet-950/60 dark:text-violet-400">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                    </div>
                </div>
                <div class="mt-3">
                    <p class="truncate text-xl font-black tracking-tight text-slate-900 dark:text-white" title="{{ $purchaseRequest->department ?: 'General' }}">
                        {{ $purchaseRequest->department ?: 'General' }}
                    </p>
                    <p class="mt-1 truncate text-xs font-semibold text-slate-500 dark:text-slate-400" title="{{ $requesterName }}">
                        Por {{ $requesterName }}
                    </p>
                </div>
            </div>

            {{-- KPI 4: Valorización total --}}
            <div class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-black uppercase tracking-widest text-slate-400">Valor estimado</span>
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 shadow-sm dark:bg-emerald-950/60 dark:text-emerald-400">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                </div>
                <div class="mt-3">
                    @if($hasTotal && $totalAmount > 0)
                        <p class="text-2xl font-black tracking-tight tabular-nums text-slate-900 dark:text-white">
                            {{ $simbolo }}{{ number_format($totalAmount, 0, ',', '.') }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Neto {{ $simbolo }}{{ number_format($neto, 0, ',', '.') }} + IVA ({{ number_format($tasa * 100) }}%)
                        </p>
                    @else
                        <p class="text-xl font-black tracking-tight text-slate-500 dark:text-slate-400">
                            Sin precios aún
                        </p>
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                            Se actualiza con la cotización recibida
                        </p>
                    @endif
                </div>
            </div>
        </section>

        {{-- ── TABS Y CONTENIDO PRINCIPAL FULL SCREEN (ALPINE VISTA) ────── --}}
        <div x-data="{ vista: 'solicitud', itemFilter: '', itemStatus: 'all' }">
            
            {{-- Barra de pestañas tipo Segmented Control --}}
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200/90 bg-white p-2 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center gap-1.5">
                    {{-- Tab Solicitud --}}
                    <button type="button" @click="vista = 'solicitud'"
                        class="inline-flex min-h-11 items-center gap-2 rounded-xl px-5 text-sm font-black transition-all"
                        :class="vista === 'solicitud'
                            ? 'bg-blue-600 text-white shadow-md shadow-blue-500/25'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a3 3 0 006 0M9 5a3 3 0 016 0m-7 7h8m-8 4h5" /></svg>
                        <span>Solicitud</span>
                        <span class="rounded-full px-2 py-0.5 text-xs font-black"
                              :class="vista === 'solicitud' ? 'bg-white/25 text-white' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'">
                            {{ $itemsCount }}
                        </span>
                    </button>

                    {{-- Tab Quién conviene --}}
                    @if($cuadricula)
                        <button type="button" @click="vista = 'comparar'"
                            class="inline-flex min-h-11 items-center gap-2 rounded-xl px-5 text-sm font-black transition-all"
                            :class="vista === 'comparar'
                                ? 'bg-blue-600 text-white shadow-md shadow-blue-500/25'
                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'">
                            <svg class="h-4 w-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                            <span>Quién conviene</span>
                            <span class="rounded-full px-2 py-0.5 text-xs font-black"
                                  :class="vista === 'comparar' ? 'bg-white/25 text-white' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'"
                                  title="{{ count($cuadricula->filas) }} partidas comparadas">
                                {{ count($cuadricula->filas) }}
                            </span>
                        </button>
                    @endif

                    {{-- Tab Cotizaciones: donde entran, subidas o dictadas --}}
                    <button type="button" @click="vista = 'cotizaciones'"
                        class="inline-flex min-h-11 items-center gap-2 rounded-xl px-5 text-sm font-black transition-all"
                        :class="vista === 'cotizaciones'
                            ? 'bg-blue-600 text-white shadow-md shadow-blue-500/25'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" /></svg>
                        <span>Cotizaciones</span>
                        @if(count($comparaciones) > 0)
                            <span class="rounded-full px-2 py-0.5 text-xs font-black"
                                  :class="vista === 'cotizaciones' ? 'bg-white/25 text-white' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'">
                                {{ count($comparaciones) }}
                            </span>
                        @endif
                    </button>

                    {{-- Tabs por cotización recibida --}}
                    @foreach ($comparables as $indice => $comparacion)
                        @php
                            $lectura = $comparacion['ingestion'];
                            $resultado = $comparacion['resultado'];
                            $cantCot = $resultado->cruzadas();
                        @endphp
                        <button type="button" @click="vista = 'cot{{ $indice }}'"
                            class="inline-flex min-h-11 items-center gap-2 rounded-xl px-4 py-1 text-left transition-all"
                            :class="vista === 'cot{{ $indice }}'
                                ? 'bg-blue-600 text-white shadow-md shadow-blue-500/25'
                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'">
                            <div class="flex flex-col">
                                <span class="text-xs font-black leading-tight">{{ \Illuminate\Support\Str::limit($lectura->supplier_name ?: 'Sin identificar', 20) }}</span>
                                <span class="text-[10px] font-bold opacity-80">{{ $resultado->estadoCorto() }}</span>
                            </div>
                            <span class="rounded-full px-2 py-0.5 text-xs font-black"
                                  :class="vista === 'cot{{ $indice }}' ? 'bg-white/25 text-white' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'"
                                  title="{{ $cantCot }} de {{ $resultado->partidas() }} partidas cruzadas">
                                {{ $cantCot }}
                            </span>
                        </button>
                    @endforeach
                </div>

                {{-- Status rápido en el header de tabs --}}
                <div class="hidden items-center gap-3 pr-2 sm:flex text-xs font-semibold text-slate-500 dark:text-slate-400">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        {{ $itemsCount }} partidas cargadas
                    </span>
                </div>
            </div>

            {{-- ── GRID LAYOUT FULL WIDTH (8 COLS IZQUIERDA / 4 COLS DERECHA) ─ --}}
            <div class="grid gap-6 xl:grid-cols-12">

                {{-- COLUMNA PRINCIPAL (TABLA DE PARTIDAS Y COMPARADOR) --}}
                <div class="space-y-6" :class="vista === 'solicitud' ? 'xl:col-span-8 2xl:col-span-9' : 'xl:col-span-12'">

                    {{-- ── VISTA 1: PARTIDAS DE LA SOLICITUD ── --}}
                    <div x-show="vista === 'solicitud'" class="space-y-6">
                        <section class="overflow-hidden rounded-3xl border border-slate-200/90 bg-white shadow-sm transition-all dark:border-slate-800 dark:bg-slate-900">
                            
                            {{-- Header de la tabla con buscador y filtros en tiempo real --}}
                            <div class="flex flex-col gap-4 border-b border-slate-200/80 p-5 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="flex items-center gap-3">
                                        <h2 class="text-lg font-black tracking-tight text-slate-900 dark:text-white">Partidas</h2>
                                        <span class="inline-flex items-center gap-1.5 rounded-xl border border-blue-200/80 bg-blue-50 px-3 py-1 font-mono text-xs font-black text-blue-700 shadow-sm dark:border-blue-800/80 dark:bg-blue-950/60 dark:text-blue-300">
                                            <span class="h-2 w-2 rounded-full bg-blue-600"></span>
                                            <span>{{ $itemsCount }} {{ \Illuminate\Support\Str::plural('ítem', $itemsCount) }}</span>
                                        </span>
                                    </div>
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Listado completo de insumos, equipos y servicios solicitados</p>
                                </div>

                                {{-- Barra de herramientas de la tabla --}}
                                <div class="flex flex-wrap items-center gap-3">
                                    {{-- Buscador instantáneo --}}
                                    <div class="relative min-w-[240px] flex-1 sm:w-72">
                                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                        </div>
                                        <input type="text" x-model="itemFilter" placeholder="Buscar en las 19 partidas..."
                                            class="h-10 w-full rounded-2xl border border-slate-200 bg-slate-50/80 pl-10 pr-3.5 text-xs font-medium text-slate-900 placeholder-slate-400 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:focus:bg-slate-900">
                                    </div>

                                    @if(filled($purchaseRequest->total()))
                                        <div class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-3.5 py-2 text-xs font-bold text-slate-700 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                            <span>Neto: {{ $simbolo }}{{ number_format($neto, 0, ',', '.') }}</span>
                                            <span class="text-slate-300 dark:text-slate-600">·</span>
                                            <span class="text-blue-600 dark:text-blue-400">Total: {{ $simbolo }}{{ number_format($neto * (1 + $tasa), 0, ',', '.') }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            @if($purchaseRequest->hasPartialPricing())
                                <div class="flex items-center gap-3 border-b border-amber-300/80 bg-amber-50/80 px-6 py-3 text-xs font-bold text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">
                                    <svg class="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                    <span>El total no incluye todas las partidas: algunas no tienen precio.</span>
                                </div>
                            @endif

                            {{-- Vista móvil (Tarjetas estructuradas) --}}
                            <div class="divide-y divide-slate-100 dark:divide-slate-800 md:hidden">
                                @foreach($purchaseRequest->items as $index => $item)
                                    <article class="p-4 transition hover:bg-blue-50/30 dark:hover:bg-slate-800/40"
                                        x-show="!itemFilter || '{{ addslashes(mb_strtolower($item->product_service)) }}'.includes(itemFilter.toLowerCase()) || '{{ addslashes(mb_strtolower($item->specification ?? '')) }}'.includes(itemFilter.toLowerCase())">
                                        <div class="flex gap-3">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-blue-50 font-mono text-xs font-black text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 shadow-sm">
                                                {{ $index + 1 }}
                                            </span>
                                            <div class="min-w-0 flex-1 space-y-1.5">
                                                <p class="font-bold text-slate-900 dark:text-white leading-snug">{{ $item->product_service }}</p>
                                                @if(filled($item->specification))
                                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $item->specification }}</p>
                                                @endif
                                                <div class="flex flex-wrap items-center gap-2 pt-1 text-xs">
                                                    <span class="inline-flex items-center rounded-xl bg-slate-100 px-3 py-1 font-bold text-slate-800 dark:bg-slate-800 dark:text-slate-200">
                                                        {{ rtrim(rtrim(number_format((float) $item->quantity, 3, ',', '.'), '0'), ',') }} {{ $item->unit }}
                                                    </span>
                                                    @if(filled($item->quantity_note))
                                                        <span class="text-xs text-slate-500 dark:text-slate-400">({{ $item->quantity_note }})</span>
                                                    @endif
                                                    @if(filled($item->unit_price))
                                                        <span class="rounded-xl bg-emerald-50 px-3 py-1 font-bold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                                                            $ {{ number_format((float) $item->unit_price, 0, ',', '.') }} c/u · Total $ {{ number_format((float) $item->lineTotal(), 0, ',', '.') }}
                                                        </span>
                                                    @endif
                                                </div>
                                                @if(filled($item->destination))
                                                    <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Destino: {{ $item->destination }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>

                            {{-- Vista Desktop (Data Grid Full Width con alta legibilidad) --}}
                            <div class="hidden overflow-x-auto md:block">
                                <table class="w-full text-left text-sm border-collapse">
                                    <thead class="border-b border-slate-200 bg-slate-50/90 text-[11px] font-black uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-950/70 dark:text-slate-400">
                                        <tr>
                                            <th class="w-14 px-6 py-3.5 text-center">N°</th>
                                            <th class="px-6 py-3.5">Producto / Servicio</th>
                                            @if($hayEspecificacion)
                                                <th class="px-6 py-3.5">Especificación Técnica</th>
                                            @endif
                                            <th class="w-40 px-6 py-3.5 text-right">Cantidad</th>
                                            @if($hayPrecio)
                                                <th class="w-40 px-6 py-3.5 text-right">Precio unit.</th>
                                                <th class="w-40 px-6 py-3.5 text-right">Total</th>
                                            @endif
                                            @if($hayDestino)
                                                <th class="px-6 py-3.5">Destino</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                                        @foreach($purchaseRequest->items as $index => $item)
                                            <tr class="transition-colors hover:bg-blue-50/40 dark:hover:bg-blue-950/20"
                                                x-show="!itemFilter || '{{ addslashes(mb_strtolower($item->product_service)) }}'.includes(itemFilter.toLowerCase()) || '{{ addslashes(mb_strtolower($item->specification ?? '')) }}'.includes(itemFilter.toLowerCase())">
                                                <td class="px-6 py-4 text-center">
                                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-xl bg-slate-100 font-mono text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                        {{ $index + 1 }}
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="font-bold text-slate-900 dark:text-white leading-snug">
                                                        {{ $item->product_service }}
                                                    </div>
                                                    @if(filled($item->quantity_note))
                                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $item->quantity_note }}</p>
                                                    @endif
                                                </td>
                                                @if($hayEspecificacion)
                                                    <td class="px-6 py-4 text-xs text-slate-600 dark:text-slate-300">
                                                        {{ $item->specification ?: '—' }}
                                                    </td>
                                                @endif
                                                <td class="px-6 py-4 text-right whitespace-nowrap font-mono">
                                                    <span class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-black text-slate-800 shadow-sm dark:bg-slate-800 dark:text-slate-100">
                                                        <span>{{ rtrim(rtrim(number_format((float) $item->quantity, 3, ',', '.'), '0'), ',') }}</span>
                                                        <span class="font-sans font-semibold text-slate-500 dark:text-slate-400">{{ $item->unit }}</span>
                                                    </span>
                                                </td>
                                                @if($hayPrecio)
                                                    <td class="px-6 py-4 text-right font-mono text-xs text-slate-600 tabular-nums dark:text-slate-300">
                                                        {{ filled($item->unit_price) ? '$ '.number_format((float) $item->unit_price, 0, ',', '.') : '—' }}
                                                    </td>
                                                    <td class="px-6 py-4 text-right font-mono text-xs font-black text-slate-900 tabular-nums dark:text-white">
                                                        {{ filled($item->unit_price) ? '$ '.number_format((float) $item->lineTotal(), 0, ',', '.') : '—' }}
                                                    </td>
                                                @endif
                                                @if($hayDestino)
                                                    <td class="px-6 py-4 text-xs text-slate-600 dark:text-slate-300">
                                                        {{ $item->destination ?: '—' }}
                                                    </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            {{-- Footer resumen de partidas --}}
                            <div class="flex items-center justify-between border-t border-slate-200/80 bg-slate-50/80 px-6 py-4 text-xs font-semibold text-slate-600 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400">
                                <div class="flex items-center gap-2">
                                    <span>Mostrando {{ $itemsCount }} partidas de la solicitud</span>
                                </div>
                                @if($hayPrecio && $hasTotal)
                                    <div class="font-mono text-sm font-black text-slate-900 dark:text-white">
                                        Total Solicitud: {{ $simbolo }}{{ number_format($totalAmount, 0, ',', '.') }}
                                    </div>
                                @else
                                    <span class="italic text-slate-400">Precios pendientes de valorización por proveedor</span>
                                @endif
                            </div>
                        </section>
                    </div>

                    {{-- ── VISTA: COTIZACIONES ──
                         Por donde entran. Subir el documento es lo normal,
                         pero cuando la compra ya está hecha y la factura en la
                         mano, escanear un papel para anotar cuatro precios que
                         ya se saben es trabajo inventado: ahí se dicta. --}}
                    <div x-show="vista === 'cotizaciones'" x-cloak class="space-y-4">
                        <section class="overflow-hidden rounded-3xl border border-slate-200/90 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div class="border-b border-slate-200/80 p-6 dark:border-slate-800">
                                <h2 class="text-lg font-black text-slate-900 dark:text-white">Cotizaciones de esta solicitud</h2>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                    Lo que entre aquí se contrasta con tus {{ $itemsCount }} partidas y queda como respaldo del precio.
                                </p>
                            </div>

                            <div class="grid gap-6 p-6 lg:grid-cols-2">
                                {{-- Subir el documento --}}
                                <form method="POST" action="{{ route('purchase_requests.quotes.store', $purchaseRequest) }}"
                                    enctype="multipart/form-data"
                                    class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50/60 p-5 dark:border-slate-800 dark:bg-slate-950/40">
                                    @csrf
                                    <div class="flex items-center gap-2.5">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-sky-600 text-white">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg>
                                        </div>
                                        <h3 class="text-sm font-black text-slate-900 dark:text-white">Subir el documento</h3>
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        El PDF o la foto que te mandó el proveedor. Lo leemos y lo dejamos comparado.
                                    </p>
                                    <input type="file" name="quote" accept=".pdf,.jpg,.jpeg,.png" required
                                        class="block w-full rounded-xl border border-slate-300 bg-white text-xs text-slate-600 file:mr-3 file:min-h-10 file:cursor-pointer file:border-0 file:bg-slate-100 file:px-4 file:text-xs file:font-bold file:text-slate-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300 dark:file:bg-slate-800 dark:file:text-slate-200">
                                    @error('quote')
                                        <p class="text-xs font-bold text-rose-600 dark:text-rose-400">{{ $message }}</p>
                                    @enderror
                                    <button type="submit"
                                        class="mt-auto inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-sky-600 px-4 text-sm font-bold text-white shadow-sm hover:bg-sky-700">
                                        Subir y contrastar
                                    </button>
                                </form>

                                {{-- Dictarla --}}
                                <form method="POST" action="{{ route('purchase_requests.quotes.compose', $purchaseRequest) }}"
                                    class="flex flex-col gap-3 rounded-2xl border border-indigo-200 bg-indigo-50/50 p-5 dark:border-indigo-900/60 dark:bg-indigo-950/20">
                                    @csrf
                                    <div class="flex items-center gap-2.5">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-600 text-white">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                        </div>
                                        <h3 class="text-sm font-black text-slate-900 dark:text-white">Escribirla tú</h3>
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        Si ya compraste y tienes la factura, dicta los precios y nos saltamos el papel.
                                    </p>

                                    <div class="grid gap-2.5 sm:grid-cols-2">
                                        <label class="block">
                                            <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">Proveedor</span>
                                            <input type="text" name="supplier_name" maxlength="255" value="{{ old('supplier_name') }}"
                                                placeholder="MAX SERVICE"
                                                class="mt-1 block w-full rounded-xl border-slate-300 bg-white py-2 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                        </label>
                                        <label class="block">
                                            <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">N° documento</span>
                                            <input type="text" name="document_number" maxlength="60" value="{{ old('document_number') }}"
                                                placeholder="Factura 12345"
                                                class="mt-1 block w-full rounded-xl border-slate-300 bg-white py-2 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                        </label>
                                    </div>

                                    <fieldset class="flex flex-wrap gap-4 text-sm">
                                        <legend class="sr-only">Qué es</legend>
                                        <label class="inline-flex items-center gap-2 font-semibold text-slate-700 dark:text-slate-200">
                                            <input type="radio" name="kind" value="cotizacion" checked class="border-slate-300 text-indigo-600 dark:border-slate-600 dark:bg-slate-950">
                                            Es una cotización
                                        </label>
                                        <label class="inline-flex items-center gap-2 font-semibold text-slate-700 dark:text-slate-200">
                                            <input type="radio" name="kind" value="factura" class="border-slate-300 text-indigo-600 dark:border-slate-600 dark:bg-slate-950">
                                            Ya lo compré
                                        </label>
                                    </fieldset>

                                    <label class="block">
                                        <span class="sr-only">Lo que compraste o te cotizaron</span>
                                        <textarea name="text" rows="5" required minlength="3" maxlength="4000"
                                            placeholder="3 correas a 12.500 cada una, 2 filtros de aceite a 8.900 y 10 litros de cloro a 1.290 el litro"
                                            class="mt-1 block w-full rounded-xl border-slate-300 bg-white py-2 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">{{ old('text') }}</textarea>
                                    </label>
                                    @error('text')
                                        <p class="text-xs font-bold text-rose-600 dark:text-rose-400">{{ $message }}</p>
                                    @enderror

                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                        Nombra el producto, la cantidad y el precio por unidad. Se guarda tal cual lo escribas, para poder revisarlo después.
                                    </p>

                                    <button type="submit"
                                        class="mt-auto inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 text-sm font-bold text-white shadow-sm hover:bg-indigo-700">
                                        Anotarla y contrastar
                                    </button>
                                </form>
                            </div>
                        </section>

                        {{-- Lo que ya está cargado --}}
                        @if(count($comparaciones) > 0)
                            <section class="overflow-hidden rounded-3xl border border-slate-200/90 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                                <div class="border-b border-slate-200/80 px-6 py-4 dark:border-slate-800">
                                    <h3 class="text-sm font-black text-slate-900 dark:text-white">
                                        {{-- El pluralizador de Laravel es inglés: «cotización» le sale «cotizacións». --}}
                                        {{ count($comparaciones) }} {{ count($comparaciones) === 1 ? 'cotización cargada' : 'cotizaciones cargadas' }}
                                    </h3>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-sm">
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                                            @foreach ($comparaciones as $comparacion)
                                                @php
                                                    $lectura = $comparacion['ingestion'];
                                                    $resultado = $comparacion['resultado'];
                                                    $dictada = $lectura->source_kind === \App\Services\PurchaseRequests\Reading\PurchaseRequestSourceKind::TEXT;
                                                    $comprada = (bool) ($lectura->extracted['already_purchased'] ?? false);
                                                    $leyendo = in_array($lectura->status, [\App\Models\PurchaseRequestIngestion::PENDING, \App\Models\PurchaseRequestIngestion::PROCESSING], true);
                                                @endphp
                                                <tr class="align-top hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                                    <td class="px-6 py-4">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <span class="font-black text-slate-900 dark:text-white">{{ $lectura->supplier_name ?: 'Proveedor sin identificar' }}</span>
                                                            @if($comprada)
                                                                <span class="rounded-lg bg-emerald-100 px-2 py-0.5 text-[10px] font-black uppercase text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300">ya comprado</span>
                                                            @endif
                                                            @if($dictada)
                                                                <span class="rounded-lg bg-indigo-100 px-2 py-0.5 text-[10px] font-black uppercase text-indigo-800 dark:bg-indigo-950/80 dark:text-indigo-300">escrita a mano</span>
                                                            @endif
                                                        </div>
                                                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $lectura->original_name }}</p>
                                                    </td>
                                                    <td class="px-6 py-4 text-xs">
                                                        @if($leyendo)
                                                            <span class="font-bold text-amber-600 dark:text-amber-400">Leyéndola…</span>
                                                        @elseif($resultado->elDocumentoNoAporto())
                                                            <span class="font-bold text-rose-700 dark:text-rose-400">No se pudo leer</span>
                                                        @else
                                                            <span class="font-bold text-slate-700 dark:text-slate-200">{{ $resultado->cruzadas() }} de {{ $resultado->partidas() }} cruzadas</span>
                                                            <span class="block text-slate-500 dark:text-slate-400">{{ $resultado->resumen() }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="w-40 whitespace-nowrap px-6 py-4 text-right">
                                                        <a href="{{ route('purchase_requests.ingestions.download', $lectura) }}"
                                                            class="text-xs font-bold text-sky-700 underline decoration-dotted underline-offset-2 hover:text-sky-900 dark:text-sky-400">
                                                            {{ $dictada ? 'Ver lo escrito' : 'Ver documento' }}
                                                        </a>
                                                        <form method="POST" action="{{ route('purchase_requests.quotes.destroy', [$purchaseRequest, $lectura]) }}" class="mt-1">
                                                            @csrf @method('DELETE')
                                                            <button type="submit" class="text-xs font-bold text-slate-400 underline decoration-dotted underline-offset-2 hover:text-rose-600 dark:hover:text-rose-400">
                                                                Quitar
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        @endif
                    </div>
                    {{-- ── VISTA 2: CUADRO COMPARATIVO («QUIÉN CONVIENE») ──
                         Cada celda dice cuánto cotizó, a qué precio y cuánto
                         suma esa línea, para no tener que multiplicar de cabeza
                         diecinueve veces antes de decidir a quién comprarle. --}}
                    @if($cuadricula)
                        @php
                            $plata = fn (?float $v) => $v === null ? '—' : '$ '.number_format($v, 0, ',', '.');
                            $numero = fn (?float $v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
                            $mejorTotal = collect($cuadricula->totales)->pluck('total')->filter(fn ($t) => $t > 0)->min();
                        @endphp
                        <div x-show="vista === 'comparar'" x-cloak class="space-y-4">
                            <section class="overflow-hidden rounded-3xl border border-slate-200/90 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                                <div class="border-b border-slate-200/80 p-6 dark:border-slate-800">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <h2 class="text-lg font-black text-slate-900 dark:text-white">Quién conviene</h2>
                                        <span class="inline-flex items-center gap-1.5 rounded-xl border border-amber-200/80 bg-amber-50 px-3 py-1 font-mono text-xs font-black text-amber-700 shadow-sm dark:border-amber-800/80 dark:bg-amber-950/60 dark:text-amber-300">
                                            <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                                            <span>{{ count($cuadricula->filas) }} {{ \Illuminate\Support\Str::plural('partida', count($cuadricula->filas)) }}</span>
                                        </span>
                                    </div>
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                        Tus partidas contra cada proveedor: cantidad cotizada, precio unitario y lo que suma esa línea. El unitario más barato va marcado.
                                    </p>
                                </div>

                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-sm">
                                        <thead class="border-b border-slate-200/80 bg-slate-50/80 text-[11px] font-black uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400">
                                            <tr>
                                                <th class="w-14 px-6 py-3.5 text-center">N°</th>
                                                <th class="px-6 py-3.5">Partida</th>
                                                <th class="w-28 whitespace-nowrap px-6 py-3.5 text-right">Pediste</th>
                                                @foreach ($cuadricula->proveedores as $proveedor)
                                                    <th class="w-56 px-6 py-3.5 text-right font-black">
                                                        <span class="block truncate text-slate-700 dark:text-slate-200">{{ $proveedor['nombre'] }}</span>
                                                        <span class="block text-[10px] font-semibold normal-case tracking-normal text-slate-400">cant · unitario · total</span>
                                                    </th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                                            @foreach ($cuadricula->filas as $fila)
                                                <tr class="align-top transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                                    <td class="px-6 py-4 text-center">
                                                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-xl bg-slate-100 font-mono text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                            {{ $loop->iteration }}
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 font-bold text-slate-800 dark:text-slate-100">{{ $fila['partida'] }}</td>
                                                    <td class="whitespace-nowrap px-6 py-4 text-right font-mono text-xs tabular-nums text-slate-500 dark:text-slate-400">
                                                        {{ $numero($fila['cantidad']) }}
                                                        <span class="block font-sans text-[10px] text-slate-400">{{ $fila['unidad'] }}</span>
                                                    </td>
                                                    @foreach ($fila['ofertas'] as $i => $oferta)
                                                        <td class="whitespace-nowrap px-6 py-4 text-right font-mono text-xs tabular-nums {{ $fila['masBarato'] === $i ? 'bg-emerald-50/60 dark:bg-emerald-950/20' : '' }}">
                                                            @if($oferta === null)
                                                                <span class="font-sans italic text-slate-300 dark:text-slate-600">no la cotizó</span>
                                                            @else
                                                                <span class="block font-black {{ $fila['masBarato'] === $i ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-900 dark:text-white' }}">
                                                                    {{ $plata($oferta['total']) }}
                                                                </span>
                                                                <span class="block text-[11px] text-slate-500 dark:text-slate-400">
                                                                    {{ $numero($oferta['cantidad']) }} × {{ $plata($oferta['unitario']) }}
                                                                </span>
                                                                @if($fila['masBarato'] === $i)
                                                                    <span class="mt-1 inline-flex items-center rounded-lg bg-emerald-100 px-2 py-0.5 font-sans text-[10px] font-black uppercase text-emerald-800 shadow-sm dark:bg-emerald-950/80 dark:text-emerald-300">más barato</span>
                                                                @endif
                                                                @if($oferta['porConfirmar'])
                                                                    <span class="mt-1 block font-sans text-[10px] font-bold text-indigo-600 dark:text-indigo-400">por confirmar</span>
                                                                @endif
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot class="border-t-2 border-slate-200 bg-slate-50/80 dark:border-slate-700 dark:bg-slate-950/60">
                                            <tr>
                                                <td colspan="3" class="px-6 py-4 text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                                    Suma de lo cotizado
                                                </td>
                                                @foreach ($cuadricula->totales as $total)
                                                    <td class="whitespace-nowrap px-6 py-4 text-right font-mono tabular-nums">
                                                        <span class="block text-base font-black {{ $mejorTotal !== null && abs($total['total'] - $mejorTotal) < 0.005 ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-900 dark:text-white' }}">
                                                            {{ $plata($total['total']) }}
                                                        </span>
                                                        @if($total['faltan'] > 0)
                                                            <span class="block font-sans text-[11px] font-bold text-amber-700 dark:text-amber-400">
                                                                sin cotizar: {{ $total['faltan'] }} {{ \Illuminate\Support\Str::plural('partida', $total['faltan']) }}
                                                            </span>
                                                        @endif
                                                        @if($total['porConfirmar'] > 0)
                                                            <span class="block font-sans text-[11px] font-bold text-indigo-600 dark:text-indigo-400">
                                                                incluye {{ $total['porConfirmar'] }} {{ \Illuminate\Support\Str::plural('pareja', $total['porConfirmar']) }} por confirmar
                                                            </span>
                                                        @endif
                                                    </td>
                                                @endforeach
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <p class="border-t border-slate-200/80 px-6 py-3.5 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                                    Cada total usa la cantidad que ese proveedor cotizó, no la que pediste. Los totales no son comparables entre sí mientras a alguno le falten partidas.
                                </p>
                            </section>

                            {{-- Lo que algún proveedor agregó por su cuenta. Fuera de
                                 la cuadrícula: dentro hacía que las otras columnas
                                 dijeran «no cotizó» sobre algo que nadie les pidió. --}}
                            @if(count($cuadricula->agregadas) > 0)
                                <section class="overflow-hidden rounded-3xl border border-slate-200/90 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                                    <div class="border-b border-slate-200/80 px-6 py-4 dark:border-slate-800">
                                        <h3 class="text-sm font-black text-slate-900 dark:text-white">
                                            Líneas que agregaron por su cuenta
                                        </h3>
                                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">No estaban en tu solicitud, así que no entran en la comparación ni en las sumas de arriba.</p>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left text-sm">
                                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                                                @foreach ($cuadricula->agregadas as $agregada)
                                                    <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                                        <td class="px-6 py-4 font-bold text-slate-800 dark:text-slate-100">{{ $agregada['texto'] }}</td>
                                                        <td class="w-56 px-6 py-4 text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $agregada['proveedor'] }}</td>
                                                        <td class="w-48 whitespace-nowrap px-6 py-4 text-right font-mono text-xs tabular-nums text-slate-600 dark:text-slate-300">
                                                            <span class="block font-black text-slate-900 dark:text-white">{{ $plata($agregada['total']) }}</span>
                                                            <span class="block text-[11px] text-slate-500 dark:text-slate-400">{{ $numero($agregada['cantidad']) }} × {{ $plata($agregada['unitario']) }}</span>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </section>
                            @endif
                        </div>
                    @endif

                    {{-- ── VISTAS 3+: DETALLE DE CADA COTIZACIÓN RECIBIDA ──
                         La tabla tiene exactamente las partidas que pediste, en
                         tu orden, y nada más. Lo que el proveedor agregó por su
                         cuenta va aparte, abajo: sumarlo aquí convertía una
                         solicitud de 19 partidas en 33 filas. --}}
                    @foreach ($comparables as $indice => $comparacion)
                        @php
                            $lectura = $comparacion['ingestion'];
                            $resultado = $comparacion['resultado'];
                            $totalPartidasCot = $resultado->partidas();
                            $porConfirmar = $resultado->porConfirmar();
                        @endphp
                        <div x-show="vista === 'cot{{ $indice }}'" x-cloak class="space-y-4">
                            <section class="overflow-hidden rounded-3xl border border-slate-200/90 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                                <div class="flex flex-col gap-3 border-b border-slate-200/80 p-6 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-3">
                                            <h2 class="text-lg font-black text-slate-900 dark:text-white">
                                                {{ $lectura->supplier_name ?: 'Proveedor sin identificar' }}
                                            </h2>
                                            <span class="inline-flex items-center gap-1.5 rounded-xl border border-sky-200/80 bg-sky-50 px-3 py-1 font-mono text-xs font-black text-sky-700 shadow-sm dark:border-sky-800/80 dark:bg-sky-950/60 dark:text-sky-300">
                                                <span class="h-2 w-2 rounded-full bg-sky-500"></span>
                                                <span>{{ $resultado->cruzadas() }} de {{ $totalPartidasCot }} cruzadas</span>
                                            </span>
                                        </div>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $lectura->original_name }} · {{ $resultado->resumen() }}</p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2.5">
                                        @if($porConfirmar > 0)
                                            <form method="POST" action="{{ route('purchase_requests.quotes.confirm', [$purchaseRequest, $lectura]) }}">
                                                @csrf
                                                <button type="submit"
                                                    class="inline-flex min-h-10 items-center gap-2 rounded-xl bg-indigo-600 px-4 text-xs font-bold text-white shadow-sm hover:bg-indigo-700">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                                    Confirmar {{ $porConfirmar }} {{ \Illuminate\Support\Str::plural('pareja', $porConfirmar) }}
                                                </button>
                                            </form>
                                        @endif
                                        <a href="{{ route('purchase_requests.ingestions.download', $lectura) }}"
                                            class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M5 20h14a2 2 0 002-2v-1a2 2 0 00-2-2H5a2 2 0 00-2 2v1a2 2 0 002 2zM7 4h10v6H7z" /></svg>
                                            Ver documento original
                                        </a>
                                    </div>
                                </div>

                                @if($porConfirmar > 0)
                                    <p class="border-b border-indigo-100 bg-indigo-50/70 px-6 py-3 text-xs font-semibold text-indigo-900 dark:border-indigo-900/60 dark:bg-indigo-950/30 dark:text-indigo-200">
                                        {{ $porConfirmar }} {{ \Illuminate\Support\Str::plural('pareja', $porConfirmar) }} en violeta {{ $porConfirmar === 1 ? 'la propone' : 'las propone' }} el programa por la cantidad y el orden de los renglones, porque el nombre no alcanza para afirmarlo. Revísalas y confírmalas: quedan aprendidas para las próximas cotizaciones de este proveedor.
                                    </p>
                                @endif

                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-sm">
                                        <thead class="border-b border-slate-200/80 bg-slate-50/80 text-[11px] font-black uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400">
                                            <tr>
                                                <th class="w-14 px-6 py-3.5 text-center">N°</th>
                                                <th class="px-6 py-3.5 font-bold">Partida</th>
                                                <th class="w-40 whitespace-nowrap px-6 py-3.5 font-bold">Pediste</th>
                                                <th class="w-40 whitespace-nowrap px-6 py-3.5 font-bold">Cotizaron</th>
                                                <th class="hidden px-6 py-3.5 font-bold md:table-cell">Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                                            @foreach ($resultado->filas as $fila)
                                                @php
                                                    $tono = match ($fila->estado) {
                                                        'propuesta' => 'bg-indigo-50/50 dark:bg-indigo-950/20',
                                                        'sin_cotizar' => 'bg-rose-50/40 dark:bg-rose-950/10',
                                                        'difiere' => 'bg-amber-50/40 dark:bg-amber-950/10',
                                                        default => '',
                                                    };
                                                @endphp
                                                <tr class="align-top transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/40 {{ $tono }}">
                                                    <td class="px-6 py-4 text-center">
                                                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-xl bg-slate-100 font-mono text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                            {{ $loop->iteration }}
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 text-slate-800 dark:text-slate-100">
                                                        <span class="font-bold">{{ $fila->pedida?->product_service ?? '—' }}</span>
                                                        @if($fila->cotizada)
                                                            <span class="mt-1 flex items-start gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                                                                <span class="mt-px shrink-0 {{ $fila->esPropuesta() ? 'text-indigo-500' : 'text-slate-400' }}">↳</span>
                                                                <span>{{ $fila->cotizada['product_service'] ?? '—' }}</span>
                                                            </span>
                                                        @endif
                                                        @foreach ($fila->diferencias as $diferencia)
                                                            <span class="mt-1 block text-xs font-semibold text-amber-800 md:hidden dark:text-amber-300">{{ $diferencia }}</span>
                                                        @endforeach
                                                    </td>
                                                    <td class="whitespace-nowrap px-6 py-4 font-mono text-xs tabular-nums text-slate-600 dark:text-slate-300">
                                                        @if($fila->pedida)
                                                            {{ rtrim(rtrim(number_format((float) $fila->pedida->quantity, 2, ',', '.'), '0'), ',') }} {{ $fila->pedida->unit }}
                                                            @if($fila->pedida->unit_price !== null)
                                                                <span class="block text-[11px] text-slate-400">$ {{ number_format((float) $fila->pedida->unit_price, 0, ',', '.') }}</span>
                                                            @endif
                                                        @endif
                                                    </td>
                                                    <td class="whitespace-nowrap px-6 py-4 font-mono text-xs tabular-nums text-slate-600 dark:text-slate-300">
                                                        @if($fila->cotizada)
                                                            {{ $fila->cotizada['quantity'] ?? '—' }} {{ $fila->cotizada['unit'] ?? '' }}
                                                            @if(filled($fila->cotizada['unit_price'] ?? null))
                                                                <span class="block text-[11px] font-bold text-slate-800 dark:text-slate-200">$ {{ number_format((float) $fila->cotizada['unit_price'], 0, ',', '.') }}</span>
                                                            @endif
                                                        @else
                                                            <span class="font-sans italic text-slate-400">no la cotizaron</span>
                                                        @endif
                                                    </td>
                                                    <td class="hidden px-6 py-4 text-xs md:table-cell">
                                                        @if($fila->esPropuesta())
                                                            <form method="POST" action="{{ route('purchase_requests.quotes.link', [$purchaseRequest, $lectura]) }}"
                                                                class="flex flex-wrap items-center gap-2">
                                                                @csrf
                                                                <input type="hidden" name="quote_line" value="{{ $fila->cotizada['product_service'] ?? '' }}">
                                                                <input type="hidden" name="item_id" value="{{ $fila->pedida?->getKey() }}">
                                                                <input type="hidden" name="line_index" value="{{ $fila->renglon }}">
                                                                <button type="submit"
                                                                    class="inline-flex min-h-9 items-center gap-1.5 rounded-xl bg-indigo-600 px-3 text-xs font-bold text-white shadow-sm hover:bg-indigo-700">
                                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                                                    Sí, es esa
                                                                </button>
                                                                <span class="text-[11px] font-semibold text-indigo-700 dark:text-indigo-300">por confirmar</span>
                                                            </form>
                                                        @elseif($fila->estado === 'sin_cotizar')
                                                            <span class="font-bold text-rose-700 dark:text-rose-300">No la cotizaron</span>
                                                        @else
                                                            @forelse ($fila->diferencias as $diferencia)
                                                                <span class="block font-semibold {{ $fila->hayProblema ? 'text-amber-800 dark:text-amber-300' : 'text-slate-500 dark:text-slate-400' }}">{{ $diferencia }}</span>
                                                            @empty
                                                                <span class="font-bold text-emerald-600 dark:text-emerald-400">✓ Coincide</span>
                                                            @endforelse

                                                            {{-- Sólo donde hay algo guardado que borrar: un cruce
                                                                 que salió del parecido no se deshace, se corrige
                                                                 enseñando el correcto. --}}
                                                            @if($fila->aprendida)
                                                                <form method="POST" action="{{ route('purchase_requests.quotes.unlink', [$purchaseRequest, $lectura]) }}" class="mt-1.5">
                                                                    @csrf
                                                                    <input type="hidden" name="quote_line" value="{{ $fila->cotizada['product_service'] ?? '' }}">
                                                                    <input type="hidden" name="line_index" value="{{ $fila->renglon }}">
                                                                    <button type="submit" class="text-[11px] font-bold text-slate-400 underline decoration-dotted underline-offset-2 hover:text-rose-600 dark:hover:text-rose-400">
                                                                        Lo enseñaste tú · deshacer
                                                                    </button>
                                                                </form>
                                                            @endif
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot class="border-t border-slate-200/80 bg-slate-50/80 dark:border-slate-800 dark:bg-slate-950/60">
                                            <tr>
                                                <td colspan="5" class="px-6 py-3.5 text-xs font-semibold text-slate-600 dark:text-slate-400">
                                                    Tus {{ $totalPartidasCot }} {{ \Illuminate\Support\Str::plural('partida', $totalPartidasCot) }}, una por fila
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </section>

                            {{-- Lo que el proveedor agregó por su cuenta. Va aparte
                                 porque no es una partida tuya, pero hay que verlo:
                                 puede ser un flete, o algo que alguien olvidó pedir. --}}
                            @if(count($resultado->sobrantes) > 0)
                                <section class="overflow-hidden rounded-3xl border border-slate-200/90 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                                    <div class="border-b border-slate-200/80 px-6 py-4 dark:border-slate-800">
                                        <h3 class="text-sm font-black text-slate-900 dark:text-white">
                                            El proveedor agregó {{ count($resultado->sobrantes) }} {{ \Illuminate\Support\Str::plural('línea', count($resultado->sobrantes)) }} que no pediste
                                        </h3>
                                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Si alguna es una de tus partidas escrita con otras palabras, dísela y queda aprendida.</p>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left text-sm">
                                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                                                @foreach ($resultado->sobrantes as $fila)
                                                    <tr class="align-top hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                                        <td class="px-6 py-4 font-bold text-slate-800 dark:text-slate-100">{{ $fila->cotizada['product_service'] ?? '—' }}</td>
                                                        <td class="w-40 whitespace-nowrap px-6 py-4 font-mono text-xs tabular-nums text-slate-600 dark:text-slate-300">
                                                            {{ $fila->cotizada['quantity'] ?? '—' }} {{ $fila->cotizada['unit'] ?? '' }}
                                                            @if(filled($fila->cotizada['unit_price'] ?? null))
                                                                <span class="block text-[11px] font-bold text-slate-800 dark:text-slate-200">$ {{ number_format((float) $fila->cotizada['unit_price'], 0, ',', '.') }}</span>
                                                            @endif
                                                        </td>
                                                        <td class="px-6 py-4">
                                                            @if($purchaseRequest->items->isNotEmpty())
                                                                <form method="POST" action="{{ route('purchase_requests.quotes.link', [$purchaseRequest, $lectura]) }}"
                                                                    class="flex flex-wrap items-center gap-2">
                                                                    @csrf
                                                                    <input type="hidden" name="quote_line" value="{{ $fila->cotizada['product_service'] ?? '' }}">
                                                                    <input type="hidden" name="line_index" value="{{ $fila->renglon }}">
                                                                    <label class="sr-only" for="cruce-{{ $lectura->id }}-{{ $loop->index }}">¿Qué partida es?</label>
                                                                    <select id="cruce-{{ $lectura->id }}-{{ $loop->index }}" name="item_id" required
                                                                        class="min-h-9 max-w-56 rounded-xl border-slate-300 bg-white py-1 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                                                                        <option value="">¿Es alguna de tus partidas?</option>
                                                                        @foreach ($purchaseRequest->items as $partida)
                                                                            <option value="{{ $partida->getKey() }}">{{ Str::limit($partida->product_service, 44) }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                    <button type="submit"
                                                                        class="min-h-9 rounded-xl border border-slate-300 px-3 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                                                                        Es la misma
                                                                    </button>
                                                                </form>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </section>
                            @endif
                        </div>
                    @endforeach

                </div>

                {{-- COLUMNA LATERAL (CARDS DE GESTIÓN Y DETALLE) ── --}}
                <aside x-show="vista === 'solicitud'" class="space-y-6 xl:col-span-4 2xl:col-span-3">

                    {{-- Card: Información de la solicitud --}}
                    <section class="overflow-hidden rounded-3xl border border-slate-200/90 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="border-b border-slate-100 p-5 dark:border-slate-800">
                            <div class="flex items-center gap-2.5">
                                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/60 dark:text-blue-400">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </div>
                                <h2 class="text-sm font-black text-slate-900 dark:text-white">Información de la solicitud</h2>
                            </div>
                        </div>

                        <dl class="grid gap-x-4 gap-y-4 p-5 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-[11px] font-black uppercase tracking-wider text-slate-400">Departamento</dt>
                                <dd class="mt-1 font-bold text-slate-800 dark:text-slate-100">{{ $purchaseRequest->department ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-black uppercase tracking-wider text-slate-400">Fecha requerida</dt>
                                <dd class="mt-1 font-bold text-slate-800 dark:text-slate-100">{{ $formatDate($purchaseRequest->required_date) }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-black uppercase tracking-wider text-slate-400">Solicitante</dt>
                                <dd class="mt-1 font-bold text-slate-800 dark:text-slate-100">{{ $requesterName }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-black uppercase tracking-wider text-slate-400">Solicitado para</dt>
                                <dd class="mt-1 font-bold text-slate-800 dark:text-slate-100">{{ $purchaseRequest->requested_for_name ?: $purchaseRequest->requested_for ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-black uppercase tracking-wider text-slate-400">Centro de costo</dt>
                                <dd class="mt-1 font-bold text-slate-800 dark:text-slate-100">{{ $purchaseRequest->cost_center ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-black uppercase tracking-wider text-slate-400">Lugar de entrega o uso</dt>
                                <dd class="mt-1 font-bold text-slate-800 dark:text-slate-100">{{ $purchaseRequest->delivery_location ?: '—' }}</dd>
                            </div>

                            @if(filled($purchaseRequest->urgent_reason))
                                <div class="sm:col-span-2 rounded-2xl bg-rose-50/70 p-3.5 dark:bg-rose-950/30">
                                    <dt class="text-[11px] font-black uppercase tracking-wider text-rose-700 dark:text-rose-300">Justificación de urgencia</dt>
                                    <dd class="mt-1 whitespace-pre-line text-xs font-medium text-rose-900 dark:text-rose-100">{{ $purchaseRequest->urgent_reason }}</dd>
                                </div>
                            @endif

                            @if(filled($purchaseRequest->internal_notes ?? $purchaseRequest->notes))
                                <div class="sm:col-span-2 rounded-2xl bg-slate-50 p-3.5 dark:bg-slate-800/60">
                                    <dt class="text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">Observaciones internas</dt>
                                    <dd class="mt-1 whitespace-pre-line text-xs font-medium text-slate-700 dark:text-slate-200">{{ $purchaseRequest->internal_notes ?? $purchaseRequest->notes }}</dd>
                                </div>
                            @endif
                        </dl>
                    </section>

                    {{-- Anulación pendiente solicitada --}}
                    @if($purchaseRequest->cancellation_requested_at)
                        <section class="rounded-3xl border-2 border-rose-300 bg-rose-50/90 p-5 shadow-sm dark:border-rose-800 dark:bg-rose-950/40">
                            <h2 class="flex items-center gap-2 text-sm font-black text-rose-900 dark:text-rose-100">
                                <span aria-hidden="true">⊘</span>
                                {{ $purchaseRequest->requester?->name ?? 'El solicitante' }} pidió anular esta solicitud
                            </h2>
                            <p class="mt-1 text-xs text-rose-800 dark:text-rose-200">
                                El {{ $purchaseRequest->cancellation_requested_at->format('d-m-Y') }} a las {{ $purchaseRequest->cancellation_requested_at->format('H:i') }}.
                            </p>

                            @if(filled($purchaseRequest->cancellation_reason))
                                <p class="mt-2.5 rounded-xl bg-white/80 p-3 text-xs text-rose-900 dark:bg-rose-950/70 dark:text-rose-100">
                                    {{ $purchaseRequest->cancellation_reason }}
                                </p>
                            @endif

                            <p class="mt-3 text-xs font-bold text-rose-900 dark:text-rose-100">
                                @can('cancel', $purchaseRequest)
                                    Decide antes de aprobarla: puedes anularla más abajo, o resolverla igual si corresponde.
                                @else
                                    Compras debe resolverlo.
                                @endcan
                            </p>

                            @can('withdrawCancellation', $purchaseRequest)
                                <form method="POST" action="{{ route('purchase_requests.withdraw_cancellation', $purchaseRequest) }}" class="mt-3">
                                    @csrf
                                    <button type="submit"
                                        class="min-h-10 w-full rounded-xl border border-rose-300 bg-white px-3 text-xs font-bold text-rose-800 shadow-sm hover:bg-rose-100 dark:border-rose-800 dark:bg-transparent dark:text-rose-200">
                                        Retirar mi petición de anulación
                                    </button>
                                </form>
                            @endcan
                        </section>
                    @endif

                    {{-- Lista para enviar (si está editable) --}}
                    @if($isEditable)
                        <section class="rounded-3xl border border-blue-200 bg-blue-50/80 p-5 shadow-sm dark:border-blue-900/60 dark:bg-blue-950/30">
                            <div class="flex items-center gap-2.5">
                                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-600 text-white">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                                </div>
                                <h2 class="text-sm font-black text-blue-950 dark:text-blue-100">Lista para enviar</h2>
                            </div>
                            <p class="mt-2 text-xs text-blue-800 dark:text-blue-200">Al enviar, la solicitud quedará en estado pendiente de revisión por el equipo de Compras.</p>
                            <form method="POST" action="{{ route('purchase_requests.submit', $purchaseRequest) }}" class="mt-4">
                                @csrf
                                <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-2xl bg-blue-600 px-4 text-sm font-black text-white shadow-md shadow-blue-500/25 transition hover:bg-blue-700 active:scale-95">
                                    <span>Enviar a revisión</span>
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                                </button>
                            </form>
                        </section>
                    @endif

                    {{-- Revisión de Compras (Aprobar / Solicitar cambios / Rechazar) --}}
                    @if($canReview)
                        <section x-data="{ action: '' }" class="rounded-3xl border border-amber-200/90 bg-amber-50/70 p-5 shadow-sm dark:border-amber-900/60 dark:bg-amber-950/30">
                            <div class="flex items-center gap-2.5">
                                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-600 text-white">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </div>
                                <h2 class="text-sm font-black text-amber-950 dark:text-amber-100">Revisión de Compras</h2>
                            </div>
                            <p class="mt-1.5 text-xs text-amber-800 dark:text-amber-200">Toma una decisión sobre la solicitud. La acción quedará registrada en el historial de auditoría.</p>

                            <form method="POST" action="{{ route('purchase_requests.approve', $purchaseRequest) }}" class="mt-4 space-y-4">
                                @csrf
                                <input type="hidden" name="lock_version" value="{{ $purchaseRequest->lock_version }}">

                                <div x-show="action === 'changes'" x-cloak class="rounded-2xl border border-amber-300 bg-white/80 p-4 dark:border-amber-900 dark:bg-slate-950/60">
                                    <p class="text-xs font-black text-amber-900 dark:text-amber-100">¿Qué hay que corregir?</p>
                                    <p class="mt-0.5 text-xs text-amber-800 dark:text-amber-200">Marca los puntos concretos. El solicitante los verá resaltados al editar.</p>

                                    <div class="mt-2.5 grid gap-1.5 sm:grid-cols-2">
                                        @foreach (\App\Enums\PurchaseRequestCorrection::cases() as $punto)
                                            <label class="flex min-h-10 items-center gap-2 rounded-xl px-2.5 text-xs font-semibold text-amber-950 hover:bg-amber-100/70 dark:text-amber-100 dark:hover:bg-amber-950/50">
                                                <input type="checkbox" name="corrections[]" value="{{ $punto->value }}"
                                                    class="h-4 w-4 shrink-0 rounded border-amber-400 text-amber-600 focus:ring-amber-500">
                                                <span>{{ $punto->label() }}</span>
                                            </label>
                                        @endforeach
                                    </div>

                                    @if($purchaseRequest->items->isNotEmpty())
                                        <p class="mt-3 text-xs font-black text-amber-900 dark:text-amber-100">Partidas puntuales ({{ $itemsCount }})</p>
                                        <div class="mt-1.5 max-h-44 overflow-y-auto rounded-xl border border-amber-200 bg-white/50 dark:border-amber-900 dark:bg-slate-900/50">
                                            @foreach ($purchaseRequest->items as $partida)
                                                <label class="flex min-h-10 items-center gap-2 border-b border-amber-100/80 px-2.5 text-xs text-amber-950 last:border-b-0 hover:bg-amber-100/60 dark:border-amber-900/60 dark:text-amber-100 dark:hover:bg-amber-950/40">
                                                    <input type="checkbox" name="corrections[]" value="{{ \App\Enums\PurchaseRequestCorrection::itemKey($partida->sort_order) }}"
                                                        class="h-4 w-4 shrink-0 rounded border-amber-400 text-amber-600 focus:ring-amber-500">
                                                    <span class="font-bold">{{ $partida->sort_order }}.</span>
                                                    <span class="truncate">{{ $partida->product_service }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    @endif
                                    @error('corrections.*') <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
                                </div>

                                <div class="space-y-1.5">
                                    <label for="review-comment" class="block text-xs font-bold text-amber-900 dark:text-amber-100">
                                        Comentario <span x-show="action !== 'approve'" class="text-rose-600">*</span>
                                    </label>
                                    <textarea id="review-comment" name="comment" rows="3" :required="action !== 'approve'"
                                        class="w-full rounded-2xl border-amber-300 bg-white px-3.5 py-2.5 text-xs text-slate-800 shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-amber-900 dark:bg-slate-950 dark:text-white"
                                        placeholder="Obligatorio al devolver o rechazar."></textarea>
                                </div>

                                <div class="grid grid-cols-1 gap-2">
                                    <button type="submit" @click="action = 'approve'" formaction="{{ route('purchase_requests.approve', $purchaseRequest) }}"
                                        class="min-h-11 rounded-2xl bg-emerald-600 px-4 text-sm font-black text-white shadow-md shadow-emerald-500/25 hover:bg-emerald-700 active:scale-95 transition">
                                        Aprobar
                                    </button>
                                    <button type="submit" @click="action = 'changes'" formaction="{{ route('purchase_requests.request_changes', $purchaseRequest) }}"
                                        class="min-h-11 rounded-2xl border border-amber-300 bg-white px-4 text-sm font-extrabold text-amber-800 hover:bg-amber-50 active:scale-95 transition dark:border-amber-800 dark:bg-slate-900 dark:text-amber-200 dark:hover:bg-amber-950/40">
                                        Solicitar cambios
                                    </button>
                                    <button type="submit" @click="action = 'reject'" formaction="{{ route('purchase_requests.reject', $purchaseRequest) }}"
                                        class="min-h-11 rounded-2xl border border-rose-300 bg-white px-4 text-sm font-extrabold text-rose-700 hover:bg-rose-50 active:scale-95 transition dark:border-rose-900 dark:bg-slate-900 dark:text-rose-300 dark:hover:bg-rose-950/30">
                                        Rechazar
                                    </button>
                                </div>
                            </form>
                        </section>
                    @endif

                    {{-- Card: Cotizaciones Recibidas y Comparador --}}
                    <section class="overflow-hidden rounded-3xl border border-sky-200/80 bg-sky-50/70 p-5 shadow-sm dark:border-sky-900/60 dark:bg-sky-950/30">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2.5">
                                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-sky-600 text-white">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                </div>
                                <h2 class="text-sm font-black text-sky-950 dark:text-sky-100">Cotizaciones</h2>
                            </div>
                            @if(count($comparaciones) > 0)
                                <span class="rounded-full bg-sky-200/80 px-2.5 py-0.5 text-xs font-black text-sky-900 dark:bg-sky-900 dark:text-sky-200">{{ count($comparaciones) }}</span>
                            @endif
                        </div>

                        <div class="mt-3.5 space-y-3">
                            @foreach ($comparaciones as $comparacion)
                                @php
                                    $lectura = $comparacion['ingestion'];
                                    $resultado = $comparacion['resultado'];
                                    $leyendo = in_array($lectura->status, [\App\Models\PurchaseRequestIngestion::PENDING, \App\Models\PurchaseRequestIngestion::PROCESSING], true);
                                @endphp

                                <div class="rounded-2xl border border-sky-100 bg-white/95 p-4 shadow-sm dark:border-sky-900/50 dark:bg-slate-900/90">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0 flex-1">
                                            <a href="{{ route('purchase_requests.ingestions.download', $lectura) }}"
                                                class="block truncate text-xs font-black text-sky-900 hover:text-sky-700 hover:underline dark:text-sky-200 dark:hover:text-sky-300">
                                                {{ $lectura->original_name }}
                                            </a>
                                            <p class="mt-0.5 truncate text-[11px] text-slate-500 dark:text-slate-400">
                                                {{ $lectura->supplier_name ?: 'Proveedor sin identificar' }}
                                            </p>

                                            @if($leyendo)
                                                <div class="mt-2 flex items-center gap-1.5 text-xs font-bold text-amber-600 dark:text-amber-400">
                                                    <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                                    <span>Leyéndola…</span>
                                                </div>
                                            @elseif($resultado->elDocumentoNoAporto())
                                                <p class="mt-2 text-xs font-bold text-rose-700 dark:text-rose-400">
                                                    No se pudo leer. El archivo está guardado; ábrelo y compáralo a mano.
                                                </p>
                                            @else
                                                <p class="mt-2 text-xs font-bold {{ $resultado->cuadra() ? 'text-emerald-700 dark:text-emerald-400' : ($resultado->porConfirmar() > 0 ? 'text-indigo-700 dark:text-indigo-300' : 'text-amber-700 dark:text-amber-400') }}">
                                                    {{ $resultado->estadoCorto() }}
                                                </p>
                                                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                                    {{ $resultado->cruzadas() }} de {{ $resultado->partidas() }} partidas cruzadas
                                                </p>
                                            @endif
                                        </div>
                                        <form method="POST" action="{{ route('purchase_requests.quotes.destroy', [$purchaseRequest, $lectura]) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-slate-800 transition" title="Quitar">
                                                <span class="sr-only">Quitar</span>
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                            </button>
                                        </form>
                                    </div>

                                    @can('exportToOdoo', $purchaseRequest)
                                        @if(filled($purchaseRequest->odoo_order_id) && ! $leyendo && ! $resultado->elDocumentoNoAporto())
                                            <form method="POST" action="{{ route('purchase_requests.quotes.prices', [$purchaseRequest, $lectura]) }}" class="mt-3 pt-2.5 border-t border-slate-100 dark:border-slate-800">
                                                @csrf
                                                <button type="submit"
                                                    class="min-h-10 w-full rounded-xl border border-violet-300 bg-white px-3 text-xs font-extrabold text-violet-800 hover:bg-violet-50 dark:border-violet-800 dark:bg-slate-900 dark:text-violet-200 dark:hover:bg-violet-950/40 transition">
                                                    Llevar estos precios a {{ $purchaseRequest->odoo_reference }}
                                                </button>
                                                <p class="mt-1 text-[11px] text-sky-800 dark:text-sky-300">
                                                    Sólo el precio de las partidas que cruzaron. No toca productos ni cantidades.
                                                </p>
                                            </form>
                                        @endif
                                    @endcan
                                </div>
                            @endforeach
                        </div>

                        {{-- Cargar una lleva a su pestaña, que es donde está el
                             formulario entero. Tenerlo también aquí eran dos
                             sitios para hacer lo mismo. --}}
                        <button type="button" @click="vista = 'cotizaciones'; window.scrollTo({ top: 0, behavior: 'smooth' })"
                            class="mt-4 inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-2xl bg-sky-600 px-3 text-xs font-extrabold text-white shadow-md shadow-sky-500/25 transition hover:bg-sky-700 active:scale-95">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" /></svg>
                            {{ count($comparaciones) ? 'Agregar otra cotización' : 'Agregar una cotización' }}
                        </button>
                    </section>

                    {{-- Card: Integración ERP Odoo --}}
                    @can('exportToOdoo', $purchaseRequest) @if($odooActivo)
                        <section class="overflow-hidden rounded-3xl border border-violet-200/80 bg-violet-50/70 p-5 shadow-sm dark:border-violet-900/60 dark:bg-violet-950/30">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-violet-600 text-white shadow-sm">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                    </div>
                                    <h2 class="text-sm font-black text-violet-950 dark:text-violet-100">Odoo</h2>
                                </div>
                                @if($yaEnOdoo)
                                    <span class="rounded-xl bg-violet-200/80 px-3 py-1 font-mono text-xs font-black text-violet-900 dark:bg-violet-900 dark:text-violet-200">
                                        {{ $purchaseRequest->odoo_reference }}
                                    </span>
                                @endif
                            </div>

                            @if($yaEnOdoo)
                                <div class="mt-3.5 rounded-2xl bg-white/90 p-4 dark:bg-slate-900/90 shadow-sm">
                                    <p class="text-xs font-bold text-violet-950 dark:text-violet-100">
                                        Ya está en Odoo como <span class="font-mono font-extrabold">{{ $purchaseRequest->odoo_reference }}</span> (en borrador).
                                    </p>
                                    <p class="mt-1 text-[11px] text-violet-800 dark:text-violet-300">
                                        Exportada el {{ $purchaseRequest->odoo_exported_at?->format('d-m-Y H:i') }}. No se vuelve a enviar.
                                    </p>
                                </div>
                            @else
                            @if($faltaProveedor || session()->exists('odoo_candidates') || session('odoo_query'))
                                {{-- El proveedor va antes que los productos: sin él no
                                     hay a quién comprarle, y esperar a que falle el
                                     envío para preguntarlo era hacerlo al revés. --}}
                                <div class="mt-3.5 space-y-3">
                                    @if($proveedorEscrito === null)
                                        <p class="text-xs text-violet-900 dark:text-violet-200">
                                            Esta solicitud <span class="font-bold">no dice a quién comprarle</span>. Búscalo en Odoo y elígelo:
                                        </p>
                                    @else
                                        <p class="text-xs text-violet-900 dark:text-violet-200">
                                            Odoo no reconoce a «{{ $proveedorEscrito }}». Búscalo tú:
                                        </p>
                                    @endif

                                    <form method="POST" action="{{ route('purchase_requests.odoo.supplier_search', $purchaseRequest) }}" class="flex gap-2">
                                        @csrf
                                        <input name="q" value="{{ session('odoo_query') }}" placeholder="Buscar en Odoo por nombre o RUT"
                                            class="min-h-10 w-full rounded-xl border-violet-300 bg-white px-3 text-xs dark:border-violet-800 dark:bg-slate-950 dark:text-white">
                                        <button type="submit"
                                            class="min-h-10 shrink-0 rounded-xl border border-violet-300 bg-white px-3 text-xs font-bold text-violet-800 hover:bg-violet-100 dark:border-violet-800 dark:bg-slate-900 dark:text-violet-200">
                                            Buscar
                                        </button>
                                    </form>

                                    @foreach($candidatos as $candidato)
                                        <form method="POST" action="{{ route('purchase_requests.odoo.supplier', $purchaseRequest) }}">
                                            @csrf
                                            <input type="hidden" name="odoo_partner_id" value="{{ $candidato['id'] }}">
                                            <input type="hidden" name="name" value="{{ $candidato['name'] }}">
                                            <input type="hidden" name="vat" value="{{ $candidato['vat'] }}">
                                            <button type="submit"
                                                class="w-full rounded-xl border border-violet-200 bg-white p-2.5 text-left text-xs shadow-sm hover:bg-violet-100 dark:border-violet-800 dark:bg-slate-950">
                                                <span class="block font-bold text-slate-900 dark:text-white">{{ $candidato['name'] }}</span>
                                                <span class="block text-[11px] {{ blank($candidato['vat'] ?? null) ? 'text-amber-700 dark:text-amber-300' : 'text-slate-500 dark:text-slate-400' }}">
                                                    {{ filled($candidato['vat'] ?? null) ? 'RUT '.$candidato['vat'] : 'Sin RUT en Odoo' }}
                                                </span>
                                            </button>
                                        </form>
                                    @endforeach

                                    @if($candidatos === [] && session('odoo_query'))
                                        <p class="rounded-xl border border-amber-200 bg-amber-50 p-2.5 text-[11px] text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">
                                            Odoo no trae a nadie con «{{ session('odoo_query') }}». Prueba con una palabra sola del nombre.
                                            Si de verdad no está, hay que darlo de alta en Odoo: desde aquí no se crean proveedores.
                                        </p>
                                    @endif
                                </div>
                            @endif

                            {{-- Tras un envío fallido el botón se esconde: dejarlo
                                 sólo invitaba a apretarlo otra vez y volver al mismo
                                 aviso. Mientras nadie lo haya intentado se muestra
                                 igual, porque el aviso de arriba es una sospecha del
                                 catálogo y no un veredicto de Odoo. --}}
                            @if(! session()->exists('odoo_candidates') && ! session('odoo_query'))
                                @php
                                    $emparejador = app(\App\Services\PurchaseRequests\Products\ProductMatcher::class);
                                    $proveedorOdoo = \App\Models\PurchaseSupplier::query()
                                        ->whereNotNull('odoo_partner_id')
                                        ->whereIn('tax_id', collect($purchaseRequest->suggested_suppliers ?? [])
                                            ->flatMap(fn ($s) => collect(\App\Support\Rut::findAll((string) $s))->pluck('rut'))
                                            ->all())
                                        ->value('odoo_partner_id');
                                    $busquedas = session('odoo_product_candidates', []);
                                    $consultas = session('odoo_product_query', []);
                                @endphp

                                <div class="mt-3.5 space-y-2.5">
                                    @if($proveedorDeOdoo !== null)
                                        <p class="rounded-xl bg-white/90 p-2.5 text-xs dark:bg-slate-900/90">
                                            <span class="text-[11px] font-black uppercase tracking-wider text-violet-500">Se le compra a</span>
                                            <span class="mt-0.5 block font-bold text-slate-900 dark:text-white">{{ $proveedorDeOdoo->name }}</span>
                                            @if(filled($proveedorDeOdoo->tax_id))
                                                <span class="block font-mono text-[11px] text-slate-500 dark:text-slate-400">{{ $proveedorDeOdoo->tax_id }}</span>
                                            @endif
                                        </p>
                                    @endif
                                    <p class="text-xs font-bold text-violet-950 dark:text-violet-200">Mapeo de productos Odoo ({{ $itemsCount }} partidas):</p>
                                    <div class="max-h-60 space-y-2 overflow-y-auto pr-1">
                                        @foreach($purchaseRequest->items as $partida)
                                            @php
                                                $r = $emparejador->match((string) $partida->product_service, $proveedorOdoo, $partida->specification);
                                                $encontrados = $busquedas[$partida->id] ?? null;
                                            @endphp

                                            <div class="rounded-xl border border-violet-200 bg-white p-3 text-xs dark:border-violet-900 dark:bg-slate-950 shadow-sm">
                                                <p class="font-bold text-slate-900 dark:text-white truncate">
                                                    <span class="inline-flex h-4 w-4 items-center justify-center rounded bg-violet-100 font-mono text-[10px] font-black text-violet-700 dark:bg-violet-900 dark:text-violet-200 mr-1.5">{{ $loop->iteration }}</span>{{ $partida->product_service }}
                                                </p>

                                                @if($r->resolved())
                                                    <div class="mt-1 flex items-center justify-between gap-1">
                                                        <span class="text-[11px] font-bold text-emerald-700 dark:text-emerald-400 truncate">
                                                            ✓ {{ $r->odooProductName }}
                                                        </span>
                                                        <form method="POST" action="{{ route('purchase_requests.odoo.product_unlink', [$purchaseRequest, $partida]) }}">
                                                            @csrf @method('DELETE')
                                                            <button type="submit" class="text-[10px] font-bold text-slate-400 hover:text-violet-700">Cambiar</button>
                                                        </form>
                                                    </div>
                                                @else
                                                    <p class="mt-1 text-[11px] text-amber-700 dark:text-amber-300">
                                                        Sin producto de Odoo · esta línea no sumará al stock al recibirla
                                                    </p>
                                                    @foreach(($encontrados ?? $r->candidates) as $c)
                                                        <form method="POST" action="{{ route('purchase_requests.odoo.product_link', [$purchaseRequest, $partida]) }}" class="mt-1.5">
                                                            @csrf
                                                            <input type="hidden" name="odoo_product_id" value="{{ $c['odoo_id'] }}">
                                                            <input type="hidden" name="odoo_product_name" value="{{ $c['name'] }}">
                                                            <button type="submit" class="w-full rounded-lg border border-slate-200 px-2.5 py-1 text-left text-[11px] hover:bg-violet-50 dark:border-slate-800">
                                                                <span class="font-bold text-slate-800 dark:text-slate-200">{{ $c['name'] }}</span>
                                                            </button>
                                                        </form>
                                                    @endforeach
                                                    <form method="POST" action="{{ route('purchase_requests.odoo.product_search', [$purchaseRequest, $partida]) }}" class="mt-1.5 flex gap-1">
                                                        @csrf
                                                        <input name="q" value="{{ $consultas[$partida->id] ?? '' }}" placeholder="Buscar producto"
                                                            class="h-8 w-full rounded-lg border-slate-300 px-2.5 text-[11px] dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                                        <button type="submit" class="h-8 shrink-0 rounded-lg border border-slate-300 px-2.5 text-[11px] font-bold text-slate-700 dark:border-slate-700 dark:text-slate-200">Buscar</button>
                                                    </form>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>

                                    <form method="POST" action="{{ route('purchase_requests.odoo.export', $purchaseRequest) }}" class="mt-3">
                                        @csrf
                                        <button type="submit"
                                            class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-2xl bg-violet-600 px-4 text-sm font-extrabold text-white shadow-md shadow-violet-500/25 hover:bg-violet-700 active:scale-95 transition">
                                            <span>Enviar a Odoo</span>
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                                        </button>
                                    </form>
                                </div>
                            @endif
                            @endif

                            {{-- Precios unitarios --}}
                            <form method="POST" action="{{ route('purchase_requests.prices.update', $purchaseRequest) }}"
                                class="mt-3.5 rounded-2xl border border-violet-200 bg-white/95 p-4 dark:border-violet-900 dark:bg-slate-900 shadow-sm">
                                @csrf
                                <p class="text-xs font-black text-slate-800 dark:text-slate-200">Precios unitarios</p>
                                <div class="mt-2.5 max-h-48 space-y-1.5 overflow-y-auto pr-1">
                                    @foreach($purchaseRequest->items as $partida)
                                        <label class="flex items-center justify-between gap-2 text-xs">
                                            <span class="min-w-0 flex-1 truncate text-slate-600 dark:text-slate-300">{{ $partida->product_service }}</span>
                                            <input type="number" step="0.01" min="0" inputmode="decimal"
                                                name="prices[{{ $partida->getKey() }}]"
                                                value="{{ $partida->unit_price !== null ? (float) $partida->unit_price : '' }}"
                                                placeholder="—"
                                                class="min-h-8 w-24 rounded-lg border-slate-300 py-1 text-right text-xs font-mono tabular-nums dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                        </label>
                                    @endforeach
                                </div>
                                @error('prices.*') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
                                <button type="submit"
                                    class="mt-3 min-h-10 w-full rounded-xl border border-violet-300 bg-white px-3 text-xs font-extrabold text-violet-800 hover:bg-violet-50 active:scale-95 transition dark:border-violet-800 dark:bg-slate-900 dark:text-violet-200">
                                    Guardar precios
                                </button>
                            </form>

                            @if($yaEnOdoo)
                                <form method="POST" action="{{ route('purchase_requests.prices.push', $purchaseRequest) }}" class="mt-2">
                                    @csrf
                                    <button type="submit"
                                        class="min-h-10 w-full rounded-xl bg-violet-600 px-3 text-xs font-extrabold text-white shadow-md shadow-violet-500/25 hover:bg-violet-700 active:scale-95 transition">
                                        Llevar estos precios a {{ $purchaseRequest->odoo_reference }}
                                    </button>
                                </form>
                            @endif
                        </section>
                    @endif @endcan

                    {{-- Card: Adjuntos --}}
                    @if($purchaseRequest->attachments->isNotEmpty())
                        <section class="overflow-hidden rounded-3xl border border-slate-200/90 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div class="border-b border-slate-100 p-5 dark:border-slate-800">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                                    </div>
                                    <h2 class="text-sm font-black text-slate-900 dark:text-white">Adjuntos</h2>
                                    <span class="ml-auto rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-black text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                        {{ $purchaseRequest->attachments->count() }}
                                    </span>
                                </div>
                            </div>
                            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse($purchaseRequest->attachments as $attachment)
                                    <div class="p-4 transition hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                        <p class="truncate text-xs font-bold text-slate-800 dark:text-slate-100">
                                            {{ $attachment->original_name ?: $attachment->file_name ?: 'Archivo adjunto' }}
                                        </p>
                                        <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                            {{ number_format(((int) ($attachment->size ?? $attachment->file_size ?? 0)) / 1024, 1, ',', '.') }} KB
                                        </p>
                                        <div class="mt-2.5 flex items-center gap-3">
                                            <a href="{{ route('purchase_requests.attachments.download', [$purchaseRequest, $attachment]) }}"
                                                class="text-xs font-extrabold text-blue-600 hover:text-blue-800 hover:underline dark:text-blue-400">
                                                Descargar
                                            </a>
                                            @if($isEditable)
                                                <form method="POST" action="{{ route('purchase_requests.attachments.destroy', [$purchaseRequest, $attachment]) }}">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="text-xs font-extrabold text-rose-600 hover:text-rose-800 hover:underline dark:text-rose-400">
                                                        Eliminar
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="p-4 text-xs text-slate-500 dark:text-slate-400">No hay antecedentes adjuntos.</div>
                                @endforelse
                            </div>
                        </section>
                    @endif

                    {{-- Card: Proveedores Sugeridos --}}
                    @if(count($suggestedSuppliers))
                        <section class="rounded-3xl border border-slate-200/90 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <h2 class="text-sm font-black text-slate-900 dark:text-white">Proveedores sugeridos</h2>
                            <ul class="mt-3 flex flex-wrap gap-2">
                                @foreach($suggestedSuppliers as $supplier)
                                    <li class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-800 dark:bg-slate-800 dark:text-slate-200">
                                        <svg class="h-3 w-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                        {{ $supplier }}
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    {{-- Card: Historial de Auditoría (Línea de tiempo) --}}
                    <section x-data="{ abierto: false }" class="overflow-hidden rounded-3xl border border-slate-200/90 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <button type="button" @click="abierto = !abierto"
                            class="flex min-h-12 w-full items-center justify-between gap-3 p-5 text-left transition hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                            <div class="flex items-center gap-2.5">
                                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </div>
                                <span class="text-sm font-black text-slate-900 dark:text-white">Historial</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-black text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    {{ $purchaseRequest->events->count() }}
                                </span>
                                <span class="text-xs font-bold text-slate-400" x-text="abierto ? 'Ocultar' : 'Ver'"></span>
                            </div>
                        </button>

                        <div x-show="abierto" x-cloak class="border-t border-slate-100 p-5 dark:border-slate-800">
                            <ol class="relative space-y-4 border-l-2 border-slate-200 pl-4 dark:border-slate-700">
                                @forelse($purchaseRequest->events as $event)
                                    <li class="relative">
                                        <span class="absolute -left-[23px] top-1.5 h-3 w-3 rounded-full ring-4 ring-white dark:ring-slate-900 {{ $event instanceof \App\Models\PurchaseRequestEvent ? $event->dotClasses() : 'bg-slate-400' }}" aria-hidden="true"></span>
                                        <p class="text-xs font-black text-slate-900 dark:text-white leading-tight">
                                            {{ $event instanceof \App\Models\PurchaseRequestEvent ? $event->label() : (data_get($event, 'label') ?: \Illuminate\Support\Str::headline(data_get($event, 'event_type') ?: 'actualización')) }}
                                        </p>
                                        <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                            {{ data_get($event, 'actor.name') ?: data_get($event, 'actor_name_snapshot') ?: 'Sistema' }} · {{ $formatDateTime($event->created_at) }}
                                        </p>
                                        @if(filled(data_get($event, 'comment')))
                                            <p class="mt-1.5 rounded-xl bg-slate-50 p-2.5 text-xs text-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
                                                {{ data_get($event, 'comment') }}
                                            </p>
                                        @endif
                                    </li>
                                @empty
                                    <li class="text-xs text-slate-500 dark:text-slate-400">Aún no hay eventos registrados.</li>
                                @endforelse
                            </ol>
                        </div>
                    </section>

                </aside>

            </div>
        </div>

    </div>
</x-app-layout>
