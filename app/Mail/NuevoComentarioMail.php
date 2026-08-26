<?php

namespace App\Mail;

use App\Models\Comentario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NuevoComentarioMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Comentario $comentario) {}

    public function envelope(): Envelope
    {
        $emoji = $this->comentario->tipo === 'sugerencia' ? '💡' : '📢';
        $tipoCap = ucfirst($this->comentario->tipo);

        return new Envelope(
            from: new Address(config('mail.from.address'), 'Reclamos EHE'),
            subject: "{$emoji} Nueva {$tipoCap} – Huerto Agrícola",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.nuevo_comentario');
    }

    public function attachments(): array
    {
        return [];
    }
}
