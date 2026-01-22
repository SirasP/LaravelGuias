@auth 
<nav x-data="{ open: false }" class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 shadow-sm">
@endauth
    <!-- Primary Navigation Menu -->
   <div class="w-full px-4 sm:px-4 lg:px-8">

        <div class="flex justify-between h-16">

            <!-- LEFT -->
            <div class="flex items-center">

                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('index') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800 dark:text-gray-200" />
                    </a>
                </div>

                <!-- Desktop Menu -->
                <div class="hidden sm:flex items-center space-x-6 sm:ms-10">
<!--
                   INVENTARIO COMENTADO POR MIENTRAS 
                    <div x-data="{ openInv: false }" class="relative">
                        <button @click="openInv = !openInv" @click.away="openInv = false" class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium transition
                            {{ request()->routeIs('inventario.*')
    ? 'text-blue-600 dark:text-blue-400'
    : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            Inventario
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </button>

                        <div x-show="openInv" x-transition class="absolute z-50 mt-2 w-48 rounded-xl bg-white dark:bg-gray-800
                                   shadow-lg ring-1 ring-black/5 dark:ring-white/10">

                            <a href="{{ route('inventario.productos') }}"
                                class="block px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                Productos
                            </a>

                            <a href="{{ route('inventario.categorias') }}"
                                class="block px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                Categorías
                            </a>

                            <a href="{{ route('inventario.movimientos') }}"
                                class="block px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                Movimientos
                            </a>

                            <a href="{{ route('inventario.stock') }}"
                                class="block px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                Stock
                            </a>
                        </div>
                    </div>

                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        Usuarios
                    </x-nav-link>
                            
                
                    <div x-data="{ openDte: false }" class="relative">
                        <button @click="openDte = !openDte" @click.away="openDte = false" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium
                                   text-gray-600 dark:text-gray-300
                                   hover:text-gray-900 dark:hover:text-gray-100">
                            DTE
                            <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': openDte }"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div x-show="openDte" x-transition class="absolute z-50 mt-2 w-56 rounded-xl shadow-lg
                                   bg-white dark:bg-gray-900
                                   border border-gray-200 dark:border-gray-700">

                            <a href="{{ route('inventario.dtes.gmail') }}"
                                class="block px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800">
                                Importar DTE (Gmail)
                            </a>

                            <a href="{{ route('inventario.dtes.index') }}"
                                class="block px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800">
                                Ver DTEs
                            </a>

                            <div class="border-t my-1 dark:border-gray-700"></div>

                            <a href="{{ route('google.oauth.redirect') }}"
                                class="block px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800">
                                Conectar Gmail
                            </a>
                        </div>
                    </div>
-->
                    <!-- DOCUMENTOS (PDF) -->
<!-- DOCUMENTOS (PDF) -->
<div x-data="{ openDocs: false }" class="relative">

    <button
        @click="openDocs = !openDocs"
        @click.away="openDocs = false"
        class="inline-flex items-center gap-2 px-4 py-2 rounded-full
               text-sm font-medium transition-all
               bg-sky-100 dark:bg-sky-800
               hover:bg-sky-100 dark:hover:bg-sky-900/40
               {{ request()->routeIs('pdf.*')
                    ? 'bg-sky-600 text-white dark:bg-sky-500'
                    : 'text-sky-700 dark:text-sky-200' }}"
    >
        Guías Recepcionadas
        <svg class="w-4 h-4 transition-transform"
            :class="{ 'rotate-180': openDocs }"
            xmlns="http://www.w3.org/2000/svg" fill="none"
            viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round"
                  stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <div
        x-show="openDocs"
        x-transition
        class="absolute left-0 mt-4 w-64 z-50
               rounded-2xl bg-white dark:bg-gray-900
               shadow-2xl border border-gray-100 dark:border-gray-700"
    >
        <a href="{{ route('pdf.index') }}"
           class="flex  gap-1 px-6 py-3 text-sm font-medium
                  hover:bg-indigo-50 dark:hover:bg-indigo-900/30">
            <span class="h-3.5 w-3.5 rounded-full bg-indigo-500"></span>
            PDFs importados
        </a>

        <a href="{{ route('pdf.import.form') }}"
           class="flex  gap-1 px-6 py-3 text-sm font-medium
                  hover:bg-indigo-50 dark:hover:bg-indigo-900/30">
            <span class="h-3.5 w-3.5 rounded-full bg-indigo-500"></span>
            Importar PDF
        </a>
    </div>

</div>



                    <div x-data="{ openOdoo: false }" class="relative">
                        <button @click="openOdoo = !openOdoo" @click.away="openOdoo = false" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium transition
                            {{ request()->routeIs('pdf.*')
    ? 'text-blue-600 dark:text-blue-400'
    : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            Guias ODOO
                            <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': openOdoo }"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div x-show="openOdoo" x-transition class="absolute z-50 mt-2 w-56 rounded-xl shadow-lg
                                   bg-white dark:bg-gray-900
                                   border border-gray-200 dark:border-gray-700">

                            <a href="{{ route('excel_out_transfers.index') }}"
                                class="block px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800">
                                Vista
                            </a>

                            <a href="{{ route('excel_out_transfers.import') }}"
                                class="block px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800">
                                Importar
                            </a>


                        </div>
                    </div>
                    <div x-data="{ openAgrak: false }" class="relative">
                        <button @click="openAgrak = !openAgrak" @click.away="openAgrak = false" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium transition
            {{ request()->routeIs('agrak.*')
    ? 'text-blue-600 dark:text-blue-400'
    : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100' }}">

                            Agrak
                            <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': openAgrak }"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div x-show="openAgrak" x-transition class="absolute z-50 mt-2 w-56 rounded-xl shadow-lg
               bg-white dark:bg-gray-900
               border border-gray-200 dark:border-gray-700">

                            <a href="{{ route('agrak.index') }}"
                                class="block px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800">
                                Vista
                            </a>

                            <a href="{{ route('agrak.import.form') }}"
                                class="block px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800">
                                Importar
                            </a>


                        </div>
                    </div>
                    <div x-data="{ openGuias: false }" class="relative">

    <!-- BOTÓN PRINCIPAL -->
    <button @click="openGuias = !openGuias"
        class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium transition
        {{ request()->routeIs('guias.*') 
            ? 'text-blue-600 dark:text-blue-400' 
            : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100' }}">
        Guías Recepción Bandejas

        <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': openGuias }"
            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <!-- DROPDOWN PRINCIPAL -->
    <div x-show="openGuias" x-transition @click.away="openGuias = false"
        class="absolute z-50 mt-2 w-56 rounded-xl shadow-lg
               bg-white dark:bg-gray-900
               border border-gray-200 dark:border-gray-700">

        <a href="{{ route('guias.comfrut.index') }}"
            class="block px-4 py-3 text-sm hover:bg-gray-50 dark:hover:bg-gray-800">
            Vista
        </a>

        <a href="{{ route('guias.comfrut.import.form') }}"
            class="block px-4 py-3 text-sm hover:bg-gray-50 dark:hover:bg-gray-800">
            Importar XML
        </a>

    </div>
</div>



                </div>
            </div>

            <!-- RIGHT (User) -->
            <div class="hidden sm:flex items-center">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-2 px-3 py-2 rounded-lg
                                       text-sm font-medium text-gray-600 dark:text-gray-300
                                       hover:bg-gray-100 dark:hover:bg-gray-800">
                                        @auth
    {{ Auth::user()->name }}
@endauth
                            <svg class="h-4 w-4 fill-current opacity-70" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </button>
                    </x-slot>

                    @auth
                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            Perfil
                        </x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                onclick="event.preventDefault(); this.closest('form').submit();">
                                Cerrar sesión
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                @endauth

                </x-dropdown>
                
            </div>

            <!-- Mobile Hamburger -->
            <div class="flex items-center sm:hidden">
                <button @click="open = !open" class="p-2 rounded-md text-gray-500">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>

        </div>
    </div>

    <!-- Mobile Menu -->
    <div
    x-show="open"
    x-transition
    class="sm:hidden border-t dark:border-gray-700 bg-white dark:bg-gray-900"
>


    <!-- ODOO (accordion mobile) -->
    <div x-data="{ openOdoo: false }" class="border-t dark:border-gray-700">

        <button
            @click="openOdoo = !openOdoo"
            class="w-full flex items-center justify-between px-4 py-3 text-sm
                   text-gray-700 dark:text-gray-200"
        >
            <span>Guías ODOO</span>

            <svg
                class="w-4 h-4 transition-transform"
                :class="{ 'rotate-180': openOdoo }"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div x-show="openOdoo" x-x-transition class="bg-gray-50 dark:bg-gray-800">
            <a
                href="{{ route('excel_out_transfers.index') }}"
                class="block px-6 py-2 text-sm text-gray-600 dark:text-gray-300"
            >
                Vista
            </a>

            <a
                href="{{ route('excel_out_transfers.import') }}"
                class="block px-6 py-2 text-sm text-gray-600 dark:text-gray-300"
            >
                Importar
            </a>
        </div>
    </div>
   <!-- DOCUMENTOS (PDF) — accordion mobile -->
<div x-data="{ openPdf: false }" class="border-t dark:border-gray-700">

    <!-- BOTÓN -->
    <button
        @click="openPdf = !openPdf"
        class="w-full flex items-center justify-between px-4 py-3 text-sm
               text-gray-700 dark:text-gray-200"
    >
        <span>Guías Recepcionadas</span>

        <svg
            class="w-4 h-4 transition-transform"
            :class="{ 'rotate-180': openPdf }"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <!-- CONTENIDO -->
    <div x-show="openPdf" x-transition class="bg-gray-50 dark:bg-gray-800">

        <a
            href="{{ route('pdf.index') }}"
            class="block px-6 py-2 text-sm
                   text-gray-600 dark:text-gray-300
                   hover:bg-gray-100 dark:hover:bg-gray-700"
        >
            PDFs importados
        </a>

        <a
            href="{{ route('pdf.import.form') }}"
            class="block px-6 py-2 text-sm
                   text-gray-600 dark:text-gray-300
                   hover:bg-gray-100 dark:hover:bg-gray-700"
        >
            Importar PDF
        </a>

    </div>
</div>


<!-- AGRAK — accordion mobile -->
<div x-data="{ openAgrak: false }" class="border-t dark:border-gray-700">

    <!-- BOTÓN -->
    <button
        @click="openAgrak = !openAgrak"
        class="w-full flex items-center justify-between px-4 py-3 text-sm
               text-gray-700 dark:text-gray-200"
    >
        <span>Agrak</span>

        <svg
            class="w-4 h-4 transition-transform"
            :class="{ 'rotate-180': openAgrak }"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <!-- CONTENIDO -->
    <div x-show="openAgrak" x-transition class="bg-gray-50 dark:bg-gray-800">

        <a
            href="{{ route('agrak.index') }}"
            class="block px-6 py-2 text-sm
                   text-gray-600 dark:text-gray-300
                   hover:bg-gray-100 dark:hover:bg-gray-700"
        >
            Vista
        </a>

        <a
            href="{{ route('agrak.import.form') }}"
            class="block px-6 py-2 text-sm
                   text-gray-600 dark:text-gray-300
                   hover:bg-gray-100 dark:hover:bg-gray-700"
        >
            Importar
        </a>

    </div>
</div>


<!-- GUÍAS RECEPCIÓN BANDEJAS — accordion mobile -->
<div x-data="{ openGuias: false }" class="border-t dark:border-gray-700">

    <!-- BOTÓN -->
    <button
        @click="openGuias = !openGuias"
        class="w-full flex items-center justify-between px-4 py-3 text-sm
               text-gray-700 dark:text-gray-200"
    >
        <span>Guías Recepción Bandejas</span>

        <svg
            class="w-4 h-4 transition-transform"
            :class="{ 'rotate-180': openGuias }"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <!-- CONTENIDO -->
    <div x-show="openGuias" x-transition class="bg-gray-50 dark:bg-gray-800">

        <a
            href="{{ route('guias.comfrut.index') }}"
            class="block px-6 py-2 text-sm
                   text-gray-600 dark:text-gray-300
                   hover:bg-gray-100 dark:hover:bg-gray-700"
        >
            Vista
        </a>

        <a
            href="{{ route('guias.comfrut.import.form') }}"
            class="block px-6 py-2 text-sm
                   text-gray-600 dark:text-gray-300
                   hover:bg-gray-100 dark:hover:bg-gray-700"
        >
            Importar XML
        </a>

    </div>
</div>








    <!-- Divider -->
    <div class="border-t mt-2 dark:border-gray-700"></div>

    <!-- Perfil -->
    <x-responsive-nav-link :href="route('profile.edit')">
        Perfil
    </x-responsive-nav-link>

    <!-- Logout -->
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <x-responsive-nav-link
            :href="route('logout')"
            onclick="event.preventDefault(); this.closest('form').submit();"
        >
            Cerrar sesión
        </x-responsive-nav-link>
    </form>
</div>

</nav>