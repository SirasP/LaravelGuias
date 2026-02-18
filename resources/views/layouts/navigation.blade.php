{{-- ═══════════════════════════════════════════════════════════
     SIDEBAR NAVIGATION — Colapsable
     ═══════════════════════════════════════════════════════════ --}}

<div x-data="sidebarNav()" x-cloak @toggle-mobile-sidebar.window="mobileOpen = !mobileOpen">

    {{-- ── BACKDROP MOBILE ─────────────────────────────────── --}}
    <div x-show="mobileOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        @click="mobileOpen = false"
        class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-40 lg:hidden" style="display:none"></div>

    {{-- ── SIDEBAR ─────────────────────────────────────────── --}}
    <aside :class="[
            mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
            expanded ? 'w-64' : 'w-[68px]'
        ]"
        class="fixed lg:sticky top-0.5 left-0 h-[calc(100vh-2px)] z-50 lg:z-30
               flex flex-col
               bg-white dark:bg-gray-950
               border-r border-gray-100 dark:border-gray-800
               transition-all duration-300 ease-in-out
               shrink-0 overflow-hidden">

        {{-- ── Logo + Toggle ──────────────────────────────── --}}
        <div class="h-14 flex items-center px-4 gap-3 border-b border-gray-100 dark:border-gray-800 shrink-0">
            <a href="{{ route('index') }}" class="shrink-0 group">
                <x-application-logo class="block h-8 w-auto fill-current text-gray-800 dark:text-gray-100 transition-transform duration-200 group-hover:scale-105" />
            </a>
            <template x-if="expanded">
                <span class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">Agrícola EHE</span>
            </template>
            <button @click="toggle()" class="hidden lg:flex ml-auto p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-800 dark:hover:text-gray-300 transition-colors shrink-0"
                :title="expanded ? 'Colapsar' : 'Expandir'">
                <svg class="w-4 h-4 transition-transform duration-300" :class="{ 'rotate-180': !expanded }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                </svg>
            </button>
        </div>

        {{-- ── Navigation ─────────────────────────────────── --}}
        <nav class="flex-1 overflow-y-auto overflow-x-hidden py-3 px-2.5 space-y-1 sidebar-scroll">

            {{-- ─── SECCIÓN: DOCUMENTOS ─── --}}
            <div class="mb-1" x-show="expanded" x-transition>
                <p class="px-2 pt-2 pb-1 text-[10px] font-bold uppercase tracking-widest text-gray-400">Documentos</p>
            </div>
            <div x-show="!expanded" class="mx-auto w-8 border-t border-gray-200 dark:border-gray-800 my-2"></div>

            {{-- Guías Recepcionadas --}}
            @php $pdfActive = request()->routeIs('pdf.*'); @endphp
            <div>
                <button @click="toggleSection('docs')" :title="!expanded ? 'Guías Recepcionadas' : ''"
                    class="w-full flex items-center gap-3 px-2.5 py-2 rounded-xl text-sm font-medium transition-all duration-150
                        {{ $pdfActive ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-200' }}">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ $pdfActive ? 'bg-indigo-100 dark:bg-indigo-900/40' : 'bg-gray-100 dark:bg-gray-800' }}">
                        <svg class="w-4 h-4 {{ $pdfActive ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <template x-if="expanded">
                        <span class="flex-1 text-left truncate">Guías PDF</span>
                    </template>
                    <template x-if="expanded">
                        <svg class="w-3.5 h-3.5 opacity-40 transition-transform duration-200 shrink-0" :class="{ 'rotate-180': openSection === 'docs' }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                        </svg>
                    </template>
                </button>
                <div x-show="expanded && openSection === 'docs'" x-collapse class="mt-0.5 ml-[18px] pl-4 border-l-2 border-indigo-100 dark:border-indigo-900/50 space-y-0.5">
                    <a href="{{ route('pdf.index') }}" @click="mobileOpen = false"
                        class="block px-3 py-1.5 rounded-lg text-[13px] transition-colors {{ request()->routeIs('pdf.index') ? 'text-indigo-700 dark:text-indigo-300 font-semibold bg-indigo-50/50 dark:bg-indigo-900/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                        PDFs importados</a>
                    <a href="{{ route('pdf.import.form') }}" @click="mobileOpen = false"
                        class="block px-3 py-1.5 rounded-lg text-[13px] transition-colors {{ request()->routeIs('pdf.import.form') ? 'text-indigo-700 dark:text-indigo-300 font-semibold bg-indigo-50/50 dark:bg-indigo-900/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                        Importar PDF</a>
                </div>
            </div>

            {{-- Guías ODOO --}}
            @php $odooActive = request()->routeIs('excel_out_transfers.*'); @endphp
            <div>
                <button @click="toggleSection('odoo')" :title="!expanded ? 'Guías ODOO' : ''"
                    class="w-full flex items-center gap-3 px-2.5 py-2 rounded-xl text-sm font-medium transition-all duration-150
                        {{ $odooActive ? 'bg-violet-50 dark:bg-violet-900/20 text-violet-700 dark:text-violet-300' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-200' }}">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ $odooActive ? 'bg-violet-100 dark:bg-violet-900/40' : 'bg-gray-100 dark:bg-gray-800' }}">
                        <svg class="w-4 h-4 {{ $odooActive ? 'text-violet-600 dark:text-violet-400' : 'text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7" />
                        </svg>
                    </div>
                    <template x-if="expanded">
                        <span class="flex-1 text-left truncate">Guías ODOO</span>
                    </template>
                    <template x-if="expanded">
                        <svg class="w-3.5 h-3.5 opacity-40 transition-transform duration-200 shrink-0" :class="{ 'rotate-180': openSection === 'odoo' }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                        </svg>
                    </template>
                </button>
                <div x-show="expanded && openSection === 'odoo'" x-collapse class="mt-0.5 ml-[18px] pl-4 border-l-2 border-violet-100 dark:border-violet-900/50 space-y-0.5">
                    <a href="{{ route('excel_out_transfers.index') }}" @click="mobileOpen = false"
                        class="block px-3 py-1.5 rounded-lg text-[13px] transition-colors {{ request()->routeIs('excel_out_transfers.index') ? 'text-violet-700 dark:text-violet-300 font-semibold bg-violet-50/50 dark:bg-violet-900/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                        Vista</a>
                    <a href="{{ route('excel_out_transfers.import') }}" @click="mobileOpen = false"
                        class="block px-3 py-1.5 rounded-lg text-[13px] transition-colors {{ request()->routeIs('excel_out_transfers.import') ? 'text-violet-700 dark:text-violet-300 font-semibold bg-violet-50/50 dark:bg-violet-900/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                        Importar</a>
                </div>
            </div>

            {{-- Agrak --}}
            @php $agrakActive = request()->routeIs('agrak.*'); @endphp
            <div>
                <button @click="toggleSection('agrak')" :title="!expanded ? 'Agrak' : ''"
                    class="w-full flex items-center gap-3 px-2.5 py-2 rounded-xl text-sm font-medium transition-all duration-150
                        {{ $agrakActive ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-300' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-200' }}">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ $agrakActive ? 'bg-emerald-100 dark:bg-emerald-900/40' : 'bg-gray-100 dark:bg-gray-800' }}">
                        <svg class="w-4 h-4 {{ $agrakActive ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                        </svg>
                    </div>
                    <template x-if="expanded">
                        <span class="flex-1 text-left truncate">Agrak</span>
                    </template>
                    <template x-if="expanded">
                        <svg class="w-3.5 h-3.5 opacity-40 transition-transform duration-200 shrink-0" :class="{ 'rotate-180': openSection === 'agrak' }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                        </svg>
                    </template>
                </button>
                <div x-show="expanded && openSection === 'agrak'" x-collapse class="mt-0.5 ml-[18px] pl-4 border-l-2 border-emerald-100 dark:border-emerald-900/50 space-y-0.5">
                    <a href="{{ route('agrak.index') }}" @click="mobileOpen = false"
                        class="block px-3 py-1.5 rounded-lg text-[13px] transition-colors {{ request()->routeIs('agrak.index') ? 'text-emerald-700 dark:text-emerald-300 font-semibold bg-emerald-50/50 dark:bg-emerald-900/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                        Vista</a>
                    <a href="{{ route('agrak.import.form') }}" @click="mobileOpen = false"
                        class="block px-3 py-1.5 rounded-lg text-[13px] transition-colors {{ request()->routeIs('agrak.import.form') ? 'text-emerald-700 dark:text-emerald-300 font-semibold bg-emerald-50/50 dark:bg-emerald-900/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                        Importar</a>
                </div>
            </div>

            {{-- XML Recepcionadas --}}
            @php $xmlActive = request()->routeIs('guias.*'); @endphp
            <div>
                <button @click="toggleSection('xml')" :title="!expanded ? 'XML Recepcionadas' : ''"
                    class="w-full flex items-center gap-3 px-2.5 py-2 rounded-xl text-sm font-medium transition-all duration-150
                        {{ $xmlActive ? 'bg-sky-50 dark:bg-sky-900/20 text-sky-700 dark:text-sky-300' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-200' }}">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ $xmlActive ? 'bg-sky-100 dark:bg-sky-900/40' : 'bg-gray-100 dark:bg-gray-800' }}">
                        <svg class="w-4 h-4 {{ $xmlActive ? 'text-sky-600 dark:text-sky-400' : 'text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                    <template x-if="expanded">
                        <span class="flex-1 text-left truncate">XML Recepcionadas</span>
                    </template>
                    <template x-if="expanded">
                        <svg class="w-3.5 h-3.5 opacity-40 transition-transform duration-200 shrink-0" :class="{ 'rotate-180': openSection === 'xml' }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                        </svg>
                    </template>
                </button>
                <div x-show="expanded && openSection === 'xml'" x-collapse class="mt-0.5 ml-[18px] pl-4 border-l-2 border-sky-100 dark:border-sky-900/50 space-y-0.5">
                    <a href="{{ route('guias.comfrut.index') }}" @click="mobileOpen = false"
                        class="block px-3 py-1.5 rounded-lg text-[13px] transition-colors {{ request()->routeIs('guias.comfrut.index') ? 'text-sky-700 dark:text-sky-300 font-semibold bg-sky-50/50 dark:bg-sky-900/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                        Vista</a>
                    <a href="{{ route('guias.comfrut.import.form') }}" @click="mobileOpen = false"
                        class="block px-3 py-1.5 rounded-lg text-[13px] transition-colors {{ request()->routeIs('guias.comfrut.import.form') ? 'text-sky-700 dark:text-sky-300 font-semibold bg-sky-50/50 dark:bg-sky-900/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                        Importar XML</a>
                </div>
            </div>

            {{-- ─── SECCIÓN: COMBUSTIBLE ─── --}}
            <div class="mb-1 mt-3" x-show="expanded" x-transition>
                <p class="px-2 pt-2 pb-1 text-[10px] font-bold uppercase tracking-widest text-gray-400">Combustible</p>
            </div>
            <div x-show="!expanded" class="mx-auto w-8 border-t border-gray-200 dark:border-gray-800 my-2"></div>

            {{-- FuelControl --}}
            @php $fuelActive = request()->routeIs('fuelcontrol.*') || request()->routeIs('gmail.*'); @endphp
            <div>
                <button @click="toggleSection('fuel')" :title="!expanded ? 'FuelControl' : ''"
                    class="w-full flex items-center gap-3 px-2.5 py-2 rounded-xl text-sm font-medium transition-all duration-150
                        {{ $fuelActive ? 'bg-orange-50 dark:bg-orange-900/20 text-orange-700 dark:text-orange-300' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-200' }}">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ $fuelActive ? 'bg-orange-100 dark:bg-orange-900/40' : 'bg-gray-100 dark:bg-gray-800' }}">
                        <svg class="w-4 h-4 {{ $fuelActive ? 'text-orange-600 dark:text-orange-400' : 'text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <template x-if="expanded">
                        <span class="flex-1 text-left truncate">FuelControl</span>
                    </template>
                    <template x-if="expanded">
                        <svg class="w-3.5 h-3.5 opacity-40 transition-transform duration-200 shrink-0" :class="{ 'rotate-180': openSection === 'fuel' }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                        </svg>
                    </template>
                </button>
                <div x-show="expanded && openSection === 'fuel'" x-collapse class="mt-0.5 ml-[18px] pl-4 border-l-2 border-orange-100 dark:border-orange-900/50 space-y-0.5">
                    <a href="{{ route('fuelcontrol.index') }}" @click="mobileOpen = false"
                        class="block px-3 py-1.5 rounded-lg text-[13px] transition-colors {{ request()->routeIs('fuelcontrol.index') ? 'text-orange-700 dark:text-orange-300 font-semibold bg-orange-50/50 dark:bg-orange-900/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                        Dashboard</a>
                    <a href="{{ route('fuelcontrol.productos') }}" @click="mobileOpen = false"
                        class="block px-3 py-1.5 rounded-lg text-[13px] transition-colors {{ request()->routeIs('fuelcontrol.productos') ? 'text-orange-700 dark:text-orange-300 font-semibold bg-orange-50/50 dark:bg-orange-900/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                        Productos</a>
                    <a href="{{ route('fuelcontrol.vehiculos.index') }}" @click="mobileOpen = false"
                        class="block px-3 py-1.5 rounded-lg text-[13px] transition-colors {{ request()->routeIs('fuelcontrol.vehiculos.*') ? 'text-orange-700 dark:text-orange-300 font-semibold bg-orange-50/50 dark:bg-orange-900/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                        Vehículos</a>
                    <a href="{{ route('fuelcontrol.movimientos') }}" @click="mobileOpen = false"
                        class="block px-3 py-1.5 rounded-lg text-[13px] transition-colors {{ request()->routeIs('fuelcontrol.movimientos') ? 'text-orange-700 dark:text-orange-300 font-semibold bg-orange-50/50 dark:bg-orange-900/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                        Movimientos</a>
                    <a href="{{ route('gmail.index') }}" @click="mobileOpen = false"
                        class="block px-3 py-1.5 rounded-lg text-[13px] transition-colors {{ request()->routeIs('gmail.*') ? 'text-indigo-700 dark:text-indigo-300 font-semibold bg-indigo-50/50 dark:bg-indigo-900/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                        Gmail DTE</a>
                </div>
            </div>

        </nav>

        {{-- ── User + Logout ──────────────────────────────── --}}
        <div class="border-t border-gray-100 dark:border-gray-800 p-2.5 shrink-0">
            <div class="flex items-center gap-3 px-2.5 py-2 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                <a href="{{ route('profile.edit') }}" class="shrink-0">
                    <div class="h-8 w-8 rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center">
                        <span class="text-xs font-bold text-white leading-none">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
                    </div>
                </a>
                <template x-if="expanded">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate">{{ Auth::user()->name }}</p>
                        <p class="text-[10px] text-gray-400 truncate">{{ Auth::user()->email }}</p>
                    </div>
                </template>
                <template x-if="expanded">
                    <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                        @csrf
                        <button type="submit" title="Cerrar sesión"
                            class="p-1.5 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                        </button>
                    </form>
                </template>
            </div>
        </div>

    </aside>

</div>

{{-- ── Sidebar JS ─────────────────────────────────────────── --}}
<script>
    function sidebarNav() {
        return {
            expanded: localStorage.getItem('sidebar_state') !== 'collapsed',
            mobileOpen: false,
            openSection: null,

            init() {
                // Auto-abrir la sección activa
                const path = window.location.pathname;
                if (path.startsWith('/pdf') || path.startsWith('/guias-recepcionadas')) this.openSection = 'docs';
                else if (path.startsWith('/excel') || path.startsWith('/odoo')) this.openSection = 'odoo';
                else if (path.startsWith('/agrak')) this.openSection = 'agrak';
                else if (path.startsWith('/guias')) this.openSection = 'xml';
                else if (path.startsWith('/fuelcontrol') || path.startsWith('/gmail')) this.openSection = 'fuel';
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
    .sidebar-scroll::-webkit-scrollbar { width: 4px; }
    .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
    .sidebar-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
    .dark .sidebar-scroll::-webkit-scrollbar-thumb { background: #334155; }
    .sidebar-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>