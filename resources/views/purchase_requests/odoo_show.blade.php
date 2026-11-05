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
        $uom = fn (?string $u): string => match (mb_strtolower(trim((string) $u))) {
            'units', 'unit', 'unidades', 'unidad', 'unit(s)' => 'un',
            default => (string) $u,
        };
        $short = fn (?string $name): string => \Illuminate\Support\Str::before((string) $name, ',');
        $confirmed = fn ($o): bool => in_array($o->state, \App\Models\OdooPurchaseOrder::CONFIRMED_STATES, true);
        $stateClasses = fn (string $state): string => match (true) {
            in_array($state, \App\Models\OdooPurchaseOrder::QUOTATION_STATES, true) => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300',
            in_array($state, \App\Models\OdooPurchaseOrder::CONFIRMED_STATES, true) => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300',
            default => 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
        };
        $products = collect($products);
        $orderInvoices = $invoices[$order->odoo_id] ?? [];
        $clp = ! $order->currency || $order->currency === 'CLP';
        $invoicedNet = array_sum(array_column($orderInvoices, 'net'));
        $percent = $clp && $order->amount_untaxed > 0 ? round($invoicedNet * 100 / $order->amount_untaxed) : null;

        // Quién cotizó qué: los que no pusieron ni un precio no ocupan columna.
        $quoted = fn ($m): int => $products->filter(fn ($p) => ($p['cells'][$m->odoo_id]['price'] ?? 0) > 0)->count();
        $conPrecio = $group->filter(fn ($m) => $m->state !== 'cancel' && $quoted($m) > 0)
            ->sortBy(fn ($m) => [$confirmed($m) ? 0 : 1, $m->amount_untaxed])->values();
        $sinPrecio = $group->filter(fn ($m) => $m->state !== 'cancel' && $quoted($m) === 0)->values();
        $canceladas = $group->filter(fn ($m) => $m->state === 'cancel' && $quoted($m) > 0)->values();
        $columnas = $conPrecio->concat($canceladas);
        $total = $products->count();
        $completas = $conPrecio->filter(fn ($m) => $quoted($m) === $total);
        $masBarataCompleta = $completas->sortBy('amount_untaxed')->first();

        // Comprando cada producto al más barato.
        $combinada = $products->sum(fn ($p) => $p['best'] !== null ? $p['cells'][$p['best']]['subtotal'] : 0);
        $cubiertos = $products->filter(fn ($p) => $p['best'] !== null)->count();
        $comparar = $group->count() > 1 && $conPrecio->isNotEmpty();
        $ganadores = $products->countBy('best');
        $canceladasSinPrecio = $group->filter(fn ($m) => $m->state === 'cancel' && $quoted($m) === 0)->values();

        // Las facturas de esta orden y, si es parte de un grupo, las de las otras.
        $facturas = $group->flatMap(fn ($m) => collect($invoices[$m->odoo_id] ?? [])->map(fn ($i) => $i + ['orden' => $m]))
            ->sortBy(fn ($i) => $i['orden']->odoo_id === $order->odoo_id ? 0 : 1)->values();
    @endphp

    <div class="mx-auto max-w-8xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        @include('purchase_requests._module_nav')

        {{-- Cabecera: de qué se trata y en qué está --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-mono text-lg font-black text-slate-900 dark:text-white">{{ $order->name }}</span>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ $stateClasses($order->state) }}">{{ $order->stateLabel() }}</span>
                        @if($order->isRecent() && $order->state !== 'cancel')
                            <span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-bold text-rose-700 dark:bg-rose-950/60 dark:text-rose-300">Nueva</span>
                        @endif
                        @if(($folio = $order->requestFolio()) && isset($requestLinks[$folio]))
                            <a href="{{ $requestLinks[$folio] }}" class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-bold text-blue-700 ring-1 ring-blue-200 hover:bg-blue-100 dark:bg-blue-950/40 dark:text-blue-300 dark:ring-blue-900">{{ $folio }}</a>
                        @elseif($adopted)
                            <a href="{{ route('purchase_requests.show', $adopted) }}" title="Laravel la trajo: sube ahí las cotizaciones de los proveedores"
                               class="rounded-full bg-violet-50 px-2.5 py-0.5 text-xs font-bold text-violet-700 ring-1 ring-violet-200 hover:bg-violet-100 dark:bg-violet-950/40 dark:text-violet-300 dark:ring-violet-900">{{ $adopted->folio }} · creada en Odoo</a>
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ $order->date_order?->format('d-m-Y') ?? '—' }} · creada por {{ $order->created_by_name ?: '—' }}
                        @if(! ($folio && isset($requestLinks[$folio])) && $order->origin) · origen {{ $order->origin }} @endif
                        @if($order->partner_ref) · ref. proveedor {{ $order->partner_ref }} @endif
                    </p>
                    @if($order->notes)
                        <p class="mt-2 whitespace-pre-line text-sm font-medium text-slate-700 dark:text-slate-200">{{ $order->notes }}</p>
                    @endif
                </div>
                @if($url = $order->odooUrl())
                    <a href="{{ $url }}" target="_blank" rel="noopener"
                       class="inline-flex min-h-9 items-center gap-1 rounded-lg border border-slate-200 px-3 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                        Abrir en Odoo
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                @endif
            </div>
            @if($boughtWith->isNotEmpty())
                <p class="mt-3 rounded-xl bg-orange-50 px-3 py-2 text-sm text-orange-900 ring-1 ring-orange-200 dark:bg-orange-950/40 dark:text-orange-200 dark:ring-orange-900">
                    <span class="font-semibold">Esta cotización quedó abierta.</span>
                    La compra ya se hizo con {{ $boughtWith->map(fn ($o) => $o->name.' ('.$short($o->partner_name).')')->join(', ', ' y ') }}.
                    Conviene cancelarla en Odoo para que no quede pendiente.
                </p>
            @endif
        </section>

        {{-- Facturas: siempre a la vista, las de esta orden primero y después las de su grupo --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                <h2 class="text-sm font-extrabold text-slate-900 dark:text-white">Facturas</h2>
                @if($percent !== null && $orderInvoices)
                    <div class="w-full max-w-md">
                        <div class="flex justify-between text-xs">
                            <span class="text-slate-600 dark:text-slate-300">Facturado {{ $money($invoicedNet, 'CLP') }} de {{ $money($order->amount_untaxed, 'CLP') }} + IVA</span>
                            <span class="font-bold {{ $percent > 101 ? 'text-rose-600' : 'text-slate-500' }}">{{ $percent }} %</span>
                        </div>
                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                            <div class="h-full rounded-full {{ $percent > 101 ? 'bg-rose-500' : 'bg-emerald-500' }}" style="width: {{ min(100, $percent) }}%"></div>
                        </div>
                    </div>
                @elseif(! $clp && $orderInvoices)
                    <p class="text-xs text-slate-500">Orden en {{ $order->currency }}: las facturas en pesos no se comparan con ella.</p>
                @endif
            </div>
            @if($facturas->isEmpty())
                <p class="mt-2 text-sm text-slate-500">{{ $confirmed($order) ? 'Todavía no hay una factura vinculada.' : ($group->contains(fn ($m) => $confirmed($m)) ? 'Todavía no hay facturas de esta compra.' : 'Es una cotización: aún no se factura.') }}</p>
            @else
                <ul class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach($facturas as $invoice)
                        <li class="rounded-xl border border-slate-100 px-3 py-2 dark:border-slate-800">
                            <div class="flex items-center justify-between gap-2">
                                <a href="{{ route('gmail.dtes.show', $invoice['id']) }}#referencias-cruzadas" class="text-sm font-bold text-blue-600 hover:underline dark:text-blue-400">Factura N° {{ $invoice['folio'] }}</a>
                                <span class="text-sm font-semibold tabular-nums">{{ $money($invoice['amount'], 'CLP') }}</span>
                            </div>
                            <p class="text-xs text-slate-500">
                                {{ $invoice['date'] ? \Illuminate\Support\Carbon::parse($invoice['date'])->format('d-m-Y') : '' }}
                                @if($invoice['orden']->odoo_id !== $order->odoo_id)
                                    · de <a href="{{ route('purchase_requests.odoo.show', $invoice['orden']->odoo_id) }}" class="font-semibold hover:underline">{{ $invoice['orden']->name }} {{ $short($invoice['orden']->partner_name) }}</a>
                                @endif
                            </p>
                            @foreach($invoice['credit_notes'] as $note)
                                <p class="text-xs font-semibold {{ $note['annuls'] ? 'text-rose-600 dark:text-rose-400' : 'text-amber-700 dark:text-amber-400' }}">
                                    {{ $note['annuls'] ? 'Anulada por la nota de crédito N° '.$note['folio'] : 'Nota de crédito N° '.$note['folio'].': −'.$money($note['net'], 'CLP') }}
                                </p>
                            @endforeach
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if($comparar)
            {{-- Una tarjeta por proveedor, del más barato al más caro --}}
            <section>
                <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-sm font-extrabold text-slate-900 dark:text-white">Cotizada a {{ $group->count() }} proveedores · {{ $total }} productos</h2>
                    @if($sinPrecio->isNotEmpty() || $canceladasSinPrecio->isNotEmpty())
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            @if($sinPrecio->isNotEmpty())
                                Sin precios aún:
                                @foreach($sinPrecio as $m)
                                    <a href="{{ route('purchase_requests.odoo.show', $m->odoo_id) }}" class="font-semibold text-slate-700 hover:underline dark:text-slate-200">{{ $short($m->partner_name) }} <span class="font-mono text-slate-400">{{ $m->name }}</span></a>@if(! $loop->last), @endif
                                @endforeach
                            @endif
                            @if($canceladasSinPrecio->isNotEmpty())
                                @if($sinPrecio->isNotEmpty()) · @endif Canceladas:
                                @foreach($canceladasSinPrecio as $m)
                                    <a href="{{ route('purchase_requests.odoo.show', $m->odoo_id) }}" class="font-semibold text-slate-500 line-through hover:underline">{{ $short($m->partner_name) }}</a> <span class="font-mono text-slate-400">{{ $m->name }}</span>@if(! $loop->last), @endif
                                @endforeach
                            @endif
                        </p>
                    @endif
                </div>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach($conPrecio as $m)
                        @php
                            $n = $quoted($m);
                            $gana = $ganadores[$m->odoo_id] ?? 0;
                            $esLaMejor = $masBarataCompleta?->odoo_id === $m->odoo_id;
                            $diff = $masBarataCompleta && ! $esLaMejor && $n === $total ? $m->amount_total - $masBarataCompleta->amount_total : null;
                        @endphp
                        <a href="{{ route('purchase_requests.odoo.show', $m->odoo_id) }}"
                           class="block rounded-2xl border bg-white p-4 shadow-sm transition hover:shadow-md dark:bg-slate-900
                                  {{ $esLaMejor ? 'border-emerald-300 ring-2 ring-emerald-200 dark:border-emerald-800 dark:ring-emerald-900/60' : 'border-slate-200 dark:border-slate-800' }}
                                  {{ $m->odoo_id === $order->odoo_id ? 'outline outline-2 outline-offset-2 outline-blue-400' : '' }}">
                            <div class="flex items-start justify-between gap-2">
                                <p class="min-w-0 truncate text-sm font-extrabold text-slate-900 dark:text-white" title="{{ $m->partner_name }}">{{ $short($m->partner_name) }}</p>
                                @if($confirmed($m))
                                    <span class="shrink-0 rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-black uppercase text-white">Comprada</span>
                                @elseif($esLaMejor)
                                    <span class="shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-black uppercase text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">Más barata</span>
                                @endif
                            </div>
                            <p class="font-mono text-[11px] text-slate-400">{{ $m->name }} · {{ $m->stateLabel() }}</p>
                            <p class="mt-3 text-[11px] font-bold uppercase tracking-wider text-slate-400">Total con IVA</p>
                            <p class="text-2xl font-black tabular-nums text-slate-900 dark:text-white">{{ $money($m->amount_total, $m->currency) }}</p>
                            <p class="text-sm tabular-nums text-slate-500 dark:text-slate-400">{{ $money($m->amount_untaxed, $m->currency) }} + IVA</p>
                            <div class="mt-3 flex flex-wrap gap-1.5 text-[11px] font-bold">
                                <span class="rounded-md px-1.5 py-0.5 {{ $n === $total ? 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300' }}">
                                    {{ $n === $total ? ($confirmed($m) ? 'Se compró todo' : 'Cotizó todo') : ($confirmed($m) ? 'Se le compró ' : 'Cotizó ').$n.' de '.$total }}
                                </span>
                                @if($gana > 0)
                                    <span class="rounded-md bg-emerald-50 px-1.5 py-0.5 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">Más barato en {{ $gana }}</span>
                                @endif
                                @if($diff !== null && $diff > 0)
                                    <span class="rounded-md bg-rose-50 px-1.5 py-0.5 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300">{{ $money($diff, $m->currency) }} más cara</span>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
                @if($masBarataCompleta && $cubiertos === $total && $combinada < $masBarataCompleta->amount_untaxed - 1)
                    <p class="mt-3 rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-900 ring-1 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-200 dark:ring-emerald-900">
                        Comprando cada producto donde está más barato serían <span class="font-black tabular-nums">{{ $money($combinada, 'CLP') }} + IVA</span>:
                        {{ $money($masBarataCompleta->amount_untaxed - $combinada, 'CLP') }} + IVA menos que todo con {{ $short($masBarataCompleta->partner_name) }}.
                    </p>
                @endif
            </section>

            {{-- La comparación, producto por producto --}}
            <section id="comparacion" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-100 px-5 py-3 dark:border-slate-800">
                    <h2 class="text-sm font-extrabold text-slate-900 dark:text-white">Producto por producto</h2>
                    <p class="text-xs text-slate-500">Precio unitario sin IVA. En verde el más barato; debajo, cuánto más caro es cada uno.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left align-bottom">
                                <th class="sticky left-0 z-10 min-w-[16rem] bg-white px-5 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:bg-slate-900">Producto</th>
                                <th class="px-3 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-slate-400">Cant.</th>
                                @foreach($columnas as $m)
                                    <th class="min-w-[8.5rem] px-3 py-3 text-right {{ $m->state === 'cancel' ? 'opacity-50' : '' }}">
                                        <a href="{{ route('purchase_requests.odoo.show', $m->odoo_id) }}#comparacion" class="block truncate text-xs font-bold text-slate-800 hover:underline dark:text-slate-100" title="{{ $m->partner_name }}">{{ \Illuminate\Support\Str::limit($short($m->partner_name), 20) }}</a>
                                        <span class="font-mono text-[11px] text-slate-400">{{ $m->name }}</span>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($products as $product)
                                @php
                                    $cells = collect($product['cells']);
                                    $q = $cells->max('qty');
                                    $u = $uom($cells->first()['uom'] ?? '');
                                    $min = $product['best'] !== null ? $product['cells'][$product['best']]['price'] : null;
                                @endphp
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                    <td class="sticky left-0 z-10 bg-white px-5 py-2.5 text-slate-700 dark:bg-slate-900 dark:text-slate-200">{{ $product['name'] }}</td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-slate-500">{{ $qty($q) }} <span class="text-xs">{{ $u }}</span></td>
                                    @foreach($columnas as $m)
                                        @php $cell = $product['cells'][$m->odoo_id] ?? null; @endphp
                                        <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums {{ $m->state === 'cancel' ? 'opacity-50' : '' }}">
                                            @if(! $cell || $cell['price'] <= 0)
                                                <span class="text-xs text-slate-300 dark:text-slate-600">no cotizó</span>
                                            @elseif($product['best'] === $m->odoo_id)
                                                <span class="rounded-md bg-emerald-100 px-1.5 py-0.5 font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">{{ $money($cell['price'], $m->currency) }}</span>
                                            @else
                                                <span class="text-slate-700 dark:text-slate-200">{{ $money($cell['price'], $m->currency) }}</span>
                                                @if($min)
                                                    <span class="block text-[11px] font-semibold text-rose-500">+{{ round(($cell['price'] / $min - 1) * 100) }} %</span>
                                                @endif
                                            @endif
                                            @if($cell && $cell['price'] > 0 && abs($cell['qty'] - $q) > 0.0001)
                                                <span class="block text-[11px] font-bold text-amber-600">ojo: {{ $qty($cell['qty']) }} {{ $u }}</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-slate-200 dark:border-slate-700">
                            <tr>
                                <td class="sticky left-0 z-10 bg-white px-5 py-3 text-xs font-bold uppercase tracking-wider text-slate-500 dark:bg-slate-900" colspan="2">Total sin IVA</td>
                                @foreach($columnas as $m)
                                    <td class="whitespace-nowrap px-3 py-3 text-right text-base font-black tabular-nums {{ $masBarataCompleta?->odoo_id === $m->odoo_id ? 'text-emerald-700 dark:text-emerald-300' : 'text-slate-900 dark:text-white' }} {{ $m->state === 'cancel' ? 'opacity-50' : '' }}">
                                        {{ $money($m->amount_untaxed, $m->currency) }}
                                    </td>
                                @endforeach
                            </tr>
                            <tr>
                                <td class="sticky left-0 z-10 bg-white px-5 pb-3 text-xs font-bold uppercase tracking-wider text-slate-500 dark:bg-slate-900" colspan="2">Total con IVA</td>
                                @foreach($columnas as $m)
                                    <td class="whitespace-nowrap px-3 pb-3 text-right font-bold tabular-nums text-slate-600 dark:text-slate-300 {{ $m->state === 'cancel' ? 'opacity-50' : '' }}">{{ $money($m->amount_total, $m->currency) }}</td>
                                @endforeach
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        @endif

        @if(! $comparar || $confirmed($order))
            {{-- Las líneas de esta orden: sola, o comprada (con lo recibido) --}}
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-baseline justify-between border-b border-slate-100 px-5 py-3 dark:border-slate-800">
                    <h2 class="text-sm font-extrabold text-slate-900 dark:text-white">{{ $comparar ? 'Lo que se compró en '.$order->name : 'Productos' }}</h2>
                    <p class="text-right text-sm tabular-nums text-slate-500 dark:text-slate-400">{{ $money($order->amount_untaxed, $order->currency) }} + IVA · <span class="font-black text-slate-900 dark:text-white">{{ $money($order->amount_total, $order->currency) }} con IVA</span></p>
                </div>
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
                                    @if($confirmed($order))
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
                                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">{{ $qty($line->product_qty) }} <span class="text-xs text-slate-400">{{ $uom($line->uom_name) }}</span></td>
                                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">{{ $line->price_unit > 0 ? $money($line->price_unit, $order->currency) : '—' }}</td>
                                            <td class="whitespace-nowrap px-3 py-2 text-right font-semibold tabular-nums">{{ $line->price_subtotal > 0 ? $money($line->price_subtotal, $order->currency) : '—' }}</td>
                                            @if($confirmed($order))
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
        @endif
    </div>
</x-app-layout>
