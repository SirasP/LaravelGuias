<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;

class StockBajoMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $producto,
        public float $stock,
        public float $stockMinimo,
        public ?string $codigoProducto = null,
        public ?string $categoria = null
    ) {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $metadata = [
            'stock_actual' => (string) $this->stock,
        ];

        if ($this->codigoProducto !== null) {
            $metadata['producto_id'] = $this->codigoProducto;
        }

        return new Envelope(
            from: new Address(
                address: config('mail.from.address'),
                name: 'Sistema FuelControl'
            ),
            subject: "⚠️ Alerta de Stock Bajo - {$this->producto}",
            tags: ['stock-alert', 'inventory'],
            metadata: $metadata,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.stock_bajo',
            with: [
                'porcentajeStock' => $this->calcularPorcentajeStock(),
                'nivelCritico' => $this->esNivelCritico(),
                'fechaAlerta' => now()->format('d/m/Y H:i'),
            ],
        );
    }

    /**
     * Calcula el porcentaje de stock restante
     */
    private function calcularPorcentajeStock(): float
    {
        if ($this->stockMinimo == 0) {
            return 0;
        }

        return round(($this->stock / $this->stockMinimo) * 100, 2);
    }

    /**
     * Determina si el stock está en nivel crítico (menos del 25%)
     */
    private function esNivelCritico(): bool
    {
        return $this->calcularPorcentajeStock() < 25;
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}