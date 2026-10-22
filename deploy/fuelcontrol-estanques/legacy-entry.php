<?php

/**
 * Se incluye al comienzo de get_producto.php y sync_salida.php existentes.
 * Devuelve el control al código anterior cuando el soporte está desactivado.
 * No cambia sus URLs ni agrega rutas públicas al Laravel de producción.
 */
function fuelcontrolEstanquesLegacy(string $action): void
{
    $root = dirname(__DIR__, 2);
    if ($action === 'salidas' && is_file($root.'/storage/framework/fuelcontrol-estanques.activando')) {
        http_response_code(503);
        header('Content-Type: application/json; charset=UTF-8');
        header('Retry-After: 15');
        echo json_encode(['error' => 'Actualización de estanques en curso. Reintenta en unos segundos.']);
        exit;
    }
    require_once $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $request = \Illuminate\Http\Request::capture();
    $request->headers->set('Accept', 'application/json');
    $app->instance('request', $request);

    try {
        $response = $app->make(\App\Services\FuelTankLegacyAdapter::class)->handle($action, $request);
    } catch (\Throwable $exception) {
        $handler = $app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class);
        if ($action === 'salidas' && $exception instanceof \Illuminate\Validation\ValidationException) {
            try {
                $app->make(\App\Services\FuelSyncRejectionRecorder::class)->record($request, $exception);
            } catch (\Throwable $diagnosticException) {
                $handler->report($diagnosticException);
            }
        }
        $handler->report($exception);
        $response = $handler->render($request, $exception);
    }

    if ($response !== null) {
        $response->send();
        exit;
    }
}
