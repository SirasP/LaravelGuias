@auth
<nav x-data="{ mobileOpen: false }" class="relative z-50">

    {{-- Accent line top --}}
    <div class="h-0.5 bg-gradient-to-r from-indigo-500 via-violet-500 to-indigo-400"></div>

    <div class="bg-white dark:bg-gray-950 border-b border-gray-100 dark:border-gray-800 shadow-sm">
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-15 py-2.5">

                {{-- ── LOGO ─────────────────────────────────────────────────── --}}
                <a href="{{ route('index') }}"
                   class="shrink-0 flex items-center gap-2.5 group">
                    <x-application-logo
                        class="block h-8 w-auto fill-current text-gray-800 dark:text-gray-100
                               transition-transform duration-200 group-hover:scale-105" />
                </a>

                {{-- ── DESKTOP NAV ───────────────────────────────────────────── --}}
                <div class="hidden lg:flex items-center gap-1">

                    {{-- Guías Recepcionadas --}}
                    @include('components.nav-dropdown', [
                        'label'  => 'Guías Recepcionadas',
                        'icon'   => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                        'active' => request()->routeIs('pdf.*'),
                        'color'  => 'indigo',
                        'id'     => 'openDocs',
                        'items'  => [
                            ['label' => 'PDFs importados',  'route' => 'pdf.index',       'icon' => 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4', 'desc' => 'Ver documentos'],
                            ['label' => 'Importar PDF',     'route' => 'pdf.import.form',  'icon' => 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12', 'desc' => 'Subir nuevo PDF'],
                        ],
                    ])

                    {{-- Guías ODOO --}}
                    @include('components.nav-dropdown', [
                        'label'  => 'Guías ODOO',
                        'icon'   => 'M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2',
                        'active' => request()->routeIs('excel_out_transfers.*'),
                        'color'  => 'violet',
                        'id'     => 'openOdoo',
                        'items'  => [
                            ['label' => 'Vista',     'route' => 'excel_out_transfers.index',  'icon' => 'M4 6h16M4 10h16M4 14h16M4 18h16', 'desc' => 'Listado completo'],
                            ['label' => 'Importar',  'route' => 'excel_out_transfers.import', 'icon' => 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12', 'desc' => 'Importar desde Excel'],
                        ],
                    ])

                    {{-- Agrak --}}
                    @include('components.nav-dropdown', [
                        'label'  => 'Agrak',
                        'icon'   => 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
                        'active' => request()->routeIs('agrak.*'),
                        'color'  => 'emerald',
                        'id'     => 'openAgrak',
                        'items'  => [
                            ['label' => 'Vista',     'route' => 'agrak.index',       'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'desc' => 'Resumen Agrak'],
                            ['label' => 'Importar',  'route' => 'agrak.import.form', 'icon' => 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12', 'desc' => 'Importar datos'],
                        ],
                    ])

                    {{-- Bandejas --}}
                    @include('components.nav-dropdown', [
                        'label'  => 'Bandejas',
                        'icon'   => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
                        'active' => request()->routeIs('guias.*'),
                        'color'  => 'sky',
                        'id'     => 'openGuias',
                        'items'  => [
                            ['label' => 'Vista',         'route' => 'guias.comfrut.index',       'icon' => 'M4 6h16M4 10h16M4 14h16M4 18h16', 'desc' => 'Guías recibidas'],
                            ['label' => 'Importar XML',  'route' => 'guias.comfrut.import.form', 'icon' => 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4', 'desc' => 'Subir archivo XML'],
                        ],
                    ])

                    {{-- FuelControl --}}
                    @include('components.nav-dropdown', [
                        'label'  => 'FuelControl',
                        'icon'   => 'M13 10V3L4 14h7v7l9-11h-7z',
                        'active' => request()->routeIs('fuelcontrol.*'),
                        'color'  => 'orange',
                        'id'     => 'openFuel',
                        'items'  => [
                            ['label' => 'Dashboard',  'route' => 'fuelcontrol.index',           'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', 'desc' => 'Vista general'],
                            ['label' => 'Productos',  'route' => 'fuelcontrol.productos',       'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', 'desc' => 'Gestión de combustibles'],
                            ['label' => 'Vehículos',  'route' => 'fuelcontrol.vehiculos.index', 'icon' => 'M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0zM13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1', 'desc' => 'Flota registrada'],
                            ['label' => 'Movimientos','route' => 'fuelcontrol.movimientos',     'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'desc' => 'Historial de salidas'],
                        ],
                    ])

                </div>

                {{-- ── USUARIO ───────────────────────────────────────────────── --}}
                <div class="hidden lg:flex items-center">
                    <x-dropdown align="right" width="52">
                        <x-slot name="trigger">
                            <button class="flex items-center gap-2.5 pl-3 pr-2 py-1.5 rounded-xl
                                           text-sm font-medium text-gray-700 dark:text-gray-200
                                           hover:bg-gray-50 dark:hover:bg-gray-800
                                           transition-colors duration-150 group">
                                {{-- Avatar --}}
                                <div class="h-7 w-7 rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600
                                            flex items-center justify-center shrink-0">
                                    <span class="text-xs font-bold text-white leading-none">
                                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                    </span>
                                </div>
                                <span>{{ Auth::user()->name }}</span>
                                <svg class="h-3.5 w-3.5 opacity-50 group-hover:opacity-100 transition-opacity"
                                     fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                          d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                          clip-rule="evenodd"/>
                                </svg>
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <div class="px-4 py-2.5 border-b border-gray-100 dark:border-gray-700">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Sesión activa</p>
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate">
                                    {{ Auth::user()->name }}
                                </p>
                            </div>
                            <x-dropdown-link :href="route('profile.edit')" class="flex items-center gap-2">
                                <svg class="w-4 h-4 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                Perfil
                            </x-dropdown-link>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault(); this.closest('form').submit();"
                                    class="flex items-center gap-2 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
                                    <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                    </svg>
                                    Cerrar sesión
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>

                {{-- ── HAMBURGER ─────────────────────────────────────────────── --}}
                <button @click="mobileOpen = !mobileOpen"
                        class="lg:hidden p-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800
                               transition-colors duration-150">
                    <svg x-show="!mobileOpen" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg x-show="mobileOpen" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>

            </div>
        </div>

        {{-- ── MOBILE MENU ────────────────────────────────────────────────────── --}}
        <div x-show="mobileOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="lg:hidden border-t border-gray-100 dark:border-gray-800
                    bg-white dark:bg-gray-950 pb-4"
             style="display:none">

            <div class="px-4 pt-3 space-y-1">

                @php
                $mobileMenus = [
                    ['id' => 'mPdf',   'label' => 'Guías Recepcionadas', 'color' => 'indigo',  'items' => [
                        ['label' => 'PDFs importados', 'route' => 'pdf.index'],
                        ['label' => 'Importar PDF',    'route' => 'pdf.import.form'],
                    ]],
                    ['id' => 'mOdoo',  'label' => 'Guías ODOO',          'color' => 'violet',  'items' => [
                        ['label' => 'Vista',    'route' => 'excel_out_transfers.index'],
                        ['label' => 'Importar', 'route' => 'excel_out_transfers.import'],
                    ]],
                    ['id' => 'mAgrak', 'label' => 'Agrak',               'color' => 'emerald', 'items' => [
                        ['label' => 'Vista',    'route' => 'agrak.index'],
                        ['label' => 'Importar', 'route' => 'agrak.import.form'],
                    ]],
                    ['id' => 'mBand',  'label' => 'Bandejas',            'color' => 'sky',     'items' => [
                        ['label' => 'Vista',        'route' => 'guias.comfrut.index'],
                        ['label' => 'Importar XML', 'route' => 'guias.comfrut.import.form'],
                    ]],
                    ['id' => 'mFuel',  'label' => 'FuelControl',         'color' => 'orange',  'items' => [
                        ['label' => 'Dashboard',   'route' => 'fuelcontrol.index'],
                        ['label' => 'Productos',   'route' => 'fuelcontrol.productos'],
                        ['label' => 'Vehículos',   'route' => 'fuelcontrol.vehiculos.index'],
                        ['label' => 'Movimientos', 'route' => 'fuelcontrol.movimientos'],
                    ]],
                ];
                $colorMap = [
                    'indigo'  => 'text-indigo-600  dark:text-indigo-400  bg-indigo-50  dark:bg-indigo-900/20',
                    'violet'  => 'text-violet-600  dark:text-violet-400  bg-violet-50  dark:bg-violet-900/20',
                    'emerald' => 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/20',
                    'sky'     => 'text-sky-600     dark:text-sky-400     bg-sky-50     dark:bg-sky-900/20',
                    'orange'  => 'text-orange-600  dark:text-orange-400  bg-orange-50  dark:bg-orange-900/20',
                ];
                @endphp

                @foreach ($mobileMenus as $menu)
                <div x-data="{ open_{{ $menu['id'] }}: false }">
                    <button @click="open_{{ $menu['id'] }} = !open_{{ $menu['id'] }}"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl
                                   text-sm font-medium text-gray-700 dark:text-gray-200
                                   hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                        <span>{{ $menu['label'] }}</span>
                        <svg class="w-4 h-4 opacity-50 transition-transform duration-200"
                             :class="{ 'rotate-180': open_{{ $menu['id'] }} }"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open_{{ $menu['id'] }}"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="mt-1 ml-3 pl-3 border-l-2 border-gray-100 dark:border-gray-800 space-y-0.5"
                         style="display:none">
                        @foreach ($menu['items'] as $item)
                        <a href="{{ route($item['route']) }}"
                           class="block px-3 py-2 rounded-lg text-sm text-gray-600 dark:text-gray-300
                                  hover:{{ $colorMap[$menu['color']] }} transition-colors duration-150
                                  {{ request()->routeIs($item['route']) ? $colorMap[$menu['color']] . ' font-medium' : '' }}">
                            {{ $item['label'] }}
                        </a>
                        @endforeach
                    </div>
                </div>
                @endforeach

                {{-- Divider + User --}}
                <div class="pt-3 border-t border-gray-100 dark:border-gray-800 space-y-1">
                    <a href="{{ route('profile.edit') }}"
                       class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm
                              text-gray-600 dark:text-gray-300
                              hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                        <div class="h-7 w-7 rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600
                                    flex items-center justify-center shrink-0">
                            <span class="text-xs font-bold text-white">
                                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                            </span>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800 dark:text-gray-100 leading-tight">
                                {{ Auth::user()->name }}
                            </p>
                            <p class="text-xs text-gray-400">Ver perfil</p>
                        </div>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="w-full flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm
                                       text-red-600 dark:text-red-400
                                       hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            Cerrar sesión
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</nav>
@endauth