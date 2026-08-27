<?php

namespace App\Services\PurchaseRequests\Products;

/**
 * Qué se sabe de una partida respecto al catálogo de Odoo.
 *
 * Distingue tres estados y no los mezcla: se sabe, se sospecha, o no hay nada.
 * La diferencia entre los dos primeros es lo único que impide que una
 * corazonada acabe moviendo stock.
 */
final readonly class ProductMatch
{
    /** Alguien lo confirmó antes, o el código calza exacto. No se pregunta. */
    public const CIERTO = 'certain';

    /** Se parece a algo. Hace falta que una persona lo mire. */
    public const SUGERENCIA = 'suggestion';

    /** Ni idea. Se busca a mano o la línea se marca como que no va a stock. */
    public const SIN_IDEA = 'unknown';

    /**
     * @param  list<array{odoo_id: int, name: string, score: float, reason: string}>  $candidates
     */
    private function __construct(
        public string $kind,
        public ?int $odooProductId,
        public ?string $odooProductName,
        public string $reason,
        public array $candidates = [],
    ) {}

    public static function cierto(int $odooProductId, string $name, string $reason): self
    {
        return new self(self::CIERTO, $odooProductId, $name, $reason);
    }

    /** @param  list<array{odoo_id: int, name: string, score: float, reason: string}>  $candidates */
    public static function sugerencia(array $candidates): self
    {
        return new self(
            self::SUGERENCIA,
            null,
            null,
            'Hay productos parecidos, pero ninguno seguro. Elige cuál es.',
            $candidates,
        );
    }

    public static function sinIdea(string $reason = 'No se encontró nada parecido en el catálogo.'): self
    {
        return new self(self::SIN_IDEA, null, null, $reason);
    }

    public function resolved(): bool
    {
        return $this->kind === self::CIERTO;
    }
}
