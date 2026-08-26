<?php

namespace App\Notifications;

use App\Models\PurchaseRequest;
use App\Models\User;
use App\Support\PurchaseMailRouting;
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
    ) {}

    /**
     * Dentro del sistema siempre; por correo sólo si la persona tiene una
     * dirección y el envío está habilitado.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (PurchaseMailRouting::alcanza($notifiable)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Cada canal por su vía.
     *
     * El aviso dentro del sistema es un INSERT: se hace en el acto, para que
     * la persona lo vea apenas recarga, aunque el worker esté caído. El correo
     * sí va a la cola: depende de un servidor externo y no puede hacer esperar
     * a nadie frente a la pantalla.
     *
     * @return array<string, string|null>
     */
    public function viaConnections(): array
    {
        return [
            'database' => 'sync',
            'mail' => 'database',
        ];
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
