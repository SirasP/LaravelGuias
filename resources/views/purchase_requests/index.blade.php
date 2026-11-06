<x-app-layout>
    <x-slot name="header">
        <div class="flex min-w-0 items-center gap-2">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-400">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a3 3 0 006 0M9 5a3 3 0 016 0m-7 7h8m-8 4h5" />
                </svg>
            </div>
            <div class="min-w-0">
                <h1 class="truncate text-sm font-extrabold text-slate-900 dark:text-white">Solicitudes de compra</h1>
                <p class="truncate text-xs text-slate-500 dark:text-slate-400">Necesidades internas y seguimiento</p>
            </div>
        </div>
    </x-slot>

    @php
        $currentStatus = $status ?? request()->query('status');
        if ($currentStatus instanceof \BackedEnum) {
            $currentStatus = $currentStatus->value;
        }

        $countFor = static function (string $key) use ($counts): int {
            return (int) data_get($counts ?? [], $key, 0);
        };

        $formatDate = static function ($value): string {
            if (blank($value)) {
                return '—';
            }

            try {
                return $value instanceof \Carbon\CarbonInterface
                    ? $value->format('d-m-Y')
                    : \Illuminate\Support\Carbon::parse($value)->format('d-m-Y');
            } catch (\Throwable) {
                return (string) $value;
            }
        };

        $statusMeta = static function ($value): array {
            $raw = $value instanceof \BackedEnum ? $value->value : (string) $value;
            $label = is_object($value) && method_exists($value, 'label')
                ? $value->label()
                : \Illuminate\Support\Str::headline($raw ?: 'sin estado');
            $classes = is_object($value) && method_exists($value, 'badgeClasses')
                ? $value->badgeClasses()
                : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
            $editable = is_object($value) && method_exists($value, 'isEditable')
                ? $value->isEditable()
                : in_array($raw, ['draft', 'changes_requested'], true);

            return compact('raw', 'label', 'classes', 'editable');
        };

        $priorityMeta = static function (?string $priority): array {
            return match ($priority) {
                'urgent' => ['Urgente', 'bg-rose-100 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300'],
                'high' => ['Alta', 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300'],
                default => ['Normal', 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'],
            };
        };
    @endphp

    <div class="mx-auto max-w-full space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        @include('purchase_requests._module_nav', ['status' => $currentStatus])

        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200">
                {{ session('error') }}
            </div>
        @endif

        <section class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6" aria-label="Resumen de solicitudes">
            <a href="{{ route('purchase_requests.index') }}"
                class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Activas</p>
                <p class="mt-2 text-2xl font-black tabular-nums text-slate-900 dark:text-white">{{ $countFor('activas') }}</p>
                {{-- Cuenta lo que la lista muestra: si dijera el total y la
                     lista no lo mostrara, el número estaría mintiendo. --}}
                <p class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400">sin terminadas ni anuladas</p>
            </a>
            <a href="{{ route('purchase_requests.index', ['status' => 'draft']) }}"
                class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Borradores</p>
                <p class="mt-2 text-2xl font-black tabular-nums text-slate-700 dark:text-slate-200">{{ $countFor('draft') }}</p>
            </a>
            <a href="{{ route('purchase_requests.index', ['status' => \App\Enums\PurchaseRequestStatus::GROUP_AWAITING_REVIEW]) }}"
                class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-amber-900/50 dark:bg-amber-950/30">
                <p class="text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">Por revisar</p>
                {{-- Incluye las reenviadas: una corrección que volvió sigue esperando decisión. --}}
                <p class="mt-2 text-2xl font-black tabular-nums text-amber-800 dark:text-amber-200">{{ $countFor(\App\Enums\PurchaseRequestStatus::GROUP_AWAITING_REVIEW) }}</p>
                @if($countFor('resubmitted') > 0)
                    <p class="mt-1 text-xs font-semibold text-amber-700 dark:text-amber-300">
                        {{ $countFor('resubmitted') }} {{ \Illuminate\Support\Str::plural('corregida', $countFor('resubmitted')) }}
                    </p>
                @endif
            </a>
            <a href="{{ route('purchase_requests.index', ['status' => 'approved']) }}"
                class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-emerald-900/50 dark:bg-emerald-950/30">
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Aprobadas</p>
                <p class="mt-2 text-2xl font-black tabular-nums text-emerald-800 dark:text-emerald-200">{{ $countFor('approved') }}</p>
                <p class="mt-1 text-xs font-semibold text-emerald-700 dark:text-emerald-300">en marcha</p>
            </a>
            {{-- Lo cerrado, aparte. Sin esta casilla una compra terminada
                 seguía contando entre las aprobadas y no se sabía qué
                 quedaba pendiente de verdad. --}}
            <a href="{{ route('purchase_requests.index', ['status' => 'completed']) }}"
                class="rounded-2xl border border-violet-200 bg-violet-50 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-violet-900/50 dark:bg-violet-950/30">
                <p class="text-xs font-bold uppercase tracking-wider text-violet-600 dark:text-violet-400">Terminadas</p>
                <p class="mt-2 text-2xl font-black tabular-nums text-violet-800 dark:text-violet-200">{{ $countFor('completed') }}</p>
                <p class="mt-1 text-xs font-semibold text-violet-700 dark:text-violet-300">compra cerrada</p>
            </a>
            <a href="{{ route('purchase_requests.index', ['status' => 'cancelled']) }}"
                class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900/60">
                <p class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Anuladas</p>
                <p class="mt-2 text-2xl font-black tabular-nums text-zinc-700 dark:text-zinc-200">{{ $countFor('cancelled') }}</p>
                <p class="mt-1 text-xs font-semibold text-zinc-600 dark:text-zinc-400">sin efecto</p>
            </a>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div>
                    <h2 class="font-extrabold text-slate-900 dark:text-white">
                        @if($currentStatus === \App\Enums\PurchaseRequestStatus::COMPLETED->value)
                            Compras terminadas
                        @elseif($currentStatus === 'submitted' && auth()->user()?->role === 'admin')
                            Solicitudes por revisar
                        @else
                            Mis solicitudes
                        @endif
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        @if($currentStatus === \App\Enums\PurchaseRequestStatus::COMPLETED->value)
                            Cerradas y fuera de lo pendiente. Se puede volver a abrir cualquiera desde su ficha.
                        @elseif(blank($currentStatus))
                            El trabajo vivo: lo terminado y lo anulado tienen su propia casilla.
                        @else
                            Los filtros se aplican sobre todos los registros.
                        @endif
                    </p>
                </div>

                @php
                    $f = $filters ?? [];
                    // El estado también cuenta: desde que vive en el panel, es un
                    // filtro más y el número del botón tiene que reflejarlo.
                    $activeFilters = collect($f)->filter(fn ($v) => filled($v))->count()
                        + (filled($currentStatus) ? 1 : 0);
                @endphp

                {{--
                    Todos los filtros viven en un panel lateral. Antes se abrían hacia
                    abajo y empujaban la tabla media pantalla; ahora entran por la
                    derecha y la lista se queda quieta detrás.
                --}}
                <form id="filtros-solicitudes" method="GET" action="{{ route('purchase_requests.index') }}"
                    x-data="{ open: false }"
                    x-effect="document.querySelector('main')?.classList.toggle('overflow-hidden', open)"
                    @keydown.escape.window="open = false"
                    class="w-full sm:w-auto">

                    <button type="button" x-ref="abrirFiltros"
                        @click="open = true; $nextTick(() => $refs.primerCampo?.focus())"
                        class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 sm:w-auto"
                        :aria-expanded="open.toString()" aria-controls="filtros-avanzados">
                        <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L15 12.414V19a1 1 0 01-.553.894l-4 2A1 1 0 019 21v-8.586L3.293 6.707A1 1 0 013 6V4z" />
                        </svg>
                        Filtros
                        @if ($activeFilters > 0)
                            <span class="rounded-full bg-blue-600 px-1.5 text-xs font-bold text-white">{{ $activeFilters }}</span>
                        @endif
                    </button>

                    {{--
                        Se sacan al <body>. Dentro del contenedor del tema
                        —`premium-content`, con position:relative y z-index:10—
                        el panel no puede pasar por encima de la barra del
                        título: un z-index alto sólo compite dentro de su
                        contexto, y ese contexto vale 10 contra los 30 de la
                        barra. Los campos se quedan enlazados al formulario por
                        su atributo `form`.
                    --}}
                    <template x-teleport="body">
                    <div>
                    <div x-show="open" x-cloak x-transition.opacity.duration.200ms
                        @click="open = false"
                        class="fixed inset-0 z-[70] bg-slate-900/40 backdrop-blur-[1px]"
                        aria-hidden="true"></div>

                    {{-- El panel: entra deslizándose desde el borde derecho --}}
                    <div id="filtros-avanzados" x-show="open" x-cloak
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="translate-x-full"
                        x-transition:enter-end="translate-x-0"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="translate-x-0"
                        x-transition:leave-end="translate-x-full"
                        role="dialog" aria-modal="true" aria-labelledby="filtros-avanzados-titulo"
                        class="fixed inset-y-0 right-0 z-[71] flex w-full max-w-sm flex-col bg-white text-left shadow-2xl dark:bg-slate-900">

                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                            <div>
                                <h3 id="filtros-avanzados-titulo" class="font-extrabold text-slate-900 dark:text-white">Más filtros</h3>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Se aplican sobre todos los registros.</p>
                            </div>
                            <button type="button" @click="open = false; $refs.abrirFiltros?.focus()"
                                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200">
                                <span class="sr-only">Cerrar filtros</span>
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                            <div>
                                <label for="search" class="block text-xs font-bold text-slate-600 dark:text-slate-300">Buscar</label>
                                <input id="search" name="search" form="filtros-solicitudes" type="search" x-ref="primerCampo" value="{{ $f['search'] ?? '' }}"
                                    placeholder="Folio, motivo o solicitante…"
                                    class="mt-1 min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            </div>

                            <div>
                                <label for="status-filter" class="block text-xs font-bold text-slate-600 dark:text-slate-300">Estado</label>
                                <select id="status-filter" name="status" form="filtros-solicitudes"
                                    class="mt-1 min-h-11 w-full rounded-xl border-slate-300 bg-white py-2 pl-3 pr-9 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                    <option value="" @selected(blank($currentStatus))>Activas (sin terminadas ni anuladas)</option>
                                    <option value="{{ \App\Enums\PurchaseRequestStatus::GROUP_ALL }}" @selected($currentStatus === \App\Enums\PurchaseRequestStatus::GROUP_ALL)>
                                        Todas, incluidas las terminadas
                                    </option>
                                    <option value="{{ \App\Enums\PurchaseRequestStatus::GROUP_AWAITING_REVIEW }}" @selected($currentStatus === \App\Enums\PurchaseRequestStatus::GROUP_AWAITING_REVIEW)>
                                        ⏳ Pendientes de decisión (enviadas y corregidas)
                                    </option>
                                    @foreach (\App\Enums\PurchaseRequestStatus::cases() as $case)
                                        <option value="{{ $case->value }}" @selected($currentStatus === $case->value)>
                                            {{ $case->icon() }} {{ $case->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="filter-department" class="block text-xs font-bold text-slate-600 dark:text-slate-300">Área</label>
                                <select id="filter-department" name="department" form="filtros-solicitudes"
                                    class="mt-1 min-h-11 w-full rounded-xl border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                    <option value="">Todas</option>
                                    @foreach (($departments ?? collect()) as $department)
                                        <option value="{{ $department->name }}" @selected(($f['department'] ?? null) === $department->name)>
                                            {{ $department->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="filter-requester" class="block text-xs font-bold text-slate-600 dark:text-slate-300">Solicitante</label>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Quien la creó o para quién es.</p>
                                <input id="filter-requester" name="requester" form="filtros-solicitudes" value="{{ $f['requester'] ?? '' }}" placeholder="Nombre"
                                    class="mt-1 min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            </div>

                            <fieldset class="rounded-xl border border-slate-200 p-3 dark:border-slate-800">
                                <legend class="px-1 text-xs font-bold text-slate-600 dark:text-slate-300">Fecha de solicitud</legend>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label for="requested_from" class="block text-xs text-slate-500 dark:text-slate-400">Desde</label>
                                        <input id="requested_from" type="date" name="requested_from" form="filtros-solicitudes" value="{{ $f['requested_from'] ?? '' }}"
                                            class="mt-1 min-h-11 w-full rounded-xl border-slate-300 bg-white px-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                    </div>
                                    <div>
                                        <label for="requested_to" class="block text-xs text-slate-500 dark:text-slate-400">Hasta</label>
                                        <input id="requested_to" type="date" name="requested_to" form="filtros-solicitudes" value="{{ $f['requested_to'] ?? '' }}"
                                            class="mt-1 min-h-11 w-full rounded-xl border-slate-300 bg-white px-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="rounded-xl border border-slate-200 p-3 dark:border-slate-800">
                                <legend class="px-1 text-xs font-bold text-slate-600 dark:text-slate-300">Fecha requerida</legend>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label for="required_from" class="block text-xs text-slate-500 dark:text-slate-400">Desde</label>
                                        <input id="required_from" type="date" name="required_from" form="filtros-solicitudes" value="{{ $f['required_from'] ?? '' }}"
                                            class="mt-1 min-h-11 w-full rounded-xl border-slate-300 bg-white px-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                    </div>
                                    <div>
                                        <label for="required_to" class="block text-xs text-slate-500 dark:text-slate-400">Hasta</label>
                                        <input id="required_to" type="date" name="required_to" form="filtros-solicitudes" value="{{ $f['required_to'] ?? '' }}"
                                            class="mt-1 min-h-11 w-full rounded-xl border-slate-300 bg-white px-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                    </div>
                                </div>
                            </fieldset>
                        </div>

                        <div class="flex items-center gap-2 border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                            @if ($activeFilters > 0)
                                <a href="{{ route('purchase_requests.index') }}"
                                    class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl border border-slate-200 px-3 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                                    Limpiar
                                </a>
                            @endif
                            <button type="submit" form="filtros-solicitudes"
                                class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl bg-blue-600 px-4 text-sm font-bold text-white hover:bg-blue-700">
                                Aplicar filtros
                            </button>
                        </div>
                    </div>
                    </div>
                    </template>
                </form>
            </div>

            {{-- Tarjetas para teléfono y tablet angosta --}}
            <div class="divide-y divide-slate-100 dark:divide-slate-800 md:hidden">
                @forelse($requests as $purchaseRequest)
                    @php
                        $meta = $statusMeta($purchaseRequest->status);
                        [$priorityLabel, $priorityClasses] = $priorityMeta($purchaseRequest->priority ?? null);
                    @endphp
                    <article class="space-y-3 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-mono text-xs font-bold text-blue-600 dark:text-blue-400">
                                    {{ $purchaseRequest->folio ?? ('Borrador #' . $purchaseRequest->id) }}
                                    @if($purchaseRequest->isFromOdoo())<span class="ml-1 rounded-full bg-violet-100 px-1.5 py-0.5 font-sans text-[10px] font-extrabold text-violet-700 dark:bg-violet-950/60 dark:text-violet-300">Creada en Odoo</span>@endif
                                </p>
                                <h3 class="mt-1 line-clamp-2 font-bold text-slate-900 dark:text-white">
                                    {{ $purchaseRequest->reason }}
                                </h3>
                            </div>
                            <span class="inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-[11px] font-bold {{ $meta['classes'] }}">
                                {{ $meta['label'] }}
                            </span>
                        </div>

                        <dl class="grid grid-cols-2 gap-3 text-xs">
                            <div>
                                <dt class="font-semibold text-slate-400">Departamento</dt>
                                <dd class="mt-0.5 text-slate-700 dark:text-slate-300">{{ $purchaseRequest->department ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-slate-400">Fecha requerida</dt>
                                <dd class="mt-0.5 text-slate-700 dark:text-slate-300">{{ $formatDate($purchaseRequest->required_date) }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-slate-400">Solicitante</dt>
                                <dd class="mt-0.5 truncate text-slate-700 dark:text-slate-300">{{ $purchaseRequest->requester_name_snapshot ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-slate-400">Solicitado para</dt>
                                <dd class="mt-0.5 truncate text-slate-700 dark:text-slate-300">{{ $purchaseRequest->requested_for_name ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-slate-400">Prioridad</dt>
                                <dd class="mt-1"><span class="rounded-full px-2 py-0.5 font-bold {{ $priorityClasses }}">{{ $priorityLabel }}</span></dd>
                            </div>
                        </dl>

                        <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                            @if($meta['editable'])
                                <a href="{{ route('purchase_requests.edit', $purchaseRequest) }}"
                                    class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                                    Editar
                                </a>
                            @endif
                            <a href="{{ route('purchase_requests.show', $purchaseRequest) }}"
                                class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-600 px-4 text-sm font-bold text-white hover:bg-blue-700">
                                Ver detalle
                            </a>
                        </div>
                    </article>
                @empty
                    <div class="px-5 py-14 text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <p class="mt-3 font-bold text-slate-700 dark:text-slate-200">No hay solicitudes para mostrar</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Crea una nueva solicitud o cambia el filtro.</p>
                    </div>
                @endforelse
            </div>

            {{-- Tabla para escritorio --}}
            <div class="hidden overflow-x-auto bg-slate-50/70 px-4 pb-3 md:block dark:bg-slate-950/40">
                {{-- Cada fila es una tarjeta redondeada con el borde izquierdo del color de su estado. --}}
                <table class="min-w-full border-separate border-spacing-x-0 border-spacing-y-2 text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            <th class="pb-1 pl-6 pr-5 pt-4">Solicitud</th>
                            <th class="w-56 px-5 pb-1 pt-4">Avance</th>
                            <th class="w-32 px-5 pb-1 pt-4 text-right">Partidas</th>
                            <th class="w-40 px-5 pb-1 pt-4">Requerida</th>
                            {{-- Lo que de verdad hacía falta: qué espera cada una. --}}
                            <th class="w-56 px-5 pb-1 pt-4">Siguiente paso</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $purchaseRequest)
                            @php
                                $meta = $statusMeta($purchaseRequest->status);
                                [$priorityLabel, $priorityClasses] = $priorityMeta($purchaseRequest->priority ?? null);
                                [$paso, $pendiente] = $purchaseRequest->siguientePaso();
                                $estado = $purchaseRequest->status->value;
                                $cotizaciones = (int) ($purchaseRequest->received_quotes_count ?? 0);
                                $sinPrecio = (int) ($purchaseRequest->items_sin_precio_count ?? 0);

                                // En qué va: cinco pasos, del envío a tener todos los precios.
                                $pasos = ['Revisión', 'Aprobada', 'En Odoo', 'Cotizada', 'Con precios'];
                                $hecho = match (true) {
                                    $estado === 'draft' => 0,
                                    in_array($estado, ['submitted', 'resubmitted', 'changes_requested'], true) => 1,
                                    $estado === 'completed' => 5,
                                    in_array($estado, ['cancelled', 'rejected'], true) => 0,
                                    ! $purchaseRequest->hasBeenExportedToOdoo() => 2,
                                    $cotizaciones === 0 => 3,
                                    $sinPrecio > 0 => 4,
                                    default => 5,
                                };
                                $apagada = in_array($estado, ['cancelled', 'rejected'], true);
                                $etapa = match (true) {
                                    $estado === 'completed' => 'Terminada',
                                    $apagada => $meta['label'],
                                    $estado === 'draft' => 'Borrador',
                                    $estado === 'changes_requested' => 'Con correcciones',
                                    default => $pasos[max(0, $hecho - 1)],
                                };
                                [$franja, $relleno] = match (true) {
                                    $estado === 'completed' => ['border-l-violet-500', 'bg-violet-500'],
                                    $apagada => ['border-l-slate-300 dark:border-l-slate-600', 'bg-slate-300 dark:bg-slate-700'],
                                    $estado === 'changes_requested' => ['border-l-amber-500', 'bg-amber-500'],
                                    $pendiente => ['border-l-blue-500', 'bg-blue-500'],
                                    default => ['border-l-emerald-500', 'bg-emerald-500'],
                                };
                                $celda = 'border-y border-slate-200 bg-white transition-colors group-hover:border-slate-300 group-hover:bg-blue-50/40 dark:border-slate-800 dark:bg-slate-900 dark:group-hover:bg-slate-800/60';

                                // Cuándo se necesita, en palabras.
                                $requerida = $purchaseRequest->required_date;
                                $dias = $requerida ? (int) now()->startOfDay()->diffInDays($requerida->copy()->startOfDay(), false) : null;
                                $vencida = $dias !== null && $dias < 0 && ! in_array($estado, ['completed', 'cancelled', 'rejected'], true);
                                $cuando = match (true) {
                                    $dias === null => null,
                                    $dias === 0 => 'hoy',
                                    $dias === 1 => 'mañana',
                                    $dias > 1 => 'en '.$dias.' días',
                                    $dias === -1 => 'ayer',
                                    default => 'hace '.abs($dias).' días',
                                };
                            @endphp
                            <tr class="group cursor-pointer" data-row-href="{{ route('purchase_requests.show', $purchaseRequest) }}">
                                <td class="{{ $celda }} rounded-l-2xl border-l-[5px] py-4 pl-5 pr-5 {{ $franja }}">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        {{-- Enlace de verdad: se puede tabular, copiar y abrir en otra pestaña --}}
                                        <a href="{{ route('purchase_requests.show', $purchaseRequest) }}"
                                            class="font-mono text-xs font-bold text-blue-600 hover:underline focus:outline-none focus-visible:underline dark:text-blue-400">
                                            {{ $purchaseRequest->folio ?? ('BOR-' . $purchaseRequest->id) }}
                                        </a>
                                        @if(($purchaseRequest->priority ?? null) !== 'normal')
                                            <span class="rounded-full px-1.5 py-0.5 text-[10px] font-extrabold {{ $priorityClasses }}">{{ $priorityLabel }}</span>
                                        @endif
                                        @if($purchaseRequest->isFromOdoo())
                                            <span class="rounded-full bg-violet-100 px-1.5 py-0.5 text-[10px] font-extrabold text-violet-700 dark:bg-violet-950/60 dark:text-violet-300">Creada en Odoo</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 line-clamp-2 max-w-2xl font-bold text-slate-900 group-hover:text-blue-700 dark:text-white dark:group-hover:text-blue-300">{{ $purchaseRequest->reason }}</p>
                                    <p class="mt-1 flex flex-wrap items-center gap-x-2 text-xs text-slate-500 dark:text-slate-400">
                                        <span>{{ $purchaseRequest->department ?: 'Sin departamento' }}</span>
                                        @if($variosSolicitantes)
                                            <span class="text-slate-300 dark:text-slate-600">·</span>
                                            <span>pedida por {{ $purchaseRequest->requester_name_snapshot ?: '—' }}@if(filled($purchaseRequest->requested_for_name)) para {{ $purchaseRequest->requested_for_name }}@endif</span>
                                        @endif
                                        <span class="text-slate-300 dark:text-slate-600">·</span>
                                        <span>creada {{ $formatDate($purchaseRequest->request_date) }}</span>
                                    </p>
                                </td>
                                <td class="{{ $celda }} px-5 py-4 align-middle">
                                    <div class="flex gap-1" title="{{ implode(' → ', $pasos) }}">
                                        @foreach($pasos as $i => $nombrePaso)
                                            <span class="h-1.5 flex-1 rounded-full {{ $i < $hecho ? $relleno : 'bg-slate-200 dark:bg-slate-700' }}" title="{{ $nombrePaso }}"></span>
                                        @endforeach
                                    </div>
                                    <p class="mt-1.5 flex items-center justify-between text-xs">
                                        <span class="font-bold {{ $apagada ? 'text-slate-400' : 'text-slate-700 dark:text-slate-200' }}">{{ $etapa }}</span>
                                        <span class="font-mono text-[10px] text-slate-400">{{ $hecho }}/5</span>
                                    </p>
                                </td>
                                <td class="{{ $celda }} px-5 py-4 text-right align-middle">
                                    <p class="font-mono text-base font-black tabular-nums text-slate-800 dark:text-slate-100">{{ $purchaseRequest->items_count }}</p>
                                    <p class="whitespace-nowrap text-[11px] {{ $cotizaciones > 0 ? 'font-bold text-sky-700 dark:text-sky-300' : 'text-slate-400' }}">
                                        {{ $cotizaciones === 0 ? 'sin cotizaciones' : $cotizaciones.' '.($cotizaciones === 1 ? 'cotización' : 'cotizaciones') }}
                                    </p>
                                </td>
                                <td class="{{ $celda }} px-5 py-4 align-middle">
                                    <p class="font-semibold tabular-nums text-slate-700 dark:text-slate-200">{{ $formatDate($requerida) }}</p>
                                    @if($cuando)
                                        <p class="text-[11px] {{ $vencida ? 'font-bold text-rose-600 dark:text-rose-400' : 'text-slate-400' }}">{{ $vencida ? 'vencida '.$cuando : $cuando }}</p>
                                    @endif
                                </td>
                                {{-- Qué espera cada una: sin esto la bandeja decía lo que la
                                     solicitud es, nunca lo que falta hacerle. --}}
                                <td class="{{ $celda }} rounded-r-2xl border-r px-5 py-4 align-middle">
                                    @if($pendiente)
                                        <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-xl bg-amber-50 px-3 py-1.5 text-xs font-extrabold text-amber-800 ring-1 ring-amber-200 group-hover:bg-amber-100 dark:bg-amber-950/40 dark:text-amber-200 dark:ring-amber-900">
                                            {{ $paso }}
                                            <svg class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-400 dark:text-slate-500">
                                            @if($estado === 'completed' || $paso === 'Lista')<svg class="h-3.5 w-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>@endif
                                            {{ $paso }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-14 text-center text-sm text-slate-500 dark:text-slate-400">
                                    No hay solicitudes para mostrar.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(method_exists($requests, 'links'))
                <div class="border-t border-slate-100 px-4 py-4 dark:border-slate-800 sm:px-5">
                    {{ $requests->withQueryString()->links() }}
                </div>
            @endif
        </section>
    </div>

    <script>
        // Cada fila de la tabla lleva a su solicitud. Va como un solo oyente en
        // el documento para que las filas que llegan al cambiar de página
        // funcionen igual, sin volver a enganchar nada.
        document.addEventListener('click', (evento) => {
            const fila = evento.target.closest('[data-row-href]');

            if (! fila) {
                return;
            }

            // Un enlace o un botón dentro de la fila hace lo suyo, no lo nuestro.
            if (evento.target.closest('a, button, input, select, label')) {
                return;
            }

            // Si venía seleccionando texto para copiarlo, no lo sacamos de la página.
            if ((window.getSelection()?.toString() ?? '') !== '') {
                return;
            }

            if (evento.metaKey || evento.ctrlKey) {
                window.open(fila.dataset.rowHref, '_blank', 'noopener');
                return;
            }

            window.location = fila.dataset.rowHref;
        });
    </script>
</x-app-layout>
