<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

class FcmNotificationService
{
    /**
     * Envía una notificación push FCM a todos los tokens activos de una app.
     *
     * @param  string  $appType   'combustible' | 'mantencion'
     * @param  string  $title
     * @param  string  $body
     * @param  array   $data      Payload adicional (key/value strings)
     * @return array{sent: int, failed: int}
     */
    public function send(string $appType, string $title, string $body, array $data = []): array
    {
        $credentialsPath = storage_path('app/firebase/firebase-credentials.json');

        if (!file_exists($credentialsPath)) {
            Log::warning('[FCM] firebase-credentials.json no encontrado. Push desactivado.');
            return ['sent' => 0, 'failed' => 0];
        }

        $tokens = DB::connection('fuelcontrol')
            ->table('device_tokens')
            ->where('active', true)
            ->where('app_type', $appType)
            ->pluck('fcm_token')
            ->toArray();

        if (empty($tokens)) {
            Log::info("[FCM] No hay tokens activos para app_type={$appType}");
            return ['sent' => 0, 'failed' => 0];
        }

        try {
            $factory   = (new Factory)->withServiceAccount($credentialsPath);
            $messaging = $factory->createMessaging();
        } catch (\Throwable $e) {
            Log::error('[FCM] Error inicializando Firebase: ' . $e->getMessage());
            return ['sent' => 0, 'failed' => count($tokens)];
        }

        // Todos los valores del payload deben ser strings
        $stringData = array_map('strval', $data);

        $sent   = 0;
        $failed = 0;

        foreach ($tokens as $token) {
            try {
                $message = CloudMessage::fromArray([
                    'token'        => $token,
                    'notification' => [
                        'title' => $title,
                        'body'  => $body,
                    ],
                    'data' => $stringData,
                ]);

                $messaging->send($message);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                // Token inválido → desactivar
                if (
                    str_contains($e->getMessage(), 'not-found') ||
                    str_contains($e->getMessage(), 'invalid-registration-token') ||
                    str_contains($e->getMessage(), 'UNREGISTERED')
                ) {
                    DB::connection('fuelcontrol')
                        ->table('device_tokens')
                        ->where('fcm_token', $token)
                        ->update(['active' => false]);
                }
                Log::warning("[FCM] Error enviando a token: {$e->getMessage()}");
            }
        }

        Log::info("[FCM] app={$appType} enviadas={$sent} fallidas={$failed}");
        return ['sent' => $sent, 'failed' => $failed];
    }
}
