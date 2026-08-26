<?php

namespace App\Notifications;

use App\Models\PurchaseRequestIngestion;
use App\Support\PurchaseMailRouting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * El documento se subió bien, pero el lector no está accesible ahora mismo.
 *
 * No es un error y no hay nada que hacer: el documento queda en cola y se lee
 * solo en cuanto el lector vuelva. Se avisa para que nadie se quede mirando
 * una pantalla que no avanza, ni vuelva a subir el mismo archivo pensando que
 * se perdió.
 */
class QuotationWaitingForReader extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly PurchaseRequestIngestion $ingestion) {}

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
        return ['database' => 'sync', 'mail' => 'database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'purchase_request.quotation_waiting',
            'ingestion_id' => $this->ingestion->getKey(),
            'documento' => $this->ingestion->original_name,
            'status' => $this->ingestion->status,
            'title' => 'Tu cotización está esperando',
            'message' => sprintf(
                '«%s» se guardó bien, pero el asistente de lectura no está disponible en este momento. '
                .'Se leerá solo en cuanto vuelva: no hace falta que la subas de nuevo.',
                $this->ingestion->original_name,
            ),
            'url' => route('purchase_requests.ingestions.index'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tu cotización está esperando · '.$this->ingestion->original_name)
            ->greeting('Hola')
            ->line(sprintf('«%s» se guardó correctamente.', $this->ingestion->original_name))
            ->line('El asistente de lectura no está disponible en este momento, así que el documento quedó en espera.')
            ->line('Se leerá solo en cuanto el asistente vuelva. No hace falta que lo subas otra vez.')
            ->action('Ver mis cotizaciones', route('purchase_requests.ingestions.index'))
            ->salutation('Solicitudes de Compra · Agrícola EHE');
    }
}
