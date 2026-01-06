<x-app-layout>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between">
            <div class="space-y-1">
                <nav class="text-xs text-gray-500">
                    <a href="{{ route('excel_out_transfers.index') }}" class="hover:underline">
                        Excel OUT Transfers
                    </a>
                    <span class="mx-1">/</span>
                    <span class="text-gray-700">Detalle</span>
                </nav>

                <div class="text-lg font-semibold font-mono">
                    Guía {{ $transfer->guia_entrega }}
                </div>
            </div>

            <a href="{{ route('excel_out_transfers.index') }}" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg
              border border-gray-300 bg-white
              text-sm text-gray-600 hover:bg-gray-50 transition">
                ← Volver
            </a>
        </div>

    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 space-y-6">

            {{-- Cabecera --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="rounded-xl border p-4 bg-white">
                    <div class="text-xs text-gray-500">Contacto</div>
                    <div class="font-medium">{{ $transfer->contacto ?? '—' }}</div>
                </div>

                <div class="rounded-xl border p-4 bg-white">
                    <div class="text-xs text-gray-500">Chofer</div>
                    <div class="font-medium">{{ $transfer->chofer ?? '—' }}</div>
                </div>

                <div class="rounded-xl border p-4 bg-white">
                    <div class="text-xs text-gray-500">Patente</div>
                    <div class="font-medium">{{ $transfer->patente ?? '—' }}</div>
                </div>

                <div class="rounded-xl border p-4 bg-white">
                    <div class="text-xs text-gray-500">Referencia</div>
                    <div class="font-medium">{{ $transfer->referencia ?? '—' }}</div>
                </div>
            </div>

            {{-- Tabla de ítems --}}
            <div class="rounded-xl border overflow-hidden bg-white">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr class="[&>th]:px-4 [&>th]:py-3 text-left">
                            <th>Producto</th>
                            <th class="w-32 text-right">Cantidad</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y">
                        @forelse($transfer->lines as $line)
                            <tr>
                                <td class="px-4 py-3">
                                    {{ $line->producto ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono">
                                    {{ number_format((float) $line->cantidad, 3, ',', '.') }}

                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-4 py-6 text-center text-gray-500">
                                    Sin ítems
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</x-app-layout>