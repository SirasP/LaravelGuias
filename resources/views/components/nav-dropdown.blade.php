{{--
Uso:
@include('components.nav-dropdown', [
'label' => 'FuelControl',
'icon' => '<svg path d="..." />', ← path del heroicon (outline)
'active' => request()->routeIs('fuelcontrol.*'),
'color' => 'orange', ← indigo | violet | emerald | sky | orange
'id' => 'openFuel', ← id único para Alpine
'items' => [
['label' => 'Dashboard', 'route' => 'fuelcontrol.index', 'icon' => '...path...', 'desc' => 'Vista general'],
],
])
--}}

@php
    $colorClasses = [
        'indigo' => ['dot' => 'bg-indigo-500', 'hover' => 'hover:bg-indigo-50 dark:hover:bg-indigo-900/30', 'icon' => 'text-indigo-500', 'active' => 'text-indigo-600 dark:text-indigo-400', 'ring' => 'ring-indigo-200  dark:ring-indigo-800'],
        'violet' => ['dot' => 'bg-violet-500', 'hover' => 'hover:bg-violet-50 dark:hover:bg-violet-900/30', 'icon' => 'text-violet-500', 'active' => 'text-violet-600 dark:text-violet-400', 'ring' => 'ring-violet-200  dark:ring-violet-800'],
        'emerald' => ['dot' => 'bg-emerald-500', 'hover' => 'hover:bg-emerald-50 dark:hover:bg-emerald-900/30', 'icon' => 'text-emerald-500', 'active' => 'text-emerald-600 dark:text-emerald-400', 'ring' => 'ring-emerald-200 dark:ring-emerald-800'],
        'sky' => ['dot' => 'bg-sky-500', 'hover' => 'hover:bg-sky-50 dark:hover:bg-sky-900/30', 'icon' => 'text-sky-500', 'active' => 'text-sky-600 dark:text-sky-400', 'ring' => 'ring-sky-200     dark:ring-sky-800'],
        'orange' => ['dot' => 'bg-orange-500', 'hover' => 'hover:bg-orange-50 dark:hover:bg-orange-900/30', 'icon' => 'text-orange-500', 'active' => 'text-orange-600 dark:text-orange-400', 'ring' => 'ring-orange-200  dark:ring-orange-800'],
    ];
    $c = $colorClasses[$color ?? 'indigo'];
@endphp

<div x-data="{ {{ $id }}: false }" class="relative">

    {{-- Trigger --}}
    <button @click="{{ $id }} = !{{ $id }}" @click.away="{{ $id }} = false" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm font-medium
                   transition-all duration-150 select-none
                   {{ $active
    ? 'bg-gray-100 dark:bg-gray-800 ' . $c['active']
    : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-800/60'
                   }}">

        {{-- Icono del módulo --}}
        <svg class="w-3.5 h-3.5 {{ $active ? $c['icon'] : 'opacity-50' }}" fill="none" stroke="currentColor"
            viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}" />
        </svg>

        {{ $label }}

        <svg class="w-3 h-3 opacity-40 transition-transform duration-200" :class="{ 'rotate-180': {{ $id }} }"
            fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    {{-- Dropdown panel --}}
    <div x-show="{{ $id }}" x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 -translate-y-1" class="absolute left-0 top-full mt-2 w-60 z-50
                rounded-2xl bg-white dark:bg-gray-900
                shadow-xl shadow-black/8 dark:shadow-black/30
                ring-1 {{ $c['ring'] }} ring-opacity-60
                overflow-hidden" style="display:none">

        {{-- Header del panel --}}
        <div class="px-4 pt-3 pb-2 border-b border-gray-50 dark:border-gray-800">
            <p class="text-[11px] font-semibold uppercase tracking-widest
                       {{ $c['icon'] }} opacity-70">
                {{ $label }}
            </p>
        </div>

        {{-- Items --}}
        <div class="py-1.5">
            @foreach ($items as $item)
                    <a href="{{ route($item['route']) }}" @click="{{ $id }} = false" class="flex items-start gap-3 px-4 py-2.5
                              {{ $c['hover'] }}
                              {{ request()->routeIs($item['route']) ? 'bg-gray-50 dark:bg-gray-800' : '' }}
                              transition-colors duration-100 group">

                        {{-- Item icon --}}
                        <div class="mt-0.5 shrink-0 p-1.5 rounded-lg
                                    {{ request()->routeIs($item['route'])
                ? $c['dot'] . ' bg-opacity-15'
                : 'bg-gray-100 dark:bg-gray-800 group-hover:' . explode(' ', $c['dot'])[0] . ' group-hover:bg-opacity-10'
                                    }}">
                            <svg class="w-3.5 h-3.5
                                        {{ request()->routeIs($item['route']) ? $c['icon'] : 'text-gray-500 dark:text-gray-400 group-hover:' . $c['icon'] }}
                                        transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}" />
                            </svg>
                        </div>

                        {{-- Labels --}}
                        <div class="min-w-0">
                            <p class="text-sm font-medium
                                       {{ request()->routeIs($item['route'])
                ? $c['active'] . ' font-semibold'
                : 'text-gray-700 dark:text-gray-200'
                                       }}
                                       leading-tight truncate">
                                {{ $item['label'] }}
                            </p>
                            @if (!empty($item['desc']))
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5 leading-tight">
                                    {{ $item['desc'] }}
                                </p>
                            @endif
                        </div>

                        {{-- Active dot --}}
                        @if (request()->routeIs($item['route']))
                            <span class="ml-auto shrink-0 mt-1.5 h-1.5 w-1.5 rounded-full {{ $c['dot'] }}"></span>
                        @endif
                    </a>
            @endforeach
        </div>
    </div>
</div>