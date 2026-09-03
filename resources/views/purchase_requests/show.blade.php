<x-app-layout>
    @php
        $rawStatus = $purchaseRequest->status instanceof \BackedEnum ? $purchaseRequest->status->value : (string) $purchaseRequest->status;
        $statusLabel = is_object($purchaseRequest->status) && method_exists($purchaseRequest->status, 'label')
            ? $purchaseRequest->status->label()
            : \Illuminate\Support\Str::headline($rawStatus);
        $statusClasses = is_object($purchaseRequest->status) && method_exists($purchaseRequest->status, 'badgeClasses')
            ? $purchaseRequest->status->badgeClasses()
            : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200';
        // La pantalla pregunta a la policy, no reimplementa sus reglas: si la
        // vista decide por su cuenta quién puede qué, tarde o temprano deja de
        // coincidir con el backend y aparecen botones que no funcionan —o,
        // peor, acciones legítimas que quedan ocultas.
        $isEditable = auth()->user()?->can('update', $purchaseRequest) ?? false;
        $canReview = auth()->user()?->can('approve', $purchaseRequest) ?? false;
        $formatDate = static function ($date): string {
            if (blank($date)) return '—';
            try { return $date instanceof \Carbon\CarbonInterface ? $date->format('d-m-Y') : \Illuminate\Support\Carbon::parse($date)->format('d-m-Y'); } catch (\Throwable) { return (string) $date; }
        };
        $formatDateTime = static function ($date): string {
            if (blank($date)) return '—';
            try { return $date instanceof \Carbon\CarbonInterface ? $date->format('d-m-Y H:i') : \Illuminate\Support\Carbon::parse($date)->format('d-m-Y H:i'); } catch (\Throwable) { return (string) $date; }
        };
        $priority = $purchaseRequest->priority === 'urgent' ? ['Urgente', 'bg-rose-100 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300'] : ['Normal', 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'];
        $suggestedSuppliers = $purchaseRequest->suggested_suppliers ?? [];
        $suggestedSuppliers = is_array($suggestedSuppliers) ? array_filter($suggestedSuppliers) : [];
    @endphp

    <x-slot name="header">
        <div class="flex min-w-0 items-center gap-2">
            <a href="{{ route('purchase_requests.index') }}" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-blue-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-blue-400" aria-label="Volver a solicitudes">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </a>
            <div class="min-w-0">
                <h1 class="truncate text-sm font-extrabold text-slate-900 dark:text-white">Solicitud {{ $purchaseRequest->folio ?: '#'.$purchaseRequest->id }}</h1>
                <p class="truncate text-xs text-slate-500 dark:text-slate-400">Detalle, antecedentes e historial de revisión</p>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-8xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        @include('purchase_requests._module_nav')

        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200">{{ session('error') }}</div>
        @endif

        <section x-data="{ revisiones: false }" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-4 p-4 sm:p-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-mono text-sm font-extrabold text-blue-600 dark:text-blue-400">{{ $purchaseRequest->folio ?: 'BOR-'.$purchaseRequest->id }}</span>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold {{ $statusClasses }}">{{ $statusLabel }}</span>
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $priority[1] }}">{{ $priority[0] }}</span>
                    </div>
                    <h2 class="mt-3 text-xl font-black tracking-tight text-slate-900 dark:text-white sm:text-2xl">{{ $purchaseRequest->reason }}</h2>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Creada el {{ $formatDate($purchaseRequest->created_at) }} · revisión {{ $purchaseRequest->revision_number ?: 0 }}</p>
                </div>
                <div class="flex flex-wrap gap-2 lg:justify-end">
                    <a href="{{ route('purchase_requests.pdf', $purchaseRequest) }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M5 20h14a2 2 0 002-2v-1a2 2 0 00-2-2H5a2 2 0 00-2 2v1a2 2 0 002 2zM7 4h10v6H7z" /></svg>
                        PDF
                    </a>
                    @if($isEditable)
                        <a href="{{ route('purchase_requests.edit', $purchaseRequest) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-600 px-4 text-sm font-extrabold text-white shadow-sm hover:bg-blue-700">Editar</a>
                    @endif
                    @if($purchaseRequest->revisions->isNotEmpty())
                        {{-- Con una sola revisión esto repetía el botón PDF de al
                             lado, así que ocupaba una tarjeta entera para no decir
                             nada nuevo. Como botón sólo estorba cuando se abre. --}}
                        <button type="button" @click="revisiones = !revisiones"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                            Revisiones
                            <span class="rounded-full bg-slate-100 px-1.5 text-xs font-extrabold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $purchaseRequest->revisions->count() }}</span>
                        </button>
                    @endif
                </div>
            </div>

            {{-- El panel se abre en el flujo normal, debajo del encabezado, y no
                 flotando: una capa flotante aquí queda tapada por el contenedor
                 de la página, que crea su propio contexto de apilamiento. --}}
            @if($purchaseRequest->revisions->isNotEmpty())
                <div x-show="revisiones" x-cloak class="border-t border-slate-100 dark:border-slate-800">
                    <p class="px-4 pt-3 text-xs text-slate-500 dark:text-slate-400 sm:px-5">
                        Cada revisión guarda el documento tal como se envió y no se regenera con datos nuevos.
                    </p>
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($purchaseRequest->revisions as $rev)
                            <li class="flex items-center justify-between gap-3 px-4 py-3 sm:px-5">
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-slate-900 dark:text-white">
                                        Revisión {{ $rev->revision_number }}
                                        @if($rev->revision_number === $purchaseRequest->revision_number)
                                            <span class="ml-1 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-bold text-blue-700 dark:bg-blue-950/60 dark:text-blue-300">vigente</span>
                                        @endif
                                    </p>
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                        {{ $rev->submitted_by_name_snapshot }} · {{ optional($rev->submitted_at)->format('d-m-Y H:i') }}
                                        · {{ $rev->item_count }} {{ \Illuminate\Support\Str::plural('partida', $rev->item_count) }}
                                    </p>
                                </div>
                                @can('downloadPdf', $purchaseRequest)
                                    <a href="{{ route('purchase_requests.pdf', ['purchaseRequest' => $purchaseRequest, 'revision' => $rev->revision_number]) }}"
                                        class="inline-flex min-h-11 shrink-0 items-center rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                                        PDF
                                    </a>
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($rawStatus === 'changes_requested')
                @php $lastChange = $purchaseRequest->events?->first(fn ($event) => data_get($event, 'event_type') === 'changes_requested'); @endphp
                <div class="border-t border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100 sm:px-5">
                    <p class="font-extrabold">Esta solicitud requiere correcciones.</p>
                    @if(filled(data_get($lastChange, 'comment')))<p class="mt-1">{{ data_get($lastChange, 'comment') }}</p>@endif

                    {{-- Puntos marcados por el revisor: el comentario dice el
                         porqué, esta lista dice exactamente dónde. --}}
                    @if(filled($purchaseRequest->requested_corrections))
                        <p class="mt-3 text-xs font-extrabold uppercase tracking-wider text-amber-700 dark:text-amber-300">Puntos a corregir</p>
                        <ul class="mt-1.5 flex flex-wrap gap-1.5">
                            @foreach($purchaseRequest->requested_corrections as $punto)
                                <li class="rounded-full bg-amber-200 px-2.5 py-1 text-xs font-bold text-amber-900 dark:bg-amber-900/60 dark:text-amber-100">
                                    {{ \App\Enums\PurchaseRequestCorrection::labelFor($punto) }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif
        </section>

        <div class="grid gap-5 xl:grid-cols-3">
            <div class="space-y-5 xl:col-span-2">
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-100 px-4 py-4 dark:border-slate-800 sm:px-5"><h2 class="font-extrabold text-slate-900 dark:text-white">Información de la solicitud</h2></div>
                    <dl class="grid gap-x-6 gap-y-4 p-4 text-sm sm:grid-cols-2 sm:p-5">
                        <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Departamento</dt><dd class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ $purchaseRequest->department ?: '—' }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Fecha requerida</dt><dd class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ $formatDate($purchaseRequest->required_date) }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Solicitante</dt><dd class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ $purchaseRequest->requester_name_snapshot ?: data_get($purchaseRequest, 'requester.name', '—') }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Solicitado para</dt><dd class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ $purchaseRequest->requested_for_name ?: $purchaseRequest->requested_for ?: '—' }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Centro de costo</dt><dd class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ $purchaseRequest->cost_center ?: '—' }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Lugar de entrega o uso</dt><dd class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ $purchaseRequest->delivery_location ?: '—' }}</dd></div>
                        @if(filled($purchaseRequest->urgent_reason))
                            <div class="sm:col-span-2"><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Justificación de urgencia</dt><dd class="mt-1 whitespace-pre-line text-slate-700 dark:text-slate-200">{{ $purchaseRequest->urgent_reason }}</dd></div>
                        @endif
                        @if(filled($purchaseRequest->internal_notes ?? $purchaseRequest->notes))
                            <div class="sm:col-span-2"><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Observaciones internas</dt><dd class="mt-1 whitespace-pre-line text-slate-700 dark:text-slate-200">{{ $purchaseRequest->internal_notes ?? $purchaseRequest->notes }}</dd></div>
                        @endif
                    </dl>
                </section>

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-4 dark:border-slate-800 sm:px-5"><h2 class="font-extrabold text-slate-900 dark:text-white">Partidas</h2><div class="flex items-center gap-3">@if(filled($purchaseRequest->total()))
                        @php
                            $tasa = (float) config('purchase_requests.tax_rate', 0.19);
                            $simbolo = $purchaseRequest->currency === 'CLP' ? '$' : $purchaseRequest->currency.' ';
                            // El total guardado es la suma de las partidas; si esos
                            // precios ya traen IVA, el neto se saca hacia atrás.
                            $neto = $purchaseRequest->prices_include_tax
                                ? $purchaseRequest->total() / (1 + $tasa)
                                : $purchaseRequest->total();
                        @endphp
                        <span class="flex flex-wrap items-center gap-x-2 text-sm">
                            <span class="text-slate-500 dark:text-slate-400">Neto {{ $simbolo }}{{ number_format($neto, 0, ',', '.') }}</span>
                            <span class="text-slate-300 dark:text-slate-600">·</span>
                            <span class="text-slate-500 dark:text-slate-400">IVA {{ $simbolo }}{{ number_format($neto * $tasa, 0, ',', '.') }}</span>
                            <span class="text-slate-300 dark:text-slate-600">·</span>
                            <span class="font-extrabold text-slate-900 dark:text-white">Total {{ $simbolo }}{{ number_format($neto * (1 + $tasa), 0, ',', '.') }}</span>
                        </span>
                    @endif<span class="text-xs font-bold text-slate-400">{{ $purchaseRequest->items->count() }} ítem{{ $purchaseRequest->items->count() === 1 ? '' : 's' }}</span></div></div>
                    @if($purchaseRequest->hasPartialPricing())
                        {{-- Un total que sólo suma parte de las partidas engaña más
                             que ayudar: hay que decir que está incompleto. --}}
                        <p class="border-b border-amber-200 bg-amber-50 px-4 py-2 text-xs font-bold text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200 sm:px-5">
                            El total no incluye todas las partidas: algunas no tienen precio.
                        </p>
                    @endif
                    <div class="divide-y divide-slate-100 dark:divide-slate-800 md:hidden">
                        @foreach($purchaseRequest->items as $index => $item)
                            <article class="p-4"><div class="flex gap-3"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-50 text-xs font-extrabold text-blue-700 dark:bg-blue-950/50 dark:text-blue-300">{{ $index + 1 }}</span><div class="min-w-0"><p class="font-bold text-slate-900 dark:text-white">{{ $item->product_service }}</p>@if(filled($item->specification))<p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $item->specification }}</p>@endif<div class="mt-2 flex flex-wrap gap-2 text-xs"><span class="rounded-full bg-slate-100 px-2 py-1 font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ rtrim(rtrim(number_format((float) $item->quantity, 3, ',', '.'), '0'), ',') }} {{ $item->unit }}</span>@if(filled($item->quantity_note))<span class="text-slate-500 dark:text-slate-400">{{ $item->quantity_note }}</span>@endif @if(filled($item->unit_price))<span class="rounded-full bg-slate-100 px-2 py-1 font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ number_format((float) $item->unit_price, 0, ',', '.') }} c/u · total {{ number_format((float) $item->lineTotal(), 0, ',', '.') }}</span>@endif</div>@if(filled($item->destination))<p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Destino: {{ $item->destination }}</p>@endif</div></div></article>
                        @endforeach
                    </div>
                    <div class="hidden overflow-x-auto md:block"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:bg-slate-950/50 dark:text-slate-400"><tr><th class="px-5 py-3">N°</th><th class="px-5 py-3">Producto / servicio</th><th class="px-5 py-3">Especificación</th><th class="px-5 py-3 text-right">Cantidad</th><th class="px-5 py-3 text-right">Precio unit.</th><th class="px-5 py-3 text-right">Total</th><th class="px-5 py-3">Destino</th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800">@foreach($purchaseRequest->items as $index => $item)<tr><td class="px-5 py-4 font-bold text-slate-400">{{ $index + 1 }}</td><td class="px-5 py-4 font-semibold text-slate-800 dark:text-slate-100">{{ $item->product_service }}@if(filled($item->quantity_note))<p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">{{ $item->quantity_note }}</p>@endif</td><td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $item->specification ?: '—' }}</td><td class="px-5 py-4 text-right font-bold text-slate-800 dark:text-slate-100">{{ rtrim(rtrim(number_format((float) $item->quantity, 3, ',', '.'), '0'), ',') }} {{ $item->unit }}</td><td class="px-5 py-4 text-right text-slate-600 dark:text-slate-300">{{ filled($item->unit_price) ? number_format((float) $item->unit_price, 0, ',', '.') : '—' }}</td><td class="px-5 py-4 text-right font-bold text-slate-800 dark:text-slate-100">{{ filled($item->unit_price) ? number_format((float) $item->lineTotal(), 0, ',', '.') : '—' }}</td><td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $item->destination ?: '—' }}</td></tr>@endforeach</tbody></table></div>
                </section>

                @if(count($suggestedSuppliers))
                    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5"><h2 class="font-extrabold text-slate-900 dark:text-white">Proveedores sugeridos</h2><ul class="mt-3 grid gap-2 sm:grid-cols-2">@foreach($suggestedSuppliers as $supplier)<li class="rounded-xl bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $supplier }}</li>@endforeach</ul></section>
                @endif
            </div>

            <aside class="space-y-5">
                @if($isEditable)
                    <section class="rounded-2xl border border-blue-200 bg-blue-50 p-4 shadow-sm dark:border-blue-900/60 dark:bg-blue-950/30"><h2 class="font-extrabold text-blue-950 dark:text-blue-100">Lista para enviar</h2><p class="mt-1 text-sm text-blue-800 dark:text-blue-200">Al enviar, la solicitud quedará pendiente de revisión.</p><form method="POST" action="{{ route('purchase_requests.submit', $purchaseRequest) }}" class="mt-4">@csrf<button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-blue-600 px-4 text-sm font-extrabold text-white hover:bg-blue-700">Enviar a revisión</button></form></section>
                @endif

                {{-- Anulación pendiente: el revisor tiene que verla ANTES de
                     decidir. Una solicitud se aprobó en producción treinta
                     segundos después de que el solicitante pidiera anularla. --}}
                @if($purchaseRequest->cancellation_requested_at)
                    <section class="rounded-2xl border-2 border-rose-300 bg-rose-50 p-4 shadow-sm dark:border-rose-800 dark:bg-rose-950/40">
                        <h2 class="flex items-center gap-2 font-extrabold text-rose-900 dark:text-rose-100">
                            <span aria-hidden="true">⊘</span>
                            {{ $purchaseRequest->requester?->name ?? 'El solicitante' }} pidió anular esta solicitud
                        </h2>
                        <p class="mt-1 text-xs text-rose-800 dark:text-rose-200">
                            El {{ $purchaseRequest->cancellation_requested_at->format('d-m-Y') }} a las
                            {{ $purchaseRequest->cancellation_requested_at->format('H:i') }}.
                        </p>

                        @if(filled($purchaseRequest->cancellation_reason))
                            <p class="mt-2 rounded-xl bg-white/70 px-3 py-2 text-sm text-rose-900 dark:bg-rose-950/60 dark:text-rose-100">
                                {{ $purchaseRequest->cancellation_reason }}
                            </p>
                        @endif

                        <p class="mt-2 text-sm font-semibold text-rose-900 dark:text-rose-100">
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
                                    class="min-h-11 w-full rounded-xl border border-rose-300 bg-white px-3 text-sm font-bold text-rose-800 hover:bg-rose-100 dark:border-rose-800 dark:bg-transparent dark:text-rose-200">
                                    Retirar mi petición de anulación
                                </button>
                            </form>
                        @endcan
                    </section>
                @endif

                @php
                    $odooActivo = (bool) config('purchase_requests.odoo.enabled');
                    $yaEnOdoo = filled($purchaseRequest->odoo_order_id);
                    $candidatos = session('odoo_candidates', []);
                @endphp

                @can('exportToOdoo', $purchaseRequest) @if($odooActivo)
                    <section class="rounded-2xl border border-violet-200 bg-violet-50 p-4 shadow-sm dark:border-violet-900/60 dark:bg-violet-950/30">
                        <h2 class="font-extrabold text-violet-950 dark:text-violet-100">Odoo</h2>

                        @if($yaEnOdoo)
                            <p class="mt-1 text-sm text-violet-900 dark:text-violet-200">
                                Ya está en Odoo como <span class="font-extrabold">{{ $purchaseRequest->odoo_reference }}</span>,
                                en borrador. Se confirma allá.
                            </p>
                            <p class="mt-1 text-xs text-violet-800 dark:text-violet-300">
                                Enviada el {{ $purchaseRequest->odoo_exported_at?->format('d-m-Y H:i') }}.
                                No se vuelve a enviar: habría dos cotizaciones para la misma compra.
                            </p>
                        {{-- `exists` y no `filled`: cuando Odoo no encontró nada
                             la lista llega vacía, y es justo el momento en que
                             más falta hace el buscador. --}}
                        @elseif(session()->exists('odoo_candidates') || session('odoo_query'))
                            <p class="mt-1 text-sm text-violet-900 dark:text-violet-200">
                                Odoo no reconoce a «{{ collect($purchaseRequest->suggested_suppliers ?? [])->first() ?: 'el proveedor' }}».
                                Búscalo tú, que sabes a quién buscas.
                            </p>

                            <form method="POST" action="{{ route('purchase_requests.odoo.supplier_search', $purchaseRequest) }}" class="mt-3 flex gap-2">
                                @csrf
                                <input name="q" value="{{ session('odoo_query') }}" placeholder="Buscar en Odoo por nombre o RUT"
                                    class="min-h-11 w-full rounded-xl border-violet-300 bg-white px-3 text-sm dark:border-violet-800 dark:bg-slate-950 dark:text-white">
                                <button type="submit"
                                    class="min-h-11 shrink-0 rounded-xl border border-violet-300 px-4 text-sm font-bold text-violet-800 hover:bg-violet-100 dark:border-violet-800 dark:text-violet-200">
                                    Buscar
                                </button>
                            </form>

                            @foreach($candidatos as $candidato)
                                <form method="POST" action="{{ route('purchase_requests.odoo.supplier', $purchaseRequest) }}" class="mt-2">
                                    @csrf
                                    <input type="hidden" name="odoo_partner_id" value="{{ $candidato['id'] }}">
                                    <input type="hidden" name="name" value="{{ $candidato['name'] }}">
                                    <input type="hidden" name="vat" value="{{ $candidato['vat'] }}">
                                    <button type="submit"
                                        class="w-full rounded-xl border border-violet-300 bg-white px-3 py-2.5 text-left text-sm hover:bg-violet-100 dark:border-violet-800 dark:bg-slate-950 dark:hover:bg-violet-950/60">
                                        <span class="block font-bold text-slate-900 dark:text-white">{{ $candidato['name'] }}</span>
                                        <span class="block text-xs {{ blank($candidato['vat'] ?? null) ? 'text-amber-700 dark:text-amber-300' : 'text-slate-500 dark:text-slate-400' }}">
                                            {{ filled($candidato['vat'] ?? null) ? 'RUT '.$candidato['vat'] : 'Sin RUT en Odoo · habrá que ponérselo allá para que se reconozca solo' }}
                                        </span>
                                    </button>
                                </form>
                            @endforeach

                            <p class="mt-3 text-xs text-violet-800 dark:text-violet-300">
                                Si no aparece, hay que darlo de alta en Odoo. Este programa no crea proveedores:
                                el maestro de la empresa se administra allá.
                            </p>

                        @else
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

                            {{-- Sin producto, Odoo no genera recepción al confirmar
                                 la orden y el stock nunca sube. Por eso se muestra
                                 partida por partida antes de enviar. --}}
                            <div class="mt-3 space-y-2">
                                @foreach($purchaseRequest->items as $partida)
                                    @php
                                        $r = $emparejador->match((string) $partida->product_service, $proveedorOdoo, $partida->specification);
                                        $encontrados = $busquedas[$partida->id] ?? null;
                                    @endphp

                                    <div class="rounded-xl border border-violet-200 bg-white p-3 dark:border-violet-900 dark:bg-slate-950">
                                        <p class="text-sm font-bold text-slate-900 dark:text-white">{{ $partida->product_service }}</p>

                                        @if($r->resolved())
                                            <p class="mt-1 text-xs font-bold text-emerald-700 dark:text-emerald-400">
                                                ✓ {{ $r->odooProductName }}
                                            </p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $r->reason }}</p>
                                        @else
                                            <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">
                                                Sin producto de Odoo · esta línea no sumará al stock al recibirla
                                            </p>

                                            @foreach(($encontrados ?? $r->candidates) as $c)
                                                <form method="POST" action="{{ route('purchase_requests.odoo.product_link', [$purchaseRequest, $partida]) }}" class="mt-1.5">
                                                    @csrf
                                                    <input type="hidden" name="odoo_product_id" value="{{ $c['odoo_id'] }}">
                                                    <input type="hidden" name="odoo_product_name" value="{{ $c['name'] }}">
                                                    <button type="submit"
                                                        class="w-full rounded-lg border border-slate-200 px-2.5 py-2 text-left text-xs hover:bg-violet-50 dark:border-slate-700 dark:hover:bg-violet-950/50">
                                                        <span class="font-bold text-slate-800 dark:text-slate-100">{{ $c['name'] }}</span>
                                                        <span class="block text-slate-500 dark:text-slate-400">
                                                            {{ $c['reason'] }} · parecido {{ number_format($c['score'] * 100, 0) }}%
                                                        </span>
                                                    </button>
                                                </form>
                                            @endforeach

                                            <form method="POST" action="{{ route('purchase_requests.odoo.product_search', [$purchaseRequest, $partida]) }}" class="mt-2 flex gap-1.5">
                                                @csrf
                                                <input name="q" value="{{ $consultas[$partida->id] ?? '' }}"
                                                    placeholder="Buscar producto en Odoo"
                                                    class="min-h-10 w-full rounded-lg border-slate-300 bg-white px-2 text-xs dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                                <button type="submit"
                                                    class="min-h-10 shrink-0 rounded-lg border border-slate-300 px-3 text-xs font-bold text-slate-700 dark:border-slate-700 dark:text-slate-200">
                                                    Buscar
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            <p class="mt-1 text-sm text-violet-900 dark:text-violet-200">
                                Crea la cotización en Odoo, en borrador. No la confirma: eso se decide allá.
                            </p>
                            <form method="POST" action="{{ route('purchase_requests.odoo.export', $purchaseRequest) }}" class="mt-3">
                                @csrf
                                <button type="submit"
                                    class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-violet-600 px-4 text-sm font-extrabold text-white hover:bg-violet-700">
                                    Enviar a Odoo
                                </button>
                            </form>
                        @endif
                    </section>
                @endif @endcan

                @if($canReview)
                    <section x-data="{ action: '' }" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm dark:border-amber-900/60 dark:bg-amber-950/30"><h2 class="font-extrabold text-amber-950 dark:text-amber-100">Revisión de Compras</h2><p class="mt-1 text-sm text-amber-800 dark:text-amber-200">La acción quedará registrada en el historial.</p><form method="POST" action="{{ route('purchase_requests.approve', $purchaseRequest) }}" class="mt-4 space-y-3">@csrf<input type="hidden" name="lock_version" value="{{ $purchaseRequest->lock_version }}"><div x-show="action === 'changes'" x-cloak class="rounded-xl border border-amber-300 bg-white/70 p-3 dark:border-amber-900 dark:bg-slate-950/40">
    <p class="text-xs font-extrabold text-amber-900 dark:text-amber-100">¿Qué hay que corregir?</p>
    <p class="mt-0.5 text-xs text-amber-800 dark:text-amber-200">Marca los puntos concretos. El solicitante los verá resaltados al editar.</p>

    <div class="mt-2 grid gap-1.5 sm:grid-cols-2">
        @foreach (\App\Enums\PurchaseRequestCorrection::cases() as $punto)
            <label class="flex min-h-11 items-center gap-2 rounded-lg px-2 text-xs font-semibold text-amber-900 hover:bg-amber-50 dark:text-amber-100 dark:hover:bg-amber-950/40">
                <input type="checkbox" name="corrections[]" value="{{ $punto->value }}"
                    class="h-4 w-4 shrink-0 rounded border-amber-400 text-amber-600 focus:ring-amber-500">
                <span>{{ $punto->label() }}</span>
            </label>
        @endforeach
    </div>

    @if($purchaseRequest->items->isNotEmpty())
        <p class="mt-3 text-xs font-extrabold text-amber-900 dark:text-amber-100">Partidas puntuales</p>
        <div class="mt-1.5 max-h-44 overflow-y-auto rounded-lg border border-amber-200 dark:border-amber-900">
            @foreach ($purchaseRequest->items as $partida)
                <label class="flex min-h-11 items-center gap-2 border-b border-amber-100 px-2 text-xs text-amber-900 last:border-b-0 hover:bg-amber-50 dark:border-amber-900/60 dark:text-amber-100 dark:hover:bg-amber-950/40">
                    <input type="checkbox" name="corrections[]" value="{{ \App\Enums\PurchaseRequestCorrection::itemKey($partida->sort_order) }}"
                        class="h-4 w-4 shrink-0 rounded border-amber-400 text-amber-600 focus:ring-amber-500">
                    <span class="font-bold">{{ $partida->sort_order }}.</span>
                    <span class="truncate">{{ $partida->product_service }}</span>
                </label>
            @endforeach
        </div>
    @endif
    @error('corrections.*') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
