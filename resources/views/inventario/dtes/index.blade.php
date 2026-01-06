<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-3">
            <div class="text-sm text-gray-600 dark:text-gray-400">DTE / Facturas</div>

            <form method="GET" class="flex flex-wrap items-center gap-2 justify-end">
                {{-- Buscar --}}
                <input name="q" value="{{ $q }}" placeholder="Buscar RUT/Folio/Empresa..."
                    class="w-72 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 text-sm">

                {{-- Tipo --}}
                <select name="tipo"
                    class="rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 text-sm">
                    <option value="">Tipo (todos)</option>
                    @foreach($tiposDte as $k => $label)
                        <option value="{{ $k }}" {{ (string) $tipo === (string) $k ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>

                {{-- Estado --}}
                <select name="estado"
                    class="rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 text-sm">
                    <option value="">Estado (todos)</option>
                    <option value="pendiente" {{ $estado === 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                    <option value="parcial" {{ $estado === 'parcial' ? 'selected' : '' }}>Parcial</option>
                    <option value="completo" {{ $estado === 'completo' ? 'selected' : '' }}>Completo</option>
                </select>

                {{-- Fechas --}}
                <input type="date" name="desde" value="{{ $desde }}"
                    class="rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 text-sm">
                <input type="date" name="hasta" value="{{ $hasta }}"
                    class="rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 text-sm">

                {{-- PerPage --}}
                <select name="per_page"
                    class="rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 text-sm">
                    <option value="20" {{ (string) $perPage === '20' ? 'selected' : '' }}>20</option>
                    <option value="50" {{ (string) $perPage === '50' ? 'selected' : '' }}>50</option>
                    <option value="100" {{ (string) $perPage === '100' ? 'selected' : '' }}>100</option>
                </select>

                <button class="px-4 py-2 rounded-xl bg-gray-200 dark:bg-gray-700 text-sm">
                    Filtrar
                </button>

                @if($q || $tipo || $estado || $desde || $hasta || ((int) $perPage !== 20))
                    <a href="{{ route('inventario.dtes.index') }}"
                        class="px-3 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-sm">
                        Limpiar
                    </a>
                @endif
            </form>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto px-4 space-y-4">

            @if(session('ok'))
                <div class="rounded-xl bg-green-50 border border-green-200 p-3 text-green-800">
                    {{ session('ok') }}
                </div>
            @endif

            {{-- Barra info --}}
            <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-300">
                <div>
                    @if($dtes->total() > 0)
                        Mostrando <b>{{ $dtes->firstItem() }}</b>–<b>{{ $dtes->lastItem() }}</b> de
                        <b>{{ $dtes->total() }}</b>
                    @else
                        Sin resultados
                    @endif
                </div>

                <div class="text-xs text-gray-500">
                    Tip: clic en la fila para ver detalle
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/60">
                        <tr class="[&>th]:px-4 [&>th]:py-3 [&>th]:text-left">
                            <th class="w-56">Tipo</th>
                            <th class="w-28">Folio</th>
                            <th>Emisor</th>
                            <th>Receptor</th>
                            <th class="text-right w-32">Total</th>
                            <th class="w-32">Fecha</th>
                            <th class="w-36">Inventario</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($dtes as $d)
                            @php
                                $total = (float) ($d->mnt_total ?? 0);
                                $fecha = $d->fch_emis ?: null;

                                $estadoInv = $d->estado_inventario ?? 'pendiente';
                                $pct = (float) ($d->pct_ingresado ?? 0);

                                $badge = match ($estadoInv) {
                                    'completo' => 'bg-green-50 text-green-700 border-green-200 dark:bg-green-900/20 dark:text-green-200 dark:border-green-900/40',
                                    'parcial' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/20 dark:text-amber-200 dark:border-amber-900/40',
                                    default => 'bg-gray-50 text-gray-700 border-gray-200 dark:bg-gray-900/20 dark:text-gray-200 dark:border-gray-700',
                                };

                                $labelEstado = match ($estadoInv) {
                                    'completo' => 'Completo',
                                    'parcial' => 'Parcial',
                                    default => 'Pendiente',
                                };
                            @endphp

                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/40 cursor-pointer"
                                onclick="window.location='{{ route('inventario.dtes.show', $d->id) }}'">
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex items-center px-2 py-1 rounded-lg text-xs border border-gray-200 dark:border-gray-700">
                                        {{ $d->tipo_nombre ?? $d->tipo_dte }}
                                    </span>
                                </td>

                                <td class="px-4 py-3 font-semibold text-blue-600">
                                    {{ $d->folio }}
                                </td>

                                <td class="px-4 py-3">
                                    <div class="font-medium truncate max-w-[28rem]">{{ $d->rz_emisor }}</div>
                                    <div class="text-xs text-gray-500">{{ $d->rut_emisor }}</div>
                                </td>

                                <td class="px-4 py-3">
                                    <div class="font-medium truncate max-w-[28rem]">{{ $d->rz_receptor }}</div>
                                    <div class="text-xs text-gray-500">{{ $d->rut_receptor }}</div>
                                </td>

                                <td class="px-4 py-3 text-right font-semibold">
                                    {{ number_format($total, 0, ',', '.') }}
                                </td>

                                <td class="px-4 py-3">
                                    {{ $fecha ? \Carbon\Carbon::parse($fecha)->format('d-m-Y') : '—' }}
                                </td>

                                <td class="px-4 py-3">
                                    <div class="inline-flex items-center gap-2">
                                        <span
                                            class="inline-flex items-center px-2 py-1 rounded-lg text-xs border {{ $badge }}">
                                            {{ $labelEstado }}
                                        </span>
                                        <span class="text-xs text-gray-500">
                                            {{ number_format($pct, 1, ',', '.') }}%
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-gray-500 mt-1">
                                        {{ number_format((float) ($d->total_ingresado ?? 0), 3, ',', '.') }}
                                        /
                                        {{ number_format((float) ($d->total_objetivo ?? 0), 3, ',', '.') }}
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-gray-500">No hay DTE.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $dtes->links() }}</div>
        </div>
    </div>
</x-app-layout>