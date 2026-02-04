@if (session('success') || session('error'))
    <template x-teleport="body">
        <div x-data="{ show: true }" x-show="show" x-cloak x-init="setTimeout(() => show = false, 3000)"
            x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-6"
            x-transition:enter-end="opacity-100 translate-x-0" x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-x-0" x-transition:leave-end="opacity-0 translate-x-6"
            class="fixed top-6 right-6 z-[999999] pointer-events-auto">
            <div class="flex items-center gap-3 px-4 py-3 rounded-lg shadow-2xl text-white"
                style="{{ session('success') ? 'background:#16a34a' : 'background:#dc2626' }}">
                <span class="text-sm font-medium">
                    {{ session('success') ?? session('error') }}
                </span>

                <button @click="show = false" class="ml-2 text-white/80 hover:text-white">
                    ✕
                </button>
            </div>
        </div>
    </template>
@endif