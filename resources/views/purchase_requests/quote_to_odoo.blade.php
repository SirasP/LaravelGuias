<x-app-layout>
    <x-slot name="header">
        <div class="flex min-w-0 items-center gap-2">
            <a href="{{ route('purchase_requests.show', $purchaseRequest) }}"
               class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300" aria-label="Volver a la solicitud">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div class="min-w-0">
                <h1 class="truncate text-sm font-extrabold text-slate-900 dark:text-white">Llevar a Odoo · {{ $lectura->supplier_name ?: 'Proveedor sin identificar' }}</h1>
                <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $purchaseRequest->folio }} · {{ $lectura->original_name }}</p>
            </div>
        </div>
    </x-slot>

    @php
        $dinero = fn (?float $v): string => $v === null ? '—' : '$'.number_format($v, $v == floor($v) ? 0 : 2, ',', '.');
        $cant = fn (?float $v): string => $v === null ? '—' : rtrim(rtrim(number_format($v, 4, ',', '.'), '0'), ',');
        $orden = $plan['orden'];
        $odooUrl = $orden === null || blank(config('purchase_requests.odoo.url'))
            ? null : rtrim((string) config('purchase_requests.odoo.url'), '/').'/odoo/purchase/'.$orden['id'];
        $filas = collect($plan['filas'])->sortBy('orden_partida')->values();
        $escribibles = $filas->where('escribible', true);
        $marcadas = $escribibles->filter(fn ($f) => $f['cambia'] && ! $f['medidas_chocan']);
        $aRevisar = $filas->where('medidas_chocan', true)->count();
        $sinLinea = $filas->whereNull('linea')->count();
    @endphp

    <div class="mx-auto max-w-8xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
            @if($orden === null)
                <p class="text-sm font-bold text-amber-800 dark:text-amber-300">{{ $plan['motivo'] }}</p>
            @else
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-mono text-lg font-black text-slate-900 dark:text-white">{{ $orden['name'] }}</span>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ $orden['editable'] ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' }}">
                        {{ ['draft' => 'Cotización', 'sent' => 'Cotización enviada', 'to approve' => 'Por aprobar', 'purchase' => 'Orden de compra', 'done' => 'Bloqueada'][$orden['state']] ?? $orden['state'] }}
                    </span>
                    @if($odooUrl)
                        <a href="{{ $odooUrl }}" target="_blank" rel="noopener" class="text-xs font-bold text-violet-700 hover:underline dark:text-violet-300">Abrir en Odoo ↗</a>
                    @endif
                </div>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    La orden de <span class="font-bold">{{ $lectura->supplier_name }}</span> en Odoo. Revisa cada fila: lo marcado se escribe en esa orden, y nada más.
                </p>
                @if($plan['motivo'])
                    <p class="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-200">{{ $plan['motivo'] }}</p>
                @endif
                <div class="mt-3 flex flex-wrap gap-2 text-xs font-bold">
                    <span class="rounded-full bg-violet-100 px-2.5 py-1 text-violet-800 dark:bg-violet-950/60 dark:text-violet-200">{{ $marcadas->count() }} {{ $marcadas->count() === 1 ? 'línea cambia' : 'líneas cambian' }}</span>
                    @if($aRevisar > 0)
                        <span class="rounded-full bg-rose-100 px-2.5 py-1 text-rose-800 dark:bg-rose-950/60 dark:text-rose-200">{{ $aRevisar }} con medidas que no calzan · quedan sin marcar</span>
                    @endif
                    @if($sinLinea > 0)
                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-amber-800 dark:bg-amber-950/60 dark:text-amber-200">{{ $sinLinea }} sin línea en la orden</span>
                    @endif
                    @if(count($plan['sobrantes']) > 0)
                        <span class="rounded-full bg-sky-100 px-2.5 py-1 text-sky-800 dark:bg-sky-950/60 dark:text-sky-200">{{ count($plan['sobrantes']) }} que nadie pidió así</span>
                    @endif
                </div>
            @endif
        </section>

        @if($orden !== null)
            <form method="POST" action="{{ route('purchase_requests.quotes.prices', [$purchaseRequest, $lectura]) }}" class="space-y-5">
                @csrf
                <input type="hidden" name="vista_previa" value="1">

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="hidden grid-cols-[2rem_minmax(0,1fr)_minmax(0,1.2fr)_minmax(0,1.3fr)] gap-3 border-b border-slate-200 bg-slate-50 px-4 py-2 text-[11px] font-black uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400 md:grid">
                        <span></span><span>Pediste</span><span>Cotizó</span><span>En Odoo hoy → quedaría</span>
                    </div>

                    @foreach($filas as $f)
                        @php
                            $marcada = $f['escribible'] && $f['cambia'] && ! $f['medidas_chocan'];
                            $p = $f['propuesto'];
                        @endphp
                        <div class="grid grid-cols-[2rem_minmax(0,1fr)] gap-x-3 gap-y-2 border-b border-slate-100 px-4 py-3 last:border-0 dark:border-slate-800 md:grid-cols-[2rem_minmax(0,1fr)_minmax(0,1.2fr)_minmax(0,1.3fr)] {{ $f['medidas_chocan'] ? 'bg-rose-50/60 dark:bg-rose-950/20' : '' }}">
                            <div class="row-span-3 pt-0.5 md:row-span-1">
                                @if($f['escribible'])
                                    <input type="checkbox" name="filas[]" value="{{ $f['item_id'] }}" @checked($marcada)
                                        class="h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500 dark:border-slate-600 dark:bg-slate-800"
                                        aria-label="Llevar la partida {{ $f['orden_partida'] }}">
                                @endif
                            </div>

                            <div class="min-w-0 text-sm">
                                <p class="font-bold text-slate-900 dark:text-white"><span class="text-slate-400">{{ $f['orden_partida'] }}.</span> {{ $f['pedida'] }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $cant($f['cantidad_pedida']) }} {{ $f['unidad_pedida'] }}</p>
                            </div>

                            <div class="col-start-2 min-w-0 text-sm md:col-start-auto">
                                <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $f['cotizada'] }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $cant($f['cantidad_cotizada']) }} {{ $f['unidad_cotizada'] }} a {{ $dinero($f['precio_cotizado']) }}</p>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @if($f['medidas_chocan'])
                                        <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-bold text-rose-800 dark:bg-rose-950/60 dark:text-rose-200">Las medidas no calzan: ¿es la pareja correcta?</span>
                                    @endif
                                    @if($f['propuesta'])
                                        <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[11px] font-bold text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-200">Pareja sin confirmar: no se lleva</span>
                                    @endif
                                </div>
                            </div>

                            <div class="col-start-2 min-w-0 text-sm md:col-start-auto">
                                @if($f['linea'] === null)
                                    <p class="text-xs font-bold text-amber-700 dark:text-amber-400">No se encontró su línea en {{ $orden['name'] }}.</p>
                                @else
                                    <p class="truncate text-xs text-slate-400 line-through decoration-slate-300" title="{{ $f['linea']['nombre'] }}">
                                        {{ $f['linea']['nombre'] }} · {{ $cant($f['linea']['cantidad']) }} {{ $f['linea']['unidad'] }} a {{ $dinero($f['linea']['precio']) }}
                                    </p>
                                    @if($f['cambia'])
                                        <p class="font-semibold text-violet-800 dark:text-violet-200">
                                            {{ $p['nombre'] ?: $f['linea']['nombre'] }}
                                        </p>
                                        <p class="text-xs font-bold text-violet-700 dark:text-violet-300">
                                            {{ $cant($p['cantidad'] ?? $f['linea']['cantidad']) }} {{ $f['linea']['unidad'] }} a {{ $dinero($p['precio'] ?? $f['linea']['precio']) }}
                                            <span class="font-normal text-slate-500 dark:text-slate-400">= {{ $dinero(($p['cantidad'] ?? $f['linea']['cantidad']) * ($p['precio'] ?? $f['linea']['precio'])) }}</span>
                                        </p>
                                    @else
                                        <p class="text-xs font-bold text-emerald-700 dark:text-emerald-400">Odoo ya dice lo mismo.</p>
                                    @endif
                                    @if($f['por_medida'] && $f['escribible'])
                                        <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                                            Cotizan {{ $cant($f['cantidad_cotizada']) }} por medida y pediste {{ $cant($f['cantidad_pedida']) }}: la orden sigue en {{ $cant($f['cantidad_pedida']) }} {{ $f['linea']['unidad'] }}, con el mismo total.
                                        </p>
                                        <label class="mt-1 flex items-center gap-1.5 text-[11px] font-semibold text-slate-600 dark:text-slate-300">
                                            <input type="checkbox" name="sin_convertir[]" value="{{ $f['item_id'] }}" class="h-3.5 w-3.5 rounded border-slate-300 text-violet-600 dark:border-slate-600 dark:bg-slate-800">
                                            Mejor llevar {{ $cant($f['cantidad_cotizada']) }} a {{ $dinero($f['precio_cotizado']) }}, como viene
                                        </label>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endforeach
                </section>

                @if(count($plan['piezas'] ?? []) > 0)
                    <section class="rounded-2xl border border-violet-200 bg-violet-50/50 p-4 shadow-sm dark:border-violet-900/60 dark:bg-violet-950/20">
                        <h2 class="text-sm font-black text-violet-900 dark:text-violet-200">Piezas de una partida</h2>
                        <p class="mt-0.5 text-xs text-violet-800 dark:text-violet-300">
                            El proveedor cotizó aparte algo que va dentro de una partida. Entra a {{ $orden['name'] }} en su propia línea, que es como lo va a facturar.
                        </p>
                        <div class="mt-3 space-y-2">
                            @foreach($plan['piezas'] as $pz)
                                <label class="flex items-start gap-2 rounded-xl bg-white p-2.5 text-sm dark:bg-slate-900">
                                    <input type="checkbox" name="piezas[]" value="{{ $pz['renglon'] }}" @checked($pz['cambia']) @disabled(! $pz['cambia'])
                                        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-violet-600 dark:border-slate-600 dark:bg-slate-800">
                                    <span class="min-w-0">
                                        <span class="font-semibold text-slate-900 dark:text-white">{{ $pz['nombre'] }}</span>
                                        <span class="block text-xs text-slate-500 dark:text-slate-400">
                                            {{ $cant($pz['propuesto']['cantidad']) }} {{ $pz['unidad'] }} a {{ $dinero($pz['propuesto']['precio']) }}
                                            · parte de <span class="font-semibold">{{ $pz['orden_partida'] }}. {{ $pz['pedida'] }}</span>
                                        </span>
                                        <span class="block text-[11px] font-semibold {{ $pz['cambia'] ? 'text-violet-700 dark:text-violet-300' : 'text-emerald-700 dark:text-emerald-400' }}">
                                            {{ ! $pz['cambia'] ? 'Odoo ya la tiene así.' : ($pz['linea'] ? 'Se actualiza su línea en '.$orden['name'].'.' : 'Entra como línea nueva; si Odoo no tiene el producto, se da de alta.') }}
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if(count($plan['sobrantes']) > 0)
                    <section class="rounded-2xl border border-sky-200 bg-sky-50/60 p-4 shadow-sm dark:border-sky-900/60 dark:bg-sky-950/20">
                        <h2 class="text-sm font-black text-sky-900 dark:text-sky-200">Cotizado aparte, sin partida propia</h2>
                        <p class="mt-0.5 text-xs text-sky-800 dark:text-sky-300">
                            Lo que el proveedor separó en otra línea o agregó por su cuenta. Si lo compras, márcalo y entra a {{ $orden['name'] }} como línea nueva.
                            Si Odoo no tiene el producto, se da de alta.
                        </p>
                        <div class="mt-3 space-y-2">
                            @foreach($plan['sobrantes'] as $s)
                                <label class="flex items-start gap-2 rounded-xl bg-white p-2.5 text-sm dark:bg-slate-900">
                                    @if($s['renglon'] !== null)
                                        <input type="checkbox" name="agregar[]" value="{{ $s['renglon'] }}" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-sky-600 dark:border-slate-600 dark:bg-slate-800">
                                    @endif
                                    <span class="min-w-0">
                                        <span class="font-semibold text-slate-900 dark:text-white">{{ $s['nombre'] }}</span>
                                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $cant($s['cantidad']) }} {{ $s['unidad'] }} a {{ $dinero($s['precio']) }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if($orden['editable'] && $escribibles->isNotEmpty())
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="submit"
                            onclick="return confirm('Se escribe en {{ $orden['name'] }} de Odoo lo que quedó marcado. ¿Seguimos?')"
                            class="min-h-11 rounded-xl bg-violet-600 px-5 text-sm font-extrabold text-white shadow-md shadow-violet-500/25 hover:bg-violet-700 active:scale-95 transition">
                            Escribir en {{ $orden['name'] }}
                        </button>
                        <a href="{{ route('purchase_requests.show', $purchaseRequest) }}" class="text-sm font-bold text-slate-500 hover:text-slate-700 dark:text-slate-400">Volver sin escribir</a>
                        <p class="w-full text-xs text-slate-500 dark:text-slate-400">
                            Para corregir una pareja, vuelve a la solicitud y empareja ahí: esta página se arma de nuevo con lo que digas.
                        </p>
                    </div>
                @endif
            </form>
        @endif
    </div>
</x-app-layout>
