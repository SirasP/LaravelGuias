<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div class="text-sm text-gray-600">
                Guía COMFRUT #{{ $guia->guia_numero }}
            </div>

            <a href="{{ route('guias.comfrut.index') }}"
                class="px-4 py-2 rounded-xl bg-white border text-sm hover:bg-gray-50">
                ← Volver
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="w-full px-4 sm:px-7 lg:px-9 space-y-6">

            {{-- ===================== --}}
            {{-- Datos principales --}}
            {{-- ===================== --}}
            <div class="rounded-xl border bg-white p-6">
                <h3 class="font-semibold text-gray-800 mb-4">Datos de la guía</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                    <div>
                        <div class="text-gray-500">Guía</div>
                        <div class="font-mono font-semibold">{{ $guia->guia_numero }}</div>
                    </div>

                    <div>
                        <div class="text-gray-500">Fecha emisión</div>
                        <div>{{ $guia->fecha_guia?->format('d-m-Y') ?? '—' }}</div>
                    </div>

                    <div>
                        <div class="text-gray-500">Tipo DTE</div>
                        <div>{{ $guia->tipo_dte }}</div>
                    </div>

                    <div>
                        <div class="text-gray-500">Productor</div>
                        <div>{{ $guia->productor ?? '—' }}</div>
                    </div>

                    <div>
                        <div class="text-gray-500">RUT Productor</div>
                        <div>{{ $guia->rut_productor ?? '—' }}</div>
                    </div>

                    <div>
                        <div class="text-gray-500">Patente</div>
                        <div class="uppercase">{{ $guia->patente ?? '—' }}</div>
                    </div>
                </div>
            </div>

            {{-- ===================== --}}
            {{-- Totales --}}
            {{-- ===================== --}}
            <div class="rounded-xl border bg-white p-6">
                <h3 class="font-semibold text-gray-800 mb-4">Totales</h3>

                <div class="flex flex-wrap gap-4 text-sm">
                    <span class="px-3 py-2 rounded-lg bg-gray-100">
                        Cantidad total:
                        <span class="font-semibold">
                            {{ number_format($guia->cantidad_total, 2, ',', '.') }}
                        </span>
                    </span>

                    <span class="px-3 py-2 rounded-lg bg-gray-100">
                        Monto total:
                        <span class="font-semibold">
                            ${{ number_format($guia->monto_total, 0, ',', '.') }}
                        </span>
                    </span>

               
                </div>
            </div>

            {{-- ===================== --}}
            {{-- Detalle --}}
            {{-- ===================== --}}
            <div class="rounded-xl border bg-white overflow-hidden">
                <div class="px-6 py-4 border-b">
                    <h3 class="font-semibold text-gray-800">Detalle de productos</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-700">
                            <tr class="[&>th]:px-4 [&>th]:py-3 [&>th]:text-left">
                                <th>#</th>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Unidad</th>
                                <th>Precio</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y">
                            @forelse($guia->detalles as $d)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2">{{ $d->linea }}</td>
                                    <td class="px-4 py-2 font-mono">
                                        {{ $d->codigo_tipo }} : {{ $d->codigo_valor }}
                                    </td>

                                    <td class="px-4 py-2">
                                        {{ $d->nombre_item }}
                                    </td>

                                    <td class="px-4 py-2">
                                        {{ number_format($d->cantidad, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-2">{{ $d->unidad }}</td>
                                    <td class="px-4 py-2 ">
                                        {{ number_format($d->precio, 2, ',', '.') }}
                                    </td>
                                   
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                        Sin detalle.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>