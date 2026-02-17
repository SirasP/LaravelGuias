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
            --primary-color: #3b82f6;
            --primary-dark: #2563eb;
            --primary-light: #60a5fa;
        }

        body {
            background: #f3f4f6;
            height: 100vh;
            overflow: hidden;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            position: relative;
        }

        /* Patrón sutil de fondo */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image:
                linear-gradient(rgba(0, 0, 0, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 0, 0, 0.02) 1px, transparent 1px);
            background-size: 20px 20px;
            pointer-events: none;
        }

        .auth-card {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: box-shadow 0.3s ease;
        }

        .auth-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .auth-brand {
            font-weight: 800;
            font-size: 2rem;
            color: #1f2937;
            letter-spacing: -0.5px;
        }

        .form-control {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 12px 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        .input-group-text {
            border-radius: 0.375rem 0 0 0.375rem;
            border: 2px solid #e5e7eb;
            background: #f9fafb;
            border-right: 0;
            transition: all 0.2s ease;
        }

        .input-group .form-control {
            border-left: 0;
            border-radius: 0 0.375rem 0.375rem 0;
        }

        .input-group:focus-within .input-group-text {
            border-color: var(--primary-color);
            background: #eff6ff;
        }

        .btn-primary {
            background: var(--primary-color);
            border: 0;
            border-radius: 0.375rem;
            padding: 0.625rem 1rem;
            font-weight: 600;
            color: white;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .btn-password-toggle {
            border: 0;
            background: transparent;
            color: #6b7280;
            cursor: pointer;
            padding: 0 12px;
            transition: color 0.2s;
        }

        .btn-password-toggle:hover {
            color: var(--primary-color);
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .text-primary {
            color: var(--primary-color);
        }

        a {
            color: var(--primary-color);
            text-decoration: none;
            transition: color 0.2s;
        }

        a:hover {
            color: var(--primary-dark);
        }

        .alert {
            border-radius: 12px;
            border: 0;
        }

        .tagline {
            color: #6b7280;
            font-weight: 500;
            font-size: 0.875rem;
        }

        .footer-text {
            color: #9ca3af;
            font-weight: 400;
            font-size: 0.875rem;
        }

        @media (max-width: 576px) {
            .auth-brand {
                font-size: 1.5rem;
            }
            .footer-section {
                padding: 12px 16px;
            }
        }
    </style>
</head>

<body>
    <main class="container" style="height: 100vh; overflow-y: auto; display: flex; align-items: center;">
        <div class="row justify-content-center w-100" style="padding: 20px 0;">
            <div class="col-12 col-sm-10 col-md-6 col-lg-4" style="max-width: 420px;">

                <div class="text-center mb-4">
                    <div class="auth-brand mb-2">EHE</div>
                    <p class="tagline mb-0">Sistema de Gestión Agrícola</p>
                </div>

                @yield('content')

                <div class="text-center mt-4">
                    <p class="footer-text mb-0">
                        © {{ date('Y') }} EHE. Todos los derechos reservados.
                    </p>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>