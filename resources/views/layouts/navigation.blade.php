@auth
    <nav x-data="{ mobileOpen: false }" class="relative z-50">

        {{-- Línea accent superior --}}
        <div class="h-0.5 bg-gradient-to-r from-indigo-500 via-violet-500 to-indigo-400"></div>

        <div class="bg-white dark:bg-gray-950 border-b border-gray-100 dark:border-gray-800 shadow-sm">
            <div class="w-full px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-14">

                    {{-- ── LOGO ────────────────────────────────────────────── --}}
                    <div class="shrink-0 flex items-center">
                        <a href="{{ route('index') }}" class="group">
                            <x-application-logo
                                class="block h-8 w-auto fill-current text-gray-800 dark:text-gray-100 transition-transform duration-200 group-hover:scale-105" />
                        </a>
                    </div>

                    {{-- ── DESKTOP NAV ──────────────────────────────────────── --}}
                    <div class="hidden sm:flex items-center gap-1">

                        {{-- Guías Recepcionadas --}}
                        <div x-data="{ openDocs: false }" class="relative">
                            <button @click="openDocs = !openDocs" @click.away="openDocs = false"
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm font-medium transition-all duration-150
                                           {{ request()->routeIs('pdf.*') ? 'bg-gray-100 dark:bg-gray-800 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                                <svg class="w-3.5 h-3.5 {{ request()->routeIs('pdf.*') ? 'text-indigo-500' : 'opacity-40' }}"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Guías Recepcionadas
                                <svg class="w-3 h-3 opacity-40 transition-transform duration-200"
                                    :class="{ 'rotate-180': openDocs }" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                        d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div x-show="openDocs" x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                x-transition:leave="transition ease-in duration-100"
                                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                                class="absolute left-0 top-full mt-2 w-56 z-50 rounded-2xl bg-white dark:bg-gray-900 shadow-xl ring-1 ring-indigo-100 dark:ring-indigo-900 overflow-hidden"
                                style="display:none">
                                <div class="px-4 pt-3 pb-2 border-b border-gray-50 dark:border-gray-800">
                                    <p
                                        class="text-[11px] font-semibold uppercase tracking-widest text-indigo-500 opacity-70">
                                        Documentos PDF</p>
                                </div>
                                <div class="py-1.5">
                                    <a href="{{ route('pdf.index') }}" @click="openDocs = false"
                                        class="flex items-center gap-3 px-4 py-2.5 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors group">
                                        <span
                                            class="p-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 group-hover:bg-indigo-100 dark:group-hover:bg-indigo-900">
                                            <svg class="w-3.5 h-3.5 text-gray-500 group-hover:text-indigo-500 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                        </span>
                                        <div>
                                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">PDFs importados
                                            </p>
                                            <p class="text-xs text-gray-400">Ver documentos</p>
                                        </div>
                                    </a>
                                    <a href="{{ route('pdf.import.form') }}" @click="openDocs = false"
                                        class="flex items-center gap-3 px-4 py-2.5 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors group">
                                        <span
                                            class="p-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 group-hover:bg-indigo-100 dark:group-hover:bg-indigo-900">
                                            <svg class="w-3.5 h-3.5 text-gray-500 group-hover:text-indigo-500 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                            </svg>
                                        </span>
                                        <div>
                                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Importar PDF</p>
                                            <p class="text-xs text-gray-400">Subir nuevo PDF</p>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>

                        {{-- Guías ODOO --}}
                        <div x-data="{ openOdoo: false }" class="relative">
                            <button @click="openOdoo = !openOdoo" @click.away="openOdoo = false"
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm font-medium transition-all duration-150
                                           {{ request()->routeIs('excel_out_transfers.*') ? 'bg-gray-100 dark:bg-gray-800 text-violet-600 dark:text-violet-400' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                                <svg class="w-3.5 h-3.5 {{ request()->routeIs('excel_out_transfers.*') ? 'text-violet-500' : 'opacity-40' }}"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7" />
                                </svg>
                                Guías ODOO
                                <svg class="w-3 h-3 opacity-40 transition-transform duration-200"
                                    :class="{ 'rotate-180': openOdoo }" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                        d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div x-show="openOdoo" x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                x-transition:leave="transition ease-in duration-100"
                                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                                class="absolute left-0 top-full mt-2 w-56 z-50 rounded-2xl bg-white dark:bg-gray-900 shadow-xl ring-1 ring-violet-100 dark:ring-violet-900 overflow-hidden"
                                style="display:none">
                                <div class="px-4 pt-3 pb-2 border-b border-gray-50 dark:border-gray-800">
                                    <p
                                        class="text-[11px] font-semibold uppercase tracking-widest text-violet-500 opacity-70">
                                        Guías ODOO</p>
                                </div>
                                <div class="py-1.5">
                                    <a href="{{ route('excel_out_transfers.index') }}" @click="openOdoo = false"
                                        class="flex items-center gap-3 px-4 py-2.5 hover:bg-violet-50 dark:hover:bg-violet-900/30 transition-colors group">
                                        <span
                                            class="p-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 group-hover:bg-violet-100"><svg
                                                class="w-3.5 h-3.5 text-gray-500 group-hover:text-violet-500 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                            </svg></span>
                                        <div>
                                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Vista</p>
                                            <p class="text-xs text-gray-400">Listado completo</p>
                                        </div>
                                    </a>
                                    <a href="{{ route('excel_out_transfers.import') }}" @click="openOdoo = false"
                                        class="flex items-center gap-3 px-4 py-2.5 hover:bg-violet-50 dark:hover:bg-violet-900/30 transition-colors group">
                                        <span
                                            class="p-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 group-hover:bg-violet-100"><svg
                                                class="w-3.5 h-3.5 text-gray-500 group-hover:text-violet-500 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                            </svg></span>
                                        <div>
                                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Importar</p>
                                            <p class="text-xs text-gray-400">Importar desde Excel</p>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>

                        {{-- Agrak --}}
                        <div x-data="{ openAgrak: false }" class="relative">
                            <button @click="openAgrak = !openAgrak" @click.away="openAgrak = false"
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm font-medium transition-all duration-150
                                           {{ request()->routeIs('agrak.*') ? 'bg-gray-100 dark:bg-gray-800 text-emerald-600 dark:text-emerald-400' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                                <svg class="w-3.5 h-3.5 {{ request()->routeIs('agrak.*') ? 'text-emerald-500' : 'opacity-40' }}"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                </svg>
                                Agrak
                                <svg class="w-3 h-3 opacity-40 transition-transform duration-200"
                                    :class="{ 'rotate-180': openAgrak }" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                        d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div x-show="openAgrak" x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                x-transition:leave="transition ease-in duration-100"
                                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                                class="absolute left-0 top-full mt-2 w-56 z-50 rounded-2xl bg-white dark:bg-gray-900 shadow-xl ring-1 ring-emerald-100 dark:ring-emerald-900 overflow-hidden"
                                style="display:none">
                                <div class="px-4 pt-3 pb-2 border-b border-gray-50 dark:border-gray-800">
                                    <p
                                        class="text-[11px] font-semibold uppercase tracking-widest text-emerald-500 opacity-70">
                                        Agrak</p>
                                </div>
                                <div class="py-1.5">
                                    <a href="{{ route('agrak.index') }}" @click="openAgrak = false"
                                        class="flex items-center gap-3 px-4 py-2.5 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-colors group">
                                        <span
                                            class="p-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 group-hover:bg-emerald-100"><svg
                                                class="w-3.5 h-3.5 text-gray-500 group-hover:text-emerald-500 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                            </svg></span>
                                        <div>
                                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Vista</p>
                                            <p class="text-xs text-gray-400">Resumen Agrak</p>
                                        </div>
                                    </a>
                                    <a href="{{ route('agrak.import.form') }}" @click="openAgrak = false"
                                        class="flex items-center gap-3 px-4 py-2.5 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-colors group">
                                        <span
                                            class="p-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 group-hover:bg-emerald-100"><svg
                                                class="w-3.5 h-3.5 text-gray-500 group-hover:text-emerald-500 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                            </svg></span>
                                        <div>
                                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Importar</p>
                                            <p class="text-xs text-gray-400">Importar datos</p>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>

                        {{-- Bandejas --}}
                        <div x-data="{ openGuias: false }" class="relative">
                            <button @click="openGuias = !openGuias" @click.away="openGuias = false"
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm font-medium transition-all duration-150
                                           {{ request()->routeIs('guias.*') ? 'bg-gray-100 dark:bg-gray-800 text-sky-600 dark:text-sky-400' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                                <svg class="w-3.5 h-3.5 {{ request()->routeIs('guias.*') ? 'text-sky-500' : 'opacity-40' }}"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                Bandejas
                                <svg class="w-3 h-3 opacity-40 transition-transform duration-200"
                                    :class="{ 'rotate-180': openGuias }" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                        d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div x-show="openGuias" x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                x-transition:leave="transition ease-in duration-100"
                                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                                class="absolute left-0 top-full mt-2 w-56 z-50 rounded-2xl bg-white dark:bg-gray-900 shadow-xl ring-1 ring-sky-100 dark:ring-sky-900 overflow-hidden"
                                style="display:none">
                                <div class="px-4 pt-3 pb-2 border-b border-gray-50 dark:border-gray-800">
                                    <p class="text-[11px] font-semibold uppercase tracking-widest text-sky-500 opacity-70">
                                        Guías Recepción Bandejas</p>
                                </div>
                                <div class="py-1.5">
                                    <a href="{{ route('guias.comfrut.index') }}" @click="openGuias = false"
                                        class="flex items-center gap-3 px-4 py-2.5 hover:bg-sky-50 dark:hover:bg-sky-900/30 transition-colors group">
                                        <span
                                            class="p-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 group-hover:bg-sky-100"><svg
                                                class="w-3.5 h-3.5 text-gray-500 group-hover:text-sky-500 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                            </svg></span>
                                        <div>
                                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Vista</p>
                                            <p class="text-xs text-gray-400">Guías recibidas</p>
                                        </div>
                                    </a>
                                    <a href="{{ route('guias.comfrut.import.form') }}" @click="openGuias = false"
                                        class="flex items-center gap-3 px-4 py-2.5 hover:bg-sky-50 dark:hover:bg-sky-900/30 transition-colors group">
                                        <span
                                            class="p-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 group-hover:bg-sky-100"><svg
                                                class="w-3.5 h-3.5 text-gray-500 group-hover:text-sky-500 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                            </svg></span>
                                        <div>
                                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Importar XML</p>
                                            <p class="text-xs text-gray-400">Subir archivo XML</p>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>

                        {{-- FuelControl --}}
                        <div x-data="{ openFuel: false }" class="relative">
                            <button @click="openFuel = !openFuel" @click.away="openFuel = false"
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm font-medium transition-all duration-150
                                           {{ request()->routeIs('fuelcontrol.*') ? 'bg-orange-50 dark:bg-orange-900/20 text-orange-600 dark:text-orange-400' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                                <svg class="w-3.5 h-3.5 {{ request()->routeIs('fuelcontrol.*') ? 'text-orange-500' : 'opacity-40' }}"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                FuelControl
                                <svg class="w-3 h-3 opacity-40 transition-transform duration-200"
                                    :class="{ 'rotate-180': openFuel }" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                        d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div x-show="openFuel" x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                x-transition:leave="transition ease-in duration-100"
                                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                                class="absolute left-0 top-full mt-2 w-56 z-50 rounded-2xl bg-white dark:bg-gray-900 shadow-xl ring-1 ring-orange-100 dark:ring-orange-900 overflow-hidden"
                                style="display:none">
                                <div class="px-4 pt-3 pb-2 border-b border-gray-50 dark:border-gray-800">
                                    <p
                                        class="text-[11px] font-semibold uppercase tracking-widest text-orange-500 opacity-70">
                                        FuelControl</p>
                                </div>
                                <div class="py-1.5">
                                    <a href="{{ route('fuelcontrol.index') }}" @click="openFuel = false"
                                        class="flex items-center gap-3 px-4 py-2.5 hover:bg-orange-50 dark:hover:bg-orange-900/30 transition-colors group">
                                        <span
                                            class="p-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 group-hover:bg-orange-100"><svg
                                                class="w-3.5 h-3.5 text-gray-500 group-hover:text-orange-500 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                            </svg></span>
                                        <div>
                                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Dashboard</p>
                                            <p class="text-xs text-gray-400">Vista general</p>
                                        </div>
                                    </a>
                                    <a href="{{ route('fuelcontrol.productos') }}" @click="openFuel = false"
                                        class="flex items-center gap-3 px-4 py-2.5 hover:bg-orange-50 dark:hover:bg-orange-900/30 transition-colors group">
                                        <span
                                            class="p-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 group-hover:bg-orange-100"><svg
                                                class="w-3.5 h-3.5 text-gray-500 group-hover:text-orange-500 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                            </svg></span>
                                        <div>
                                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Productos</p>
                                            <p class="text-xs text-gray-400">Gestión de combustibles</p>
                                        </div>
                                    </a>
                                    <a href="{{ route('fuelcontrol.vehiculos.index') }}" @click="openFuel = false"
                                        class="flex items-center gap-3 px-4 py-2.5 hover:bg-orange-50 dark:hover:bg-orange-900/30 transition-colors group">
                                        <span
                                            class="p-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 group-hover:bg-orange-100"><svg
                                                class="w-3.5 h-3.5 text-gray-500 group-hover:text-orange-500 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0zM13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1" />
                                            </svg></span>
                                        <div>
                                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Vehículos</p>
                                            <p class="text-xs text-gray-400">Flota registrada</p>
                                        </div>
                                    </a>
                                    <a href="{{ route('fuelcontrol.movimientos') }}" @click="openFuel = false"
                                        class="flex items-center gap-3 px-4 py-2.5 hover:bg-orange-50 dark:hover:bg-orange-900/30 transition-colors group">
                                        <span
                                            class="p-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 group-hover:bg-orange-100"><svg
                                                class="w-3.5 h-3.5 text-gray-500 group-hover:text-orange-500 transition-colors"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                            </svg></span>
                                        <div>
                                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Movimientos</p>
                                            <p class="text-xs text-gray-400">Historial de salidas</p>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>

                    </div>

                    {{-- ── USUARIO (desktop) ────────────────────────────────── --}}
                    <div class="hidden sm:flex items-center">
                        <x-dropdown align="right" width="52">
                            <x-slot name="trigger">
                                <button
                                    class="flex items-center gap-2 px-2 py-1.5 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <div
                                        class="h-7 w-7 rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shrink-0">
                                        <span
                                            class="text-xs font-bold text-white leading-none">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
                                    </div>
                                    <span>{{ Auth::user()->name }}</span>
                                    <svg class="h-3.5 w-3.5 opacity-40" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                <div class="px-4 py-2.5 border-b border-gray-100 dark:border-gray-700">
                                    <p class="text-xs text-gray-400">Sesión activa</p>
                                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate">
                                        {{ Auth::user()->name }}</p>
                                </div>
                                <x-dropdown-link :href="route('profile.edit')">Perfil</x-dropdown-link>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <x-dropdown-link :href="route('logout')"
                                        onclick="event.preventDefault(); this.closest('form').submit();">
                                        Cerrar sesión
                                    </x-dropdown-link>
                                </form>
                            </x-slot>
                        </x-dropdown>
                    </div>

                    {{-- ── HAMBURGER ────────────────────────────────────────── --}}
                    <button @click="mobileOpen = !mobileOpen"
                        class="sm:hidden p-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                        <svg x-show="!mobileOpen" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg x-show="mobileOpen" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            style="display:none">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                </div>
            </div>

            {{-- ── MOBILE MENU ─────────────────────────────────────────────── --}}
            <div x-show="mobileOpen" x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-1"
                class="sm:hidden border-t border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-950 pb-4"
                style="display:none">
                <div class="px-4 pt-3 space-y-0.5">

                    {{-- Guías Recepcionadas --}}
                    <div x-data="{ open: false }">
                        <button @click="open = !open"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            <span>Guías Recepcionadas</span>
                            <svg class="w-4 h-4 opacity-40 transition-transform duration-200"
                                :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open"
                            class="mt-1 ml-3 pl-3 border-l-2 border-indigo-100 dark:border-indigo-900 space-y-0.5"
                            style="display:none">
                            <a href="{{ route('pdf.index') }}"
                                class="block px-3 py-2 rounded-lg text-sm text-gray-600 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors">PDFs
                                importados</a>
                            <a href="{{ route('pdf.import.form') }}"
                                class="block px-3 py-2 rounded-lg text-sm text-gray-600 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors">Importar
                                PDF</a>
                        </div>
                    </div>

                    {{-- Guías ODOO --}}
                    <div x-data="{ open: false }">
                        <button @click="open = !open"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            <span>Guías ODOO</span>
                            <svg class="w-4 h-4 opacity-40 transition-transform duration-200"
                                :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open"
                            class="mt-1 ml-3 pl-3 border-l-2 border-violet-100 dark:border-violet-900 space-y-0.5"
                            style="display:none">
                            <a href="{{ route('excel_out_transfers.index') }}"
                                class="block px-3 py-2 rounded-lg text-sm text-gray-600 dark:text-gray-300 hover:bg-violet-50 dark:hover:bg-violet-900/30 transition-colors">Vista</a>
                            <a href="{{ route('excel_out_transfers.import') }}"
                                class="block px-3 py-2 rounded-lg text-sm text-gray-600 dark:text-gray-300 hover:bg-violet-50 dark:hover:bg-violet-900/30 transition-colors">Importar</a>
                        </div>
                    </div>

                    {{-- Agrak --}}
                    <div x-data="{ open: false }">
                        <button @click="open = !open"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            <span>Agrak</span>
                            <svg class="w-4 h-4 opacity-40 transition-transform duration-200"
                                :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open"
                            class="mt-1 ml-3 pl-3 border-l-2 border-emerald-100 dark:border-emerald-900 space-y-0.5"
                            style="display:none">
                            <a href="{{ route('agrak.index') }}"
                                class="block px-3 py-2 rounded-lg text-sm text-gray-600 dark:text-gray-300 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-colors">Vista</a>
                            <a href="{{ route('agrak.import.form') }}"
                                class="block px-3 py-2 rounded-lg text-sm text-gray-600 dark:text-gray-300 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-colors">Importar</a>
                        </div>
                    </div>

                    {{-- Bandejas --}}
                    <div x-data="{ open: false }">
                        <button @click="open = !open"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            <span>Bandejas</span>
                            <svg class="w-4 h-4 opacity-40 transition-transform duration-200"
                                :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open" class="mt-1 ml-3 pl-3 border-l-2 border-sky-100 dark:border-sky-900 space-y-0.5"
                            style="display:none">
                            <a href="{{ route('guias.comfrut.index') }}"
                                class="block px-3 py-2 rounded-lg text-sm text-gray-600 dark:text-gray-300 hover:bg-sky-50 dark:hover:bg-sky-900/30 transition-colors">Vista</a>
                            <a href="{{ route('guias.comfrut.import.form') }}"
                                class="block px-3 py-2 rounded-lg text-sm text-gray-600 dark:text-gray-300 hover:bg-sky-50 dark:hover:bg-sky-900/30 transition-colors">Importar
                                XML</a>
                        </div>
                    </div>

                    {{-- FuelControl --}}
                    <div x-data="{ open: false }">
                        <button @click="open = !open"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors {{ request()->routeIs('fuelcontrol.*') ? 'text-orange-600 dark:text-orange-400 font-semibold' : 'text-gray-700 dark:text-gray-200' }}">
                            <span>FuelControl</span>
                            <svg class="w-4 h-4 opacity-40 transition-transform duration-200"
                                :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open"
                            class="mt-1 ml-3 pl-3 border-l-2 border-orange-100 dark:border-orange-900 space-y-0.5"
                            style="display:none">
                            <a href="{{ route('fuelcontrol.index') }}"
                                class="block px-3 py-2 rounded-lg text-sm text-gray-600 dark:text-gray-300 hover:bg-orange-50 dark:hover:bg-orange-900/30 transition-colors">Dashboard</a>
                            <a href="{{ route('fuelcontrol.productos') }}"
                                class="block px-3 py-2 rounded-lg text-sm text-gray-600 dark:text-gray-300 hover:bg-orange-50 dark:hover:bg-orange-900/30 transition-colors">Productos</a>
                            <a href="{{ route('fuelcontrol.vehiculos.index') }}"
                                class="block px-3 py-2 rounded-lg text-sm text-gray-600 dark:text-gray-300 hover:bg-orange-50 dark:hover:bg-orange-900/30 transition-colors">Vehículos</a>
                            <a href="{{ route('fuelcontrol.movimientos') }}"
                                class="block px-3 py-2 rounded-lg text-sm text-gray-600 dark:text-gray-300 hover:bg-orange-50 dark:hover:bg-orange-900/30 transition-colors">Movimientos</a>
                        </div>
                    </div>

                    {{-- Divider + User --}}
                    <div class="pt-3 mt-1 border-t border-gray-100 dark:border-gray-800 space-y-0.5">
                        <a href="{{ route('profile.edit') }}"
                            class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            <div
                                class="h-7 w-7 rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shrink-0">
                                <span
                                    class="text-xs font-bold text-white">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
                            </div>
                            <span>{{ Auth::user()->name }}</span>
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                class="w-full flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
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