<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-3">
            <div>
                <div class="text-sm text-gray-600 dark:text-gray-400">DTE (vista)</div>
                <div class="text-base font-semibold text-gray-900 dark:text-gray-100">
                    {{ $filename ?? '' }}
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a class="px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-sm hover:bg-gray-50 dark:hover:bg-gray-900/40"
                    href="{{ url()->previous() }}">
                    Volver
                </a>

                <a class="px-3 py-2 rounded-xl bg-blue-600 text-white text-sm hover:bg-blue-700"
                    href="{{ route('inventario.dte.leer', ['id' => $messageId]) }}" target="_blank">
                    Ver XML
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4">
            <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-950 p-6">

                {{-- Meta --}}
                <div
                    class="mb-5 rounded-xl bg-gray-50 dark:bg-gray-900/50 border border-gray-200/70 dark:border-gray-700 p-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs text-gray-600 dark:text-gray-300">
                        <div class="truncate">
                            <div class="text-gray-500">Asunto</div>
                            <div class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $subject }}</div>
                        </div>

                        <div class="truncate">
                            <div class="text-gray-500">Desde</div>
                            <div class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $from }}</div>
                        </div>

                        <div>
                            <div class="text-gray-500">Fecha</div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ $date }}</div>
                        </div>
                    </div>
                </div>

                @if(empty($datos['ok']))
                    <div class="rounded-xl bg-red-50 border border-red-200 p-4 text-red-800">
                        <div class="font-semibold mb-1">No se pudo leer el DTE</div>
                        <div class="text-sm">XML inválido.</div>
                        @if(!empty($datos['error']))
                            <div class="text-sm mt-2">{{ $datos['error'] }}</div>
                        @endif
                    </div>
                @else
                    {{-- Cards --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-950 p-4">
                            <div class="font-semibold mb-3">Documento</div>
                            <div class="space-y-1 text-gray-700 dark:text-gray-200">
                                <div class=" justify-between gap-3">
                                    <span class="text-gray-500">Tipo</span>
                                    <span class="font-medium">{{ $datos['dte']['tipoDte'] ?? '' }}</span>
                                </div>
                                <div class=" justify-between gap-3">
                                    <span class="text-gray-500">Folio</span>
                                    <span class="font-medium">{{ $datos['dte']['folio'] ?? '' }}</span>
                                </div>
                                <div class=" justify-between gap-3">
                                    <span class="text-gray-500">Emisión</span>
                                    <span class="font-medium">{{ $datos['dte']['fchEmis'] ?? '' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-950 p-4">
                            <div class="font-semibold mb-3">Totales</div>
                            <div class="space-y-1 text-gray-700 dark:text-gray-200">
                                <div class=" justify-between gap-3">
                                    <span class="text-gray-500">Neto</span>
                                    <span class="font-medium tabular-nums">
                                        {{ number_format((int) ($datos['totales']['MntNeto'] ?? 0), 0, ',', '.') }}
                                    </span>
                                </div>
                                <div class=" justify-between gap-3">
                                    <span class="text-gray-500">IVA</span>
                                    <span class="font-medium tabular-nums">
                                        {{ number_format((int) ($datos['totales']['IVA'] ?? 0), 0, ',', '.') }}
                                    </span>
                                </div>
                                <div class="pt-2 mt-2 border-t border-gray-200 dark:border-gray-700  justify-between gap-3">
                                    <span class="text-gray-500">Total</span>
                                    <span class="font-medium text-base tabular-nums">
                                        {{ number_format((int) ($datos['totales']['MntTotal'] ?? 0), 0, ',', '.') }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-950 p-4">
                            <div class="font-semibold mb-3">Emisor</div>
                            <div class="space-y-1 text-gray-700 dark:text-gray-200">
                                <div class=" justify-between gap-3">
                                    <span class="text-gray-500">RUT</span>
                                    <span class="font-medium">{{ $datos['emisor']['RUTEmisor'] ?? '' }}</span>
                                </div>
                                <div class="text-gray-500 text-xs mt-2">Razón social</div>
                                <div class="font-medium text-gray-900 dark:text-gray-100 break-words">
                                    {{ $datos['emisor']['RznSoc'] ?? '' }}
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-950 p-4">
                            <div class="font-semibold mb-3">Receptor</div>
                            <div class="space-y-1 text-gray-700 dark:text-gray-200">
                                <div class=" justify-between gap-3">
                                    <span class="text-gray-500">RUT</span>
                                    <span class="font-medium">{{ $datos['receptor']['RUTRecep'] ?? '' }}</span>
                                </div>
                                <div class="text-gray-500 text-xs mt-2">Razón social</div>
                                <div class="font-medium text-gray-900 dark:text-gray-100 break-words">
                                    {{ $datos['receptor']['RznSocRecep'] ?? '' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Items --}}
                    <div class="mt-6">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="font-semibold">Ítems</h3>
                            <div class="text-xs text-gray-500">
                                {{ count($datos['items'] ?? []) }} líneas
                            </div>
                        </div>

                        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                            <table class="w-full table-fixed text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-900/60">
                                    <tr class="[&>th]:px-3 [&>th]:py-2 [&>th]:text-left">
                                        <th class="w-12">#</th>
                                        <th class="w-[55%]">Ítem</th>
                                        <th class="w-24 text-right">Cant.</th>
                                        <th class="w-20">Un.</th>
                                        <th class="w-28 text-right">Precio</th>
                                        <th class="w-32 text-right">Monto</th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @forelse(($datos['items'] ?? []) as $it)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                                            <td class="px-3 py-2 text-gray-500 tabular-nums">{{ $it['NroLinDet'] }}</td>

                                            <td class="px-3 py-2 max-w-[520px]">
                                                <div class="font-medium text-gray-900 dark:text-gray-100">
                                                    {{ $it['NmbItem'] ?: '-' }}
                                                </div>

                                                @if(!empty($it['DscItem']))
                                                    <div class="text-xs text-gray-500 mt-0.5">
                                                        {{ $it['DscItem'] }}
                                                    </div>
                                                @endif

                                                @if(!empty($it['Codigos']))
                                                    <div class="text-xs text-gray-500 mt-0.5">
                                                        Códigos: {{ implode(', ', $it['Codigos']) }}
                                                    </div>
                                                @endif
                                            </td>

                                            <td class="px-3 py-2 text-left tabular-nums">
                                                @php $qty = $it['QtyItem']; @endphp
                                                {{ $qty !== '' ? rtrim(rtrim(number_format((float) $qty, 3, ',', '.'), '0'), ',') : '' }}
                                            </td>

                                            <td class="px-3 py-2 text-gray-700 dark:text-gray-200">
                                                {{ $it['UnmdItem'] }}
                                            </td>

                                            <td class="px-3 py-2 text-left tabular-nums">
                                                {{ $it['PrcItem'] !== '' ? number_format((float) $it['PrcItem'], 0, ',', '.') : '' }}
                                            </td>

                                            <td class="px-3 py-2 text-left font-semibold tabular-nums">
                                                {{ $it['MontoItem'] !== '' ? number_format((float) $it['MontoItem'], 0, ',', '.') : '' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-3 py-10 text-center text-gray-500">
                                                No hay ítems en este DTE.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>