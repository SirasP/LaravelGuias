<?php

namespace App\Services\PurchaseRequests\Drafting;

/**
 * Puerto del asistente de captura.
 *
 * Convierte una descripción escrita a mano por el trabajador en una propuesta
 * de partidas. Jamás persiste nada ni cambia el estado de una solicitud: su
 * salida es material para que una persona revise antes de enviar.
 */
interface PurchaseRequestDrafter
{
    public function isEnabled(): bool;

    /** @param list<string> $knownUnits unidades del catálogo, para no inventar otras */
    public function draftFromText(string $text, array $knownUnits = []): DraftSuggestion;
}
