<?php

namespace App\Notifications;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestIngestion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Avisa el resultado de leer una cotización: el borrador quedó listo, quedó
 * con dudas, o no se pudo leer.
 */
class QuotationDraftReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly PurchaseRequestIngestion $ingestion,
        public readonly ?PurchaseRequest $purchaseRequest = null,
    ) {
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (config('purchase_requests.mail_enabled') && filled($notifiable->routeNotificationFor('mail'))) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /** @return array<string, string|null> */
    public function viaConnections(): array
    {
        // El aviso interno se escribe en el acto; el correo va a la cola.
        return ['database' => 'sync', 'mail' => 'database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'purchase_request.quotation_read',
            'ingestion_id' => $this->ingestion->getKey(),
            'documento' => $this->ingestion->original_name,
            'status' => $this->ingestion->status,
            'purchase_request_id' => $this->purchaseRequest?->getKey(),
            'folio' => $this->purchaseRequest?->folio,
            'avisos' => $this->ingestion->warnings ?? [],
            'title' => $this->titulo(),
            'message' => $this->mensaje(),
            'url' => $this->purchaseRequest !== null
                ? route('purchase_requests.edit', $this->purchaseRequest->public_id)
                : route('purchase_requests.ingestions.index'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->titulo())
            ->view('emails.purchase_requests.quotation_read', [
                'notifiable' => $notifiable,
                'ingestion' => $this->ingestion,
                'purchaseRequest' => $this->purchaseRequest,
                'titulo' => $this->titulo(),
                'mensaje' => $this->mensaje(),
                'avisos' => $this->ingestion->warnings ?? [],
                'url' => $this->purchaseRequest !== null
                    ? route('purchase_requests.edit', $this->purchaseRequest->public_id)
                    : route('purchase_requests.ingestions.index'),
            ]);
    }

    private function titulo(): string
    {
        return match ($this->ingestion->status) {
            PurchaseRequestIngestion::COMPLETED => 'Borrador listo desde «'.$this->ingestion->original_name.'»',
            PurchaseRequestIngestion::NEEDS_REVIEW => 'Borrador con dudas desde «'.$this->ingestion->original_name.'»',
            default => 'No se pudo leer «'.$this->ingestion->original_name.'»',
        };
    }

    private function mensaje(): string
    {
        $partidas = count($this->ingestion->extracted['items'] ?? []);

        return match ($this->ingestion->status) {
            PurchaseRequestIngestion::COMPLETED => sprintf(
                'Se reconocieron %d %s. Revísalas y envía la solicitud cuando estén correctas.',
                $partidas,
                $partidas === 1 ? 'partida' : 'partidas',
            ),
            PurchaseRequestIngestion::NEEDS_REVIEW => sprintf(
                'Se reconocieron %d %s, pero hay datos que no se pudieron confirmar. Complétalos antes de enviar.',
                $partidas,
                $partidas === 1 ? 'partida' : 'partidas',
            ),
            default => $this->ingestion->error_message ?? 'El documento no pudo procesarse.',
        };
    }
}
