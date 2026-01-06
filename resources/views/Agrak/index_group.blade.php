{{-- resources/views/agrak/index_group.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div class="text-sm text-gray-600 dark:text-gray-400">
                Agrak · Viajes por camión
            </div>

            <a href="{{ route('agrak.index', request()->except('view')) }}"
               class="inline-flex items-center gap-2 px-3 py-2 rounded-lg
                      bg-gray-100 hover:bg-gray-200
                      dark:bg-gray-800 dark:hover:bg-gray-700 text-sm">
                ← Volver a lista
            </a>
        </div>
    </x-slot>
{{-- HEADER GRUPO --}}

<a href="{{ route('agrak.export') }}"
   class="inline-flex items-center gap-2 px-4 py-2 rounded-lg
          bg-emerald-600 hover:bg-emerald-700
          text-white text-sm font-semibold">
    📥 Exportar TODO a Excel
</a>


    @php
        /**
         * Badge visual estado Odoo (MATCH A NIVEL GRUPO)
         */
        function odooBadge($m) {
            if (!$m) {
                return '<span class="px-2 py-0.5 rounded-full text-xs bg-gray-300 text-gray-800">
                            Odoo: SIN MATCH
                        </span>';
            }

            return match ($m->estado) {
                'ok' => '<span class="px-2 py-0.5 rounded-full text-xs bg-green-600 text-white">
                            ✔ Odoo OK (' . $m->score . ')
                         </span>',
                'probable' => '<span class="px-2 py-0.5 rounded-full text-xs bg-yellow-400 text-black">
                            ⚠ Odoo PROBABLE (' . $m->score . ')
                         </span>',
                default => '<span class="px-2 py-0.5 rounded-full text-xs bg-red-600 text-white">
                            ✖ Odoo MANUAL (' . $m->score . ')
                         </span>',
            };
        }
        
    @endphp

    <div class="py-6">
        <div class="max-w-full mx-auto px-4 space-y-4">

            {{-- Mensaje OK --}}
            @if(session('ok'))
                <div class="rounded-xl bg-green-50 border border-green-200 p-3 text-green-800">
                    {{ session('ok') }}
                </div>
            @endif

            {{-- Filtros --}}
            <div class="flex items-center gap-2">
                <a href="{{ request()->fullUrlWithQuery(['modo' => 'all']) }}"
                   class="px-3 py-1 rounded-lg text-xs
                          {{ request('modo','all') === 'all'
                              ? 'bg-gray-900 text-white'
                              : 'bg-gray-100 text-gray-700' }}">
                    Todos
                </a>

                <a href="{{ request()->fullUrlWithQuery(['modo' => 'pendientes']) }}"
                   class="px-3 py-1 rounded-lg text-xs
                          {{ request('modo') === 'pendientes'
                              ? 'bg-red-600 text-white'
                              : 'bg-red-100 text-red-700' }}">
                    ⚠ Pendientes
                </a>

                <a href="{{ request()->fullUrlWithQuery(['modo' => 'ok']) }}"
                   class="px-3 py-1 rounded-lg text-xs
                          {{ request('modo') === 'ok'
                              ? 'bg-green-600 text-white'
                              : 'bg-green-100 text-green-700' }}">
                    ✔ OK
                </a>
            </div>

            {{-- LISTA DE GRUPOS --}}
            @forelse($groups as $g)
                @php
                    $fechaLabel = $g->fecha_registro
                        ? \Carbon\Carbon::parse($g->fecha_registro)->format('d-m-Y')
                        : '—';

                    $trips = $g->trips ?? [];
                    $groupMatch = $g->odoo_match ?? null;
                @endphp

                <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 overflow-hidden">

                    {{-- HEADER GRUPO --}}
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded-md text-sm font-semibold bg-gray-900 text-white uppercase">
                                    {{ $g->patente_norm ?: '—' }}
                                </span>

                                <span class="text-gray-400">·</span>

                                <span class="text-sm text-gray-700 dark:text-gray-200">
                                    {{ $fechaLabel }}
                                </span>

                                {!! odooBadge($groupMatch) !!}
                            </div>

                            <div class="text-xs text-gray-500">
                                Viajes detectados: <b>{{ count($trips) }}</b>
                            </div>
                        </div>

                        @if($groupMatch)
                            <div class="mt-1 text-[11px] text-gray-500">
                                Match Odoo ID #{{ $groupMatch->excel_out_transfer_id }}
                            </div>
                        @endif
                    </div>

                    {{-- VIAJES --}}
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($trips as $idx => $trip)
                            @php
                                $horaIni = $trip['hora_inicio'] ?? '—';
                                $horaFin = $trip['hora_fin'] ?? '—';
                                $bins = (int)($trip['bins'] ?? 0);
                                $bandejas = (int)($trip['total_bandejas'] ?? 0);
                                $chofer = $trip['nombre_chofer'] ?? '—';
                                $export = $trip['exportadora'] ?? '—';
                                $items = $trip['items'] ?? collect();
                                if (is_array($items)) $items = collect($items);
                            @endphp

                            <div x-data="{ open: false }">
                                {{-- VIAJE HEADER --}}
                                <button @click="open = !open"
                                        class="w-full text-left p-4 hover:bg-gray-50 dark:hover:bg-gray-900 transition
                                               flex items-start justify-between gap-4">

                                    <div class="min-w-0 space-y-1">
                                        <div class="flex items-center gap-2 uppercase">
                                            <span class="font-semibold text-gray-900 dark:text-gray-100">
                                                {{ $chofer }}
                                            </span>

                                            @if(!$trip['camion_existe'] && $trip['patente'])
                                                <a href="{{ route('agrak.create', ['patente' => $trip['patente']]) }}"
                                                   class="px-2 py-0.5 rounded-full text-[11px]
                                                          bg-yellow-100 text-yellow-800">
                                                    ⚠ Registrar camión
                                                </a>
                                            @endif
                                        </div>

                                        <div class="flex flex-wrap gap-1 text-[11px]">
                                            <span class="px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700">
                                                ⏱ {{ $horaIni }} – {{ $horaFin }}
                                            </span>

                                            <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-700">
                                                Viaje #{{ $idx + 1 }}
                                            </span>

                                            <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700">
                                                {{ $export }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 text-xs">
                                        <span class="px-2 py-1 rounded-lg bg-gray-100">
                                            Bins: <b>{{ $bins }}</b>
                                        </span>
                                        <span class="px-2 py-1 rounded-lg bg-gray-100">
                                            Bandejas: <b>{{ $bandejas }}</b>
                                        </span>
                                        <svg class="w-4 h-4 transition-transform"
                                             :class="open ? 'rotate-180' : ''"
                                             xmlns="http://www.w3.org/2000/svg" fill="none"
                                             viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </div>
                                </button>

                                {{-- DETALLE --}}
                                <div x-show="open" x-collapse
                                     class="border-t border-gray-200 dark:border-gray-800 p-4">
                                    <div class="text-sm font-semibold mb-2">
                                        Bins del viaje ({{ $items->count() }})
                                    </div>

                                    <div class="overflow-x-auto rounded-xl border">
                                        <table class="min-w-full text-sm">
                                            <thead class="bg-gray-50 text-xs">
                                                <tr>
                                                    <th class="px-3 py-2 text-left">Hora</th>
                                                    <th class="px-3 py-2 text-left">Bin</th>
                                                    <th class="px-3 py-2 text-right">Bandejas</th>
                                                    <th class="px-3 py-2 text-left">Máquina</th>
                                                    <th class="px-3 py-2 text-left">Cuartel</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y">
                                                @forelse($items as $bin)
                                                    <tr>
                                                        <td class="px-3 py-2">{{ $bin->hora_registro }}</td>
                                                        <td class="px-3 py-2 font-semibold">{{ $bin->codigo_bin }}</td>
                                                        <td class="px-3 py-2 text-right">{{ $bin->numero_bandejas_palet }}</td>
                                                        <td class="px-3 py-2">{{ $bin->maquina }}</td>
                                                        <td class="px-3 py-2">{{ $bin->cuartel }}</td>
                                                        <td class="px-3 py-2 text-right">
                                                            <a href="{{ route('agrak.show', $bin->id) }}"
                                                               class="px-3 py-1.5 rounded-lg bg-gray-100 text-xs">
                                                                Ver bin
                                                            </a>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="6" class="text-center text-gray-500 py-4">
                                                            Sin bins
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="p-4 text-sm text-gray-500">
                                No se detectaron viajes
                            </div>
                        @endforelse
                    </div>
                </div>
            @empty
                <div class="text-sm text-gray-500">Sin resultados</div>
            @endforelse

            <div>
                {{ $groups->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
