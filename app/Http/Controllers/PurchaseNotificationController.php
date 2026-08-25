<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Avisos del módulo de Solicitudes de Compra, dentro del sistema.
 *
 * El MVP no envía correo: las notificaciones viven en la base y se consultan
 * aquí. Cada persona ve únicamente las suyas.
 */
class PurchaseNotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->whereIn('type', [
                \App\Notifications\PurchaseRequestSubmitted::class,
                \App\Notifications\PurchaseRequestReviewed::class,
            ])
            ->latest()
            ->paginate(25);

        return response()->view('purchase_notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $this->unreadQuery($user)->count(),
        ]);
    }

    /** Marca una notificación como leída y lleva a la solicitud. */
    public function read(Request $request, string $notification): RedirectResponse
    {
        $user = $request->user();

        // findOrFail sobre la relación del usuario: nadie puede marcar —ni
        // mirar el destino de— una notificación ajena.
        $record = $user->notifications()->whereKey($notification)->firstOrFail();

        if ($record->read_at === null) {
            $record->markAsRead();
        }

        $url = data_get($record->data, 'url');

        return $url !== null
            ? redirect()->to($url)
            : to_route('purchase_notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->unreadQuery($request->user())->update(['read_at' => now()]);

        return to_route('purchase_notifications.index')
            ->with('success', 'Marcamos todos los avisos como leídos.');
    }

    private function unreadQuery($user)
    {
        return $user->unreadNotifications()->whereIn('type', [
            \App\Notifications\PurchaseRequestSubmitted::class,
            \App\Notifications\PurchaseRequestReviewed::class,
        ]);
    }
}
