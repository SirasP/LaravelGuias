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
 * Avisa al grupo revisor de que hay una solicitud esperando decisión.
 *
 * El aviso en pantalla es para todos los revisores; el correo, para uno solo.
 * Quien acepta las solicitudes para Odoo es una persona, y el correo es lo que
 * empuja a decidir: mandárselo a los tres hacía que ninguno se sintiera el
 * dueño de la decisión.
 */
class PurchaseRequestSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly PurchaseRequest $purchaseRequest,
        public readonly User $actor,
        public readonly string $reason = 'submitted',
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        // La dirección suelta de quien acepta: sólo correo, porque no hay a
        // quién escribirle un aviso dentro del sistema.
        if (! $notifiable instanceof User) {
            return PurchaseMailRouting::alcanza($notifiable) ? ['mail'] : [];
        }

        $channels = ['database'];

        // Con un aprobador configurado el correo no sale por persona: sale una
        // sola vez, hacia él. Sin él, cada revisor recibe el suyo, como antes.
        if (trim((string) config('purchase_requests.approver_mail', '')) === ''
            && PurchaseMailRouting::alcanza($notifiable)) {
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
