<?php

namespace App\Services\PurchaseRequests\Odoo;

/**
 * Resultado de un intento de exportación. Inmutable y sin efectos.
 */
final readonly class PurchaseRequestExportResult
{
    private function __construct(
        public bool $performed,
        public string $status,
        public ?string $remoteReference,
        public string $message,
    ) {}

    public static function skipped(string $message): self
    {
        return new self(false, 'skipped', null, $message);
    }

    public static function simulated(string $remoteReference, string $message): self
    {
        return new self(false, 'simulated', $remoteReference, $message);
    }

    public static function alreadyExported(string $remoteReference): self
    {
        return new self(false, 'already_exported', $remoteReference, 'La solicitud ya tenía una RFQ vinculada.');
    }
}
