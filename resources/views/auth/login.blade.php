@extends('layouts.auth')

@section('title', 'Iniciar sesión')

@section('content')
    <div class="card auth-card">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <h1 class="h4 fw-bold mb-1" style="color: #1f2937;">Iniciar sesión</h1>
                <p class="text-muted small mb-0">Ingresa tus credenciales para continuar</p>
            </div>

            {{-- Mensaje de estado (ej: reset password enviado) --}}
            @if (session('status'))
                <div class="alert alert-success d-flex align-items-center py-3 mb-4" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <div>{{ session('status') }}</div>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" id="loginForm">
                @csrf

                <div class="mb-3">
                    <label class="form-label fw-semibold small mb-2">
                        <i class="bi bi-envelope me-1"></i>Correo electrónico
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-at text-muted"></i>
                        </span>
                        <input type="email" name="email" value="{{ old('email') }}"
                            class="form-control @error('email') is-invalid @enderror"
                            placeholder="tu@correo.com" required autofocus autocomplete="username">
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small mb-2">
                        <i class="bi bi-key me-1"></i>Contraseña
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-lock text-muted"></i>
                        </span>
                        <input type="password" name="password" id="passwordInput"
                            class="form-control @error('password') is-invalid @enderror"
                            placeholder="••••••••" required autocomplete="current-password">
                        <button class="btn-password-toggle" type="button" id="togglePassword">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label small" for="remember">
                            <i class="bi bi-bookmark-check me-1"></i>Recordarme
                        </label>
                    </div>

                    @if (Route::has('password.request'))
                        <a class="small fw-semibold" href="{{ route('password.request') }}">
                            ¿Olvidaste tu contraseña?
                        </a>
                    @endif
                </div>

                <button type="submit" class="btn btn-primary w-100 fw-semibold">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar sesión
                </button>
            </form>
        </div>
    </div>


    <script>
        // Toggle password visibility with smooth animation
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('passwordInput');
            const toggleIcon = document.getElementById('toggleIcon');

            // Add rotation animation
            toggleIcon.style.transform = 'scale(0.8)';

            setTimeout(() => {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    toggleIcon.classList.remove('bi-eye');
                    toggleIcon.classList.add('bi-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    toggleIcon.classList.remove('bi-eye-slash');
                    toggleIcon.classList.add('bi-eye');
                }
                toggleIcon.style.transform = 'scale(1)';
            }, 150);
        });

        // Add shimmer effect on form submit
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = this.querySelector('.btn-primary');
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verificando...';
        });
    </script>
@endsection