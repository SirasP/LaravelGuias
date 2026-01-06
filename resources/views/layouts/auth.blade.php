<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Acceso')</title>

    <!-- Bootstrap 5 (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f5f6f8;
        }

        .auth-card {
            border: 0;
            border-radius: 14px;
        }

        .auth-brand {
            font-weight: 700;
            letter-spacing: .2px;
        }

        .form-control {
            border-radius: 10px;
        }

        .btn {
            border-radius: 10px;
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

                <div class="text-center text-muted small mt-3">
                    © {{ date('Y') }} Tu Sistema
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>