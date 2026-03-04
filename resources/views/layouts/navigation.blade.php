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
    class="fixed inset-0 bg-gray-900/60 backdrop-blur-md z-40 lg:hidden"
    style="display:none"></div>

{{-- ── SIDEBAR ───────────────────────────────────────────── --}}
<aside
    :class="[mobileOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full lg:translate-x-0', !expanded ? 'lg:cursor-pointer' : '']"
    @click.stop="if(!expanded && window.innerWidth >= 1024) { expandFromRail(); }"
    class="fixed lg:sticky top-0.5 left-0 z-50 lg:z-30
           h-[calc(100vh-2px)] flex flex-col
           bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl
           border-r border-gray-100 dark:border-gray-800
           transition-all duration-300 ease-[cubic-bezier(.4,0,.2,1)]
           shrink-0 overflow-hidden
           w-[280px] sm:w-64 lg:transition-all"
    :style="!mobileOpen && window.innerWidth >= 1024 ? 'width:' + (expanded ? '256px' : '68px') : ''"
    x-effect="if(window.innerWidth >= 1024) { $el.style.width = expanded ? '256px' : '68px'; }">

    {{-- ── Logo + Toggle ──────────────────────────────────── --}}
    <div class="h-14 flex items-center gap-3 shrink-0 border-b border-gray-100 dark:border-gray-800"
        :class="expanded ? 'px-4' : 'px-0 justify-center'">
        <a href="{{ route('index') }}" class="shrink-0 group flex items-center gap-3" :class="!expanded && 'justify-center w-full'">
            <div class="w-9 h-9 flex items-center justify-center shrink-0 transition-transform duration-200 group-hover:scale-105 overflow-hidden">
                <x-application-logo class="w-8 h-8" />
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
        class="hidden lg:flex mx-auto mt-2 p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 dark:hover:text-indigo-400 transition-all relative group">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
        </svg>
        <div class="absolute left-full ml-3 px-2.5 py-1.5 bg-gray-900 dark:bg-gray-800 text-white text-xs font-semibold rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all whitespace-nowrap z-50 pointer-events-none">
            Expandir menú
            <div class="absolute top-1/2 -left-1 -translate-y-1/2 border-[5px] border-transparent border-r-gray-900 dark:border-r-gray-800"></div>
        </div>
    </button>

    {{-- ── Navigation ─────────────────────────────────────── --}}
    <nav class="flex-1 overflow-y-auto overflow-x-hidden py-3 sidebar-scroll" :class="expanded ? 'px-2.5' : 'px-1.5'">
        @if(auth()->check() && in_array(auth()->user()->role, ['admin', 'viewer']))
        {{-- ─── SECCIÓN: RECEPCIÓN ─── --}}
        <div x-show="expanded" x-transition.opacity.duration.200ms class="mb-1">
            <p class="px-2.5 pt-2 pb-1.5 text-[10px] font-bold uppercase tracking-widest text-gray-400/80">Recepción</p>
        </div>
        <div x-show="!expanded" class="mx-auto w-6 border-t border-gray-200 dark:border-gray-800 my-2.5"></div>

        {{-- Documentos (colapsable) --}}
        @php $docsActive = request()->routeIs('pdf.*') || request()->routeIs('excel_out_transfers.*') || request()->routeIs('agrak.*') || request()->routeIs('guias.comfrut.*'); @endphp
        <x-nav-item id="docs" label="Documentos" iconBgColor="indigo" :active="$docsActive">
            <x-slot name="icon">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </x-slot>
            <x-nav-sublink href="{{ route('pdf.index') }}" :active="request()->routeIs('pdf.index') || request()->routeIs('pdf.import.*')" color="indigo">Doc. Recibidos Centros</x-nav-sublink>
            <x-nav-sublink href="{{ route('excel_out_transfers.index') }}" :active="request()->routeIs('excel_out_transfers.*')" color="indigo">Match</x-nav-sublink>
            <x-nav-sublink href="{{ route('agrak.index') }}" :active="request()->routeIs('agrak.*')" color="indigo">Agrak</x-nav-sublink>
            <x-nav-sublink href="{{ route('guias.comfrut.index') }}" :active="request()->routeIs('guias.comfrut.*')" color="indigo">XML Gmail Transporte</x-nav-sublink>
        </x-nav-item>

        {{-- IMPORTAR TODO (Admin Direct Link) --}}
        @if(auth()->check() && auth()->user()->role === 'admin')
            @php $importActive = request()->routeIs('pdf.import.form'); @endphp
            <a href="{{ route('pdf.import.form') }}" @click="mobileOpen = false"
                class="flex items-center rounded-xl transition-all duration-150 mb-0.5 relative group"
                :class="expanded ? 'gap-3 px-2.5 py-2' : 'justify-center py-2'"
                :style="!expanded ? 'margin:0 auto; width:48px' : ''">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-colors duration-200
                    {{ $importActive ? 'bg-violet-100 dark:bg-violet-900/40 shadow-sm' : 'bg-gray-50 dark:bg-gray-800/80 group-hover:bg-gray-100 dark:group-hover:bg-gray-800' }}">
                    <svg class="w-[18px] h-[18px] transition-colors {{ $importActive ? 'text-violet-600 dark:text-violet-400' : 'text-gray-400 dark:text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                </div>
                <span x-show="expanded" class="text-sm font-bold truncate {{ $importActive ? 'text-violet-700 dark:text-violet-300' : 'text-gray-600 dark:text-gray-400' }}">Importar TODO</span>
                <div x-show="!expanded" class="absolute left-full ml-3 px-2.5 py-1.5 bg-gray-900 dark:bg-gray-800 text-white text-xs font-semibold rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all whitespace-nowrap z-50 pointer-events-none">
                    Importar TODO
                    <div class="absolute top-1/2 -left-1 -translate-y-1/2 border-[5px] border-transparent border-r-gray-900 dark:border-r-gray-800"></div>
                </div>
            </a>
        @endif

        @if(auth()->check() && in_array(auth()->user()->role, ['admin', 'viewer']))
            {{-- ─── SECCIÓN: COMBUSTIBLE ─── --}}
            <div x-show="expanded" x-transition.opacity.duration.200ms class="mb-1 mt-4">
                <p class="px-2.5 pt-2 pb-1.5 text-[10px] font-bold uppercase tracking-widest text-gray-400/80">Combustible</p>
            </div>
            <div x-show="!expanded" class="mx-auto w-6 border-t border-gray-200 dark:border-gray-800 my-2.5"></div>

            {{-- FuelControl --}}
            @php $fuelActive = request()->routeIs('fuelcontrol.*'); @endphp
            <x-nav-item id="fuel" label="FuelControl" iconBgColor="orange" :active="$fuelActive">
                <x-slot name="icon">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </x-slot>
                <x-nav-sublink href="{{ route('fuelcontrol.index') }}" :active="request()->routeIs('fuelcontrol.index')" color="orange">Dashboard</x-nav-sublink>
                <x-nav-sublink href="{{ route('fuelcontrol.productos') }}" :active="request()->routeIs('fuelcontrol.productos')" color="orange">Productos</x-nav-sublink>
                <x-nav-sublink href="{{ route('fuelcontrol.vehiculos.index') }}" :active="request()->routeIs('fuelcontrol.vehiculos.*')" color="orange">Vehículos</x-nav-sublink>
                <x-nav-sublink href="{{ route('fuelcontrol.movimientos') }}" :active="request()->routeIs('fuelcontrol.movimientos')" color="orange">Movimientos</x-nav-sublink>
            </x-nav-item>
        @endif
        @endif

        @if(auth()->check() && in_array(auth()->user()->role, ['admin', 'bodeguero']))
            {{-- ─── SECCIÓN: FACTURAS PROVEEDOR (admin + bodeguero) ─── --}}
            <div x-show="expanded" x-transition.opacity.duration.200ms class="mb-1 mt-4">
                <p class="px-2.5 pt-2 pb-1.5 text-[10px] font-bold uppercase tracking-widest text-gray-400/80">Facturas Proveedor</p>
            </div>
            <div x-show="!expanded" class="mx-auto w-6 border-t border-gray-200 dark:border-gray-800 my-2.5"></div>

            @php $dteProvActive = request()->routeIs('gmail.dtes.*'); @endphp
            {{-- DTE PROVEEDOR --}}
            @php $dteProvActive = request()->routeIs('gmail.dtes.*'); @endphp
            <x-nav-item id="dteprov" label="Facturas P." iconBgColor="cyan" :active="$dteProvActive">
                <x-slot name="icon">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </x-slot>
                @if(auth()->user()->role === 'admin')
                    <x-nav-sublink href="{{ route('gmail.dtes.index') }}" :active="request()->routeIs('gmail.dtes.index') || request()->routeIs('gmail.dtes.facturas.index') || request()->routeIs('gmail.dtes.boletas.index')" color="cyan">Tablero</x-nav-sublink>
                @endif
                <x-nav-sublink href="{{ route('gmail.dtes.facturas.list') }}" :active="request()->routeIs('gmail.dtes.facturas.list') || request()->routeIs('gmail.dtes.show') || request()->routeIs('gmail.dtes.print')" color="cyan">Facturas</x-nav-sublink>
                <x-nav-sublink href="{{ route('gmail.dtes.boletas.list') }}" :active="request()->routeIs('gmail.dtes.boletas.list')" color="cyan">Boletas</x-nav-sublink>
                <x-nav-sublink href="{{ route('gmail.dtes.guias.list') }}" :active="request()->routeIs('gmail.dtes.guias.list')" color="cyan">Guías</x-nav-sublink>
            </x-nav-item>
        @endif

        @if(auth()->check() && auth()->user()->role === 'admin')
            <div x-show="expanded" x-transition.opacity.duration.200ms class="mb-1 mt-4">
                <p class="px-2.5 pt-2 pb-1.5 text-[10px] font-bold uppercase tracking-widest text-gray-400/80">Cotizaciones</p>
            </div>
            <div x-show="!expanded" class="mx-auto w-6 border-t border-gray-200 dark:border-gray-800 my-2.5"></div>

            @php $poActive = request()->routeIs('purchase_orders.*'); @endphp
            <x-nav-item id="oc" label="Cotizaciones" iconBgColor="emerald" :active="$poActive">
                <x-slot name="icon">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </x-slot>
                <x-nav-sublink href="{{ route('purchase_orders.index') }}" :active="request()->routeIs('purchase_orders.index') || request()->routeIs('purchase_orders.show')" color="emerald">Tablero</x-nav-sublink>
                <x-nav-sublink href="{{ route('purchase_orders.create') }}" :active="request()->routeIs('purchase_orders.create')" color="emerald">Nueva cotización</x-nav-sublink>
            </x-nav-item>
        @endif

        @if(auth()->check() && in_array(auth()->user()->role, ['admin', 'bodeguero']))
            {{-- ─── SECCIÓN: INVENTARIO (admin + bodeguero) ─── --}}
            <div x-show="expanded" x-transition.opacity.duration.200ms class="mb-1 mt-4">
                <p class="px-2.5 pt-2 pb-1.5 text-[10px] font-bold uppercase tracking-widest text-gray-400/80">Inventario</p>
            </div>
            <div x-show="!expanded" class="mx-auto w-6 border-t border-gray-200 dark:border-gray-800 my-2.5"></div>

            @php $dteInventoryActive = request()->routeIs('gmail.inventory.*') && !request()->routeIs('gmail.inventory.sii.status'); @endphp
            <x-nav-item id="dteinv" label="Inventario" iconBgColor="violet" :active="$dteInventoryActive">
                <x-slot name="icon">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </x-slot>
                <x-nav-sublink href="{{ route('gmail.inventory.index') }}" :active="request()->routeIs('gmail.inventory.index')" color="violet">Tablero</x-nav-sublink>
                <x-nav-sublink href="{{ route('gmail.inventory.list') }}" :active="request()->routeIs('gmail.inventory.list')" color="violet">Listado</x-nav-sublink>
                <x-nav-sublink href="{{ route('gmail.inventory.exits') }}" :active="request()->routeIs('gmail.inventory.exits')" color="rose">Registro Salidas</x-nav-sublink>
                @if(auth()->user()->canSeeValues())
                    <x-nav-sublink href="{{ route('gmail.inventory.valuation') }}" :active="request()->routeIs('gmail.inventory.valuation')" color="violet">Valorizado</x-nav-sublink>
                @endif
                @if(in_array(auth()->user()->role, ['admin', 'bodeguero']))
                    <x-nav-sublink href="{{ route('gmail.inventory.exit.create') }}" :active="request()->routeIs('gmail.inventory.exit.create')" color="rose">Nueva Salida</x-nav-sublink>
                    <x-nav-sublink href="{{ route('gmail.inventory.adjust.create') }}" :active="request()->routeIs('gmail.inventory.adjust.create')" color="orange">Ajuste Stock</x-nav-sublink>
                    <x-nav-sublink href="{{ route('gmail.inventory.adjustments') }}" :active="request()->routeIs('gmail.inventory.adjustments')" color="orange">Historial Ajustes</x-nav-sublink>
                @endif
            </x-nav-item>
        @endif

        {{-- ─── SECCIÓN: ADMINISTRACIÓN (solo admin) ─── --}}
        @if(auth()->check() && auth()->user()->role === 'admin')
            <div x-show="expanded" x-transition.opacity.duration.200ms class="mb-1 mt-4">
                <p class="px-2.5 pt-2 pb-1.5 text-[10px] font-bold uppercase tracking-widest text-gray-400/80">Administración</p>
            </div>
            <div x-show="!expanded" class="mx-auto w-6 border-t border-gray-200 dark:border-gray-800 my-2.5"></div>

            @php $usersActive = request()->routeIs('dashboard') || request()->routeIs('users.*'); @endphp
            <a href="{{ route('dashboard') }}" @click="mobileOpen = false"
                class="flex items-center rounded-xl transition-all duration-150 mb-0.5 relative group"
                :class="expanded ? 'gap-3 px-2.5 py-2' : 'justify-center py-2'"
                :style="!expanded ? 'margin:0 auto; width:48px' : ''">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-colors duration-200
                    {{ $usersActive ? 'bg-rose-100 dark:bg-rose-900/40 shadow-sm' : 'bg-gray-50 dark:bg-gray-800/80 group-hover:bg-gray-100 dark:group-hover:bg-gray-800' }}">
                    <svg class="w-[18px] h-[18px] transition-colors {{ $usersActive ? 'text-rose-600 dark:text-rose-400' : 'text-gray-400 dark:text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <span x-show="expanded" class="text-sm font-medium truncate {{ $usersActive ? 'text-rose-700 dark:text-rose-300' : 'text-gray-600 dark:text-gray-400' }}">Usuarios</span>
                <div x-show="!expanded" class="absolute left-full ml-3 px-2.5 py-1.5 bg-gray-900 dark:bg-gray-800 text-white text-xs font-semibold rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all whitespace-nowrap z-50 pointer-events-none">
                    Usuarios
                    <div class="absolute top-1/2 -left-1 -translate-y-1/2 border-[5px] border-transparent border-r-gray-900 dark:border-r-gray-800"></div>
                </div>
            </a>

            @php $configActive = request()->routeIs('gmail.inventory.sii.status') || (request()->routeIs('gmail.*') && !request()->routeIs('gmail.dtes.*') && !request()->routeIs('gmail.inventory.*')); @endphp
            <x-nav-item id="config" label="Configuraciones" iconBgColor="indigo" :active="$configActive">
                <x-slot name="icon">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317a1 1 0 011.35-.936l1.618.708a1 1 0 001.042-.145l1.28-1.024a1 1 0 011.451.19l1.06 1.414a1 1 0 00.94.386l1.619-.27a1 1 0 011.133.966l.087 1.767a1 1 0 00.555.835l1.52.79a1 1 0 01.433 1.37l-.79 1.52a1 1 0 000 .928l.79 1.52a1 1 0 01-.433 1.37l-1.52.79a1 1 0 00-.555.835l-.087 1.767a1 1 0 01-1.133.966l-1.619-.27a1 1 0 00-.94.386l-1.06 1.414a1 1 0 01-1.451.19l-1.28-1.024a1 1 0 00-1.042-.145l-1.618.708a1 1 0 01-1.35-.936l-.24-1.733a1 1 0 00-.666-.796l-1.666-.555a1 1 0 01-.617-1.304l.555-1.666a1 1 0 00-.14-.908l-1.024-1.28a1 1 0 01.19-1.451l1.414-1.06a1 1 0 00.386-.94l-.27-1.619a1 1 0 01.966-1.133l1.767-.087a1 1 0 00.835-.555l.79-1.52a1 1 0 011.37-.433l1.52.79a1 1 0 00.928 0l1.52-.79z" />
                </x-slot>
                <x-nav-sublink href="{{ route('gmail.inventory.sii.status') }}" :active="request()->routeIs('gmail.inventory.sii.status')" color="indigo">Configuraciones</x-nav-sublink>
                <x-nav-sublink href="{{ route('gmail.index') }}" :active="(request()->routeIs('gmail.*') && !request()->routeIs('gmail.dtes.*') && !request()->routeIs('gmail.inventory.*'))" color="indigo">Gmail DTE</x-nav-sublink>
            </x-nav-item>

        @endif

    </nav>

    {{-- ── User Panel ─────────────────────────────────────── --}}
    <div class="border-t border-gray-100 dark:border-gray-800 shrink-0" :class="expanded ? 'p-2.5' : 'p-1.5'">

        {{-- Avatar (colapsado: solo avatar centrado) --}}
        <div x-show="!expanded" class="flex justify-center py-1 relative group">
            <a href="{{ route('profile.edit') }}"
                class="h-9 w-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-sm shadow-indigo-200 dark:shadow-indigo-900/50 hover:scale-105 transition-transform">
                <span class="text-xs font-bold text-white leading-none">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
            </a>
            <div class="absolute left-full ml-3 px-2.5 py-1.5 bg-gray-900 dark:bg-gray-800 text-white text-xs font-semibold rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all whitespace-nowrap z-50 pointer-events-none mt-1">
                {{ Auth::user()->name }}
                <div class="absolute top-1/2 -left-1 -translate-y-1/2 border-[5px] border-transparent border-r-gray-900 dark:border-r-gray-800"></div>
            </div>
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
                this.openSection = this.detectCurrentSection();

                // En mobile siempre expandido cuando se abre
                this.$watch('mobileOpen', (val) => {
                    if (val && window.innerWidth < 1024) {
                        this.expanded = true;
                    }
                });

                this.$watch('expanded', (val) => {
                    if (val) {
                        if (!this.openSection) this.openSection = this.detectCurrentSection();
                        this.scrollToActiveNav(220);
                    }
                });

                this.$watch('openSection', () => {
                    if (this.expanded) this.scrollToActiveNav(220);
                });

                if (this.expanded) {
                    this.scrollToActiveNav(0);
                }
            },

            toggle() {
                this.expanded = !this.expanded;
                localStorage.setItem('sidebar_state', this.expanded ? 'expanded' : 'collapsed');
                if (!this.expanded) {
                    this.openSection = null;
                } else if (!this.openSection) {
                    this.openSection = this.detectCurrentSection();
                }
            },

            expandFromRail() {
                this.expanded = true;
                localStorage.setItem('sidebar_state', 'expanded');
                if (!this.openSection) this.openSection = this.detectCurrentSection();
                this.scrollToActiveNav(220);
            },

            detectCurrentSection() {
                const sections = [
                    { name: 'docs', active: @json(request()->routeIs('pdf.*') || request()->routeIs('excel_out_transfers.*') || request()->routeIs('agrak.*') || request()->routeIs('guias.comfrut.*')) },
                    { name: 'dteprov', active: @json(request()->routeIs('gmail.dtes.*')) },
                    { name: 'oc', active: @json(request()->routeIs('purchase_orders.*')) },
                    { name: 'dteinv', active: @json(request()->routeIs('gmail.inventory.*') && !request()->routeIs('gmail.inventory.sii.status')) },
                    { name: 'fuel', active: @json(request()->routeIs('fuelcontrol.*')) },
                    { name: 'config', active: @json(request()->routeIs('gmail.inventory.sii.status')) || @json(request()->routeIs('gmail.*') && !request()->routeIs('gmail.dtes.*') && !request()->routeIs('gmail.inventory.*')) },
                ];
                const current = sections.find((s) => s.active);
                if (current) return current.name;

                const path = window.location.pathname;
                if (path.startsWith('/cotizaciones')) return 'oc';
                if (path.startsWith('/gmail/dtes')) return 'dteprov';
                if (path.startsWith('/gmail/inventario') || path.startsWith('/gmail/inventory')) return 'dteinv';
                if (path.startsWith('/fuelcontrol')) return 'fuel';
                return null;
            },

            toggleSection(name) {
                if (!this.expanded) {
                    this.expanded = true;
                    localStorage.setItem('sidebar_state', 'expanded');
                    this.$nextTick(() => { this.openSection = name; });
                    return;
                }
                this.openSection = this.openSection === name ? null : name;
            },

            scrollToActiveNav(delay = 0) {
                const run = () => {
                    const nav = document.querySelector('aside nav.sidebar-scroll');
                    if (!nav) return;

                    const currentPath = window.location.pathname.replace(/\/+$/, '') || '/';
                    const anchors = Array.from(nav.querySelectorAll('a'));
                    const visible = anchors.filter((el) => el.offsetParent !== null);

                    let active = visible.find((el) => (el.className || '').includes('font-semibold'));
                    if (!active) {
                        const scored = visible
                            .map((el) => {
                                try {
                                    const hrefPath = new URL(el.href, window.location.origin).pathname.replace(/\/+$/, '') || '/';
                                    if (hrefPath === '/' || hrefPath === '') return null;
                                    const exact = hrefPath === currentPath;
                                    const prefix = currentPath.startsWith(hrefPath + '/');
                                    if (!exact && !prefix) return null;
                                    return { el, score: hrefPath.length + (exact ? 1000 : 0) };
                                } catch (_) {
                                    return null;
                                }
                            })
                            .filter(Boolean)
                            .sort((a, b) => b.score - a.score);
                        active = scored[0]?.el || null;
                    }
                    if (!active) return;

                    const navRect = nav.getBoundingClientRect();
                    const itemRect = active.getBoundingClientRect();
                    const outOfView = itemRect.top < navRect.top || itemRect.bottom > navRect.bottom;

                    if (outOfView) {
                        active.scrollIntoView({
                            block: 'center',
                            inline: 'nearest',
                            behavior: 'smooth',
                        });
                    }
                };

                if (delay > 0) {
                    setTimeout(run, delay);
                } else {
                    this.$nextTick(run);
                }
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
