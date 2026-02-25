{{-- EPP / Salida exit card — variables: $m, $cardLines, $costoTotal, $detailUrl --}}
<a href="{{ $detailUrl ?? '#' }}" class="exit-card block hover:border-indigo-300 dark:hover:border-indigo-700 transition">

    {{-- Header --}}
    <div class="px-4 pt-4 pb-3 flex items-start justify-between gap-2 border-b border-gray-100 dark:border-gray-800">
        <div class="min-w-0">
            <p class="text-[11px] text-gray-400 mb-0.5">#{{ $m->id }}</p>
            <p class="font-bold text-sm text-gray-900 dark:text-gray-100 truncate">
                {{ $m->destinatario ?? '—' }}
            </p>
            @if ($m->notas)
                <p class="text-xs text-gray-400 truncate mt-0.5">{{ $m->notas }}</p>
            @endif
        </div>
        <div class="text-right shrink-0">
            <p class="text-sm font-bold text-gray-900 dark:text-gray-100">
                {{ \Carbon\Carbon::parse($m->ocurrio_el)->format('d/m/Y') }}
            </p>
            <p class="text-[11px] text-gray-400">
                {{ \Carbon\Carbon::parse($m->created_at)->format('H:i') }}
            </p>
            @if ($m->usuario_id)
                @php $userName = \App\Models\User::find($m->usuario_id)?->name ?? 'Usuario #'.$m->usuario_id; @endphp
                <p class="text-[11px] text-gray-400 mt-0.5 truncate max-w-[120px]">{{ $userName }}</p>
            @endif
        </div>
    </div>

    {{-- Product lines --}}
    <div class="px-4 py-2">
        @php
            $cantidadTotal = (float) $cardLines->sum('cantidad');
        @endphp
        <table class="w-full text-xs">
            <thead>
                <tr class="border-b border-gray-100 dark:border-gray-800">
                    <th class="text-left text-gray-400 font-semibold py-1.5 pr-2">Producto</th>
                    <th class="text-right text-gray-400 font-semibold py-1.5 pr-2">Cant.</th>
                    <th class="text-right text-gray-400 font-semibold py-1.5">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="py-1.5 pr-2">
                        <p class="font-semibold text-gray-800 dark:text-gray-200 leading-tight">Ficha Operativa</p>
                    </td>
                    <td class="py-1.5 pr-2 text-right font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap">
                        {{ number_format($cantidadTotal, 2, ',', '.') }}
                    </td>
                    <td class="py-1.5 text-right font-semibold text-gray-800 dark:text-gray-200 whitespace-nowrap">
                        $ {{ number_format($costoTotal, 0, ',', '.') }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Footer: cost only --}}
    <div class="px-4 pb-4 pt-2 border-t border-gray-100 dark:border-gray-800">
        <div>
            <p class="text-[10px] text-gray-400 uppercase tracking-wide">Costo total</p>
            <p class="text-sm font-bold text-rose-600 dark:text-rose-400">
                $ {{ number_format($costoTotal, 0, ',', '.') }}
            </p>
        </div>
    </div>
</a>
