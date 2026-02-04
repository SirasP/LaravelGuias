@if (session('success') || session('error'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
        class="fixed top-5 left-5 z-50">

        <div class="flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg text-white" :class="{
                    'bg-green-600': '{{ session('success') }}',
                    'bg-red-600': '{{ session('error') }}'
                }">

            <svg x-show="'{{ session('success') }}'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>

            <svg x-show="'{{ session('error') }}'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
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

@if (session('error'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
        class="fixed top-5 right-5 z-50">

        <div class="flex items-center gap-3 bg-red-600 text-white px-4 py-3 rounded-lg shadow-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>

            <span class="text-sm font-medium">
                {{ session('error') }}
            </span>
        </div>
    </div>
@endif