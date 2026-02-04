@if (session('success') || session('error'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-6"
        x-transition:enter-end="opacity-100 translate-x-0" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-x-0" x-transition:leave-end="opacity-0 translate-x-6"
        class="fixed bottom-5 right-5 z-50">

        <div class="flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg text-white" :class="{
                'bg-green-600': '{{ session('success') }}',
                'bg-red-600': '{{ session('error') }}'
            }">

            <!-- Icono success -->
            <svg x-show="'{{ session('success') }}'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>

            <!-- Icono error -->
            <svg x-show="'{{ session('error') }}'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>

            <!-- Mensaje -->
            <span class="text-sm font-medium">
                {{ session('success') ?? session('error') }}
            </span>

            <!-- Botón cerrar -->
            <button @click="show = false" class="ml-2 text-white/80 hover:text-white focus:outline-none">
                ✕
            </button>
        </div>
    </div>
@endif