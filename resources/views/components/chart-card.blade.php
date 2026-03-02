{{--
    x-chart-card — Tarjeta de gráfico reutilizable

    Props:
      title    string — Título del chart
      pill     string — Texto de la píldora (ej. "Diario", "Centros")
      dot      string — Clase Tailwind del punto de color (ej. "bg-blue-500")
      chartId  string — ID del canvas para Chart.js
      height   string — Altura del canvas (default "h-44")
--}}
@props([
    'title'   => '',
    'pill'    => '',
    'dot'     => 'bg-slate-400',
    'chartId' => '',
    'height'  => 'h-44',
])

<div class="chart-card">
    <div class="chart-header">
        <div class="chart-title">
            <span class="w-2 h-2 rounded-full {{ $dot }} flex-shrink-0"></span>
            {{ $title }}
        </div>
        <span class="chart-pill">{{ $pill }}</span>
    </div>
    <div class="p-5">
        <div class="relative {{ $height }}">
            <canvas id="{{ $chartId }}"></canvas>
        </div>
    </div>
</div>