</div><label for="review-comment" class="block text-xs font-bold text-amber-900 dark:text-amber-100">Comentario <span x-show="action !== 'approve'" class="text-rose-600">*</span></label><textarea id="review-comment" name="comment" rows="3" :required="action !== 'approve'" class="w-full rounded-xl border-amber-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-amber-900 dark:bg-slate-950 dark:text-white" placeholder="Obligatorio al devolver o rechazar."></textarea><div class="grid grid-cols-1 gap-2"><button type="submit" @click="action = 'approve'" formaction="{{ route('purchase_requests.approve', $purchaseRequest) }}" class="min-h-11 rounded-xl bg-emerald-600 px-3 text-sm font-extrabold text-white hover:bg-emerald-700">Aprobar</button><button type="submit" @click="action = 'changes'" formaction="{{ route('purchase_requests.request_changes', $purchaseRequest) }}" class="min-h-11 rounded-xl border border-amber-300 px-3 text-sm font-extrabold text-amber-800 hover:bg-amber-100 dark:border-amber-800 dark:text-amber-200 dark:hover:bg-amber-900/40">Solicitar cambios</button><button type="submit" @click="action = 'reject'" formaction="{{ route('purchase_requests.reject', $purchaseRequest) }}" class="min-h-11 rounded-xl border border-rose-300 px-3 text-sm font-extrabold text-rose-700 hover:bg-rose-100 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/30">Rechazar</button></div></form></section>
                @endif

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"><div class="border-b border-slate-100 px-4 py-4 dark:border-slate-800"><h2 class="font-extrabold text-slate-900 dark:text-white">Adjuntos</h2></div><div class="divide-y divide-slate-100 dark:divide-slate-800">@forelse($purchaseRequest->attachments as $attachment)<div class="p-4"><p class="truncate text-sm font-bold text-slate-800 dark:text-slate-100">{{ $attachment->original_name ?: $attachment->file_name ?: 'Archivo adjunto' }}</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ number_format(((int) ($attachment->size ?? $attachment->file_size ?? 0)) / 1024, 1, ',', '.') }} KB</p><div class="mt-3 flex gap-3"><a href="{{ route('purchase_requests.attachments.download', [$purchaseRequest, $attachment]) }}" class="text-xs font-extrabold text-blue-600 hover:text-blue-800 dark:text-blue-400">Descargar</a>@if($isEditable)<form method="POST" action="{{ route('purchase_requests.attachments.destroy', [$purchaseRequest, $attachment]) }}">@csrf @method('DELETE')<button type="submit" class="text-xs font-extrabold text-rose-600 hover:text-rose-800 dark:text-rose-400">Eliminar</button></form>@endif</div></div>@empty<div class="p-4 text-sm text-slate-500 dark:text-slate-400">No hay antecedentes adjuntos.</div>@endforelse</div></section>

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"><div class="border-b border-slate-100 px-4 py-4 dark:border-slate-800"><h2 class="font-extrabold text-slate-900 dark:text-white">Historial</h2></div><ol class="space-y-4 p-4">@forelse($purchaseRequest->events as $event)<li class="relative pl-5"><span class="absolute left-0 top-1 h-3 w-3 rounded-full {{ $event instanceof \App\Models\PurchaseRequestEvent ? $event->dotClasses() : 'bg-slate-400' }}" aria-hidden="true"></span><p class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $event instanceof \App\Models\PurchaseRequestEvent ? $event->label() : (data_get($event, 'label') ?: \Illuminate\Support\Str::headline(data_get($event, 'event_type') ?: 'actualización')) }}</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ data_get($event, 'actor.name') ?: data_get($event, 'actor_name_snapshot') ?: 'Sistema' }} · {{ $formatDateTime($event->created_at) }}</p>@if(filled(data_get($event, 'comment')))<p class="mt-1 whitespace-pre-line text-xs text-slate-600 dark:text-slate-300">{{ data_get($event, 'comment') }}</p>@endif</li>@empty<li class="text-sm text-slate-500 dark:text-slate-400">Aún no hay eventos registrados.</li>@endforelse</ol></section>

                {{-- La cotización que manda el proveedor, contrastada con lo
                     pedido. Compara y muestra; no cambia nada de la solicitud. --}}
                <section class="rounded-2xl border border-sky-200 bg-sky-50 p-4 shadow-sm dark:border-sky-900/60 dark:bg-sky-950/30">
                    <h2 class="font-extrabold text-sky-950 dark:text-sky-100">Cotización del proveedor</h2>
                    <p class="mt-1 text-sm text-sky-800 dark:text-sky-200">
                        Sube lo que te mandó el proveedor y te digo en qué se diferencia de lo que pediste.
                        No modifica la solicitud.
                    </p>

                    <form method="POST" action="{{ route('purchase_requests.quotes.store', $purchaseRequest) }}"
                        enctype="multipart/form-data" class="mt-3 space-y-2">
                        @csrf
                        <input type="file" name="quote" accept=".pdf,.jpg,.jpeg,.png" required
                            class="block w-full text-sm text-sky-900 file:mr-3 file:min-h-9 file:rounded-lg file:border-0 file:bg-sky-600 file:px-3 file:text-sm file:font-bold file:text-white hover:file:bg-sky-700 dark:text-sky-100">
                        @error('quote') <p class="text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
                        <button type="submit"
                            class="min-h-11 w-full rounded-xl bg-sky-600 px-3 text-sm font-extrabold text-white hover:bg-sky-700">
                            Comparar con mi solicitud
                        </button>
                    </form>

                    @foreach ($comparaciones as $comparacion)
                        @php($lectura = $comparacion['ingestion'])
                        @php($resultado = $comparacion['resultado'])

                        <div class="mt-4 rounded-xl border border-sky-200 bg-white p-3 dark:border-sky-900 dark:bg-slate-900">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-extrabold text-slate-900 dark:text-white">{{ $lectura->original_name }}</p>
                                    <p class="mt-0.5 text-xs {{ $resultado->cuadra() ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400' }} font-bold">
                                        {{ $resultado->cuadra() ? '✓' : '⚠' }} {{ $resultado->resumen() }}
                                    </p>
                                    @if($lectura->supplier_name)
                                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Proveedor leído: {{ $lectura->supplier_name }}</p>
                                    @endif
                                </div>
                                <form method="POST" action="{{ route('purchase_requests.quotes.destroy', [$purchaseRequest, $lectura]) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="min-h-9 rounded-lg px-2 text-xs font-bold text-slate-500 hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800">Quitar</button>
                                </form>
                            </div>

                            @if($lectura->status === \App\Models\PurchaseRequestIngestion::PENDING || $lectura->status === \App\Models\PurchaseRequestIngestion::PROCESSING)
                                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Leyéndola… recarga en un momento.</p>
                            @else
                                <div class="mt-3 overflow-x-auto">
                                    <table class="w-full min-w-[34rem] text-left text-xs">
                                        <thead class="text-slate-500 dark:text-slate-400">
                                            <tr>
                                                <th class="pb-1 font-bold">Partida</th>
                                                <th class="pb-1 font-bold">Pediste</th>
                                                <th class="pb-1 font-bold">Cotizaron</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                            @foreach ($resultado->todas() as $fila)
                                                <tr class="align-top">
                                                    <td class="py-1.5 pr-2">
                                                        <span class="font-bold text-slate-800 dark:text-slate-100">
                                                            {{ $fila->pedida?->product_service ?? $fila->cotizada['product_service'] ?? '—' }}
                                                        </span>
                                                        @foreach ($fila->diferencias as $diferencia)
                                                            <span class="mt-0.5 block text-[11px] {{ $fila->estaBien() ? 'text-slate-500' : 'text-amber-700 dark:text-amber-400' }}">{{ $diferencia }}</span>
                                                        @endforeach
                                                    </td>
                                                    <td class="py-1.5 pr-2 text-slate-600 dark:text-slate-300">
                                                        @if($fila->pedida)
                                                            {{ rtrim(rtrim(number_format((float) $fila->pedida->quantity, 2, ',', '.'), '0'), ',') }} {{ $fila->pedida->unit }}
                                                            @if($fila->pedida->unit_price !== null)
                                                                <span class="block text-[11px]">$ {{ number_format((float) $fila->pedida->unit_price, 0, ',', '.') }}</span>
                                                            @endif
                                                        @else
                                                            <span class="text-slate-400">no la pediste</span>
                                                        @endif
                                                    </td>
                                                    <td class="py-1.5 text-slate-600 dark:text-slate-300">
                                                        @if($fila->cotizada)
                                                            {{ $fila->cotizada['quantity'] ?? '—' }} {{ $fila->cotizada['unit'] ?? '' }}
                                                            @if(filled($fila->cotizada['unit_price'] ?? null))
                                                                <span class="block text-[11px]">$ {{ number_format((float) $fila->cotizada['unit_price'], 0, ',', '.') }}</span>
                                                            @endif
                                                        @else
                                                            <span class="text-slate-400">no la cotizaron</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </section>

                {{-- Aprobada y todavía no enviada: es el último momento para
                     corregirla. Se devuelve en vez de editarla en caliente,
                     para que lo que llegue a Odoo sea lo que alguien aprobó. --}}
                @can('requestChanges', $purchaseRequest)
                    @if($purchaseRequest->status === \App\Enums\PurchaseRequestStatus::APPROVED)
                        <section x-data="{ open: false }" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm dark:border-amber-900/60 dark:bg-amber-950/30">
                            <h2 class="font-extrabold text-amber-950 dark:text-amber-100">Devolver para corregir</h2>
                            <p class="mt-1 text-sm text-amber-800 dark:text-amber-200">
                                Todavía no se envía a Odoo, así que aún se puede arreglar. Vuelve a estado
                                editable y hay que aprobarla de nuevo antes de enviarla.
                            </p>
                            <button type="button" x-show="!open" @click="open = true"
                                class="mt-3 min-h-11 w-full rounded-xl border border-amber-300 px-3 text-sm font-extrabold text-amber-800 hover:bg-amber-100 dark:border-amber-800 dark:text-amber-200 dark:hover:bg-amber-900/40">
                                Devolver para corregir…
                            </button>
                            <form method="POST" action="{{ route('purchase_requests.request_changes', $purchaseRequest) }}"
                                x-show="open" x-cloak class="mt-3 space-y-3">
                                @csrf
                                <input type="hidden" name="lock_version" value="{{ $purchaseRequest->lock_version }}">
                                <label for="reopen-comment" class="block text-xs font-bold text-amber-900 dark:text-amber-100">
                                    ¿Qué hay que corregir? <span class="text-rose-600">*</span>
                                </label>
                                <textarea id="reopen-comment" name="comment" rows="3" required
                                    class="w-full rounded-xl border-amber-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-amber-900 dark:bg-slate-950 dark:text-white"
                                    placeholder="Ej.: la unidad de la partida 3 no corresponde."></textarea>
                                @error('comment') <p class="text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
                                <div class="grid grid-cols-2 gap-2">
                                    <button type="button" @click="open = false"
                                        class="min-h-11 rounded-xl border border-amber-200 text-sm font-bold text-amber-800 hover:bg-amber-100 dark:border-amber-800 dark:text-amber-200">Cancelar</button>
                                    <button type="submit"
                                        class="min-h-11 rounded-xl bg-amber-600 text-sm font-extrabold text-white hover:bg-amber-700">Devolver</button>
                                </div>
                            </form>
                        </section>
                    @endif
                @endcan

                {{-- Anular: el borrador lo anula su autor; lo enviado, un revisor --}}
                @can('cancel', $purchaseRequest)
                    <section x-data="{ open: false }" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <h2 class="font-extrabold text-slate-900 dark:text-white">Anular solicitud</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            Queda registrada en el historial con su motivo. No se elimina.
                        </p>
                        <button type="button" x-show="!open" @click="open = true"
                            class="mt-3 min-h-11 w-full rounded-xl border border-rose-300 px-3 text-sm font-extrabold text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/30">
                            Anular…
                        </button>
                        <form method="POST" action="{{ route('purchase_requests.cancel', $purchaseRequest) }}"
                            x-show="open" x-cloak class="mt-3 space-y-3">
                            @csrf
                            <input type="hidden" name="lock_version" value="{{ $purchaseRequest->lock_version }}">
                            <label for="cancel-comment" class="block text-xs font-bold text-slate-700 dark:text-slate-200">
                                Motivo <span class="text-rose-600">*</span>
                            </label>
                            <textarea id="cancel-comment" name="comment" rows="3" required
                                class="w-full rounded-xl border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-rose-500 focus:ring-rose-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                placeholder="Explica por qué se anula."></textarea>
                            @error('comment') <p class="text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" @click="open = false"
                                    class="min-h-11 rounded-xl border border-slate-200 text-sm font-bold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300">Cancelar</button>
                                <button type="submit"
                                    class="min-h-11 rounded-xl bg-rose-600 text-sm font-extrabold text-white hover:bg-rose-700">Confirmar anulación</button>
                            </div>
                        </form>
                    </section>
                @endcan

                {{-- Ya enviada: el solicitante pide la anulación, no la ejecuta --}}
                @can('requestCancellation', $purchaseRequest)
                    <section x-data="{ open: false }" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <h2 class="font-extrabold text-slate-900 dark:text-white">Pedir anulación</h2>
                        @if($purchaseRequest->cancellation_requested_at)
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                                Tu petición está registrada más arriba, con la opción de retirarla.
                            </p>
                        @else
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                Como ya fue enviada, la anulación la decide Compras. Tu petición queda registrada.
                            </p>
                            <button type="button" x-show="!open" @click="open = true"
                                class="mt-3 min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm font-extrabold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200">
                                Pedir anulación…
                            </button>
                            <form method="POST" action="{{ route('purchase_requests.request_cancellation', $purchaseRequest) }}"
                                x-show="open" x-cloak class="mt-3 space-y-3">
                                @csrf
                                <label for="cancel-request-comment" class="block text-xs font-bold text-slate-700 dark:text-slate-200">
                                    Motivo <span class="text-rose-600">*</span>
                                </label>
                                <textarea id="cancel-request-comment" name="comment" rows="3" required
                                    class="w-full rounded-xl border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                    placeholder="Explica por qué ya no la necesitas."></textarea>
                                <div class="grid grid-cols-2 gap-2">
                                    <button type="button" @click="open = false"
                                        class="min-h-11 rounded-xl border border-slate-200 text-sm font-bold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300">Cancelar</button>
                                    <button type="submit"
                                        class="min-h-11 rounded-xl bg-slate-800 text-sm font-extrabold text-white hover:bg-slate-900 dark:bg-slate-100 dark:text-slate-900">Enviar petición</button>
                                </div>
                            </form>
                        @endif
                    </section>
                @endcan
            </aside>
        </div>
    </div>
</x-app-layout>
