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
    // Cada partida una tarjeta, como las filas de la lista de solicitudes: borde verde si tiene un más barato claro.
    $tarjeta = 'border-y border-slate-200 bg-white transition-colors group-hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900 dark:group-hover:border-slate-700';
    $ultima = count($columnas) - 1;
@endphp

<div class="overflow-x-auto bg-slate-50 px-4 pb-3 dark:bg-slate-950/60">
    <table class="w-full border-separate border-spacing-x-0 border-spacing-y-2 text-sm">
        <thead>
            <tr class="text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">
                <th class="sticky left-0 z-10 min-w-[10rem] bg-slate-50 pb-1 pl-6 pr-3 pt-3 sm:min-w-[17rem] sm:pr-5 text-left dark:bg-slate-950">Partida</th>
                <th class="hidden w-24 px-4 pb-1 pt-3 text-right sm:table-cell">Pediste</th>
                @foreach ($columnas as $i => $c)
                    <th class="min-w-[9rem] px-4 pb-1 pt-3 sm:min-w-[11rem] sm:px-5 text-right align-bottom {{ ($c['apagada'] ?? false) ? 'opacity-50' : '' }}">
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
                    <td class="sticky left-0 z-10 rounded-l-2xl border-l-[5px] py-3.5 pl-3 pr-3 sm:pl-4 sm:pr-5 {{ $tarjeta }} {{ $fila['mejor'] !== null ? 'border-l-emerald-500' : 'border-l-slate-300 dark:border-l-slate-600' }}">
                        <div class="flex items-center gap-3">
                            <span class="hidden h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-slate-100 font-mono text-[11px] sm:inline-flex font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ $n + 1 }}</span>
                            <span class="min-w-0">
                                <span class="line-clamp-3 text-[13px] font-semibold text-slate-800 sm:line-clamp-2 sm:text-sm dark:text-slate-100">{{ $fila['nombre'] }}</span>
                                <span class="mt-0.5 block text-[11px] text-slate-500 sm:hidden dark:text-slate-400">Pediste <span class="font-mono font-bold text-slate-700 dark:text-slate-200">{{ $numero($fila['cantidad']) }}</span> {{ $fila['unidad'] }}</span>
                            </span>
                        </div>
                    </td>
                    <td class="hidden whitespace-nowrap px-4 py-3.5 text-right font-mono text-xs tabular-nums text-slate-500 sm:table-cell dark:text-slate-400 {{ $tarjeta }}">
                        {{ $numero($fila['cantidad']) }} <span class="font-sans text-[10px] text-slate-400">{{ $fila['unidad'] }}</span>
                    </td>
                    @foreach ($columnas as $i => $c)
                        @php
                            $celda = $fila['celdas'][$i] ?? null;
                            $gana = $fila['mejor'] === $i;
                            $moneda = $c['moneda'] ?? null;
                        @endphp
                        <td class="whitespace-nowrap border-y px-5 py-3.5 text-right font-mono tabular-nums transition-colors {{ $gana ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/40' : 'border-slate-200 bg-white group-hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900' }} {{ $i === $ultima ? 'rounded-r-2xl border-r' : '' }} {{ ($c['apagada'] ?? false) ? 'opacity-50' : '' }}">
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
                <td class="sticky left-0 z-10 rounded-l-2xl border-y border-l-[5px] border-slate-200 border-l-slate-700 bg-white py-4 pl-3 pr-3 sm:pl-4 sm:pr-5 dark:border-slate-800 dark:border-l-slate-400 dark:bg-slate-900">
                    <p class="whitespace-nowrap text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-200"><span class="sm:hidden">Suma</span><span class="hidden sm:inline">Suma de lo cotizado</span></p>
                    <div class="mt-3 space-y-1.5 text-xs text-slate-500 dark:text-slate-400">
                        <div class="flex h-5 items-center">Neto</div>
                        <div class="flex h-5 items-center">IVA</div>
                        <div class="flex h-9 items-center border-t border-dashed border-slate-200 pt-1.5 font-black uppercase tracking-wider text-slate-700 dark:border-slate-700 dark:text-slate-200">Total con IVA</div>
                    </div>
                </td>
                {{-- Bajo «Pediste», que en el teléfono no se muestra: con un colspan el pie se corría una columna. --}}
                <td class="hidden border-y border-slate-200 bg-white sm:table-cell dark:border-slate-800 dark:bg-slate-900"></td>
                @foreach ($totales as $i => $t)
                    @php
                        $gana = $mejor === $i;
                        $moneda = $columnas[$i]['moneda'] ?? null;
                    @endphp
                    <td class="whitespace-nowrap border-y px-5 py-4 text-right font-mono tabular-nums {{ $gana ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/40' : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900' }} {{ $i === $ultima ? 'rounded-r-2xl border-r' : '' }} {{ ($columnas[$i]['apagada'] ?? false) ? 'opacity-50' : '' }}">
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
