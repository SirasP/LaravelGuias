{{-- ═══════════════════════════════════════════════════════════
     SIDEBAR NAVIGATION — Colapsable
     ═══════════════════════════════════════════════════════════ --}}

{{-- ── BACKDROP MOBILE ───────────────────────────────────── --}}
<div x-show="mobileOpen"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @click="mobileOpen = false"
    class="fixed inset-0 bg-black/40 backdrop-blur-sm z-40 lg:hidden"
    style="display:none"></div>

{{-- ── SIDEBAR ───────────────────────────────────────────── --}}
<aside
    :class="[mobileOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full lg:translate-x-0', !expanded ? 'lg:cursor-pointer' : '']"
    @click.stop="if(!expanded && window.innerWidth >= 1024) { expanded = true; localStorage.setItem('sidebar_state','expanded'); }"
    class="fixed lg:sticky top-0.5 left-0 z-50 lg:z-30
           h-[calc(100vh-2px)] flex flex-col
           bg-white dark:bg-gray-950
           border-r border-gray-100 dark:border-gray-800
           transition-transform duration-300 ease-[cubic-bezier(.4,0,.2,1)]
           shrink-0 overflow-hidden
           w-64 lg:transition-all"
    :style="!mobileOpen && window.innerWidth >= 1024 ? 'width:' + (expanded ? '256px' : '68px') : ''"
    x-effect="if(window.innerWidth >= 1024) { $el.style.width = expanded ? '256px' : '68px'; }">

    {{-- ── Logo + Toggle ──────────────────────────────────── --}}
    <div class="h-14 flex items-center gap-3 shrink-0 border-b border-gray-100 dark:border-gray-800"
        :class="expanded ? 'px-4' : 'px-0 justify-center'">
        <a href="{{ route('index') }}" class="shrink-0 group flex items-center gap-3" :class="!expanded && 'justify-center w-full'">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shrink-0 shadow-sm shadow-indigo-200 dark:shadow-indigo-900/50 transition-transform duration-200 group-hover:scale-105">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
            </div>
            <span x-show="expanded" x-transition:enter="transition ease-out duration-200 delay-100" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                class="text-sm font-extrabold text-gray-900 dark:text-gray-100 tracking-tight whitespace-nowrap">Agrícola EHE</span>
        </a>
        <button @click="toggle()"
            x-show="expanded"
            class="hidden lg:flex ml-auto p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-800 dark:hover:text-gray-300 transition-all shrink-0"
            title="Colapsar menú">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
            </svg>
        </button>
    </div>

    {{-- ── Botón expandir (cuando está colapsado) ─────────── --}}
    <button @click="toggle()" x-show="!expanded"
        class="hidden lg:flex mx-auto mt-2 p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 dark:hover:text-indigo-400 transition-all"
        title="Expandir menú">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
        </svg>
    </button>

    {{-- ── Navigation ─────────────────────────────────────── --}}
    <nav class="flex-1 overflow-y-auto overflow-x-hidden py-3 sidebar-scroll" :class="expanded ? 'px-2.5' : 'px-1.5'">
        {{-- ─── SECCIÓN: DOCUMENTOS ─── --}}
        <div x-show="expanded" x-transition.opacity.duration.200ms class="mb-1">
            <p class="px-2.5 pt-2 pb-1.5 text-[10px] font-bold uppercase tracking-widest text-gray-400/80">Documentos</p>
        </div>
        <div x-show="!expanded" class="mx-auto w-6 border-t border-gray-200 dark:border-gray-800 my-2.5"></div>

        {{-- Guías Recepcionadas --}}
        @php $pdfActive = request()->routeIs('pdf.*'); @endphp
        <div class="mb-0.5">
            <button @click="toggleSection('docs')" :title="!expanded ? 'Guías PDF' : ''"
                class="w-full flex items-center rounded-xl transition-all duration-150"
                :class="expanded ? 'gap-3 px-2.5 py-2 text-sm font-medium' : 'justify-center px-0 py-2'"
                :style="!expanded ? 'margin:0 auto; width:48px' : ''">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-colors duration-200
                    {{ ($pdfActive ?? false) ? 'bg-indigo-100 dark:bg-indigo-900/40 shadow-sm' : 'bg-gray-50 dark:bg-gray-800/80 hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                    <svg class="w-[18px] h-[18px] transition-colors {{ ($pdfActive ?? false) ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-400 dark:text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <span x-show="expanded" class="flex-1 text-left truncate {{ ($pdfActive ?? false) ? 'text-indigo-700 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-400' }}">Guías PDF</span>
                <svg x-show="expanded" class="w-3.5 h-3.5 text-gray-300 dark:text-gray-600 transition-transform duration-200 shrink-0" :class="{ 'rotate-180': openSection === 'docs' }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="expanded && openSection === 'docs'" x-collapse class="mt-0.5 ml-[22px] pl-3.5 border-l-2 border-indigo-100 dark:border-indigo-900/40 space-y-0.5 pb-1">
                <a href="{{ route('pdf.index') }}" @click="mobileOpen = false"
                    class="block px-3 py-1.5 rounded-lg text-[13px] transition-colors {{ request()->routeIs('pdf.index') ? 'text-indigo-700 dark:text-indigo-300 font-semibold bg-indigo-50 dark:bg-indigo-900/20' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800/50' }}">
                    PDFs importados</a>
                @if(auth()->check() && auth()->user()->role === 'admin')
                    <a href="{{ route('pdf.import.form') }}" @click="mobileOpen = false"
                        class="block px-3 py-1.5 rounded-lg text-[13px] transition-colors {{ request()->routeIs('pdf.import.form') ? 'text-indigo-700 dark:text-indigo-300 font-semibold bg-indigo-50 dark:bg-indigo-900/20' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800/50' }}">
                        Importar PDF</a>
                @endif
            </div>
        </div>

        {{-- Guías ODOO --}}
        @php $odooActive = request()->routeIs('excel_out_transfers.*'); @endphp
        <div class="mb-0.5">
            <button @click="toggleSection('odoo')" :title="!expanded ? 'Guías ODOO' : ''"
                class="w-full flex items-center rounded-xl transition-all duration-150"
                :class="expanded ? 'gap-3 px-2.5 py-2 text-sm font-medium' : 'justify-center px-0 py-2'"
                :style="!expanded ? 'margin:0 auto; width:48px' : ''">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-colors duration-200
                    {{ $odooActive ? 'bg-violet-100 dark:bg-violet-900/40 shadow-sm' : 'bg-gray-50 dark:bg-gray-800/80 hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                    <svg class="w-[18px] h-[18px] transition-colors {{ $odooActive ? 'text-violet-600 dark:text-violet-400' : 'text-gray-400 dark:text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7" />
                    </svg>
                </div>
                <span x-show="expanded" class="flex-1 text-left truncate {{ $odooActive ? 'text-violet-700 dark:text-violet-300' : 'text-gray-600 dark:text-gray-400' }}">Guías ODOO</span>
                <svg x-show="expanded" class="w-3.5 h-3.5 text-gray-300 dark:text-gray-600 transition-transform duration-200 shrink-0" :class="{ 'rotate-180': openSection === 'odoo' }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="expanded && openSection === 'odoo'" x-collapse class="mt-0.5 ml-[22px] pl-3.5 border-l-2 border-violet-100 dark:border-violet-900/40 space-y-0.5 pb-1">
                <a href="{{ route('excel_out_transfers.index') }}" @click="mobileOpen = false"
                    class="block px-3 py-1.5 rounded-lg text-[13px] transition-colors {{ request()->routeIs('excel_out_transfers.index') ? 'text-violet-700 dark:text-violet-300 font-semibold bg-violet-50 dark:bg-violet-900/20' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800/50' }}">
                    Vista</a>
                @if(auth()->check() && auth()->user()->role === 'admin')
                    <a href="{{ route('excel_out_transfers.form') }}" @click="mobileOpen = false"
                        class="block px-3 py-1.5 rounded-lg text-[13px] transition-colors {{ request()->routeIs('excel_out_transfers.form') ? 'text-violet-700 dark:text-violet-300 font-semibold bg-violet-50 dark:bg-violet-900/20' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800/50' }}">
                        Importar</a>
                @endif
            </div>
        </div>

        {{-- Agrak --}}
        @php $agrakActive = request()->routeIs('agrak.*'); @endphp
        <div class="mb-0.5">
            <button @click="toggleSection('agrak')" :title="!expanded ? 'Agrak' : ''"
                class="w-full flex items-center rounded-xl transition-all duration-150"
                :class="expanded ? 'gap-3 px-2.5 py-2 text-sm font-medium' : 'justify-center px-0 py-2'"
                :style="!expanded ? 'margin:0 auto; width:48px' : ''">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-colors duration-200
                    {{ $agrakActive ? 'bg-emerald-100 dark:bg-emerald-900/40 shadow-sm' : 'bg-gray-50 dark:bg-gray-800/80 hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                    <svg class="w-[18px] h-[18px] transition-colors {{ $agrakActive ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                    </svg>
                </div>
                <span x-show="expanded" class="flex-1 text-left truncate {{ $agrakActive ? 'text-emerald-700 dark:text-emerald-300' : 'text-gray-600 dark:text-gray-400' }}">Agrak</span>
                <svg x-show="expanded" class="w-3.5 h-3.5 text-gray-300 dark:text-gray-600 transition-transform duration-200 shrink-0" :class="{ 'rotate-180': openSection === 'agrak' }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="expanded && openSection === 'agrak'" x-collapse class="mt-0.5 ml-[22px] pl-3.5 border-l-2 border-emerald-100 dark:border-emerald-900/40 space-y-0.5 pb-1">
                <a href="{{ route('agrak.index') }}" @click="mobileOpen = false"
                    class="block px-3 py-1.5 rounded-lg text-[13px] transition-colors {{ request()->routeIs('agrak.index') ? 'text-emerald-700 dark:text-emerald-300 font-semibold bg-emerald-50 dark:bg-emerald-900/20' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800/50' }}">
                    Vista</a>
                @if(auth()->check() && auth()->user()->role === 'admin')
                    <a href="{{ route('agrak.import.form') }}" @click="mobileOpen = false"
                        class="block px-3 py-1.5 rounded-lg text-[13px] transition-colors {{ request()->routeIs('agrak.import.form') ? 'text-emerald-700 dark:text-emerald-300 font-semibold bg-emerald-50 dark:bg-emerald-900/20' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800/50' }}">
                        Importar</a>
                @endif
            </div>
        </div>

        {{-- XML Recepcionadas --}}
        @php $xmlActive = request()->routeIs('guias.*'); @endphp
        <div class="mb-0.5">
            <button @click="toggleSection('xml')" :title="!expanded ? 'XML Recepcionadas' : ''"
                class="w-full flex items-center rounded-xl transition-all duration-150"
                :class="expanded ? 'gap-3 px-2.5 py-2 text-sm font-medium' : 'justify-center px-0 py-2'"
                :style="!expanded ? 'margin:0 auto; width:48px' : ''">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-colors duration-200
                    {{ $xmlActive ? 'bg-sky-100 dark:bg-sky-900/40 shadow-sm' : 'bg-gray-50 dark:bg-gray-800/80 hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                    <svg class="w-[18px] h-[18px] transition-colors {{ $xmlActive ? 'text-sky-600 dark:text-sky-400' : 'text-gray-400 dark:text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                </div>
                <span x-show="expanded" class="flex-1 text-left truncate {{ $xmlActive ? 'text-sky-700 dark:text-sky-300' : 'text-gray-600 dark:text-gray-400' }}">XML Recepcionadas</span>
                <svg x-show="expanded" class="w-3.5 h-3.5 text-gray-300 dark:text-gray-600 transition-transform duration-200 shrink-0" :class="{ 'rotate-180': openSection === 'xml' }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="expanded && openSection === 'xml'" x-collapse class="mt-0.5 ml-[22px] pl-3.5 border-l-2 border-sky-100 dark:border-sky-900/40 space-y-0.5 pb-1">
                <a href="{{ route('guias.comfrut.index') }}" @click="mobileOpen = false"
                    class="block px-3 py-1.5 rounded-lg text-[13px] transition-colors {{ request()->routeIs('guias.comfrut.index') ? 'text-sky-700 dark:text-sky-300 font-semibold bg-sky-50 dark:bg-sky-900/20' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800/50' }}">
                    Vista</a>
                @if(auth()->check() && auth()->user()->role === 'admin')
                    <a href="{{ route('guias.comfrut.import.form') }}" @click="mobileOpen = false"
                        class="block px-3 py-1.5 rounded-lg text-[13px] transition-colors {{ request()->routeIs('guias.comfrut.import.form') ? 'text-sky-700 dark:text-sky-300 font-semibold bg-sky-50 dark:bg-sky-900/20' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800/50' }}">
                        Importar XML</a>
                @endif
            </div>
        </div>

        {{-- ─── SECCIÓN: COMBUSTIBLE ─── --}}
        <div x-show="expanded" x-transition.opacity.duration.200ms class="mb-1 mt-4">
            <p class="px-2.5 pt-2 pb-1.5 text-[10px] font-bold uppercase tracking-widest text-gray-400/80">Combustible</p>
        </div>
        <div x-show="!expanded" class="mx-auto w-6 border-t border-gray-200 dark:border-gray-800 my-2.5"></div>

        {{-- FuelControl --}}
        @php $fuelActive = request()->routeIs('fuelcontrol.*') || (request()->routeIs('gmail.*') && !request()->routeIs('gmail.dtes.*')); @endphp
        <div class="mb-0.5">
            <button @click="toggleSection('fuel')" :title="!expanded ? 'FuelControl' : ''"
                class="w-full flex items-center rounded-xl transition-all duration-150"
                :class="expanded ? 'gap-3 px-2.5 py-2 text-sm font-medium' : 'justify-center px-0 py-2'"
                :style="!expanded ? 'margin:0 auto; width:48px' : ''">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-colors duration-200
                    {{ $fuelActive ? 'bg-orange-100 dark:bg-orange-900/40 shadow-sm' : 'bg-gray-50 dark:bg-gray-800/80 hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                    <svg class="w-[18px] h-[18px] transition-colors {{ $fuelActive ? 'text-orange-600 dark:text-orange-400' : 'text-gray-400 dark:text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <span x-show="expanded" class="flex-1 text-left truncate {{ $fuelActive ? 'text-orange-700 dark:text-orange-300' : 'text-gray-600 dark:text-gray-400' }}">FuelControl</span>
                <svg x-show="expanded" class="w-3.5 h-3.5 text-gray-300 dark:text-gray-600 transition-transform duration-200 shrink-0" :class="{ 'rotate-180': openSection === 'fuel' }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="expanded && openSection === 'fuel'" x-collapse class="mt-0.5 ml-[22px] pl-3.5 border-l-2 border-orange-100 dark:border-orange-900/40 space-y-0.5 pb-1">
                <a href="{{ route('fuelcontrol.index') }}" @click="mobileOpen = false"
                    class="block px-3 py-1.5 rounded-lg text-[13px] transition-colors {{ request()->routeIs('fuelcontrol.index') ? 'text-orange-700 dark:text-orange-300 font-semibold bg-orange-50 dark:bg-orange-900/20' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800/50' }}">
                    Dashboard</a>
                <a href="{{ route('fuelcontrol.productos') }}" @click="mobileOpen = false"
                    class="block px-3 py-1.5 rounded-lg text-[13px] transition-colors {{ request()->routeIs('fuelcontrol.productos') ? 'text-orange-700 dark:text-orange-300 font-semibold bg-orange-50 dark:bg-orange-900/20' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800/50' }}">
                    Productos</a>
                <a href="{{ route('fuelcontrol.vehiculos.index') }}" @click="mobileOpen = false"
                    class="block px-3 py-1.5 rounded-lg text-[13px] transition-colors {{ request()->routeIs('fuelcontrol.vehiculos.*') ? 'text-orange-700 dark:text-orange-300 font-semibold bg-orange-50 dark:bg-orange-900/20' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800/50' }}">
                    Vehículos</a>
                <a href="{{ route('fuelcontrol.movimientos') }}" @click="mobileOpen = false"
                    class="block px-3 py-1.5 rounded-lg text-[13px] transition-colors {{ request()->routeIs('fuelcontrol.movimientos') ? 'text-orange-700 dark:text-orange-300 font-semibold bg-orange-50 dark:bg-orange-900/20' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800/50' }}">
                    Movimientos</a>
                @if(auth()->check() && auth()->user()->role === 'admin')
                    <a href="{{ route('gmail.index') }}" @click="mobileOpen = false"
                        class="block px-3 py-1.5 rounded-lg text-[13px] transition-colors {{ request()->routeIs('gmail.*') ? 'text-indigo-700 dark:text-indigo-300 font-semibold bg-indigo-50 dark:bg-indigo-900/20' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800/50' }}">
                        Gmail DTE</a>
                @endif
            </div>
        </div>

        @if(auth()->check() && auth()->user()->role === 'admin')
            {{-- Dte XML (módulo nuevo) --}}
            @php $dteProvActive = request()->routeIs('gmail.dtes.*'); @endphp
            <div class="mb-0.5">
                <button @click="toggleSection('dteprov')" :title="!expanded ? 'Dte XML' : ''"
                    class="w-full flex items-center rounded-xl transition-all duration-150"
                    :class="expanded ? 'gap-3 px-2.5 py-2 text-sm font-medium' : 'justify-center px-0 py-2'"
                    :style="!expanded ? 'margin:0 auto; width:48px' : ''">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-colors duration-200
                        {{ $dteProvActive ? 'bg-cyan-100 dark:bg-cyan-900/40 shadow-sm' : 'bg-gray-50 dark:bg-gray-800/80 hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                        <svg class="w-[18px] h-[18px] transition-colors {{ $dteProvActive ? 'text-cyan-600 dark:text-cyan-400' : 'text-gray-400 dark:text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <span x-show="expanded" class="flex-1 text-left truncate {{ $dteProvActive ? 'text-cyan-700 dark:text-cyan-300' : 'text-gray-600 dark:text-gray-400' }}">Dte XML</span>
                    <svg x-show="expanded" class="w-3.5 h-3.5 text-gray-300 dark:text-gray-600 transition-transform duration-200 shrink-0" :class="{ 'rotate-180': openSection === 'dteprov' }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div x-show="expanded && openSection === 'dteprov'" x-collapse class="mt-0.5 ml-[22px] pl-3.5 border-l-2 border-cyan-100 dark:border-cyan-900/40 space-y-0.5 pb-1">
                    <a href="{{ route('gmail.dtes.index') }}" @click="mobileOpen = false"
                        class="block px-3 py-1.5 rounded-lg text-[13px] transition-colors {{ request()->routeIs('gmail.dtes.*') ? 'text-cyan-700 dark:text-cyan-300 font-semibold bg-cyan-50 dark:bg-cyan-900/20' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800/50' }}">
                        Vista DTE</a>
                </div>
            </div>
        @endif

        {{-- ─── SECCIÓN: ADMINISTRACIÓN (solo admin) ─── --}}
        @if(auth()->check() && auth()->user()->role === 'admin')
            <div x-show="expanded" x-transition.opacity.duration.200ms class="mb-1 mt-4">
                <p class="px-2.5 pt-2 pb-1.5 text-[10px] font-bold uppercase tracking-widest text-gray-400/80">Administración</p>
            </div>
            <div x-show="!expanded" class="mx-auto w-6 border-t border-gray-200 dark:border-gray-800 my-2.5"></div>

            @php $usersActive = request()->routeIs('dashboard') || request()->routeIs('users.*'); @endphp
            <a href="{{ route('dashboard') }}" @click="mobileOpen = false" :title="!expanded ? 'Usuarios' : ''"
                class="flex items-center rounded-xl transition-all duration-150 mb-0.5"
                :class="expanded ? 'gap-3 px-2.5 py-2' : 'justify-center py-2'"
                :style="!expanded ? 'margin:0 auto; width:48px' : ''">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-colors duration-200
                    {{ $usersActive ? 'bg-rose-100 dark:bg-rose-900/40 shadow-sm' : 'bg-gray-50 dark:bg-gray-800/80 hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                    <svg class="w-[18px] h-[18px] transition-colors {{ $usersActive ? 'text-rose-600 dark:text-rose-400' : 'text-gray-400 dark:text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <span x-show="expanded" class="text-sm font-medium truncate {{ $usersActive ? 'text-rose-700 dark:text-rose-300' : 'text-gray-600 dark:text-gray-400' }}">Usuarios</span>
            </a>

        @endif

    </nav>

    {{-- ── User Panel ─────────────────────────────────────── --}}
    <div class="border-t border-gray-100 dark:border-gray-800 shrink-0" :class="expanded ? 'p-2.5' : 'p-1.5'">

        {{-- Avatar (colapsado: solo avatar centrado) --}}
        <div x-show="!expanded" class="flex justify-center py-1">
            <a href="{{ route('profile.edit') }}" title="{{ Auth::user()->name }}"
                class="h-9 w-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-sm shadow-indigo-200 dark:shadow-indigo-900/50 hover:scale-105 transition-transform">
                <span class="text-xs font-bold text-white leading-none">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
            </a>
        </div>

        {{-- Panel completo (expandido) --}}
        <div x-show="expanded" x-transition.opacity.duration.200ms
            class="rounded-xl bg-gray-50 dark:bg-gray-900/50 p-3">
            <div class="flex items-center gap-3 mb-2.5">
                <div class="h-9 w-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shrink-0 shadow-sm shadow-indigo-200 dark:shadow-indigo-900/50">
                    <span class="text-xs font-bold text-white leading-none">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-gray-800 dark:text-gray-100 truncate">{{ Auth::user()->name }}</p>
                    <p class="text-[10px] text-gray-400 truncate">{{ Auth::user()->email }}</p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-1.5">
                <a href="{{ route('profile.edit') }}"
                    class="h-8 flex items-center justify-center gap-1.5 px-3 rounded-lg text-xs font-semibold
                           text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-800
                           border border-gray-200 dark:border-gray-700
                           hover:border-indigo-300 hover:text-indigo-600 dark:hover:text-indigo-400
                           transition-colors">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    Perfil
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="h-8 w-full flex items-center justify-center gap-1.5 px-3 rounded-lg text-xs font-semibold
                               text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-800
                               border border-gray-200 dark:border-gray-700
                               hover:border-red-300 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20
                               transition-colors">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        Salir
                    </button>
                </form>
            </div>
        </div>

    </div>

</aside>

{{-- ── Sidebar JS ─────────────────────────────────────────── --}}
<script>
    function sidebarNav() {
        return {
            expanded: localStorage.getItem('sidebar_state') === 'expanded',
            mobileOpen: false,
            openSection: null,

            init() {
                // Auto-abrir la sección activa
                const path = window.location.pathname;
                if (path.startsWith('/pdf') || path.startsWith('/guias-recepcionadas')) this.openSection = 'docs';
                else if (path.startsWith('/excel') || path.startsWith('/odoo')) this.openSection = 'odoo';
                else if (path.startsWith('/agrak')) this.openSection = 'agrak';
                else if (path.startsWith('/guias')) this.openSection = 'xml';
                else if (path.startsWith('/gmail/dtes')) this.openSection = 'dteprov';
                else if (path.startsWith('/fuelcontrol') || path.startsWith('/gmail')) this.openSection = 'fuel';

                // En mobile siempre expandido cuando se abre
                this.$watch('mobileOpen', (val) => {
                    if (val && window.innerWidth < 1024) {
                        this.expanded = true;
                    }
                });
            },

            toggle() {
                this.expanded = !this.expanded;
                localStorage.setItem('sidebar_state', this.expanded ? 'expanded' : 'collapsed');
                if (!this.expanded) this.openSection = null;
            },

            toggleSection(name) {
                if (!this.expanded) {
                    this.expanded = true;
                    localStorage.setItem('sidebar_state', 'expanded');
                    this.$nextTick(() => { this.openSection = name; });
                    return;
                }
                this.openSection = this.openSection === name ? null : name;
            }
        };
    }
</script>

<style>
    .sidebar-scroll::-webkit-scrollbar { width: 3px; }
    .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
    .sidebar-scroll::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 99px; }
    .dark .sidebar-scroll::-webkit-scrollbar-thumb { background: #1e293b; }
    .sidebar-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>
