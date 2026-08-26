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
        // Con la integración apagada —lo normal, y lo que ocurre en la suite—
        // se usa el adaptador simulado, que no abre ninguna conexión. El real
        // sólo aparece cuando alguien lo enciende a conciencia en el .env.
        $this->app->bind(
            \App\Services\PurchaseRequests\Odoo\PurchaseRequestExporter::class,
            function ($app) {
                if (! config('purchase_requests.odoo.enabled')) {
                    return new \App\Services\PurchaseRequests\Odoo\SimulatedPurchaseRequestExporter;
                }

                return new \App\Services\PurchaseRequests\Odoo\OdooPurchaseRequestExporter(
                    new \App\Services\PurchaseRequests\Odoo\OdooClient(
                        (string) config('purchase_requests.odoo.url'),
                        (string) config('purchase_requests.odoo.db'),
                        (string) config('purchase_requests.odoo.user'),
                        (string) config('purchase_requests.odoo.password'),
                        (int) config('purchase_requests.odoo.timeout', 30),
                    ),
                );
            },
        );

        // Asistente de captura por IA: preparado pero apagado. Encenderlo es
        // sustituir este binding por un adaptador real; el módulo funciona
        // completo sin él.
        $this->app->bind(
            \App\Services\PurchaseRequests\Drafting\PurchaseRequestDrafter::class,
            fn () => config('purchase_requests.reader.enabled')
                ? new \App\Services\PurchaseRequests\Drafting\LocalPurchaseRequestDrafter
                : new \App\Services\PurchaseRequests\Drafting\NullPurchaseRequestDrafter,
        );

        // Lector de cotizaciones: el adaptador real sólo entra si está
        // habilitado por configuración. Apagado, el módulo funciona igual.
        $this->app->bind(
            \App\Services\PurchaseRequests\Reading\QuotationReader::class,
            fn () => config('purchase_requests.reader.enabled')
                ? new \App\Services\PurchaseRequests\Reading\LocalQuotationReader
                : new \App\Services\PurchaseRequests\Reading\NullQuotationReader,
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
