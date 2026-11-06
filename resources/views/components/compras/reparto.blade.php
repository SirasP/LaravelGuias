{{--
    «¿Y si repartes la compra?»: lo que sale comprándole a cada proveedor sólo
    lo que tiene más barato, contra todo al más barato de los que cotizaron
    todo. Recibe lo que calcula CompraRepartida::de(); sin ahorro, no se muestra.
--}}
@props(['reparto'])

@if($reparto)
    @php $plata = fn (float $v): string => '$'.number_format($v, 0, ',', '.'); @endphp
    <div {{ $attributes->merge(['class' => 'rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-900 dark:bg-emerald-950/30']) }}>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-sm font-extrabold text-emerald-900 dark:text-emerald-100">¿Y si repartes la compra?</p>
                <p class="text-xs text-emerald-800/80 dark:text-emerald-200/80">Comprándole a cada proveedor sólo lo que tiene más barato. Serían {{ count($reparto['grupos']) }} órdenes de compra en vez de una.</p>
            </div>
            <span class="rounded-full bg-emerald-600 px-3 py-1 text-xs font-black text-white">Ahorras {{ $plata($reparto['ahorro']) }} neto</span>
        </div>
        <div class="mt-3 grid gap-4 lg:grid-cols-2">
            <ul class="divide-y divide-emerald-100 rounded-xl bg-white text-sm dark:divide-emerald-900/50 dark:bg-slate-900">
                @foreach($reparto['grupos'] as $g)
                    <li class="flex items-center justify-between gap-3 px-3 py-2">
                        <span class="min-w-0 truncate"><span class="font-bold text-slate-800 dark:text-slate-100">{{ $g['nombre'] }}</span> <span class="text-xs text-slate-500">· {{ $g['productos'] }} {{ $g['productos'] === 1 ? 'partida' : 'partidas' }}</span></span>
                        <span class="shrink-0 font-mono tabular-nums text-slate-700 dark:text-slate-200">{{ $plata($g['neto']) }}</span>
                    </li>
                @endforeach
            </ul>
            <dl class="rounded-xl bg-white p-3 font-mono text-sm tabular-nums dark:bg-slate-900">
                <div class="flex justify-between gap-3">
                    <dt class="font-sans text-xs font-bold uppercase tracking-wider text-slate-500">Repartida</dt>
                    <dd class="font-black text-emerald-700 dark:text-emerald-400">{{ $plata($reparto['repartida']) }}</dd>
                </div>
                <div class="mt-1.5 flex justify-between gap-3">
                    <dt class="font-sans text-xs font-bold uppercase tracking-wider text-slate-500">Todo a {{ $reparto['referencia']['nombre'] }}</dt>
                    <dd class="text-slate-700 dark:text-slate-200">{{ $plata($reparto['referencia']['neto']) }}</dd>
                </div>
                <div class="mt-2 flex justify-between gap-3 border-t border-dashed border-slate-200 pt-2 dark:border-slate-700">
                    <dt class="font-sans text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-200">Diferencia</dt>
                    <dd class="font-black text-slate-900 dark:text-white">{{ $plata($reparto['ahorro']) }} <span class="font-sans text-xs font-bold text-emerald-700 dark:text-emerald-400">({{ round($reparto['ahorro'] * 100 / $reparto['referencia']['neto']) }} %)</span></dd>
                </div>
                <p class="mt-2 font-sans text-[11px] text-slate-400">Montos netos. {{ $reparto['referencia']['nombre'] }} es la más barata de las que cotizaron todo.</p>
            </dl>
        </div>
    </div>
@endif
