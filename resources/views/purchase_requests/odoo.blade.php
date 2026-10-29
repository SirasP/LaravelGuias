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
        $qty = fn (float $value): string => rtrim(rtrim(number_format($value, 4, ',', '.'), '0'), ',');
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

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($orders as $order)
                    <li>
                        <details class="group">
                            <summary class="flex cursor-pointer list-none items-start gap-3 px-4 py-3.5 hover:bg-slate-50/70 dark:hover:bg-slate-800/40 sm:px-5">
                                <svg class="mt-1 h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-mono text-xs font-bold text-blue-600 dark:text-blue-400">{{ $order->name }}</span>
                                        <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $stateClasses($order->state) }}">{{ $order->stateLabel() }}</span>
                                        @if($order->isRecent())
                                            <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-bold text-rose-700 dark:bg-rose-950/60 dark:text-rose-300">Nueva</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 truncate font-semibold text-slate-900 dark:text-white" title="{{ $order->partner_name }}">{{ $order->partner_name ?: 'Sin proveedor' }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                        {{ $order->date_order?->format('d-m-Y') ?? '—' }}
                                        · creada por {{ $order->created_by_name ?: '—' }}
                                        · {{ $order->lines->reject->isNote()->count() }} {{ $order->lines->reject->isNote()->count() === 1 ? 'línea' : 'líneas' }}
                                        @if($order->origin) · origen {{ $order->origin }} @endif
                                    </p>
                                </div>
                                @php
                                    $orderInvoices = $invoices[$order->odoo_id] ?? [];
                                    $invoicedNet = array_sum(array_column($orderInvoices, 'net'));
                                @endphp
                                <div class="shrink-0 text-right">
                                    <p class="font-bold tabular-nums text-slate-900 dark:text-white">{{ $money($order->amount_total, $order->currency) }}</p>
                                    <p class="text-xs tabular-nums text-slate-400">neto {{ $money($order->amount_untaxed, $order->currency) }}</p>
                                    @if($orderInvoices && $order->currency && $order->currency !== 'CLP')
                                        {{-- Facturas en pesos contra una orden en dólares: no hay porcentaje honesto. --}}
                                        <p class="mt-1 inline-flex rounded-full bg-sky-100 px-2 py-0.5 text-[11px] font-bold text-sky-800 dark:bg-sky-950/60 dark:text-sky-300">
                                            Con {{ count($orderInvoices) }} {{ count($orderInvoices) === 1 ? 'factura' : 'facturas' }}
                                        </p>
                                    @elseif($orderInvoices)
                                        <p class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-bold {{ $invoicedNet >= $order->amount_untaxed * 0.99 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300' }}">
                                            {{ $invoicedNet >= $order->amount_untaxed * 0.99 ? 'Facturada' : 'Facturada en parte' }}
                                            @if($order->amount_untaxed > 0) · {{ round($invoicedNet * 100 / $order->amount_untaxed) }} % @endif
                                        </p>
                                    @elseif(in_array($order->state, \App\Models\OdooPurchaseOrder::CONFIRMED_STATES, true))
                                        <p class="mt-1 text-[11px] font-semibold text-slate-400">Sin factura vinculada</p>
                                    @endif
                                </div>
                            </summary>

                            <div class="space-y-3 border-t border-slate-100 bg-slate-50/50 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/30 sm:px-5 sm:pl-12">
                                @if($order->lines->isEmpty())
                                    <p class="text-sm text-slate-500">Sin líneas en Odoo.</p>
                                @else
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full text-sm">
                                            <thead class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-400">
                                                <tr>
                                                    <th class="py-1.5 pr-4">Producto</th>
                                                    <th class="py-1.5 pr-4 text-right">Cantidad</th>
                                                    <th class="py-1.5 pr-4 text-right">Precio</th>
                                                    <th class="py-1.5 pr-4 text-right">Subtotal</th>
                                                    @if($order->state === 'purchase' || $order->state === 'done')
                                                        <th class="py-1.5 text-right">Recibido</th>
                                                    @endif
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                                @foreach($order->lines as $line)
                                                    @if($line->isNote())
                                                        <tr><td colspan="5" class="py-1.5 text-xs font-bold text-slate-500">{{ $line->name }}</td></tr>
                                                    @else
                                                        <tr>
                                                            <td class="max-w-md py-1.5 pr-4 text-slate-700 dark:text-slate-200">{{ $line->name }}</td>
                                                            <td class="whitespace-nowrap py-1.5 pr-4 text-right tabular-nums">{{ $qty($line->product_qty) }} <span class="text-xs text-slate-400">{{ $line->uom_name }}</span></td>
                                                            <td class="whitespace-nowrap py-1.5 pr-4 text-right tabular-nums">{{ $money($line->price_unit, $order->currency) }}</td>
                                                            <td class="whitespace-nowrap py-1.5 pr-4 text-right tabular-nums font-semibold">{{ $money($line->price_subtotal, $order->currency) }}</td>
                                                            @if($order->state === 'purchase' || $order->state === 'done')
                                                                <td class="whitespace-nowrap py-1.5 text-right tabular-nums text-slate-500">{{ $qty($line->qty_received ?? 0) }}</td>
                                                            @endif
                                                        </tr>
                                                    @endif
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                                @if($orderInvoices)
                                    <div class="text-xs text-slate-600 dark:text-slate-300">
                                        <span class="font-bold uppercase tracking-wider text-slate-400">Facturas</span>
                                        @foreach($orderInvoices as $invoice)
                                            <a href="{{ route('gmail.dtes.show', $invoice['id']) }}#referencias-cruzadas" class="ml-2 font-semibold text-blue-600 hover:underline dark:text-blue-400">
                                                N° {{ $invoice['folio'] }}</a>
                                            <span class="text-slate-400">{{ $invoice['date'] ? \Illuminate\Support\Carbon::parse($invoice['date'])->format('d-m-Y') : '' }} · {{ $money($invoice['amount'], 'CLP') }}</span>
                                            @foreach($invoice['credit_notes'] as $note)
                                                <span class="font-semibold {{ $note['annuls'] ? 'text-rose-600 dark:text-rose-400' : 'text-amber-700 dark:text-amber-400' }}">
                                                    {{ $note['annuls'] ? '(anulada por la NC '.$note['folio'].')' : '(NC '.$note['folio'].': −'.$money($note['net'], 'CLP').')' }}
                                                </span>
                                            @endforeach
                                        @endforeach
                                    </div>
                                @endif
                                @if($order->notes)
                                    <p class="whitespace-pre-line text-xs text-slate-500 dark:text-slate-400">{{ $order->notes }}</p>
                                @endif
                                @if($url = $order->odooUrl())
                                    <a href="{{ $url }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:underline dark:text-blue-400">
                                        Abrir en Odoo
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    </a>
                                @endif
                            </div>
                        </details>
                    </li>
                @empty
                    <li class="px-5 py-14 text-center text-sm text-slate-500 dark:text-slate-400">
                        {{ $syncedAt ? 'No hay órdenes en esta pestaña.' : 'Aún no se han traído las órdenes de Odoo.' }}
                    </li>
                @endforelse
            </ul>

            @if($orders->hasPages())
                <div class="border-t border-slate-100 px-4 py-4 dark:border-slate-800 sm:px-5">{{ $orders->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
