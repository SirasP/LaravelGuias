<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BancoChileWebhookLogMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // 1. Registrar entrada
        $requestId = uniqid('bch_');
        $ip = $request->ip();
        $payloadFromOdoo = $request->all();

        Log::channel('bch_webhook')->info("[$requestId] === ENTRADA DESDE ODOO ===");
        Log::channel('bch_webhook')->info("[$requestId] IP: $ip");
        Log::channel('bch_webhook')->info("[$requestId] Payload Recibido de Odoo:", $payloadFromOdoo);

        // 2. Procesar la petición
        $response = $next($request);

        // 3. Registrar respuesta devuelta por Laravel a Odoo
        Log::channel('bch_webhook')->info("[$requestId] === RESPUESTA DEVUELTA A ODOO ===");
        Log::channel('bch_webhook')->info("[$requestId] Status Code: " . $response->status());
        Log::channel('bch_webhook')->info("[$requestId] Cuerpo de Respuesta:", json_decode($response->getContent(), true) ?: [$response->getContent()]);
        Log::channel('bch_webhook')->info("[$requestId] =======================================\n");

        return $response;
    }
}
