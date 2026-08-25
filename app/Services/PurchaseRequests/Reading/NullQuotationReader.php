<?php

namespace App\Services\PurchaseRequests\Reading;

/**
 * Lector inerte: el asistente está apagado.
 *
 * Es el enlace por defecto y el que usan las pruebas, para que la suite nunca
 * dependa de que haya un modelo cargado.
 */
class NullQuotationReader implements QuotationReader
{
    public function isEnabled(): bool
    {
        return false;
    }

    public function describe(): string
    {
        return 'asistente desactivado';
    }

    public function read(string $absolutePath, string $mimeType, array $knownUnits = []): QuotationReading
    {
        return QuotationReading::failed('El asistente de lectura no está habilitado en este entorno.');
    }
}
