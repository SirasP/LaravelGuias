<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                Centro: {{ $contacto }}
            </h2>
            <p class="text-sm text-gray-500">
                Detalle de guías y productos enviados de centros a EHE
            </p>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 py-6 space-y-6">

       {{-- 📄 GUÍAS POR CENTRO --}}
<div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 space-y-4">

    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold">
            Guías por centro
        </h3>
        <span class="text-xs text-gray-500">
            Centro: {{ $contacto }}
        </span>
    </div>

    {{-- KPIs --}}
<div class="flex flex-wrap gap-4">

    <div class="flex-1 min-w-[220px] rounded-lg bg-gray-50 dark:bg-gray-700 p-4">
        <p class="text-xs text-gray-500 uppercase">Total guías</p>
        <p class="text-3xl font-bold">
            {{ $guias->count() }}
        </p>
    </div>

    <div class="flex-1 min-w-[220px] rounded-lg bg-gray-50 dark:bg-gray-700 p-4">
        <p class="text-xs font-bold text-blue-700 uppercase">Bandejas (guías)</p>
        <p class="text-3xl font-bold text-blue-700">
            {{ number_format($totalBandejas, 0, ',', '.') }}
        </p>
    </div>

    <div class="flex-1 min-w-[220px] rounded-lg bg-gray-50 dark:bg-gray-700 p-4">
        <p class="text-xs text-gray-500 uppercase">Pallets</p>
        <p class="text-3xl font-bold">
            {{ number_format($totalPallets, 0, ',', '.') }}
        </p>
    </div>

    <div class="flex-1 min-w-[220px] rounded-lg bg-gray-50 dark:bg-gray-700 p-4">
        <p class="text-xs text-green-700 uppercase">Monto total</p>
        <p class="text-3xl font-bold text-green-700">
            {{ number_format($guias->sum('monto_total'), 0, ',', '.') }}
        </p>
    </div>

</div>



    {{-- Productos --}}
    <div class="pt-4">
        <h4 class="font-semibold mb-2">Productos en guías</h4>

        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 border-b">
                    <th class="py-2">Producto</th>
                    <th class="py-2 text-right">Cantidad</th>
                </tr>
            </thead>
            <tbody>
                @foreach($productos as $p)
                    <tr class="border-b last:border-0">
                        <td class="py-2">{{ $p->nombre_item }}</td>
                        <td class="py-2 text-right font-medium">
                            {{ number_format($p->total_unidades, 0, ',', '.') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>
{{-- 🚚 SALIDAS REALES (EXCEL OUT TRANSFERS) --}}
<div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 space-y-4">

    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold">
            Salidas reales de bandejas
        </h3>
        <span class="text-xs text-gray-500">
            Fuente: ODOO
        </span>
    </div>

    {{-- KPIs --}}
<div class="flex flex-wrap gap-4">

    <div class="flex-1 min-w-[220px] rounded-lg bg-gray-50 dark:bg-gray-700 p-4">
        <p class="text-xs text-blue-700 uppercase">Bandejas enviadas</p>
        <p class="text-3xl font-bold text-blue-700">
            {{ number_format($totalBandejasOut ?? 0, 0, ',', '.') }}
        </p>
    </div>

    <div class="flex-1 min-w-[220px] rounded-lg bg-gray-50 dark:bg-gray-700 p-4">
        <p class="text-xs text-gray-500 uppercase">Tipos de bandeja</p>
        <p class="text-3xl font-bold">
            {{ $bandejasPorTipo->count() }}
        </p>
    </div>

    <div class="flex-1 min-w-[220px] rounded-lg bg-gray-50 dark:bg-gray-700 p-4">
        <p class="text-xs text-gray-500 uppercase">Último traslado</p>
        <p class="text-xl font-semibold">
            @if(!empty($bandejasPorTransfer) && $bandejasPorTransfer->count() > 0)
                {{ \Carbon\Carbon::parse($bandejasPorTransfer->first()->fecha_traslado)->format('d-m-Y') }}
            @else
                —
            @endif
        </p>
    </div>

</div>



    {{-- Tabla por tipo --}}
    <div class="pt-4">
        <h4 class="font-semibold mb-2">Bandejas por tipo</h4>

        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 border-b">
                    <th class="py-2">Tipo bandeja</th>
                    <th class="py-2 text-right">Cantidad</th>
                </tr>
            </thead>
            <tbody>
                @forelse($bandejasPorTipo as $b)
                    <tr class="border-b last:border-0">
                        <td class="py-2">{{ $b->tipo_bandeja }}</td>
                        <td class="py-2 text-right font-medium">
                            {{ number_format($b->total_bandejas, 0, ',', '.') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="py-4 text-center text-gray-500">
                            Sin registros de salida
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>



    </div>
</x-app-layout>