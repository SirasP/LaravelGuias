<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Acceso') — EHE</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] { display: none !important; }

        body {
            font-family: 'Figtree', system-ui, sans-serif;
        }

        .auth-grid {
            background-image:
                linear-gradient(rgba(0, 0, 0, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 0, 0, 0.02) 1px, transparent 1px);
            background-size: 24px 24px;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .au { animation: fadeUp .5s cubic-bezier(.22, 1, .36, 1) both; }
        .d1 { animation-delay: .05s; }
        .d2 { animation-delay: .12s; }
        .d3 { animation-delay: .20s; }
    </style>
</head>

<body class="bg-gray-50 text-gray-900 antialiased">
    <div class="auth-grid min-h-screen flex flex-col items-center justify-center px-4 py-8 sm:px-6">

        {{-- Brand --}}
        <div class="au d1 text-center mb-6">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-600 shadow-lg shadow-indigo-200 mb-4">
                <span class="text-xl font-extrabold text-white tracking-tight">E</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">EHE</h1>
            <p class="text-sm text-gray-500 mt-1">Sistema de Gesti&oacute;n Agr&iacute;cola</p>
        </div>

        {{-- Card --}}
        <div class="au d2 w-full max-w-sm sm:max-w-md">
            <div class="bg-white rounded-2xl shadow-xl shadow-gray-200/60 border border-gray-100 overflow-hidden">
                @yield('content')
            </div>
        </div>

        {{-- Footer --}}
        <p class="au d3 text-xs text-gray-400 mt-6 text-center">
            &copy; {{ date('Y') }} EHE. Todos los derechos reservados.
        </p>

    </div>
</body>

</html>
