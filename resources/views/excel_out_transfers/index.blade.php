<x-app-layout>

    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">
                Excel OUT Transfers
            </div>

            <form method="GET" class="flex items-center gap-2">
                <input name="q" value="{{ $q }}" placeholder="Buscar contacto / guía / patente..."
                    class="w-72 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 text-sm">

                <select name="exists"
                    class="rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 text-sm">
                    <option value="" {{ ($exists ?? '') === '' ? 'selected' : '' }}>Todos</option>
                    <option value="1" {{ ($exists ?? '') === '1' ? 'selected' : '' }}>Con match</option>
                    <option value="0" {{ ($exists ?? '') === '0' ? 'selected' : '' }}>Sin match</option>
                </select>

                <button class="px-4 py-2 rounded-xl bg-gray-200 dark:bg-gray-700 text-sm">Buscar</button>

                <a href="{{ route('excel_out_transfers.index') }}"
                    class="px-4 py-2 rounded-xl bg-white border border-gray-300 dark:border-gray-700 text-sm hover:bg-gray-50 dark:hover:bg-gray-900/40">
                    Limpiar
                </a>
            </form>
            <a href="{{ route('excel_out_transfers.export', request()->query()) }}"
                class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm hover:bg-emerald-700">
                Descargar Excel
            </a>
        </div>
    </x-slot>



    <div class="py-2">
        <div class="w-full px-4 sm:px-7 lg:px-9">

            @if(session('ok'))
                <div class="mb-12 rounded-xl bg-green-50 border border-green-200 p-3 text-green-800">
                    {{ session('ok') }}
                </div>
            @endif

            {{-- ✅ Stats PRO arriba de la tabla --}}
            <div class="hidden lg:block mb-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="mt-2 flex flex-wrap gap-2 text-xs">
                    <span class="px-2 py-1 rounded-full bg-gray-100 text-gray-700">
                        Total: <span class="font-semibold">{{ $total }}</span>
                    </span>
                    <span class="px-2 py-1 rounded-full bg-green-100 text-green-800">
                        Con match: <span class="font-semibold">{{ $matched }}</span>
                    </span>
                    <span class="px-2 py-1 rounded-full bg-gray-100 text-gray-700">
                        Sin match: <span class="font-semibold">{{ $unmatched }}</span>
                    </span>
                </div>
            </div>

            {{-- tabla (la tuya) --}}
            <div
                class="hidden lg:block rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-950 overflow-hidden">
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/60">
                            <tr class="[&>th]:px-4 [&>th]:py-3 [&>th]:text-left text-gray-700 dark:text-gray-200">
                                <th>Contacto</th>
                                <th>Fecha prevista</th>
                                <th>Patente</th>
                                <th>Nombre Chofer</th>
                                <th>Guía entrega</th>
                                <th>Referencia</th>
                                <th>Detalles</th>
                                {{-- Ordenar por match --}}
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">
                                    @php
                                        $isActive = $orderBy === 'exists_guia';
                                        $nextDir = ($isActive && $dir === 'asc') ? 'desc' : 'asc';
                                    @endphp

                                    <a href="{{ request()->fullUrlWithQuery(['order_by' => 'exists_guia', 'dir' => $nextDir]) }}"
                                        class="inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-gray-100">
                                        Existe Guia
                                        <svg class="w-3 h-3 opacity-60" xmlns="http://www.w3.org/2000/svg" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            @if($isActive && $dir === 'asc')
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M5 15l7-7 7 7" />
                                            @else
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 9l-7 7-7-7" />
                                            @endif
                                        </svg>
                                    </a>
                                </th>

                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($rows as $r)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                                    <td class="px-4 py-3">{{ $r->contacto ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $r->fecha_prevista?->format('Y-m-d H:i:s') ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $r->patente ?? '—' }}</td>
                                    <td class="px-4 py-3 uppercase">{{ $r->chofer ?? '—' }}</td>
                                    <td class="px-4 py-3 font-mono font-semibold">
                                        {{ $r->guia_entrega ?? '—' }}
                                    </td>

                                    <td class="px-4 py-3">{{ $r->referencia ?? '—' }}</td>


                                    <td class="px-4 py-3">
                                        <a href="{{ route('excel_out_transfers.show', $r) }}"
                                            class="inline-flex items-center px-3 py-1.5 rounded-lg
                                                                                                                         hover:bg-indigo-700 transition">
                                            Ver detalle
                                        </a>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if((int) ($r->exists_guia ?? 0) === 1)
                                            <div class="flex items-center gap-2">
                                                <span
                                                    class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-green-100 text-green-800">
                                                    ✔ Match
                                                </span>
                                                <a class="text-xs text-green-700 hover:underline"
                                                    href="{{ route('pdf.index', ['q' => $r->guia_entrega]) }}">
                                                    Ver PDF
                                                </a>
                                            </div>
                                        @else
                                            <span
                                                class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-600">
                                                —
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-10 text-center text-gray-500">
                                        No hay registros.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                    </table>
                </div>

            </div>

            {{-- Pagination --}}
            {{-- Esto evita que Turbo/SPA te "recicle" el DOM y se quede pegado con los mismos rows --}}
            <div class="hidden lg:block mt-4" data-turbo="false">
                {{ $rows->links() }}
            </div>


            {{-- Import report (si existe) --}}
            @if(session('import_report'))
                <div class="mt-6 p-4 rounded-xl border bg-white dark:bg-gray-950 dark:border-gray-700">
                    <div class="font-semibold text-gray-800 dark:text-gray-100 mb-2">Detalle de importación</div>

                    <div class="overflow-auto border rounded-lg dark:border-gray-700">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-gray-700 dark:bg-gray-900/60 dark:text-gray-200">
                                <tr class="text-left">
                                    <th class="p-2">Archivo</th>
                                    <th class="p-2 w-20">Estado</th>
                                    <th class="p-2 w-24">Guía</th>
                                    <th class="p-2">Detalle</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y dark:divide-gray-700">
                                @foreach(session('import_report') as $r)
                                    @php $st = $r['status'] ?? ''; @endphp
                                    <tr>
                                        <td class="p-2">{{ $r['file'] ?? '—' }}</td>
                                        <td class="p-2">
                                            <span
                                                class="px-2 py-0.5 rounded text-xs
                                                                                                                                                                                                                                                                                {{ $st === 'imported' ? 'bg-green-100 text-green-800' : '' }}
                                                                                                                                                                                                                                                                                {{ $st === 'duplicate' ? 'bg-amber-100 text-amber-900' : '' }}
                                                                                                                                                                                                                                                                                {{ $st === 'skip' ? 'bg-gray-100 text-gray-700' : '' }}">
                                                {{ $st }}
                                            </span>
                                        </td>
                                        <td class="p-2 font-semibold">{{ $r['guia'] ?? '—' }}</td>
                                        <td class="p-2 text-gray-600 dark:text-gray-300">{{ $r['reason'] ?? '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

        </div>
    </div>


    <div class="sm:hidden max-w-7xl mx-auto px-4 py-4 space-y-6">

        {{-- STATS --}}
        <div class="flex flex-wrap gap-2 text-xs">
            <span class="px-2 py-1 rounded-full bg-gray-100">
                Total: <b>{{ $total }}</b>
            </span>
            <span class="px-2 py-1 rounded-full bg-green-100 text-green-800">
                Con match: <b>{{ $matched }}</b>
                ({{ round($matched / max($total, 1) * 100) }}%)
            </span>
            <span class="px-2 py-1 rounded-full bg-red-100 text-red-700">
                Sin match: <b>{{ $unmatched }}</b>
            </span>
        </div>

        {{-- MOBILE --}}
        <div class="sm:hidden space-y-3">
            @foreach($rows as $r)
                <div class="border rounded-lg p-3 bg-white dark:bg-gray-900">
                    <p class="font-semibold text-sm">
                        {{ $r->contacto ?? '—' }}
                    </p>

                    <div class="text-xs text-gray-500 space-y-1 mt-1">
                        <div>Guía: <b>{{ $r->guia_entrega ?? '—' }}</b></div>
                        <div>Patente: {{ $r->patente ?? '—' }}</div>
                        <div>
                            Estado:
                            @if($r->exists_guia)
                                <span class="text-green-600 font-medium">✔ Match</span>
                            @else
                                <span class="text-red-600 font-medium">✖ Sin guía</span>
                            @endif
                        </div>
                    </div>

                    <a href="{{ route('excel_out_transfers.show', $r) }}"
                        class="inline-block mt-2 text-xs text-indigo-600 hover:underline">
                        Ver detalle →
                    </a>
                </div>
            @endforeach
        </div>

        {{-- DESKTOP TABLE --}}
        <div class="hidden lg:block rounded-xl border bg-white dark:bg-gray-950 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/60">
                        <tr class="text-gray-600 dark:text-gray-300">
                            <th class="px-4 py-3 text-left">Contacto</th>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Patente</th>
                            <th class="px-4 py-3">Chofer</th>
                            <th class="px-4 py-3">Guía</th>
                            <th class="px-4 py-3">Referencia</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>

                    <tbody class="divide-y dark:divide-gray-700">
                        @foreach($rows as $r)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                                <td class="px-4 py-3">{{ $r->contacto ?? '—' }}</td>

                                <td class="px-4 py-3 text-xs text-gray-500">
                                    {{ $r->fecha_prevista?->format('d-m-Y') }}<br>
                                    <span class="opacity-60">{{ $r->fecha_prevista?->format('H:i') }}</span>
                                </td>

                                <td class="px-4 py-3">{{ $r->patente ?? '—' }}</td>
                                <td class="px-4 py-3 uppercase">{{ $r->chofer ?? '—' }}</td>

                                <td class="px-4 py-3 font-mono font-semibold text-indigo-600">
                                    {{ $r->guia_entrega ?? '—' }}
                                </td>

                                <td class="px-4 py-3">{{ $r->referencia ?? '—' }}</td>

                                <td class="px-4 py-3">
                                    @if($r->exists_guia)
                                        <span class="px-2 py-1 rounded-full text-xs bg-green-100 text-green-800">
                                            ✔ Match
                                        </span>
                                    @else
                                        <span class="px-2 py-1 rounded-full text-xs bg-red-100 text-red-700">
                                            ✖ Sin guía
                                        </span>
                                    @endif
                                </td>

                                <td class="px-4 py-3">
                                    <a href="{{ route('excel_out_transfers.show', $r) }}"
                                        class="text-indigo-600 text-sm hover:underline">
                                        Ver
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- PAGINACIÓN --}}
        <div class="sm:hidden" data-turbo="false">
            {{ $rows->links() }}
        </div>

    </div>

</x-app-layout>
