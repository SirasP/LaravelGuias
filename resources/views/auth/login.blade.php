@extends('layouts.auth')

@section('title', 'Iniciar sesión')

@section('content')
    <div class="p-6 sm:p-8" x-data="{ showPass: false }">

        <div class="text-center mb-6">
            <h2 class="text-lg font-bold text-gray-900">Iniciar sesi&oacute;n</h2>
            <p class="text-sm text-gray-500 mt-1">Ingresa tus credenciales para continuar</p>
        </div>

        {{-- Status --}}
        @if (session('status'))
            <div class="flex items-center gap-2 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 mb-5 text-sm text-emerald-700">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" id="loginForm" class="space-y-4">
            @csrf

            {{-- Email --}}
            <div>
                <label for="email" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                    Correo electr&oacute;nico
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <input id="email" type="email" name="email" value="{{ old('email') }}"
                        class="w-full pl-10 pr-4 py-2.5 text-sm rounded-xl
                               border border-gray-200 bg-gray-50
                               text-gray-900 placeholder-gray-400
                               focus:ring-2 focus:ring-indigo-500 focus:border-transparent focus:bg-white
                               outline-none transition @error('email') border-red-400 ring-1 ring-red-400 @enderror"
                        placeholder="tu@correo.com" required autofocus autocomplete="username">
                </div>
                @error('email')
                    <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                @enderror
            </div>

            {{-- Password --}}
            <div>
                <label for="password" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                    Contrase&ntilde;a
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <input id="password" :type="showPass ? 'text' : 'password'" name="password"
                        class="w-full pl-10 pr-11 py-2.5 text-sm rounded-xl
                               border border-gray-200 bg-gray-50
                               text-gray-900 placeholder-gray-400
                               focus:ring-2 focus:ring-indigo-500 focus:border-transparent focus:bg-white
                               outline-none transition @error('password') border-red-400 ring-1 ring-red-400 @enderror"
                        placeholder="••••••••" required autocomplete="current-password">
                    <button type="button" @click="showPass = !showPass"
                        class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-gray-400 hover:text-indigo-600 transition">
                        <svg x-show="!showPass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <svg x-show="showPass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            style="display:none">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                        </svg>
                    </button>
                </div>
                @error('password')
                    <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                @enderror
            </div>

            {{-- Remember + Forgot --}}
            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 cursor-pointer group">
                    <input type="checkbox" name="remember" id="remember"
                        class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 transition">
                    <span class="text-sm text-gray-600 group-hover:text-gray-900 transition">Recordarme</span>
                </label>

                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}"
                        class="text-sm font-semibold text-indigo-600 hover:text-indigo-800 transition">
                        ¿Olvidaste tu contrase&ntilde;a?
                    </a>
                @endif
            </div>

            {{-- Submit --}}
            <button type="submit" id="loginBtn"
                class="w-full flex items-center justify-center gap-2 px-4 py-2.5
                       text-sm font-bold rounded-xl
                       bg-indigo-600 hover:bg-indigo-700 active:scale-[0.98]
                       text-white transition-all shadow-sm shadow-indigo-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                        d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                </svg>
                <span id="loginBtnText">Iniciar sesi&oacute;n</span>
            </button>
        </form>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function () {
            const btn = document.getElementById('loginBtn');
            const txt = document.getElementById('loginBtnText');
            btn.disabled = true;
            btn.classList.add('opacity-75');
            txt.textContent = 'Verificando...';
        });
    </script>
@endsection
