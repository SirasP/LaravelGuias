{{--
    Las facturas que respondieron a esta solicitud, en su propia pestaña.
    Cada factura con su cabecera y sus partidas en tarjetas, como las demás
    tablas de la ficha: el borde dice si la línea calza (verde), si hay que
    mirarla (ámbar) o si las medidas no coinciden (rojo).
--}}
@if(count($invoiceReferences ?? []) > 0)
@php
    $cantidad = function (?string $valor): string {
        $texto = rtrim(rtrim((string) $valor, '0'), '.');

        return str_replace('.', ',', $texto === '' ? '0' : $texto);
    };
    $unidad = fn (?string $valor): string => \App\Services\DtePurchases\Quantity::unit($valor) === 'un' ? 'un.' : mb_strtolower((string) $valor);
    $pesos = fn (float $valor): string => '$'.number_format($valor, 0, ',', '.');
    $tolerancia = \App\Services\DtePurchases\PurchaseReferences::PRICE_TOLERANCE;
    $tarjeta = 'border-y border-slate-200 bg-white transition-colors group-hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900';
@endphp
<div class="space-y-6">
    @foreach($invoiceReferences as $invoice)
        @php
            $revisar = collect($invoice['references'])->filter(fn ($r) => $r['stale'] || $r['measures'])->count();
            // Sin precios en la solicitud no hay con qué comparar: la columna sólo decía eso en cada fila.
            $conPrecio = collect($invoice['references'])->contains(fn ($r) => $r['price'] !== null);
        @endphp
        <section class="overflow-hidden rounded-3xl border border-l-[5px] border-slate-200/90 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 {{ $revisar > 0 ? 'border-l-amber-500' : 'border-l-indigo-500' }}">
            <header class="flex flex-wrap items-start justify-between gap-4 p-5 sm:p-6">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-indigo-700 ring-1 ring-indigo-200 dark:bg-indigo-950/50 dark:text-indigo-300 dark:ring-indigo-900">Factura N° {{ $invoice['folio'] }}</span>
                        @if($invoice['date'])
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ \Illuminate\Support\Carbon::parse($invoice['date'])->format('d-m-Y') }}</span>
                        @endif
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 font-mono text-[10px] font-black text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ count($invoice['references']) }} {{ count($invoice['references']) === 1 ? 'partida' : 'partidas' }}</span>
                        @if($revisar > 0)
                            <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-black text-amber-700 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900">⚠ {{ $revisar }} por revisar</span>
                        @endif
                    </div>
                    <h2 class="mt-2 truncate text-lg font-black text-slate-900 dark:text-white" title="{{ $invoice['supplier'] }}">{{ $invoice['supplier'] ?: 'Proveedor' }}</h2>
                </div>
                @if($invoice['url'])
                    <a href="{{ $invoice['url'] }}" class="inline-flex min-h-10 shrink-0 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                        Ver factura
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                @else
                    <span class="shrink-0 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700">Documento eliminado</span>
                @endif
            </header>

            <div class="overflow-x-auto border-t border-slate-100 bg-slate-50/70 px-2 pb-3 sm:px-4 dark:border-slate-800 dark:bg-slate-950/40">
                <table class="w-full border-separate border-spacing-x-0 border-spacing-y-2 text-left text-sm">
                    <thead>
                        <tr class="text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            <th class="pb-1 pl-6 pr-4 pt-3">Partida</th>
                            @if($conPrecio)
                                <th class="hidden w-56 px-4 pb-1 pt-3 text-right md:table-cell">Precio</th>
                            @endif
                            <th class="w-28 whitespace-nowrap px-4 pb-1 pt-3 text-right">Facturado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice['references'] as $reference)
                            @php
                                $otroNombre = \Illuminate\Support\Str::slug($reference['invoice_product']) !== \Illuminate\Support\Str::slug($reference['request_product']);
                                $otraCantidad = $cantidad($reference['invoice_quantity']) !== $cantidad($reference['request_quantity']);
                                $price = $reference['price'];
                                $borde = match (true) {
                                    (bool) $reference['measures'] => 'border-l-rose-500',
                                    $reference['stale'] || (bool) ($reference['returned'] ?? null) => 'border-l-amber-500',
                                    default => 'border-l-emerald-500',
                                };
                                $colorPrecio = $price === null ? '' : (abs($price['change']) <= $tolerancia ? 'text-slate-500 dark:text-slate-400' : ($price['change'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'));
                            @endphp
                            <tr class="group align-top">
                                <td class="{{ $tarjeta }} rounded-l-2xl border-l-[5px] py-3.5 pl-3 pr-2 md:pl-5 md:pr-4 {{ $borde }}">
                                    <div class="flex items-start gap-3">
                                        <span class="mt-0.5 hidden h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-slate-100 font-mono text-[11px] font-bold text-slate-500 sm:inline-flex dark:bg-slate-800 dark:text-slate-400">{{ $loop->iteration }}</span>
                                        <div class="min-w-0">
                                            <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $reference['request_product'] }}</p>
                                            @if($otroNombre || $otraCantidad)
                                                <p class="mt-1 flex items-start gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                                                    <span class="mt-px shrink-0 text-slate-400">↳</span>
                                                    <span>En la factura: {{ $otroNombre ? $reference['invoice_product'] : '' }}{{ $otroNombre && $otraCantidad ? ' · ' : '' }}{{ $otraCantidad ? $cantidad($reference['invoice_quantity']).' '.$unidad($reference['invoice_unit']) : '' }}</span>
                                                </p>
                                            @endif
                                            @if($price)
                                                <p class="mt-1 font-mono text-[11px] tabular-nums md:hidden {{ $colorPrecio }}">
                                                    {{ $pesos($price['invoiced_unit']) }} c/u · solicitado {{ $pesos($price['requested_unit']) }}
                                                </p>
                                            @endif
                                            @if($reference['measures'])
                                                <span class="mt-1.5 inline-flex rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-bold text-rose-700 ring-1 ring-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:ring-rose-900">
                                                    Medidas distintas: factura {{ implode(', ', $reference['measures']['invoice']) }} · solicitud {{ implode(', ', $reference['measures']['request']) }}
                                                </span>
                                            @endif
                                            @if($reference['returned'] ?? null)
                                                <span class="mt-1.5 inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-bold text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900">
                                                    Nota de crédito: devolvieron {{ $cantidad($reference['returned']) }} {{ $unidad($reference['request_unit']) }}, vuelven a quedar pendientes
                                                </span>
                                            @endif
                                            @if($reference['stale'])
                                                <span class="mt-1.5 inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-black text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900">Requiere revisión</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                @if($conPrecio)
                                <td class="hidden whitespace-nowrap px-4 py-3.5 text-right font-mono tabular-nums md:table-cell {{ $tarjeta }}">
                                    @if($price)
                                        <span class="block text-sm font-bold {{ abs($price['change']) <= $tolerancia ? 'text-slate-900 dark:text-white' : $colorPrecio }}">{{ $pesos($price['invoiced_unit']) }} <span class="font-sans text-[11px] font-normal text-slate-400">c/u</span></span>
                                        <span class="block text-[11px] text-slate-500 dark:text-slate-400">
                                            solicitado {{ $pesos($price['requested_unit']) }}
                                            @if($price['change'] != 0)
                                                <span class="font-sans font-bold {{ $colorPrecio }}">{{ $price['change'] > 0 ? '+' : '' }}{{ str_replace('.', ',', (string) $price['change']) }}%</span>
                                            @endif
                                        </span>
                                    @else
                                        <span class="font-sans text-xs italic text-slate-300 dark:text-slate-600">sin precio en la solicitud</span>
                                    @endif
                                </td>
                                @endif
                                <td class="whitespace-nowrap rounded-r-2xl border-r px-4 py-3.5 text-right {{ $tarjeta }}">
                                    <span class="font-mono text-sm font-black tabular-nums text-slate-900 dark:text-white">{{ $cantidad($reference['request_quantity']) }}</span>
                                    <span class="text-xs text-slate-400">{{ $unidad($reference['request_unit']) }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach

    {{-- Lo que todavía no llega en ninguna factura. --}}
    @if(count($unbilledItems ?? []) > 0)
        <section class="overflow-hidden rounded-3xl border border-l-[5px] border-slate-200/90 border-l-slate-400 bg-white shadow-sm dark:border-slate-800 dark:border-l-slate-500 dark:bg-slate-900">
            <header class="p-5 sm:p-6">
                <h2 class="text-sm font-black text-slate-900 dark:text-white">Por facturar · {{ count($unbilledItems) }} {{ count($unbilledItems) === 1 ? 'partida' : 'partidas' }}</h2>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Lo que pediste y todavía no aparece en ninguna factura.</p>
            </header>
            <div class="border-t border-slate-100 bg-slate-50/70 px-2 pb-3 sm:px-4 dark:border-slate-800 dark:bg-slate-950/40">
                <ul class="space-y-2 pt-3">
                    @foreach($unbilledItems as $item)
                        <li class="flex items-center justify-between gap-3 rounded-2xl border border-l-[5px] border-slate-200 border-l-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-800 dark:border-l-slate-600 dark:bg-slate-900">
                            <span class="min-w-0 font-semibold text-slate-800 dark:text-slate-100" title="{{ $item['product'] }}">{{ $item['product'] }}</span>
                            <span class="shrink-0 whitespace-nowrap font-mono tabular-nums text-slate-700 dark:text-slate-200">
                                <span class="font-black">{{ $cantidad($item['remaining']) }}</span>@if($cantidad($item['remaining']) !== $cantidad($item['quantity'])) <span class="text-slate-400">de {{ $cantidad($item['quantity']) }}</span>@endif
                                <span class="font-sans text-xs text-slate-400">{{ $unidad($item['unit']) }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
</div>
@endif
