<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Decide si a un destinatario le toca correo, además del aviso en pantalla.
 *
 * La lógica vive aquí y no repartida por cada notificación porque son tres
 * clases que hacían la misma comprobación: la primera vez que se cambió una
 * de ellas, las otras dos se quedaron atrás.
 */
final class PurchaseMailRouting
{
    public static function alcanza(object $notifiable): bool
    {
        if (! config('purchase_requests.mail_enabled')) {
            return false;
        }

        // `routeNotificationFor` resuelve la dirección tanto para un User como
        // para un destinatario anónimo creado con Notification::route().
        // Mirar $notifiable->email directamente dejaba fuera el segundo caso.
        $destino = $notifiable->routeNotificationFor('mail');

        if (blank($destino)) {
            return false;
        }

        /** @var list<string> $permitidas */
        $permitidas = config('purchase_requests.mail_only', []);

        if ($permitidas === []) {
            return true;
        }

        foreach ($permitidas as $permitida) {
            if (Str::lower(trim((string) $destino)) === Str::lower(trim((string) $permitida))) {
                return true;
            }
        }

        return false;
    }
}
