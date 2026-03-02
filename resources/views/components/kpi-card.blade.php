{{--
    x-kpi-card — Tarjeta de KPI reutilizable

    Props:
      label       string  — Etiqueta superior (ej. "Kilos")
      value       string  — Valor principal (pasado como string formateado)
      unit        string? — Unidad opcional junto al valor (ej. "kg")
      iconBg      string  — Clases Tailwind para el fondo del ícono (ej. "bg-blue-50 dark:bg-blue-900/20")
      id          string? — ID HTML opcional para el valor (para actualizar via JS)
      loading     bool    — Si true muestra skeleton en lugar del valor
      badge       string? — Texto del badge de variación (ej. "+12%" o "—")
      badgeDir    string  — "up" | "down" | "neu" (default "neu")
--}}
@props([
    'label'    => '',
    'value'    => '',
    'unit'     => null,
    'iconBg'   => 'bg-gray-50 dark:bg-gray-800',
    'id'       => null,
    'loading'  => false,
    'badge'    => null,
    'badgeDir' => 'neu',
])

<div class="kpi-card">
    <div>
        <div class="kpi-label">{{ $label }}</div>

        @if($loading)
            <div class="skeleton h-8 w-24 mt-1"></div>
        @else
            <div
                class="kpi-value"
                @if($id) id="{{ $id }}" @endif
            >
                {{ $value }}
                @if($unit)
                    <span class="text-base font-semibold text-gray-400">{{ $unit }}</span>
                @endif
            </div>
        @endif

        @if($badge)
            <span class="kpi-badge kpi-badge-{{ $badgeDir }}">
                @if($badgeDir === 'up') ▲ @elseif($badgeDir === 'down') ▼ @endif
                {{ $badge }}
            </span>
        @endif
    </div>

    <div class="kpi-icon {{ $iconBg }}">
        {{ $slot }}
    </div>
</div>
