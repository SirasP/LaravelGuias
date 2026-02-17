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
            --primary-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --secondary-gradient: linear-gradient(135deg, #0ba360 0%, #3cba92 100%);
            --accent-gradient: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
        }

        body {
            background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            position: relative;
            overflow-x: hidden;
        }

        /* Gradiente verde superpuesto */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(17, 153, 142, 0.8) 0%, rgba(56, 239, 125, 0.6) 100%);
            pointer-events: none;
        }

        /* Patrón de puntos animado */
        body::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: moveBackground 20s linear infinite;
            pointer-events: none;
        }

        @keyframes moveBackground {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }

        /* Partículas flotantes */
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            33% { transform: translateY(-20px) rotate(120deg); }
            66% { transform: translateY(-10px) rotate(240deg); }
        }

        .particle {
            position: absolute;
            background: rgba(56, 239, 125, 0.3);
            border-radius: 50%;
            pointer-events: none;
        }

        .particle:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 10%;
            left: 10%;
            animation: float 8s ease-in-out infinite;
        }

        .particle:nth-child(2) {
            width: 60px;
            height: 60px;
            top: 70%;
            left: 80%;
            animation: float 10s ease-in-out infinite 2s;
        }

        .particle:nth-child(3) {
            width: 100px;
            height: 100px;
            top: 40%;
            left: 5%;
            animation: float 12s ease-in-out infinite 1s;
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
            border-color: #11998e;
            box-shadow: 0 0 0 0.2rem rgba(17, 153, 142, 0.15), 0 0 20px rgba(56, 239, 125, 0.3);
            transform: translateY(-2px);
        }

        .input-group-text {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            background: transparent;
            border-right: 0;
            transition: all 0.3s ease;
        }

        .input-group .form-control {
            border-left: 0;
        }

        .input-group:focus-within .input-group-text {
            border-color: #11998e;
            background: linear-gradient(135deg, rgba(17, 153, 142, 0.1) 0%, rgba(56, 239, 125, 0.1) 100%);
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: 0;
            border-radius: 12px;
            padding: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(17, 153, 142, 0.4);
            position: relative;
            overflow: hidden;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(17, 153, 142, 0.6), 0 0 30px rgba(56, 239, 125, 0.4);
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
            color: #11998e;
        }

        .form-check-input:checked {
            background-color: #11998e;
            border-color: #11998e;
            box-shadow: 0 0 10px rgba(17, 153, 142, 0.5);
        }

        .text-gradient {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        a {
            color: #11998e;
            text-decoration: none;
            transition: all 0.2s;
            position: relative;
        }

        a:hover {
            color: #38ef7d;
        }

        a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary-gradient);
            transition: width 0.3s ease;
        }

        a:hover::after {
            width: 100%;
        }

        .alert {
            border-radius: 12px;
            border: 0;
        }

        .tagline {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            font-size: 0.7rem;
        }

        .footer-section {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
            border-radius: 16px;
            padding: 12px 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .footer-text {
            color: rgba(255, 255, 255, 0.95);
            font-weight: 500;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .footer-icon {
            filter: drop-shadow(0 2px 4px rgba(17, 153, 142, 0.5));
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
    <!-- Partículas flotantes -->
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>

    <main class="container" style="position: relative; z-index: 1;">
        <div class="row justify-content-center align-items-center" style="min-height: 100vh; padding: 20px 0;">
            <div class="col-12 col-sm-10 col-md-6 col-lg-4" style="max-width: 480px;">

                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center gap-2 mb-2">
                        <div style="position: relative;">
                            <i class="bi bi-lightning-charge-fill text-gradient" style="font-size: 2.5rem; filter: drop-shadow(0 4px 8px rgba(17, 153, 142, 0.5));"></i>
                            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 50px; height: 50px; background: radial-gradient(circle, rgba(56, 239, 125, 0.3), transparent); border-radius: 50%; filter: blur(10px);"></div>
                        </div>
                        <div class="auth-brand" style="font-size: 2.5rem; text-shadow: 0 4px 12px rgba(17, 153, 142, 0.4);">EHE</div>
                    </div>
                    <div class="tagline">
                        <span style="display: inline-block; padding: 4px 16px; background: rgba(17, 153, 142, 0.2); border-radius: 20px; border: 1px solid rgba(56, 239, 125, 0.3); font-size: 0.65rem;">
                            Sistema de Gestión Empresarial
                        </span>
                    </div>
                </div>

                @yield('content')

                <div class="footer-section mt-4">
                    <div class="text-center">
                        <div class="d-flex align-items-center justify-content-center gap-2">
                            <i class="bi bi-shield-check-fill footer-icon text-success" style="font-size: 1.1rem;"></i>
                            <span class="footer-text">
                                © {{ date('Y') }} <strong>EHE</strong> - Plataforma Empresarial
                            </span>
                        </div>
                        <div class="mt-2" style="color: rgba(255, 255, 255, 0.6); font-size: 0.7rem;">
                            Protegido con encriptación de grado empresarial
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>