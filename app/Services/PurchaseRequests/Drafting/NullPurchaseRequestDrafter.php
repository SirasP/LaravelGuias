<?php

namespace App\Services\PurchaseRequests\Drafting;

/**
 * Adaptador inerte: el asistente está apagado.
 *
 * Es el enlace por defecto y el que usan las pruebas, de modo que la suite
 * jamás dependa de que haya un modelo cargado.
 */
class NullPurchaseRequestDrafter implements PurchaseRequestDrafter
{
    public function isEnabled(): bool
    {
        return false;
    }

    public function draftFromText(string $text, array $knownUnits = []): DraftSuggestion
    {
        return DraftSuggestion::unavailable('El asistente no está habilitado en este entorno.');
    }
}
