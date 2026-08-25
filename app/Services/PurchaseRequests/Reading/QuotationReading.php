<?php

namespace App\Services\PurchaseRequests\Reading;

/**
 * Lo que el asistente entendió de un documento.
 *
 * Es una propuesta, nunca un hecho. Cada partida trae su grado de confianza y
 * los avisos de lo que no se pudo determinar, para que la persona sepa dónde
 * mirar antes de confirmar.
 */
final readonly class QuotationReading
{
    /**
     * @param  list<array<string, string|null>>  $items
     * @param  list<string>  $warnings
     */
    private function __construct(
        public bool $successful,
        public ?string $supplier,
        public ?string $reason,
        public array $items,
        public array $warnings,
        public ?string $model,
        public ?string $sourceKind,
        public ?string $error = null,
    ) {
    }

    /**
     * @param  list<array<string, string|null>>  $items
     * @param  list<string>  $warnings
     */
    public static function of(
        array $items,
        ?string $supplier = null,
        ?string $reason = null,
        array $warnings = [],
        ?string $model = null,
        ?string $sourceKind = null,
    ): self {
        return new self(true, $supplier, $reason, $items, $warnings, $model, $sourceKind);
    }

    public static function failed(string $error, ?string $model = null, ?string $sourceKind = null): self
    {
        return new self(false, null, null, [], [], $model, $sourceKind, $error);
    }

    /** Sin partidas no hay nada que confirmar. */
    public function hasItems(): bool
    {
        return $this->items !== [];
    }

    /**
     * Una lectura es dudosa si a alguna partida le faltó la cantidad o la
     * unidad. Se muestra igual, pero marcada: es preferible que la persona
     * complete un dato a que el asistente lo invente.
     */
    public function isDoubtful(): bool
    {
        foreach ($this->items as $item) {
            if (blank($item['quantity'] ?? null) || blank($item['unit'] ?? null)) {
                return true;
            }
        }

        return $this->warnings !== [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'successful' => $this->successful,
            'supplier' => $this->supplier,
            'reason' => $this->reason,
            'items' => $this->items,
            'warnings' => $this->warnings,
            'model' => $this->model,
            'source_kind' => $this->sourceKind,
            'error' => $this->error,
        ];
    }
}
