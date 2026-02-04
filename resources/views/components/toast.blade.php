@if (session('success') || session('error'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-6"
        x-transition:enter-end="opacity-100 translate-x-0" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-x-0" x-transition:leave-end="opacity-0 translate-x-6"
        class="fixed top-5 right-5 z-[9999]">
        <div class="flex items-center gap-3 px-4 py-3 rounded-lg shadow-xl text-white" :class="{
                    'bg-green-600': '{{ session('success') }}',
                    'bg-red-600': '{{ session('error') }}'
                }">
            <!-- Icon -->
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4" />
            </svg>

            <span class="text-sm font-medium">
                {{ session('success') ?? session('error') }}
            </span>

            <button @click="show = false" class="ml-2 text-white/80 hover:text-white">
                ✕
            </button>
        </div>
    </div>
@endif