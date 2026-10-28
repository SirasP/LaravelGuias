@if(count($invoiceReferences ?? []) > 0)
<section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <h2 class="font-bold text-slate-900 dark:text-white">Facturas relacionadas</h2>
    <p class="text-xs text-slate-500 mt-1">Partidas vinculadas a esta solicitud, separadas por proveedor y factura.</p>
    <div class="mt-4 space-y-4">
        @foreach($invoiceReferences as $invoice)
            <article class="border-t border-slate-100 dark:border-slate-800 pt-3">
                @if($invoice['url'])
                    <a href="{{ $invoice['url'] }}" class="text-sm font-bold text-indigo-600 dark:text-indigo-300">Factura {{ $invoice['folio'] }} · {{ $invoice['supplier'] }}</a>
                @else
                    <p class="text-sm font-semibold text-amber-700">{{ $invoice['folio'] }}</p>
                @endif
                @foreach($invoice['references'] as $reference)
                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                        {{ $reference['request_product'] }} · {{ rtrim(rtrim($reference['request_quantity'], '0'), '.') }} {{ $reference['request_unit'] }}
                        @if($reference['stale'])<strong class="text-amber-700">· Requiere revisión</strong>@endif
                    </p>
                @endforeach
            </article>
        @endforeach
    </div>
</section>
@endif
