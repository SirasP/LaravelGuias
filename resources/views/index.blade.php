<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                Dashboard Inventario
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Resumen últimos 5 días
            </p>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 py-4 space-y-6">

        {{-- KPI 5 DÍAS --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
            <p class="text-sm text-gray-500">Total enviado últimos 10 días</p>
            <p class="text-2xl font-bold text-green-600">
                {{ $kpi5Dias }} kg
            </p>
        </div>

        {{-- GRÁFICO --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
            <h3 class="font-semibold mb-3">
                Kilos enviados — últimos 5 días
            </h3>

            <div class="relative h-48">
                <canvas id="kilosChart"></canvas>
            </div>

            @if (empty($chartLabels) || count($chartLabels) === 0)
                <p class="text-sm text-gray-500 mt-3">
                    No hay datos para los últimos 5 días.
                </p>
            @endif
        </div>

        {{-- TABLA RESUMEN --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
            <h3 class="font-semibold mb-3">Detalle por producto</h3>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-gray-500 border-b">
                        <tr>
                            <th class="text-left py-2">Producto</th>
                            <th class="text-right py-2">Kilos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($productos as $p)
                            <tr class="border-b last:border-0">
                                <td class="py-2">{{ $p->producto }}</td>
                                <td class="py-2 text-right font-medium">
                                    {{ $p->total_kilos }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="py-4 text-center text-gray-500">
                                    Sin registros
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    {{-- Chart.js --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>

    <script>
document.addEventListener('DOMContentLoaded', function () {

    const ctx = document.getElementById('kilosChart');
    if (!ctx) return;

    const labels = @json($chartLabels ?? []);
    const dataValues = @json($chartData ?? []);

    if (!labels.length || !dataValues.length) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Kilos enviados',
                data: dataValues,
                backgroundColor: '#3b82f6',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            const v = ctx.parsed.y ?? 0;
                            return v.toLocaleString('es-CL', {
                                minimumFractionDigits: 3,
                                maximumFractionDigits: 3
                            }) + ' kg';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function (value) {
                            return value.toLocaleString('es-CL', {
                                minimumFractionDigits: 3,
                                maximumFractionDigits: 3
                            }) + ' kg';
                        }
                    }
                }
            }
        }
    });
});
</script>

</x-app-layout>
