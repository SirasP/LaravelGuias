<x-app-layout>
    <x-slot name="header">
        <div class="flex min-w-0 items-center gap-2">
            <a href="{{ url()->previous() !== url()->current() && str_contains(url()->previous(), '/solicitudes-compra/odoo') ? url()->previous() : route('purchase_requests.odoo') }}"
               class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300" aria-label="Volver a las órdenes de Odoo">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div class="min-w-0">
                <h1 class="truncate text-sm font-extrabold text-slate-900 dark:text-white">{{ $order->name }} · {{ $order->partner_name }}</h1>
                <p class="truncate text-xs text-slate-500 dark:text-slate-400">Orden hecha directo en Odoo</p>
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
        $orderInvoices = $invoices[$order->odoo_id] ?? [];
        $clp = ! $order->currency || $order->currency === 'CLP';
        $invoicedNet = array_sum(array_column($orderInvoices, 'net'));
        $percent = $clp && $order->amount_untaxed > 0 ? round($invoicedNet * 100 / $order->amount_untaxed) : null;
        $products = collect($products);
    @endphp

    <div class="mx-auto max-w-full space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        @include('purchase_requests._module_nav')

        {{-- Cabecera --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-mono text-lg font-black text-slate-900 dark:text-white">{{ $order->name }}</span>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ $stateClasses($order->state) }}">{{ $order->stateLabel() }}</span>
                        @if($order->isRecent() && $order->state !== 'cancel')
                            <span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-bold text-rose-700 dark:bg-rose-950/60 dark:text-rose-300">Nueva</span>
                        @endif
                    </div>
                    <p class="mt-1 text-base font-bold text-slate-800 dark:text-slate-100">{{ $order->partner_name ?: 'Sin proveedor' }}</p>
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                        {{ $order->date_order?->format('d-m-Y') ?? '—' }} · creada por {{ $order->created_by_name ?: '—' }}
                        @if($order->origin) · origen {{ $order->origin }} @endif
                        @if($order->partner_ref) · ref. proveedor {{ $order->partner_ref }} @endif
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-2xl font-black tabular-nums text-slate-900 dark:text-white">{{ $money($order->amount_untaxed, $order->currency) }}</p>
                    <p class="text-xs text-slate-500">neto · total {{ $money($order->amount_total, $order->currency) }}</p>
                    @if($url = $order->odooUrl())
                        <a href="{{ $url }}" target="_blank" rel="noopener"
                           class="mt-2 inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                            Abrir en Odoo
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    @endif
                </div>
            </div>
            @if($order->notes)
                <p class="mt-3 whitespace-pre-line rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600 dark:bg-slate-800/50 dark:text-slate-300">{{ $order->notes }}</p>
            @endif
        </section>

        <div class="grid gap-5 xl:grid-cols-3">
            {{-- Líneas --}}
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 xl:col-span-2">
                <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-extrabold text-slate-900 dark:border-slate-800 dark:text-white">Productos</h2>
                @if($order->lines->isEmpty())
                    <p class="px-5 py-6 text-sm text-slate-500">Sin líneas en Odoo.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-400">
                                <tr>
                                    <th class="px-5 py-2">Producto</th>
                                    <th class="whitespace-nowrap px-3 py-2 text-right">Cantidad</th>
                                    <th class="whitespace-nowrap px-3 py-2 text-right">Precio</th>
                                    <th class="whitespace-nowrap px-3 py-2 text-right">Subtotal</th>
                                    @if(in_array($order->state, \App\Models\OdooPurchaseOrder::CONFIRMED_STATES, true))
                                        <th class="whitespace-nowrap px-5 py-2 text-right">Recibido</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach($order->lines as $line)
                                    @if($line->isNote())
                                        <tr><td colspan="5" class="bg-slate-50 px-5 py-1.5 text-xs font-bold text-slate-500 dark:bg-slate-950/40">{{ $line->name }}</td></tr>
                                    @else
                                        <tr>
                                            <td class="px-5 py-2 text-slate-700 dark:text-slate-200">{{ $line->name }}</td>
                                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">{{ $qty($line->product_qty) }} <span class="text-xs text-slate-400">{{ $line->uom_name }}</span></td>
                                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">{{ $money($line->price_unit, $order->currency) }}</td>
                                            <td class="whitespace-nowrap px-3 py-2 text-right font-semibold tabular-nums">{{ $money($line->price_subtotal, $order->currency) }}</td>
                                            @if(in_array($order->state, \App\Models\OdooPurchaseOrder::CONFIRMED_STATES, true))
                                                <td class="whitespace-nowrap px-5 py-2 text-right tabular-nums text-slate-500">{{ $qty($line->qty_received ?? 0) }}</td>
                                            @endif
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Facturas --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-sm font-extrabold text-slate-900 dark:text-white">Facturas</h2>
                @if($percent !== null && $orderInvoices)
                    <div class="mt-3">
                        <div class="flex justify-between text-xs">
                            <span class="text-slate-600 dark:text-slate-300">Facturado {{ $money($invoicedNet, 'CLP') }} de {{ $money($order->amount_untaxed, 'CLP') }}</span>
                            <span class="font-bold {{ $percent > 101 ? 'text-rose-600' : 'text-slate-500' }}">{{ $percent }} %</span>
                        </div>
                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                            <div class="h-full rounded-full {{ $percent > 101 ? 'bg-rose-500' : 'bg-emerald-500' }}" style="width: {{ min(100, $percent) }}%"></div>
                        </div>
                    </div>
                @elseif(! $clp && $orderInvoices)
                    <p class="mt-2 text-xs text-slate-500">Orden en {{ $order->currency }}: las facturas en pesos no se comparan con ella.</p>
                @endif
                <ul class="mt-3 space-y-2">
                    @forelse($orderInvoices as $invoice)
                        <li class="rounded-xl border border-slate-100 px-3 py-2 dark:border-slate-800">
                            <div class="flex items-center justify-between gap-2">
                                <a href="{{ route('gmail.dtes.show', $invoice['id']) }}#referencias-cruzadas" class="text-sm font-bold text-blue-600 hover:underline dark:text-blue-400">Factura N° {{ $invoice['folio'] }}</a>
                                <span class="text-sm font-semibold tabular-nums">{{ $money($invoice['amount'], 'CLP') }}</span>
                            </div>
                            <p class="text-xs text-slate-500">{{ $invoice['date'] ? \Illuminate\Support\Carbon::parse($invoice['date'])->format('d-m-Y') : '' }}</p>
                            @foreach($invoice['credit_notes'] as $note)
                                <p class="text-xs font-semibold {{ $note['annuls'] ? 'text-rose-600 dark:text-rose-400' : 'text-amber-700 dark:text-amber-400' }}">
                                    {{ $note['annuls'] ? 'Anulada por la nota de crédito N° '.$note['folio'] : 'Nota de crédito N° '.$note['folio'].': −'.$money($note['net'], 'CLP') }}
                                </p>
                            @endforeach
                        </li>
                    @empty
                        <li class="text-sm text-slate-500">{{ in_array($order->state, \App\Models\OdooPurchaseOrder::CONFIRMED_STATES, true) ? 'Todavía no hay una factura vinculada.' : 'Es una cotización: aún no se factura.' }}</li>
                    @endforelse
                </ul>
            </section>
        </div>

        {{-- Comparación del grupo: la misma compra cotizada a varios proveedores --}}
        @if($group->count() > 1)
            <section id="comparacion" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-100 px-5 py-3 dark:border-slate-800">
                    <h2 class="text-sm font-extrabold text-slate-900 dark:text-white">Cotizada a {{ $group->pluck('partner_odoo_id')->unique()->count() }} proveedores</h2>
                    <p class="text-xs text-slate-500">Precio unitario neto de cada uno. En verde, el más barato entre las que no se cancelaron.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left align-bottom">
                                <th class="sticky left-0 min-w-[14rem] bg-white px-5 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:bg-slate-900">Producto</th>
                                @foreach($group as $member)
                                    <th class="min-w-[9rem] px-3 py-3 text-right {{ $member->odoo_id === $order->odoo_id ? 'bg-blue-50/70 dark:bg-blue-950/30' : '' }}">
                                        <a href="{{ route('purchase_requests.odoo.show', $member->odoo_id) }}#comparacion" class="block text-xs font-bold text-slate-800 hover:underline dark:text-slate-100" title="{{ $member->partner_name }}">
                                            {{ \Illuminate\Support\Str::limit(\Illuminate\Support\Str::before((string) $member->partner_name, ','), 22) }}
                                        </a>
                                        <span class="font-mono text-[11px] text-slate-400">{{ $member->name }}</span>
                                        <span class="mt-0.5 block"><span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $stateClasses($member->state) }}">{{ $member->stateLabel() }}</span></span>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($products as $product)
                                <tr>
                                    <td class="sticky left-0 bg-white px-5 py-2 text-slate-700 dark:bg-slate-900 dark:text-slate-200">{{ $product['name'] }}</td>
                                    @foreach($group as $member)
                                        @php $cell = $product['cells'][$member->odoo_id] ?? null; @endphp
                                        <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums
                                            {{ $member->odoo_id === $order->odoo_id ? 'bg-blue-50/70 dark:bg-blue-950/30' : '' }}
                                            {{ $member->state === 'cancel' ? 'text-slate-400' : '' }}">
                                            @if(! $cell || $cell['price'] <= 0)
                                                <span class="text-slate-300 dark:text-slate-600">—</span>
                                            @else
                                                <span class="{{ $product['best'] === $member->odoo_id ? 'rounded-md bg-emerald-100 px-1.5 py-0.5 font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : '' }}">{{ $money($cell['price'], $member->currency) }}</span>
                                                <span class="block text-[11px] text-slate-400">{{ $qty($cell['qty']) }} {{ $cell['uom'] }}</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-slate-200 dark:border-slate-700">
                            <tr>
                                <td class="sticky left-0 bg-white px-5 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-500 dark:bg-slate-900">Total neto</td>
                                @foreach($group as $member)
                                    <td class="whitespace-nowrap px-3 py-2.5 text-right font-bold tabular-nums {{ $member->odoo_id === $order->odoo_id ? 'bg-blue-50/70 dark:bg-blue-950/30' : '' }} {{ $member->state === 'cancel' ? 'text-slate-400' : 'text-slate-900 dark:text-white' }}">
                                        {{ $member->amount_untaxed > 0 ? $money($member->amount_untaxed, $member->currency) : '—' }}
                                    </td>
                                @endforeach
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
