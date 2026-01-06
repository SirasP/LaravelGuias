<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">
                Agrak
            </div>

            <form method="GET" action="{{ route('agrak.index') }}" class="flex gap-2">
                <input name="q" value="{{ $q }}" placeholder="Buscar: bin, campo, chofer, patente, usuario..."
                    class="w-96 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 text-sm">
                <button class="px-4 py-2 rounded-xl bg-gray-200 dark:bg-gray-700 text-sm">Buscar</button>
            </form>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto px-4">

            @if(session('ok'))
                <div class="mb-4 rounded-xl bg-green-50 border border-green-200 p-3 text-green-800">
                    {{ session('ok') }}
                </div>
            @endif

            {{-- filtros rápidos --}}
            <form method="GET" action="{{ route('agrak.index') }}" class="mb-4 flex flex-wrap gap-2">
                <input type="hidden" name="q" value="{{ $q }}">
                <input type="hidden" name="order_by" value="{{ $orderBy }}">
                <input type="hidden" name="dir" value="{{ $dir }}">
                <a href="{{ route('agrak.index', array_merge(request()->all(), ['view' => 'group'])) }}"
                    class="px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm">
                    Ver por camión
                </a>
                <select name="campo"
                    class="rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 text-sm">
                    <option value="">Campo (todos)</option>
                    @foreach($campos as $c)
                        <option value="{{ $c }}" @selected($campo === $c)>{{ $c }}</option>
                    @endforeach
                </select>

                <select name="cuartel"
                    class="rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 text-sm">
                    <option value="">Cuartel (todos)</option>
                    @foreach($cuarteles as $c)
                        <option value="{{ $c }}" @selected($cuartel === $c)>{{ $c }}</option>
                    @endforeach
                </select>

                <select name="especie"
                    class="rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 text-sm">
                    <option value="">Especie (todas)</option>
                    @foreach($especies as $e)
                        <option value="{{ $e }}" @selected($especie === $e)>{{ $e }}</option>
                    @endforeach
                </select>

                <button class="px-4 py-2 rounded-xl bg-gray-200 dark:bg-gray-700 text-sm">Filtrar</button>
                <a class="px-4 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-sm"
                    href="{{ route('agrak.index') }}">Limpiar</a>
            </form>

            <div
                class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900 text-gray-600 dark:text-gray-300">
                        <tr>
                            <th class="px-3 py-2 text-left">ID</th>
                            <th class="px-3 py-2 text-left">Bin</th>
                            <th class="px-3 py-2 text-left">Cuartel</th>


                            <th class="px-3 py-2 text-left">Fecha</th>
                            <th class="px-3 py-2 text-left">Bandejas</th>
                            <th class="px-3 py-2 text-left">Máquina</th>
                            <th class="px-3 py-2 text-left">Chofer</th>
                            <th class="px-3 py-2 text-left">Patente</th>
                            <th class="px-3 py-2 text-left">Exportadora</th>

                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($items as $it)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-900">
                                <td class="px-3 py-2">{{ $it->id }}</td>
                                <td class="px-3 py-2 font-semibold">{{ $it->codigo_bin }}</td>

                                <td class="px-3 py-2">{{ $it->cuartel }}</td>

                                <td class="px-3 py-2 whitespace-nowrap">
                                    {{ optional(\Carbon\Carbon::parse($it->fecha_registro))->format('d-m-Y') }}
                                    {{ $it->hora_registro }}
                                </td>

                                <td class="px-3 py-2">{{ $it->numero_bandejas_palet }}</td>
                                <td class="px-3 py-2">{{ $it->maquina }}</td>
                                <td class="px-3 py-2">{{ $it->nombre_chofer }}</td>
                                <td class="px-3 py-2">{{ $it->patente_camion }}</td>
                                <td class="px-3 py-2">{{ $it->exportadora_1 ?? $it->exportadora_2 }}</td>
                                <td class="px-3 py-2 text-right">
                                    <a class="px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 text-sm"
                                        href="{{ route('agrak.show', $it->id) }}">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="15" class="px-3 py-6 text-center text-gray-500">Sin resultados</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $items->links() }}
            </div>

        </div>
    </div>
</x-app-layout>