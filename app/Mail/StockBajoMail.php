<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StockBajoMail extends Mailable
{
    public function __construct(
        public string $producto,
        public float $stock
    ) {
    }

    public function build()
    {
        return $this
            ->subject("⚠️ Stock bajo: {$this->producto}")
            ->view('emails.stock_bajo');
    }
}
