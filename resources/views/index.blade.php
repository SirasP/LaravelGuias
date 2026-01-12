<script>
    const KG_PROMEDIO_URL = "{{ route('agrak.kg-promedio') }}";
</script>

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
        @php
            $kpi = (float) $kpi5Dias;
            $kpiFormatted = $kpi == floor($kpi)
                ? number_format($kpi, 1, ',', '.')
                : number_format($kpi, 2, ',', '.');
        @endphp
        {{-- KPI DOBLE --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
            <p class="text-sm text-gray-500 mb-3">
                Totales últimos 40 días ODOO
            </p>

            <div class="flex justify-between gap-8">
                {{-- KILOS --}}
                <div>
                    <p class="text-xs text-gray-400">Kilos Odoo</p>
                    <p class="text-2xl font-bold text-green-600">
                        {{ $kpiFormatted }} kg
                    </p>
                </div>

                {{-- BANDEJAS --}}
                <div class="text-right">
                    <p class="text-xs text-gray-400">Bandejas</p>
                    <p class="text-2xl font-bold text-green-600">
                        {{ number_format($kpiBandejas ?? 0, 0, ',', '.') }}
                    </p>
                </div>
            </div>


        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
            <p class="text-sm text-gray-500 mb-3">
                Totales últimos 40 días (AGRAK)
            </p>

            <div class="flex justify-between gap-10 items-end">
                {{-- KILOS --}}
                <div>
                    <p class="text-xs text-gray-400">Kilos AGRAK (estimado)</p>
                    <p id="kilosAgrak" class="text-2xl font-bold text-green-600">
                        —
                    </p>
                </div>

                {{-- BANDEJAS --}}
                <div class="text-right">
                    <p class="text-xs text-gray-400">Bandejas</p>
                    <p id="bandejasAgrak" class="text-2xl font-bold text-green-600">
                        {{ number_format($kpiBandejasAgrak ?? 0, 0, ',', '.') }}
                    </p>
                </div>

                {{-- BINS --}}
                <div class="text-right">
                    <p class="text-xs text-gray-400">Bins</p>
                    <p class="text-2xl font-bold text-green-600">
                        {{ number_format($kpiBinsAgrak ?? 0, 0, ',', '.') }}
                    </p>
                </div>
            </div>

            {{-- AJUSTE PROMEDIO --}}
            <div class="mt-5 border-t pt-4 flex items-center justify-between relative">
                {{-- VALOR ACTIVO --}}
                <div class="text-xs text-gray-500">
                    Peso promedio activo:
                    <span id="kgPromedioLabel" class="font-semibold text-gray-700 dark:text-gray-200">
                        2,5 kg / bandeja
                    </span>
                </div>

                {{-- BOTÓN --}}
                <button id="kgToggle" onclick="toggleKgPopover(event)"
                    class="text-xs text-blue-600 hover:text-blue-800 flex items-center gap-1">
                    ⚙️ Ajustar
                </button>

                {{-- POPOVER --}}
                <div id="kgPopover" class="absolute right-0 top-10 bg-white dark:bg-gray-800
               border dark:border-gray-700 rounded-lg shadow-lg
               w-64 p-4 hidden z-40">
                    <h4 class="text-xs font-semibold mb-2">
                        Peso promedio
                    </h4>

                    <p class="text-[11px] text-gray-500 mb-3">
                        Usado para estimar Kilos AGRAK
                    </p>

                    <div class="flex items-center gap-2 mb-3">
                        <input id="kgPromedio" type="number" step="0.1" min="0" value="{{ $kgPromedioAgrak }}" class="w-20 text-right px-2 py-1 border rounded-md text-sm
           dark:bg-gray-700 dark:border-gray-600">


                        <span class="font-semibold text-gray-700 dark:text-gray-200">
                            kg / bandeja
                        </span>
                    </div>

                    <div class="flex justify-end">
                        <button onclick="applyKgPromedio()" class="px-3 py-1 text-xs rounded bg-green-600 text-white
                       hover:bg-green-700">
                            Aplicar
                        </button>
                    </div>
                </div>
            </div>


        </div>

        @php
            $kpiC = (float) $kpiCentros;

            $kpiCFormatted = $kpiC == floor($kpiC)
                ? number_format($kpiC, 1, ',', '.')
                : number_format($kpiC, 2, ',', '.');
        @endphp

        {{-- KPI CENTROS --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
            <p class="text-sm text-gray-500 mb-3">Total informado por centros (últimos 40 días)</p>

            <div>
                <p class="text-xs text-gray-400">Kilos Recepcionados</p>
                <p class="text-2xl font-bold text-green-600">
                    {{ $kpiCFormatted }} kg
                </p>
            </div>
        </div>

<div class="sm:hidden space-y-3">
    @foreach($kilosPorContacto as $row)
        <div class="border rounded-lg p-3 bg-gray-50 dark:bg-gray-900">
            <p class="font-semibold text-sm mb-1">
                {{ $row->contacto }}
            </p>

            <div class="grid grid-cols-2 gap-y-1 text-xs">
                <div class="text-gray-500">Guías</div>
                <div class="text-right font-medium">
                    {{ $row->total_guias }}
                </div>

                <div class="text-gray-500">Sin respuesta</div>
                <div class="text-right">
                    {{ $row->guias_sin_match }}
                </div>

                <div class="text-gray-500">Bandejas ODOO</div>
                <div class="text-right font-medium">
                    {{ number_format($bandejasPorContacto[$row->contacto]->total_bandejas ?? 0, 0, ',', '.') }}
                </div>

                <div class="text-gray-500">Kilos</div>
                <div class="text-right font-medium text-green-600">
                    {{ number_format($row->total_kilos, 1, ',', '.') }} kg
                </div>
            </div>
        </div>
    @endforeach
</div>



<div class="hidden lg:block bg-white dark:bg-gray-800 rounded-xl shadow p-4">
    <h3 class="font-semibold mb-3">
        Empresas — Kilos informados por centros
    </h3>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 border-b">
                    <th class="py-2">Empresa</th>
                    <th class="py-2 text-right">Guías totales</th>
                    <th class="py-2 text-right">Guías sin respuesta</th>
                    <th class="py-2 text-right">Total bandejas ODOO</th>
                    <th class="py-2 text-right">Total kilos</th>
                </tr>
            </thead>

            <tbody>
                @foreach($kilosPorContacto as $row)
                    <tr class="border-b last:border-0">
                        <td class="py-2">{{ $row->contacto }}</td>
                        <td class="py-2 text-right font-medium">{{ $row->total_guias }}</td>
                        <td class="py-2 text-right">
                            @if($row->guias_sin_match > 0)
                                <span class="text-red-600 font-medium">{{ $row->guias_sin_match }}</span>
                            @else
                                <span class="text-gray-400">0</span>
                            @endif
                        </td>
                        <td class="py-2 text-right font-medium">
                            {{ number_format($bandejasPorContacto[$row->contacto]->total_bandejas ?? 0, 0, ',', '.') }}
                        </td>
                        <td class="py-2 text-right font-medium">
                            {{ number_format($row->total_kilos, 1, ',', '.') }} kg
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
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
        {{-- GRÁFICO CONTACTOS --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
            <h3 class="font-semibold mb-3">
                Kilos informados por empresa (Centros)
            </h3>

            <div class="relative h-64">
                <canvas id="contactosChart"></canvas>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
            <h3 class="font-semibold mb-3">
                Bandejas AGRAK — últimos 40 días
            </h3>

            <div class="relative h-48">
                <canvas id="bandejasAgrakChart"></canvas>
            </div>
        </div>


        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
            <h3 class="font-semibold mb-3">
                Bins AGRAK — últimos 40 días
            </h3>

            <div class="relative h-48">
                <canvas id="binsAgrakChart"></canvas>
            </div>
        </div>


        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
            <h3 class="font-semibold mb-3">
                Cosechadora AGRAK — total registros
            </h3>

            <div class="relative h-96">
                <canvas id="maquinasAgrakChart"></canvas>
            </div>
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
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const ctx = document.getElementById('contactosChart');
            if (!ctx) return;

            const labels = @json($contactosLabels ?? []);
            const data = @json($contactosKilos ?? []).map(Number);

            if (!labels.length || !data.length) return;

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Kilos (Centros)',
                        data: data,
                        backgroundColor: '#10b981',
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
        /**
         * Formato chileno: 1 o 2 decimales
         */
        function formatCL(value) {
            const n = Number(value);
            if (isNaN(n)) return '0';

            return n.toLocaleString('es-CL', {
                minimumFractionDigits: 1,
                maximumFractionDigits: 2
            });
        }

        /**
         * Recalcula kilos AGRAK desde bandejas * kg promedio
         */
        function recalcularKgAgrak() {
            const bandejas = {{ (int) ($kpiBandejasAgrak ?? 0) }};
            const input = document.getElementById('kgPromedio');

            if (!input) return;

            const kgProm = parseFloat(input.value) || 0;
            const totalKg = bandejas * kgProm;

            const target = document.getElementById('kilosAgrak');
            if (target) {
                target.textContent = formatCL(totalKg) + ' kg';
            }
        }

        /**
         * Muestra / oculta el popover
         */
        function toggleKgPopover(event) {
            event.stopPropagation();
            document.getElementById('kgPopover')?.classList.toggle('hidden');
        }

        /**
         * Aplica el valor ingresado
         */
        function applyKgPromedio() {
            const input = document.getElementById('kgPromedio');
            const kg = parseFloat(input.value) || 0;

            fetch(KG_PROMEDIO_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ kg_promedio: kg })
            })
                .then(res => {
                    if (!res.ok) throw new Error('Error al guardar');
                    return res.json();
                })
                .then(data => {
                    console.log('Guardado:', data);

                    recalcularKgAgrak();
                    actualizarLabelKg();
                    document.getElementById('kgPopover').classList.add('hidden');
                })
                .catch(err => {
                    alert('No se pudo guardar el promedio');
                    console.error(err);
                });
        }


        /**
         * Actualiza el label visible del promedio
         */
        function actualizarLabelKg() {
            const input = document.getElementById('kgPromedio');
            const label = document.getElementById('kgPromedioLabel');

            if (!input || !label) return;

            const kg = parseFloat(input.value) || 0;
            label.textContent = kg.toString().replace('.', ',') + ' kg / bandeja';
        }

        /**
         * Cerrar popover al hacer click fuera
         */
        document.addEventListener('click', function (e) {
            const popover = document.getElementById('kgPopover');
            const toggle = document.getElementById('kgToggle');

            if (!popover || !toggle) return;

            if (!popover.contains(e.target) && !toggle.contains(e.target)) {
                popover.classList.add('hidden');
            }
        });

        /**
         * Cálculo inicial al cargar la página
         */
        document.addEventListener('DOMContentLoaded', function () {
            actualizarLabelKg();
            recalcularKgAgrak();
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const ctx = document.getElementById('bandejasAgrakChart');
            if (!ctx) return;

            const labels = @json($bandejasAgrakLabels ?? []);
            const data = @json($bandejasAgrakData ?? []);

            if (!labels.length || !data.length) return;

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Bandejas AGRAK',
                        data: data,
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
                                label: ctx => ctx.parsed.y.toLocaleString('es-CL') + ' bandejas'
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: v => v.toLocaleString('es-CL')
                            }
                        }
                    }
                }
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const ctx = document.getElementById('binsAgrakChart');
            if (!ctx) return;

            const labels = @json($binsAgrakLabels ?? []);
            const data = @json($binsAgrakData ?? []);

            if (!labels.length || !data.length) return;

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Bins AGRAK',
                        backgroundColor: '#8b5cf6',
                        data: data,
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
                                label: ctx =>
                                    ctx.parsed.y.toLocaleString('es-CL') + ' bins'
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: v => v.toLocaleString('es-CL')
                            }
                        }
                    }
                }
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

    const ctx = document.getElementById('maquinasAgrakChart');
    if (!ctx) return;

    const labels = @json($maquinasLabels ?? []);
    const data = @json($maquinasTotales ?? []);

    if (!labels.length || !data.length) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Registros por máquina',
                backgroundColor: '#0ea5e9', // distinto color
                data: data,
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
                        label: ctx =>
                            ctx.parsed.y.toLocaleString('es-CL') + ' Bins'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: v => v.toLocaleString('es-CL')
                    }
                }
            }
        }
    });
});
</script>

</x-app-layout>