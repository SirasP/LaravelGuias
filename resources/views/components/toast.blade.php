@if (session('success') || session('error'))
    <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3000)" x-show="show"
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-[-10px]"
        x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" style="
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 999999;
        ">
        <div style="
                background: {{ session('success') ? '#16a34a' : '#dc2626' }};
                color: white;
                padding: 12px 16px;
                border-radius: 10px;
                box-shadow: 0 10px 25px rgba(0,0,0,.2);
                display: flex;
                gap: 10px;
                align-items: center;
            ">
            {{ session('success') ?? session('error') }}

            <button @click="show = false" style="opacity:.8; cursor:pointer;">
                ✕
            </button>
        </div>
    </div>
@endif