<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // El MVP sólo conoce el adaptador simulado. Sustituir este binding es
        // el punto único donde se enchufaría una integración real con Odoo.
        $this->app->bind(
            \App\Services\PurchaseRequests\Odoo\PurchaseRequestExporter::class,
            \App\Services\PurchaseRequests\Odoo\SimulatedPurchaseRequestExporter::class,
        );

        // Asistente de captura por IA: preparado pero apagado. Encenderlo es
        // sustituir este binding por un adaptador real; el módulo funciona
        // completo sin él.
        $this->app->bind(
            \App\Services\PurchaseRequests\Drafting\PurchaseRequestDrafter::class,
            \App\Services\PurchaseRequests\Drafting\NullPurchaseRequestDrafter::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Los dominios públicos siempre usan HTTPS. La IP directa del VPS se
        // conserva en HTTP, porque no puede tener un certificado TLS válido.
        if (config('app.env') === 'production'
            && filter_var(request()->getHost(), FILTER_VALIDATE_IP) === false) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        $this->configureRateLimiting();
    }

    /**
     * Límite de las acciones que escriben en Solicitudes de Compra.
     *
     * Es holgado para el uso normal —crear, corregir y enviar— pero frena
     * ráfagas de reintentos: la protección real contra el doble envío es la
     * transacción con bloqueo, no este contador.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('solicitudes-compra', function (Request $request): Limit {
            return $request->user() !== null
                ? Limit::perMinute(40)->by('sc:'.$request->user()->getAuthIdentifier())
                : Limit::perMinute(10)->by('sc:'.$request->ip());
        });
    }
}
