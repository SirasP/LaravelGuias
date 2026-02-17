<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Acceso')</title>

    <!-- Bootstrap 5 (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: moveBackground 20s linear infinite;
            pointer-events: none;
        }

        @keyframes moveBackground {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }

        .auth-card {
            border: 0;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .auth-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.4);
        }

        .auth-brand {
            font-weight: 800;
            font-size: 2rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
        }

        .form-control {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 12px 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
            transform: translateY(-2px);
        }

        .input-group-text {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            background: transparent;
            border-right: 0;
        }

        .input-group .form-control {
            border-left: 0;
        }

        .input-group:focus-within .input-group-text {
            border-color: #667eea;
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: 0;
            border-radius: 12px;
            padding: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
        }

        .btn-password-toggle {
            border: 0;
            background: transparent;
            color: #6c757d;
            cursor: pointer;
            padding: 0 12px;
            transition: color 0.2s;
        }

        .btn-password-toggle:hover {
            color: #667eea;
        }

        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }

        .text-gradient {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        a {
            color: #667eea;
            text-decoration: none;
            transition: color 0.2s;
        }

        a:hover {
            color: #764ba2;
        }

        .alert {
            border-radius: 12px;
            border: 0;
        }

        .footer-text {
            color: rgba(255, 255, 255, 0.8);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 576px) {
            .auth-brand {
                font-size: 1.5rem;
            }
        }
    </style>
</head>

<body>

    <main class="container">
        <div class="row justify-content-center align-items-center" style="min-height: 100vh;">
            <div class="col-12 col-sm-10 col-md-6 col-lg-4">

                <div class="text-center mb-3">
                    <div class="auth-brand fs-4">Tu Sistema</div>
                    <div class="text-muted small">Acceso seguro</div>
                </div>

                @yield('content')

                <div class="text-center footer-text small mt-4">
                    <i class="bi bi-shield-check me-1"></i>
                    © {{ date('Y') }} Sistema de Inventario
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>