<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
    }
}
