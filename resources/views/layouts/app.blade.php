<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Agrícola EHE') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>

<body {{ $attributes->merge(['class' => 'font-sans antialiased']) }}>

    {{-- Línea accent superior --}}
    <div class="h-0.5 bg-gradient-to-r from-indigo-500 via-violet-500 to-indigo-400 fixed top-0 inset-x-0 z-[60]"></div>

    <div class="flex h-screen pt-0.5">

        @auth
            {{-- ── SIDEBAR (desktop) ──────────────────────────── --}}
            @include('layouts.navigation')
        @endauth

        {{-- ── CONTENIDO PRINCIPAL ────────────────────────── --}}
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

            @auth
                {{-- Topbar mobile --}}
                <header class="lg:hidden bg-white dark:bg-gray-950 border-b border-gray-100 dark:border-gray-800 h-14 flex items-center px-4 gap-3 shrink-0 z-40">
                    <button @click="$dispatch('toggle-mobile-sidebar')"
                        class="p-2 -ml-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <a href="{{ route('index') }}" class="shrink-0">
                        <x-application-logo class="block h-7 w-auto fill-current text-gray-800 dark:text-gray-100" />
                    </a>
                    <div class="flex-1"></div>
                    @isset($header)
                        <div class="flex-1 min-w-0 truncate text-sm font-semibold text-gray-700 dark:text-gray-200">
                            {{ $header }}
                        </div>
                    @endisset
                </header>
            @endauth

            {{-- Context header (desktop) --}}
            @isset($header)
                <div class="hidden lg:block bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shrink-0">
                    <div class="w-full px-4 sm:px-6 lg:px-8">
                        <div class="h-16 flex items-center">
                            {{ $header }}
                        </div>
                    </div>
                </div>
            @endisset

            {{-- Main content --}}
            <main class="flex-1 overflow-y-auto pb-6">
                {{ $slot }}
            </main>

        </div>

    </div>

    @include('components.toast')

</body>

</html>