<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                Dashboard Inventario
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Accesos principales del sistema
            </p>
        </div>
    </x-slot>

    {{-- CONTENEDOR QUE ROMPE EL min-h-screen --}}
    <div class="max-w-7xl mx-auto px-4 py-2">

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
            <h3 class="text-base font-semibold mb-2">
                Kilos enviados (últimos 7 días)
            </h3>

            <div class="relative h-40">
                <canvas id="kilosChart"></canvas>
            </div>
        </div>

    </div>



    {{-- Chart.js --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const canvas = document.getElementById('kilosChart');
            if (!canvas) return;

            const labels = @json($chartLabels ?? []);
            const dataValues = @json($chartData ?? []);

            if (!labels.length || !dataValues.length) return;

            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Kilos enviados',
                        data: dataValues,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: ctx => `${ctx.parsed.y} kg`
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: value => value + ' kg'
                            }
                        }
                    }
                }
            });

        });
    </script>

</x-app-layout>
