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
                <p class="truncate text-xs text-slate-500 dark:text-slate-400">Órdenes hechas directo en Odoo</p>
            </div>
        </div>
    </x-slot>

    @php
        $money = function (float $value, ?string $currency): string {
            return $currency && $currency !== 'CLP'
                ? $currency.' '.number_format($value, 2, ',', '.')
                : '$'.number_format($value, 0, ',', '.');
        };
        $stateClasses = fn (string $state): string => match (true) {
            in_array($state, \App\Models\OdooPurchaseOrder::QUOTATION_STATES, true) => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300',
            in_array($state, \App\Models\OdooPurchaseOrder::CONFIRMED_STATES, true) => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300',
            default => 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
        };
        $tabs = [
            'cotizaciones' => ['Cotizaciones', 'aún sin confirmar'],
            'confirmadas' => ['Confirmadas', 'orden de compra'],
            'canceladas' => ['Canceladas', ''],
            'todas' => ['Todas', ''],
        ];
    @endphp

    <div class="mx-auto max-w-full space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        @include('purchase_requests._module_nav')

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="font-extrabold text-slate-900 dark:text-white">Hechas en Odoo, sin solicitud</h2>
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                        Cotizaciones y órdenes que alguien creó directo en Odoo. Las que salen de una solicitud no aparecen aquí.
                    </p>
                </div>
                <p class="text-xs text-slate-400">
                    @if($syncedAt)
                        Actualizado {{ \Illuminate\Support\Carbon::parse($syncedAt)->format('d-m-Y H:i') }} · cada noche a las 02:10
                    @else
                        Todavía no se ha sincronizado
                    @endif
                </p>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach($tabs as $key => [$label, $hint])
                    <a href="{{ route('purchase_requests.odoo', array_filter(['estado' => $key, 'q' => $search, 'creada_por' => $creator])) }}"
                       @if($filter === $key) aria-current="page" @endif
                       class="rounded-xl border px-3 py-2.5 transition {{ $filter === $key
                            ? 'border-blue-600 bg-blue-50 dark:border-blue-500 dark:bg-blue-950/40'
                            : 'border-slate-200 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800' }}">
                        <p class="text-[11px] font-bold uppercase tracking-wider {{ $filter === $key ? 'text-blue-700 dark:text-blue-300' : 'text-slate-400' }}">{{ $label }}</p>
                        <p class="text-xl font-black tabular-nums text-slate-900 dark:text-white">{{ $counts[$key] }}</p>
                        @if($hint)<p class="text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>@endif
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('purchase_requests.odoo') }}" class="mt-4 flex flex-col gap-2 sm:flex-row">
                <input type="hidden" name="estado" value="{{ $filter }}">
                <label class="sr-only" for="odoo-search">Buscar por número o proveedor</label>
                <input id="odoo-search" type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="Buscar P00… o proveedor"
                       class="min-w-0 flex-1 rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
                <label class="sr-only" for="odoo-creator">Creada por</label>
                <select id="odoo-creator" name="creada_por" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
                    <option value="">Cualquiera la creó</option>
                    @foreach($creators as $name)
                        <option value="{{ $name }}" @selected($creator === $name)>{{ $name }}</option>
                    @endforeach
                </select>
                <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900">Filtrar</button>
            </form>
        </section>

        @php
            // Facturación de una orden en una frase corta, para la columna de la tabla.
            $billing = function (\App\Models\OdooPurchaseOrder $order) use ($invoices): array {
                $list = $invoices[$order->odoo_id] ?? [];
                if ($list === []) {
                    return in_array($order->state, \App\Models\OdooPurchaseOrder::CONFIRMED_STATES, true)
                        ? ['Sin factura', 'text-slate-400'] : ['—', 'text-slate-300 dark:text-slate-600'];
                }
                if ($order->currency && $order->currency !== 'CLP') {
                    return [count($list).' '.(count($list) === 1 ? 'factura' : 'facturas'), 'text-sky-700 dark:text-sky-300'];
                }
                $net = array_sum(array_column($list, 'net'));
                $percent = $order->amount_untaxed > 0 ? round($net * 100 / $order->amount_untaxed) : 0;

                return $percent >= 99
                    ? ['Facturada'.($percent > 101 ? ' '.$percent.' %' : ''), $percent > 101 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-700 dark:text-emerald-400']
                    : ['En parte · '.$percent.' %', 'text-sky-700 dark:text-sky-300'];
            };
            $rowsCount = $items->sum(fn ($item) => $item['orders']->count());
        @endphp

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            @if($items->isEmpty())
                <p class="px-5 py-14 text-center text-sm text-slate-500 dark:text-slate-400">
                    {{ $syncedAt ? 'No hay órdenes en esta pestaña.' : 'Aún no se han traído las órdenes de Odoo.' }}
                </p>
            @else
                {{-- Escritorio: una fila por orden, sin nada que se despliegue. El detalle va en su propia página. --}}
                <div class="hidden overflow-x-auto lg:block">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400">
                            <tr>
                                <th class="whitespace-nowrap px-5 py-3">Orden</th>
                                <th class="px-5 py-3">Proveedor</th>
                                <th class="whitespace-nowrap px-5 py-3">Creada</th>
                                <th class="whitespace-nowrap px-5 py-3">Estado</th>
                                <th class="whitespace-nowrap px-5 py-3 text-right">Neto</th>
                                <th class="whitespace-nowrap px-5 py-3">Facturación</th>
                            </tr>
                        </thead>
                        @foreach($items as $item)
                            @php $grouped = $item['orders']->count() > 1; @endphp
                            <tbody class="border-b border-slate-100 last:border-0 dark:border-slate-800">
                                @if($grouped)
                                    @php
                                        $states = $item['orders']->countBy(fn ($o) => in_array($o->state, \App\Models\OdooPurchaseOrder::CONFIRMED_STATES, true) ? 'c' : ($o->state === 'cancel' ? 'x' : 'q'));
                                        $product = $item['orders']->flatMap->lines->reject->isNote()->first()?->name;
                                    @endphp
                                    <tr class="bg-slate-50/80 dark:bg-slate-950/40">
                                        <td colspan="6" class="px-5 py-2.5">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <p class="min-w-0 text-xs text-slate-600 dark:text-slate-300">
                                                    <span class="font-bold text-slate-800 dark:text-slate-100">{{ \Illuminate\Support\Str::limit($product ?: 'Misma compra', 70) }}</span>
                                                    · cotizada a {{ $item['orders']->pluck('partner_odoo_id')->unique()->count() }} proveedores
                                                    @if($states['c'] ?? 0)<span class="ml-1 font-semibold text-emerald-700 dark:text-emerald-400">· {{ $states['c'] }} {{ $states['c'] === 1 ? 'confirmada' : 'confirmadas' }}</span>@endif
                                                    @if($states['q'] ?? 0)<span class="ml-1 font-semibold text-amber-700 dark:text-amber-400">· {{ $states['q'] }} sin confirmar</span>@endif
                                                    @if($states['x'] ?? 0)<span class="ml-1 text-slate-400">· {{ $states['x'] }} {{ $states['x'] === 1 ? 'cancelada' : 'canceladas' }}</span>@endif
                                                </p>
                                                <a href="{{ route('purchase_requests.odoo.show', $item['orders']->first()->odoo_id) }}#comparacion"
                                                   class="shrink-0 text-xs font-bold text-blue-600 hover:underline dark:text-blue-400">Comparar precios →</a>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                                @foreach($item['orders'] as $order)
                                    @php [$billLabel, $billClass] = $billing($order); @endphp
                                    <tr class="cursor-pointer transition-colors hover:bg-blue-50/40 dark:hover:bg-slate-800/40 {{ $order->state === 'cancel' ? 'text-slate-400 dark:text-slate-500' : '' }}"
                                        data-row-href="{{ route('purchase_requests.odoo.show', $order->odoo_id) }}">
                                        <td class="whitespace-nowrap py-3 pr-5 {{ $grouped ? 'pl-9' : 'pl-5' }}">
                                            <a href="{{ route('purchase_requests.odoo.show', $order->odoo_id) }}" class="font-mono text-xs font-bold text-blue-600 hover:underline dark:text-blue-400">{{ $order->name }}</a>
                                            @if($order->isRecent() && $order->state !== 'cancel')
                                                <span class="ml-1 rounded-full bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-700 dark:bg-rose-950/60 dark:text-rose-300">Nueva</span>
                                            @endif
                                        </td>
                                        <td class="max-w-xs px-5 py-3">
                                            <p class="truncate font-semibold {{ $order->state === 'cancel' ? '' : 'text-slate-900 dark:text-white' }}" title="{{ $order->partner_name }}">{{ $order->partner_name ?: 'Sin proveedor' }}</p>
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3 text-xs text-slate-500 dark:text-slate-400">
                                            {{ $order->date_order?->format('d-m-Y') ?? '—' }}
                                            <span class="block text-slate-400">{{ \Illuminate\Support\Str::limit($order->created_by_name ?: '—', 22) }}</span>
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3">
                                            <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $stateClasses($order->state) }}">{{ $order->stateLabel() }}</span>
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3 text-right font-semibold tabular-nums {{ $order->state === 'cancel' ? '' : 'text-slate-900 dark:text-white' }}">
                                            {{ $money($order->amount_untaxed, $order->currency) }}
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3 text-xs font-semibold {{ $billClass }}">{{ $billLabel }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        @endforeach
                    </table>
                </div>

                {{-- Teléfono: tarjetas simples, cada una lleva a su orden. --}}
                <div class="divide-y divide-slate-100 lg:hidden dark:divide-slate-800">
                    @foreach($items as $item)
                        @if($item['orders']->count() > 1)
                            <div class="flex items-center justify-between gap-2 bg-slate-50/80 px-4 py-2 dark:bg-slate-950/40">
                                <p class="min-w-0 truncate text-xs font-bold text-slate-700 dark:text-slate-200">
                                    {{ \Illuminate\Support\Str::limit($item['orders']->flatMap->lines->reject->isNote()->first()?->name ?: 'Misma compra', 40) }}
                                    · {{ $item['orders']->pluck('partner_odoo_id')->unique()->count() }} proveedores
                                </p>
                                <a href="{{ route('purchase_requests.odoo.show', $item['orders']->first()->odoo_id) }}#comparacion" class="shrink-0 text-xs font-bold text-blue-600 dark:text-blue-400">Comparar →</a>
                            </div>
                        @endif
                        @foreach($item['orders'] as $order)
                            @php [$billLabel, $billClass] = $billing($order); @endphp
                            <a href="{{ route('purchase_requests.odoo.show', $order->odoo_id) }}"
                               class="flex items-start justify-between gap-3 px-4 py-3 {{ $item['orders']->count() > 1 ? 'pl-7' : '' }} {{ $order->state === 'cancel' ? 'opacity-60' : '' }}">
                                <div class="min-w-0">
                                    <p class="flex items-center gap-1.5">
                                        <span class="font-mono text-xs font-bold text-blue-600 dark:text-blue-400">{{ $order->name }}</span>
                                        <span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $stateClasses($order->state) }}">{{ $order->stateLabel() }}</span>
                                    </p>
                                    <p class="mt-0.5 truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $order->partner_name ?: 'Sin proveedor' }}</p>
                                    <p class="text-xs text-slate-500">{{ $order->date_order?->format('d-m-Y') }} · {{ \Illuminate\Support\Str::limit($order->created_by_name ?: '—', 20) }}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-sm font-bold tabular-nums text-slate-900 dark:text-white">{{ $money($order->amount_untaxed, $order->currency) }}</p>
                                    <p class="text-[11px] font-semibold {{ $billClass }}">{{ $billLabel }}</p>
                                </div>
                            </a>
                        @endforeach
                    @endforeach
                </div>
            @endif

            @if($pages->hasPages())
                <div class="border-t border-slate-100 px-4 py-4 dark:border-slate-800 sm:px-5">{{ $pages->links() }}</div>
            @endif
        </section>
    </div>

    <script>
        document.addEventListener('click', (event) => {
            const row = event.target.closest('tr[data-row-href]');
            if (row && !event.target.closest('a, button')) window.location = row.dataset.rowHref;
        });
    </script>
</x-app-layout>
