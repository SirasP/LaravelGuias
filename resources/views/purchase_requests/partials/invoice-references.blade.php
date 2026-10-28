@if(count($invoiceReferences ?? []) > 0)
@php
    $cantidad = function (?string $valor): string {
        $texto = rtrim(rtrim((string) $valor, '0'), '.');

        return str_replace('.', ',', $texto === '' ? '0' : $texto);
    };
    $unidad = fn (?string $valor): string => \App\Services\DtePurchases\Quantity::unit($valor) === 'un' ? 'un.' : mb_strtolower((string) $valor);
@endphp
<section class="mb-6 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <header class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
        <div class="flex items-center gap-3">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-300">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
            </div>
            <div>
                <h2 class="text-[11px] font-black uppercase tracking-widest text-slate-400">Facturas relacionadas</h2>
                <p class="text-sm font-bold text-slate-900 dark:text-white">
                    {{ count($invoiceReferences) }} {{ count($invoiceReferences) === 1 ? 'factura' : 'facturas' }}
                    · {{ collect($invoiceReferences)->sum(fn ($i) => count($i['references'])) }} partidas
                </p>
            </div>
        </div>
    </header>

    <div class="grid gap-px bg-slate-100 dark:bg-slate-800 {{ count($invoiceReferences) > 1 ? 'xl:grid-cols-2' : '' }}">
        @foreach($invoiceReferences as $invoice)
            <article class="bg-white p-5 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold text-slate-900 dark:text-white" title="{{ $invoice['supplier'] }}">{{ $invoice['supplier'] ?: 'Proveedor' }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Factura N° {{ $invoice['folio'] }}
                            @if($invoice['date']) · {{ \Illuminate\Support\Carbon::parse($invoice['date'])->format('d-m-Y') }} @endif
                        </p>
                    </div>
                    @if($invoice['url'])
                        <a href="{{ $invoice['url'] }}" class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-bold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                            Ver factura
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </a>
                    @else
                        <span class="shrink-0 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700">Documento eliminado</span>
                    @endif
                </div>

                <ul class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-100 dark:divide-slate-800 dark:border-slate-800">
                    @foreach($invoice['references'] as $reference)
                        <li class="flex items-start gap-3 px-3 py-2.5 {{ $reference['stale'] ? 'bg-amber-50/70 dark:bg-amber-950/20' : '' }}">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100" title="{{ $reference['request_product'] }}">{{ $reference['request_product'] }}</p>
                                @php
                                    $otroNombre = \Illuminate\Support\Str::slug($reference['invoice_product']) !== \Illuminate\Support\Str::slug($reference['request_product']);
                                    $otraCantidad = $cantidad($reference['invoice_quantity']) !== $cantidad($reference['request_quantity']);
                                @endphp
                                @if($otroNombre || $otraCantidad)
                                    <p class="truncate text-xs text-slate-400" title="{{ $reference['invoice_product'] }}">
                                        En la factura: {{ $otroNombre ? $reference['invoice_product'] : '' }}{{ $otroNombre && $otraCantidad ? ' · ' : '' }}{{ $otraCantidad ? $cantidad($reference['invoice_quantity']).' '.$unidad($reference['invoice_unit']) : '' }}
                                    </p>
                                @endif
                                @if($reference['stale'])
                                    <p class="mt-0.5 text-xs font-bold text-amber-700 dark:text-amber-400">Requiere revisión</p>
                                @endif
                            </div>
                            <span class="shrink-0 text-sm font-bold tabular-nums text-slate-900 dark:text-white">
                                {{ $cantidad($reference['request_quantity']) }} <span class="text-xs font-medium text-slate-400">{{ $unidad($reference['request_unit']) }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </article>
        @endforeach
    </div>
</section>
@endif
