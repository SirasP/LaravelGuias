<?php

namespace App\Notifications;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestIngestion;
use App\Support\PurchaseMailRouting;
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
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (PurchaseMailRouting::alcanza($notifiable)) {
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
            'title' => $this->titulo($notifiable),
            'message' => $this->mensaje($notifiable),
            'url' => $this->enlacePara($notifiable),
        ];
    }

    /**
     * Cada quien recibe el enlace que sí puede abrir.
     *
     * Quien subió el documento va a editar su borrador; el administrador, que
     * no es su dueño, va a verlo. Mandarle a editar sería mandarlo a un
     * «acceso denegado».
     */
    private function enlacePara(object $notifiable): string
    {
        // Ya no hay solicitud creada al terminar la lectura: se lleva a la
        // pantalla donde se revisa y se decide si crearla.
        if ($this->purchaseRequest !== null) {
            return $this->esElAutor($notifiable)
                ? route('purchase_requests.edit', $this->purchaseRequest->public_id)
                : route('purchase_requests.show', $this->purchaseRequest->public_id);
        }

        return $this->ingestion->isFinished()
            ? route('purchase_requests.ingestions.show', $this->ingestion->public_id)
            : route('purchase_requests.ingestions.index');
    }

    private function esElAutor(object $notifiable): bool
    {
        return ($notifiable->getKey() ?? null) === $this->ingestion->user_id;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->titulo($notifiable))
            ->view('emails.purchase_requests.quotation_read', [
                'notifiable' => $notifiable,
                'ingestion' => $this->ingestion,
                'purchaseRequest' => $this->purchaseRequest,
                'titulo' => $this->titulo($notifiable),
                'mensaje' => $this->mensaje($notifiable),
                'avisos' => $this->ingestion->warnings ?? [],
                'esElAutor' => $this->esElAutor($notifiable),
                'quienSubio' => $this->ingestion->uploader_name_snapshot,
                'url' => $this->enlacePara($notifiable),
            ]);
    }

    private function titulo(object $notifiable): string
    {
        $doc = '«'.$this->ingestion->original_name.'»';

        // Para el administrador lo importante es quién cotizó, no el archivo.
        if (! $this->esElAutor($notifiable)) {
            $quien = $this->ingestion->uploader_name_snapshot;

            return match ($this->ingestion->status) {
                PurchaseRequestIngestion::COMPLETED,
                PurchaseRequestIngestion::NEEDS_REVIEW => $quien.' subió una cotización',
                default => 'No se pudo leer la cotización que subió '.$quien,
            };
        }

        return match ($this->ingestion->status) {
            PurchaseRequestIngestion::COMPLETED => 'Documento leído: '.$doc,
            PurchaseRequestIngestion::NEEDS_REVIEW => 'Documento leído con dudas: '.$doc,
            default => 'No se pudo leer '.$doc,
        };
    }

    private function mensaje(object $notifiable): string
    {
        $partidas = count($this->ingestion->extracted['items'] ?? []);

        if (! $this->esElAutor($notifiable)) {
            $quien = $this->ingestion->uploader_name_snapshot;
            $proveedor = $this->ingestion->supplier_name
                ?? ($this->ingestion->supplier_tax_id !== null
                    ? 'RUT '.\App\Support\Rut::format($this->ingestion->supplier_tax_id)
                    : null);

            return match ($this->ingestion->status) {
                PurchaseRequestIngestion::COMPLETED,
                PurchaseRequestIngestion::NEEDS_REVIEW => sprintf(
                    '%s subió una cotización%s con %d %s. Aún no es una solicitud: la crea cuando revise lo leído.',
                    $quien,
                    $proveedor !== null ? ' de '.$proveedor : '',
                    $partidas,
                    $partidas === 1 ? 'partida' : 'partidas',
                ),
                default => sprintf('%s subió «%s», pero no se pudo leer.', $quien, $this->ingestion->original_name),
            };
        }

        return match ($this->ingestion->status) {
            PurchaseRequestIngestion::COMPLETED => sprintf(
                'Se reconocieron %d %s. Revísalas y crea la solicitud cuando estén correctas.',
                $partidas,
                $partidas === 1 ? 'partida' : 'partidas',
            ),
            PurchaseRequestIngestion::NEEDS_REVIEW => sprintf(
                'Se reconocieron %d %s, pero hay datos que no se pudieron confirmar. Revísalas antes de crear la solicitud.',
                $partidas,
                $partidas === 1 ? 'partida' : 'partidas',
            ),
            default => $this->ingestion->error_message ?? 'El documento no pudo procesarse.',
        };
    }
}
