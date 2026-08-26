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
        public ?string $supplierTaxId,
        public ?string $customerTaxId,
        public ?string $reason,
        public array $items,
        public array $warnings,
        public ?string $model,
        public ?string $sourceKind,
        public ?string $error = null,
        /**
         * No es que la lectura saliera mal: es que no se pudo hablar con el
         * modelo. La diferencia importa —un documento ilegible no mejora
         * reintentando, un túnel caído sí— y sin ella el job no puede decidir
         * entre rendirse y esperar.
         */
        public bool $unreachable = false,
        /** Si los precios llevan el IVA dentro, decidido con aritmética. */
        public ?TaxTreatment $taxTreatment = null,
    ) {}

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
        ?string $supplierTaxId = null,
        ?string $customerTaxId = null,
        ?TaxTreatment $taxTreatment = null,
    ): self {
        return new self(true, $supplier, $supplierTaxId, $customerTaxId, $reason, $items, $warnings, $model, $sourceKind, null, false, $taxTreatment);
    }

    /** Una copia con un aviso más. Sigue siendo inmutable. */
    public function conAviso(string $aviso): self
    {
        return new self(
            $this->successful, $this->supplier, $this->supplierTaxId, $this->customerTaxId,
            $this->reason, $this->items, [...$this->warnings, $aviso],
            $this->model, $this->sourceKind, $this->error, $this->unreachable, $this->taxTreatment,
        );
    }

    public static function failed(string $error, ?string $model = null, ?string $sourceKind = null): self
    {
        return new self(false, null, null, null, null, [], [], $model, $sourceKind, $error);
    }

    /** No se pudo contactar al modelo. Vale la pena volver a intentarlo. */
    public static function unreachable(string $error, ?string $model = null, ?string $sourceKind = null): self
    {
        return new self(false, null, null, null, null, [], [], $model, $sourceKind, $error, true);
    }

    /** ¿El documento va dirigido a esta empresa? Null si no se pudo saber. */
    public function isForOurCompany(): ?bool
    {
        if ($this->customerTaxId === null) {
            return null;
        }

        $nuestro = \App\Support\Rut::normalize(config('purchase_requests.company.tax_id'));

        return $nuestro !== null && $this->customerTaxId === $nuestro;
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
            'supplier_tax_id' => $this->supplierTaxId,
            'customer_tax_id' => $this->customerTaxId,
            'reason' => $this->reason,
            'items' => $this->items,
            'warnings' => $this->warnings,
            'model' => $this->model,
            'source_kind' => $this->sourceKind,
            'error' => $this->error,
        ];
    }
}
