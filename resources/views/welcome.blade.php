<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Inicio</title>

    <!-- Tailwind CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center">

    <div class="bg-white p-8 rounded-xl shadow w-full max-w-md text-center">

        <h1 class="text-2xl font-bold mb-2">Hola 👋</h1>
        <p class="text-gray-600 mb-6">
            Laravel está funcionando correctamente.
        </p>

        {{-- SI NO ESTÁ LOGUEADO --}}
        @guest
            <div class="space-y-3">
                <a href="{{ route('login') }}" class="block w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700">
                    Iniciar sesión
                </a>

                <a href="{{ route('register') }}"
                    class="block w-full bg-gray-200 text-gray-800 py-2 rounded hover:bg-gray-300">
                    Registrarse
                </a>
            </div>
        @endguest

        {{-- SI ESTÁ LOGUEADO --}}
        @auth
            <p class="mb-4 text-gray-700">
                Bienvenido, <strong>{{ auth()->user()->name }}</strong>
            </p>

            <div class="space-y-3">
               <a href="{{ route('index') }}"
                        class="block w-full bg-green-600 text-white py-2 rounded hover:bg-green-700">
                            Ir al Inventario
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full bg-red-600 text-white py-2 rounded hover:bg-red-700">
                        Cerrar sesión
                    </button>
                </form>
            </div>
        @endauth

    </div>

</body>

</html>