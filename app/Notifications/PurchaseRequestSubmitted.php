<?php

namespace App\Notifications;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Avisa al grupo revisor de que hay una solicitud esperando decisión.
 *
 * Sólo canal `database`: el MVP notifica dentro del sistema y no envía correo
 * externo. Si más adelante se habilita correo, esta clase ya es encolable.
 */
class PurchaseRequestSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly PurchaseRequest $purchaseRequest,
        public readonly User $actor,
        public readonly string $reason = 'submitted',
    ) {
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        // `routeNotificationFor` resuelve la dirección tanto para un User
        // como para un destinatario anónimo creado con Notification::route().
        // Mirar $notifiable->email directamente dejaba fuera el segundo caso.
        if (config('purchase_requests.mail_enabled') && filled($notifiable->routeNotificationFor('mail'))) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $esCancelacion = $this->reason === 'cancellation_requested';
        $folio = $this->purchaseRequest->folio;

        return (new MailMessage)
            ->subject(sprintf(
                '[%s] %s',
                $folio,
                $esCancelacion ? 'Piden anular una solicitud' : 'Solicitud de compra pendiente de revisión',
            ))
            ->view('emails.purchase_requests.submitted', [
                'notifiable' => $notifiable,
                'purchaseRequest' => $this->purchaseRequest,
                'actor' => $this->actor,
                'esCancelacion' => $esCancelacion,
                'url' => route('purchase_requests.show', $this->purchaseRequest->public_id),
            ]);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $isCancellation = $this->reason === 'cancellation_requested';

        return [
            'kind' => $isCancellation ? 'purchase_request.cancellation_requested' : 'purchase_request.submitted',
            'purchase_request_id' => $this->purchaseRequest->getKey(),
            'public_id' => $this->purchaseRequest->public_id,
            'folio' => $this->purchaseRequest->folio,
            'revision_number' => $this->purchaseRequest->revision_number,
            'actor_name' => $this->actor->name,
            'title' => $isCancellation
                ? 'Solicitud de anulación'
                : 'Solicitud de compra pendiente de revisión',
            'message' => $isCancellation
                ? sprintf('%s pide anular la solicitud %s.', $this->actor->name, $this->purchaseRequest->folio)
                : sprintf('%s envió la solicitud %s.', $this->actor->name, $this->purchaseRequest->folio),
            'url' => route('purchase_requests.show', $this->purchaseRequest->public_id),
        ];
    }
}
