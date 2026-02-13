<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Agrícola EHE') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body class="font-sans antialiased">
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">

        {{--
        NAV PRINCIPAL (STICKY)
        ⚠️ El propio navigation.blade.php ya maneja su bg, border y shadow.
        El <header> solo necesita sticky + z-index.
            --}}
            <header class="sticky top-0 z-50">
                @include('layouts.navigation')
            </header>

            {{-- HEADER DE CONTEXTO --}}
            @isset($header)
                <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <div class="w-full px-4 sm:px-6 lg:px-8">
                        <div class="h-16 flex items-center">
                            {{ $header }}
                        </div>
                    </div>
                </div>
            @endisset

            {{-- CONTENIDO --}}
            <main class="pb-6">
                {{ $slot }}
            </main>

    </div>

    @include('components.toast')

</body>

</html>