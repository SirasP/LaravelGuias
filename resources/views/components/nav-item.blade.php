{{--
    x-nav-item — Ítem colapsable para el Sidebar de navegación

    Props:
      id          string — Identificador para Alpine (ej. 'docs', 'odoo')
      label       string — Texto a mostrar (ej. 'Guías PDF')
      iconBgColor string — Color del fondo del ícono (ej. 'indigo', 'violet', 'emerald')
      active      bool   — Si la sección o alguna de sus rutas hijas está activa
--}}
@props([
    'id'          => '',
    'label'       => '',
    'iconBgColor' => 'gray',
    'active'      => false,
])

<div class="mb-0.5">
    <button
        @click="toggleSection('{{ $id }}')"
        class="w-full flex items-center rounded-xl transition-all duration-150 relative group"
        :class="expanded ? 'gap-3 px-2.5 py-2 text-sm font-medium' : 'justify-center px-0 py-2'"
        :style="!expanded ? 'margin:0 auto; width:48px' : ''"
    >
        <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-colors duration-200
            {{ $active
                ? "bg-{$iconBgColor}-100 dark:bg-{$iconBgColor}-900/40 shadow-sm"
                : "bg-gray-50 dark:bg-gray-800/80 group-hover:bg-gray-100 dark:group-hover:bg-gray-800" }}">
            <svg class="w-[18px] h-[18px] transition-colors {{ $active ? "text-{$iconBgColor}-600 dark:text-{$iconBgColor}-400" : 'text-gray-400 dark:text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                {{ $icon ?? '' }}
            </svg>
        </div>

        <span
            x-show="expanded"
            class="flex-1 text-left truncate {{ $active ? "text-{$iconBgColor}-700 dark:text-{$iconBgColor}-300 font-semibold" : 'text-gray-600 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-gray-200 transition-colors' }}"
        >
            {{ $label }}
        </span>

        <svg
            x-show="expanded"
            class="w-3.5 h-3.5 text-gray-300 dark:text-gray-600 transition-transform duration-200 shrink-0"
            :class="{ 'rotate-180': openSection === '{{ $id }}' }"
            fill="none" stroke="currentColor" viewBox="0 0 24 24"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
        </svg>

        {{-- Tooltip custom (solo visible colapsado con hover) --}}
        <div
            x-show="!expanded"
            class="absolute left-full ml-3 px-2.5 py-1.5 bg-gray-900 dark:bg-gray-800 text-white text-xs font-semibold rounded-lg shadow-lg
                   opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all whitespace-nowrap z-50 pointer-events-none"
        >
            {{ $label }}
            <div class="absolute top-1/2 -left-1 -translate-y-1/2 border-[5px] border-transparent border-r-gray-900 dark:border-r-gray-800"></div>
        </div>
    </button>

    <div
        x-show="expanded && openSection === '{{ $id }}'"
        x-collapse
        class="mt-0.5 ml-[22px] pl-3.5 border-l-2 border-{{ $iconBgColor }}-100 dark:border-{{ $iconBgColor }}-900/40 space-y-0.5 pb-1"
    >
        {{ $slot }}
    </div>
</div>
