{{--
    La comparación de proveedores, igual en la solicitud («Quién conviene») y
    en la orden de Odoo. Cada celda tiene siempre dos renglones —lo que suma la
    línea y, debajo, cantidad × precio con la diferencia contra el más barato—,
    así todas las filas quedan a la misma altura y se leen de corrido.

    columnas: list<{nombre, detalle?, url?, moneda?, apagada?}>
    filas:    list<{nombre, cantidad, unidad, mejor: ?int, celdas: list<?{cantidad, unitario, total, piezas, precio, porConfirmar?}>}>
    totales:  list<{neto, iva, total, notas?: list<string>}>
    mejor:    índice de la columna más conveniente, o null
--}}
@props(['columnas', 'filas', 'totales', 'mejor' => null])

@php
    $plata = function (?float $v, ?string $moneda = null): string {
        if ($v === null) {
            return '—';
        }

        return $moneda && $moneda !== 'CLP' ? $moneda.' '.number_format($v, 2, ',', '.') : '$'.number_format($v, 0, ',', '.');
    };
    $numero = fn (?float $v): string => $v === null ? '—' : rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
@endphp

<div class="overflow-x-auto">
    <table class="w-full border-separate border-spacing-0 text-sm">
        <thead>
            <tr class="text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">
                <th class="sticky left-0 z-10 min-w-[17rem] border-b border-slate-200 bg-slate-50 px-5 py-3 text-left dark:border-slate-800 dark:bg-slate-950">Partida</th>
                <th class="w-24 border-b border-slate-200 bg-slate-50 px-4 py-3 text-right dark:border-slate-800 dark:bg-slate-950">Pediste</th>
                @foreach ($columnas as $i => $c)
                    <th class="min-w-[11rem] border-b border-l border-slate-200 bg-slate-50 px-5 py-3 text-right align-bottom dark:border-slate-800 dark:bg-slate-950 {{ ($c['apagada'] ?? false) ? 'opacity-50' : '' }}">
                        @if(! empty($c['url']))
                            <a href="{{ $c['url'] }}" class="block truncate text-slate-800 hover:underline dark:text-slate-100" title="{{ $c['nombre'] }}">{{ $c['nombre'] }}</a>
                        @else
                            <span class="block truncate text-slate-800 dark:text-slate-100" title="{{ $c['nombre'] }}">{{ $c['nombre'] }}</span>
                        @endif
                        @if(! empty($c['detalle']))
                            <span class="block truncate font-mono text-[10px] font-normal normal-case tracking-normal text-slate-400">{{ $c['detalle'] }}</span>
                        @endif
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $n => $fila)
                @php $min = $fila['mejor'] !== null ? ($fila['celdas'][$fila['mejor']]['precio'] ?? null) : null; @endphp
                <tr class="group">
                    <td class="sticky left-0 z-10 border-b border-slate-100 bg-white px-5 py-3 group-hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:group-hover:bg-slate-800/60">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-slate-100 font-mono text-[11px] font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ $n + 1 }}</span>
                            <span class="line-clamp-2 font-semibold text-slate-800 dark:text-slate-100">{{ $fila['nombre'] }}</span>
                        </div>
                    </td>
                    <td class="whitespace-nowrap border-b border-slate-100 px-4 py-3 text-right font-mono text-xs tabular-nums text-slate-500 group-hover:bg-slate-50 dark:border-slate-800 dark:text-slate-400 dark:group-hover:bg-slate-800/60">
                        {{ $numero($fila['cantidad']) }} <span class="font-sans text-[10px] text-slate-400">{{ $fila['unidad'] }}</span>
                    </td>
                    @foreach ($columnas as $i => $c)
                        @php
                            $celda = $fila['celdas'][$i] ?? null;
                            $gana = $fila['mejor'] === $i;
                            $moneda = $c['moneda'] ?? null;
                        @endphp
                        <td class="whitespace-nowrap border-b border-l border-slate-100 px-5 py-3 text-right font-mono tabular-nums dark:border-slate-800 {{ $gana ? 'bg-emerald-50 dark:bg-emerald-950/30' : 'group-hover:bg-slate-50 dark:group-hover:bg-slate-800/60' }} {{ ($c['apagada'] ?? false) ? 'opacity-50' : '' }}">
                            @if($celda === null || ($celda['total'] ?? null) === null)
                                <span class="font-sans text-xs italic text-slate-300 dark:text-slate-600">no cotizó</span>
                            @else
                                <span class="block text-sm font-bold {{ $gana ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-900 dark:text-white' }}">{{ $plata($celda['total'], $moneda) }}</span>
                                <span class="mt-0.5 flex items-center justify-end gap-1.5 text-[11px] text-slate-500 dark:text-slate-400">
                                    <span>{{ $numero($celda['cantidad']) }} × {{ $plata($celda['unitario'], $moneda) }}</span>
                                    @if($gana)
                                        <span class="font-sans font-black text-emerald-600 dark:text-emerald-400" title="El precio más barato de esta partida">▼ menor</span>
                                    @elseif($min && ($celda['precio'] ?? null))
                                        <span class="font-sans font-bold text-rose-500">+{{ round(($celda['precio'] / $min - 1) * 100) }}%</span>
                                    @endif
                                </span>
                                @if(($celda['piezas'] ?? 0) > 0)
                                    <span class="block font-sans text-[10px] font-bold text-sky-700 dark:text-sky-300" title="Lo cotizó aparte y va en esta partida; ya está sumado.">incl. piezas {{ $plata($celda['piezas'], $moneda) }}</span>
                                @endif
                                @if($celda['porConfirmar'] ?? false)
                                    <span class="block font-sans text-[10px] font-bold text-indigo-600 dark:text-indigo-400">por confirmar</span>
                                @endif
                                @if($fila['cantidad'] !== null && $celda['cantidad'] !== null && abs($celda['cantidad'] - $fila['cantidad']) > 0.0001)
                                    <span class="block font-sans text-[10px] font-bold text-amber-600">ojo: {{ $numero($celda['cantidad']) }} de {{ $numero($fila['cantidad']) }}</span>
                                @endif
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
        {{-- Neto, IVA y lo que sale de verdad, con el más conveniente en verde. --}}
        <tfoot>
            <tr class="align-top">
                <td colspan="2" class="sticky left-0 z-10 bg-slate-50 px-5 py-4 dark:bg-slate-950">
                    <p class="text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-200">Suma de lo cotizado</p>
                    <div class="mt-3 space-y-1.5 text-xs text-slate-500 dark:text-slate-400">
                        <div class="flex h-5 items-center">Neto</div>
                        <div class="flex h-5 items-center">IVA</div>
                        <div class="flex h-9 items-center border-t border-dashed border-slate-200 pt-1.5 font-black uppercase tracking-wider text-slate-700 dark:border-slate-700 dark:text-slate-200">Total con IVA</div>
                    </div>
                </td>
                @foreach ($totales as $i => $t)
                    @php
                        $gana = $mejor === $i;
                        $moneda = $columnas[$i]['moneda'] ?? null;
                    @endphp
                    <td class="whitespace-nowrap border-l border-slate-100 px-5 py-4 text-right font-mono tabular-nums dark:border-slate-800 {{ $gana ? 'bg-emerald-50 dark:bg-emerald-950/30' : 'bg-slate-50 dark:bg-slate-950' }} {{ ($columnas[$i]['apagada'] ?? false) ? 'opacity-50' : '' }}">
                        <p class="h-4 font-sans text-[10px] font-black uppercase tracking-wider {{ $gana ? 'text-emerald-700 dark:text-emerald-400' : 'text-transparent' }}">{{ $gana ? 'Más conveniente' : '·' }}</p>
                        <div class="mt-3 space-y-1.5">
                            <div class="flex h-5 items-center justify-end text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $plata($t['neto'], $moneda) }}</div>
                            <div class="flex h-5 items-center justify-end text-xs text-slate-400 dark:text-slate-500">+ {{ $plata($t['iva'], $moneda) }}</div>
                            <div class="flex h-9 items-center justify-end border-t border-dashed pt-1.5 text-lg font-black {{ $gana ? 'border-emerald-200 text-emerald-700 dark:border-emerald-900 dark:text-emerald-400' : 'border-slate-200 text-slate-900 dark:border-slate-700 dark:text-white' }}">{{ $plata($t['total'], $moneda) }}</div>
                        </div>
                        @foreach ($t['notas'] ?? [] as $nota)
                            <span class="mt-1 block font-sans text-[11px] font-bold text-amber-700 dark:text-amber-400">{{ $nota }}</span>
                        @endforeach
                    </td>
                @endforeach
            </tr>
        </tfoot>
    </table>
</div>
