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
    'pct'      => null,
])

@php
    if (isset($pct)) {
        if ($pct > 0) {
            $badgeDir = 'up';
            $badge = '+'.number_format($pct, 1, ',', '.').'% prev';
        } elseif ($pct < 0) {
            $badgeDir = 'down';
            $badge = number_format($pct, 1, ',', '.').'% prev';
        } else {
            $badgeDir = 'neu';
            $badge = '0% prev';
        }
    }
@endphp

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
            <div class="mt-2 flex items-center gap-1.5 text-xs font-semibold {{ $badgeDir === 'up' ? 'text-emerald-600 dark:text-emerald-400' : ($badgeDir === 'down' ? 'text-red-500' : 'text-gray-400') }}">
                @if($badgeDir === 'up')
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                @elseif($badgeDir === 'down')
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6"></path></svg>
                @else
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 12h14"></path></svg>
                @endif
                {{ $badge }}
            </div>
        @endif
    </div>

    <div class="kpi-icon {{ $iconBg }}">
        {{ $slot }}
    </div>
</div>
