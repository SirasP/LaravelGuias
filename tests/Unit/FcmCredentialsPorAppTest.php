<?php

namespace Tests\Unit;

use App\Services\FcmNotificationService;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Cada app movil vive en su propio proyecto de Firebase y un token solo es
 * valido para el proyecto que lo emitio. Antes la credencial era una ruta fija
 * a la de mantencion, de modo que todo envio a TerraFuel fallaba aunque los
 * tokens estuvieran bien registrados.
 */
class FcmCredentialsPorAppTest extends TestCase
{
    public function test_cada_app_movil_tiene_su_propia_credencial_configurada(): void
    {
        $credenciales = config('fcm.credentials');

        $this->assertIsArray($credenciales);
        $this->assertArrayHasKey('mantencion', $credenciales);
        $this->assertArrayHasKey('combustible', $credenciales);
        $this->assertNotSame(
            $credenciales['mantencion'],
            $credenciales['combustible'],
            'Las dos apps son proyectos de Firebase distintos: no pueden compartir credencial.'
        );
    }

    public function test_un_app_type_sin_credencial_se_registra_como_error_y_no_envia(): void
    {
        config(['fcm.credentials' => []]);
        Log::spy();

        $resultado = app(FcmNotificationService::class)
            ->send('combustible', 'Titulo', 'Cuerpo');

        $this->assertSame(['sent' => 0, 'failed' => 0], $resultado);
        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $m) => str_contains($m, 'combustible'))
            ->once();
    }

    public function test_una_credencial_ausente_se_registra_como_error_no_como_aviso_silencioso(): void
    {
        config(['fcm.credentials.combustible' => '/ruta/que/no/existe.json']);
        Log::spy();

        $resultado = app(FcmNotificationService::class)
            ->send('combustible', 'Titulo', 'Cuerpo');

        $this->assertSame(['sent' => 0, 'failed' => 0], $resultado);
        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $m) => str_contains($m, 'Falta la credencial'))
            ->once();
    }
}
