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

    <div class="mx-auto max-w-8xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
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

        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Resumen de solicitudes">
            <a href="{{ route('purchase_requests.index') }}"
                class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Total</p>
                <p class="mt-2 text-2xl font-black tabular-nums text-slate-900 dark:text-white">{{ $countFor('total') }}</p>
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
            </a>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div>
                    <h2 class="font-extrabold text-slate-900 dark:text-white">
                        {{ $currentStatus === 'submitted' && auth()->user()?->role === 'admin' ? 'Solicitudes por revisar' : 'Mis solicitudes' }}
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Los filtros se aplican sobre todos los registros.</p>
                </div>

                @php
                    $f = $filters ?? [];
                    $activeFilters = collect($f)->filter(fn ($v) => filled($v))->count();
                @endphp

                <form method="GET" action="{{ route('purchase_requests.index') }}"
                    x-data="{ open: {{ $activeFilters > 0 ? 'true' : 'false' }} }" class="w-full sm:w-auto">

                    {{-- Búsqueda y estado siempre visibles; el resto se despliega --}}
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <label for="search" class="sr-only">Buscar por folio, motivo o solicitante</label>
                        <input id="search" name="search" type="search" value="{{ $f['search'] ?? '' }}"
                            placeholder="Folio, motivo o solicitante…"
                            class="min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white sm:w-56">

                        <label for="status-filter" class="sr-only">Filtrar por estado</label>
                        <select id="status-filter" name="status"
                            class="min-h-11 w-full rounded-xl border-slate-300 bg-white py-2 pl-3 pr-9 text-sm font-semibold text-slate-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 sm:w-auto">
                            <option value="" @selected(blank($currentStatus))>Todos los estados</option>
                            <option value="{{ \App\Enums\PurchaseRequestStatus::GROUP_AWAITING_REVIEW }}" @selected($currentStatus === \App\Enums\PurchaseRequestStatus::GROUP_AWAITING_REVIEW)>
                                ⏳ Pendientes de decisión (enviadas y corregidas)
                            </option>
                            @foreach (\App\Enums\PurchaseRequestStatus::cases() as $case)
                                <option value="{{ $case->value }}" @selected($currentStatus === $case->value)>
                                    {{ $case->icon() }} {{ $case->label() }}
                                </option>
                            @endforeach
                        </select>

                        <button type="button" @click="open = !open"
                            class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl border border-slate-200 px-3 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                            :aria-expanded="open.toString()" aria-controls="filtros-avanzados">
                            Más filtros
                            @if ($activeFilters > 0)
                                <span class="rounded-full bg-blue-600 px-1.5 text-xs font-bold text-white">{{ $activeFilters }}</span>
                            @endif
                        </button>

                        <button type="submit"
                            class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-600 px-4 text-sm font-bold text-white hover:bg-blue-700">
                            Filtrar
                        </button>
                    </div>

                    <div id="filtros-avanzados" x-show="open" x-cloak
                        class="mt-3 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-950/50 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label for="filter-department" class="block text-xs font-bold text-slate-600 dark:text-slate-300">Área</label>
                            <select id="filter-department" name="department"
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
                            <input id="filter-requester" name="requester" value="{{ $f['requester'] ?? '' }}" placeholder="Nombre"
                                class="mt-1 min-h-11 w-full rounded-xl border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label for="requested_from" class="block text-xs font-bold text-slate-600 dark:text-slate-300">Solicitada desde</label>
                                <input id="requested_from" type="date" name="requested_from" value="{{ $f['requested_from'] ?? '' }}"
                                    class="mt-1 min-h-11 w-full rounded-xl border-slate-300 bg-white px-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            </div>
                            <div>
                                <label for="requested_to" class="block text-xs font-bold text-slate-600 dark:text-slate-300">hasta</label>
                                <input id="requested_to" type="date" name="requested_to" value="{{ $f['requested_to'] ?? '' }}"
                                    class="mt-1 min-h-11 w-full rounded-xl border-slate-300 bg-white px-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2 sm:col-span-2 lg:col-span-1">
                            <div>
                                <label for="required_from" class="block text-xs font-bold text-slate-600 dark:text-slate-300">Requerida desde</label>
                                <input id="required_from" type="date" name="required_from" value="{{ $f['required_from'] ?? '' }}"
                                    class="mt-1 min-h-11 w-full rounded-xl border-slate-300 bg-white px-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            </div>
                            <div>
                                <label for="required_to" class="block text-xs font-bold text-slate-600 dark:text-slate-300">hasta</label>
                                <input id="required_to" type="date" name="required_to" value="{{ $f['required_to'] ?? '' }}"
                                    class="mt-1 min-h-11 w-full rounded-xl border-slate-300 bg-white px-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            </div>
                        </div>

                        @if ($activeFilters > 0 || filled($currentStatus))
                            <div class="flex items-end">
                                <a href="{{ route('purchase_requests.index') }}"
                                    class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-slate-200 px-3 text-sm font-semibold text-slate-600 hover:bg-white dark:border-slate-700 dark:text-slate-300">
                                    Limpiar filtros
                                </a>
                            </div>
                        @endif
                    </div>
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
            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50/80 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:bg-slate-950/50 dark:text-slate-400">
                        <tr>
                            <th class="whitespace-nowrap px-5 py-3">Folio</th>
                            <th class="min-w-72 px-5 py-3">Solicitud</th>
                            <th class="whitespace-nowrap px-5 py-3">Departamento</th>
                            <th class="whitespace-nowrap px-5 py-3">Requerida</th>
                            <th class="whitespace-nowrap px-5 py-3">Prioridad</th>
                            <th class="whitespace-nowrap px-5 py-3">Estado</th>
                            <th class="px-5 py-3 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($requests as $purchaseRequest)
                            @php
                                $meta = $statusMeta($purchaseRequest->status);
                                [$priorityLabel, $priorityClasses] = $priorityMeta($purchaseRequest->priority ?? null);
                            @endphp
                            <tr class="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="whitespace-nowrap px-5 py-4 font-mono text-xs font-bold text-blue-600 dark:text-blue-400">
                                    {{ $purchaseRequest->folio ?? ('BOR-' . $purchaseRequest->id) }}
                                </td>
                                <td class="px-5 py-4">
                                    <p class="line-clamp-2 font-semibold text-slate-900 dark:text-white">{{ $purchaseRequest->reason }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Para {{ $purchaseRequest->requested_for_name ?: 'sin especificar' }}</p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-slate-600 dark:text-slate-300">{{ $purchaseRequest->department ?: '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-slate-600 dark:text-slate-300">{{ $formatDate($purchaseRequest->required_date) }}</td>
                                <td class="whitespace-nowrap px-5 py-4"><span class="rounded-full px-2 py-1 text-xs font-bold {{ $priorityClasses }}">{{ $priorityLabel }}</span></td>
                                <td class="whitespace-nowrap px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $meta['classes'] }}">{{ $meta['label'] }}</span></td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        @if($meta['editable'])
                                            <a href="{{ route('purchase_requests.edit', $purchaseRequest) }}" class="font-semibold text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">Editar</a>
                                        @endif
                                        <a href="{{ route('purchase_requests.show', $purchaseRequest) }}" class="font-bold text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">Ver</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-14 text-center text-sm text-slate-500 dark:text-slate-400">
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
</x-app-layout>
