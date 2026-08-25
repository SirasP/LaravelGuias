<?php

namespace App\Notifications;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Avisa al solicitante del resultado de la revisión: aprobada, rechazada,
 * devuelta para corrección o anulada. Nunca se notifica a proveedores.
 */
class PurchaseRequestReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly PurchaseRequest $purchaseRequest,
        public readonly User $actor,
        public readonly string $outcome,
        public readonly ?string $comment = null,
    ) {
    }

    /**
     * Dentro del sistema siempre; por correo sólo si la persona tiene una
     * dirección y el envío está habilitado.
     *
     * @return list<string>
     */
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
        $folio = $this->purchaseRequest->folio;

        return (new MailMessage)
            ->subject(sprintf('[%s] Tu solicitud fue %s', $folio, $this->outcome))
            ->view('emails.purchase_requests.reviewed', [
                'notifiable' => $notifiable,
                'purchaseRequest' => $this->purchaseRequest,
                'actor' => $this->actor,
                'outcome' => $this->outcome,
                'comment' => $this->comment,
                'url' => route('purchase_requests.show', $this->purchaseRequest->public_id),
            ]);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'purchase_request.reviewed',
            'purchase_request_id' => $this->purchaseRequest->getKey(),
            'public_id' => $this->purchaseRequest->public_id,
            'folio' => $this->purchaseRequest->folio,
            'outcome' => $this->outcome,
            'actor_name' => $this->actor->name,
            'comment' => $this->comment,
            'title' => sprintf('Tu solicitud %s fue %s', $this->purchaseRequest->folio, $this->outcome),
            'message' => filled($this->comment)
                ? sprintf('%s: «%s»', $this->actor->name, $this->comment)
                : sprintf('%s revisó tu solicitud.', $this->actor->name),
            'url' => route('purchase_requests.show', $this->purchaseRequest->public_id),
        ];
    }
}
