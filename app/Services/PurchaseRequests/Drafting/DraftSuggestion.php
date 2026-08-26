<?php

namespace App\Services\PurchaseRequests\Drafting;

/**
 * Propuesta del asistente. Nunca se guarda: se ofrece al formulario para que
 * una persona la revise, corrija y confirme.
 */
final readonly class DraftSuggestion
{
    /**
     * @param  list<array<string, string|null>>  $items
     * @param  list<string>  $warnings  avisos legibles sobre lo que no se pudo determinar
     */
    private function __construct(
        public bool $available,
        public ?string $reason,
        public ?string $requestedForName,
        public ?string $supplier,
        public array $items,
        public array $warnings,
        public string $priority = 'normal',
        public ?string $urgentReason = null,
        public ?string $deliveryLocation = null,
        public ?string $error = null,
    ) {}

    /**
     * @param  list<array<string, string|null>>  $items
     * @param  list<string>  $warnings
     */
    public static function of(
        ?string $reason,
        ?string $requestedForName,
        array $items,
        array $warnings = [],
        ?string $supplier = null,
        string $priority = 'normal',
        ?string $urgentReason = null,
        ?string $deliveryLocation = null,
    ): self {
        return new self(
            true, $reason, $requestedForName, $supplier, $items, $warnings,
            $priority, $urgentReason, $deliveryLocation,
        );
    }

    public static function unavailable(string $error): self
    {
        return new self(false, null, null, null, [], [], 'normal', null, null, $error);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'available' => $this->available,
            'reason' => $this->reason,
            'requested_for_name' => $this->requestedForName,
            'supplier' => $this->supplier,
            'priority' => $this->priority,
            'urgent_reason' => $this->urgentReason,
            'delivery_location' => $this->deliveryLocation,
            'items' => $this->items,
            'warnings' => $this->warnings,
            'error' => $this->error,
        ];
    }
}
