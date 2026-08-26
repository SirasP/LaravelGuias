<?php

namespace App\Services\PurchaseRequests\Odoo;

/**
 * Resultado de un intento de exportación. Inmutable y sin efectos.
 */
final readonly class PurchaseRequestExportResult
{
    /**
     * @param  list<array{id: int, name: string, vat: string|null}>  $candidates
     */
    private function __construct(
        public bool $performed,
        public string $status,
        public ?string $remoteReference,
        public string $message,
        /**
         * Proveedores que Odoo tiene y se parecen al escrito.
         *
         * No se elige uno solo: nadie escribe el nombre legal completo, y
         * acertar por parecido es exactamente como se llegó a llamar
         * «BOBINADOS LONCOMILLA» a una cotización de RODASERVIC.
         */
        public array $candidates = [],
    ) {}

    public static function skipped(string $message): self
    {
        return new self(false, 'skipped', null, $message);
    }

    public static function simulated(string $remoteReference, string $message): self
    {
        return new self(false, 'simulated', $remoteReference, $message);
    }

    /** La RFQ se creó de verdad, en borrador y sin confirmar. */
    public static function created(string $remoteReference, string $message): self
    {
        return new self(true, 'created', $remoteReference, $message);
    }

    /** No se pudo: red caída, proveedor sin RUT, Odoo rechazando. */
    public static function failed(string $message): self
    {
        return new self(false, 'failed', null, $message);
    }

    /**
     * Odoo no reconoce el proveedor escrito, pero tiene parecidos.
     *
     * @param  list<array{id: int, name: string, vat: string|null}>  $candidates
     */
    public static function needsSupplier(string $message, array $candidates = []): self
    {
        return new self(false, 'needs_supplier', null, $message, $candidates);
    }

    public static function alreadyExported(string $remoteReference): self
    {
        return new self(false, 'already_exported', $remoteReference, 'La solicitud ya tenía una RFQ vinculada.');
    }
}
