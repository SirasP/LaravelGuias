<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                Dashboard Inventario
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Resumen últimos 40 días
            </p>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 py-4 space-y-6">

        {{-- KPI --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
            <p class="text-sm text-gray-500">Total enviado últimos 40 días</p>
            <p class="text-2xl font-bold text-green-600">
                @php
    $kpi = (float) $kpi5Dias;

    $kpiFormatted = $kpi == floor($kpi)
        ? number_format($kpi, 1, ',', '.')   // entero → 1 decimal
        : number_format($kpi, 2, ',', '.');  // decimal → 2 decimales
@endphp

{{ $kpiFormatted }} kg
            </p>
        </div>
{{-- KPI CENTROS --}}
<div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
    <p class="text-sm text-gray-500">Total informado por centros (últimos 40 días)</p>
    <p class="text-2xl font-bold text-indigo-600">
        @php
            $kpiC = (float) $kpiCentros;

            $kpiCFormatted = $kpiC == floor($kpiC)
                ? number_format($kpiC, 1, ',', '.')
                : number_format($kpiC, 2, ',', '.');
        @endphp

        {{ $kpiCFormatted }} kg
    </p>
</div>

        {{-- GRÁFICO --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
            <h3 class="font-semibold mb-3">
                Kilos enviados — últimos 40 días
            </h3>

            <div class="relative h-48">
                <canvas id="kilosChart"></canvas>
            </div>

            @if (empty($chartLabels) || count($chartLabels) === 0)
                <p class="text-sm text-gray-500 mt-3">
                    No hay datos para los últimos 40 días.
                </p>
            @endif
        </div>
{{-- GRÁFICO CENTROS --}}
<div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
    <h3 class="font-semibold mb-3">
        Kilos informados por centros — últimos 40 días
    </h3>

    <div class="relative h-48">
        <canvas id="centrosChart"></canvas>
    </div>

    @if (empty($centrosLabels) || count($centrosLabels) === 0)
        <p class="text-sm text-gray-500 mt-3">
            
        </p>
    @endif
</div>

      

    </div>

    {{-- Chart.js --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>

    <script>
    
function formatCL(value) {
    const n = Number(value);
    if (isNaN(n)) return value;

    // 1 decimal si es entero, hasta 2 si viene con decimales
    const str = n % 1 === 0
        ? n.toFixed(1)
        : n.toFixed(2);

    // punto → coma
    return str.replace('.', ',');
}


    document.addEventListener('DOMContentLoaded', function () {

        const ctx = document.getElementById('kilosChart');
        if (!ctx) return;

        const labels = @json($chartLabels ?? []);
        const dataValues = @json($chartData ?? []).map(Number);

        // DEBUG (puedes borrar después)
        console.log(dataValues);

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
                                return formatCL(ctx.parsed.y) + ' kg';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return formatCL(value) + ' kg';
                            }
                        }
                    }
                }
            }
        });
    });
    </script>
   <script>
function formatCL(value) {
    const n = Number(value);
    if (isNaN(n)) return value;

    const str = n % 1 === 0
        ? n.toFixed(1)
        : n.toFixed(2);

    return str.replace('.', ',');
}

document.addEventListener('DOMContentLoaded', function () {

    const ctx2 = document.getElementById('centrosChart');
    if (!ctx2) return;

    // 🔥 USAR LAS MISMAS FECHAS DEL DASHBOARD
    const labels = @json($chartLabels ?? []);
    const data = @json($centrosData ?? []).map(Number);

    console.log('CENTROS:', data);

    if (!labels.length || !data.length) return;

    new Chart(ctx2, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Kilos informados por centros',
                data: data,
                backgroundColor: '#6366f1',
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
                            return formatCL(ctx.parsed.y) + ' kg';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function (value) {
                            return formatCL(value) + ' kg';
                        }
                    }
                }
            }
        }
    });
});
</script>


</x-app-layout>