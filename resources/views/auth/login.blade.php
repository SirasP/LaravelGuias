@extends('layouts.auth')

@section('title', 'Iniciar sesión')

@section('content')
    <div class="card auth-card shadow-sm">
        <div class="card-body p-4">
            <h1 class="h5 mb-1">Iniciar sesión</h1>
            <p class="text-muted small mb-4">Ingresa tus datos para continuar</p>

            {{-- Mensaje de estado (ej: reset password enviado) --}}
            @if (session('status'))
                <div class="alert alert-info py-2 small">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Correo</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                        class="form-control @error('email') is-invalid @enderror" required autofocus
                        autocomplete="username">
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                        required autocomplete="current-password">
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label small" for="remember">
                            Recordarme
                        </label>
                    </div>

                    @if (Route::has('password.request'))
                        <a class="small text-decoration-none" href="{{ route('password.request') }}">
                            ¿Olvidaste tu contraseña?
                        </a>
                    @endif
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    Entrar
                </button>
            </form>

            
        </div>
    </div>
@endsection