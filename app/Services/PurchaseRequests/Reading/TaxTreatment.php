<?php

namespace App\Services\PurchaseRequests\Reading;

/**
 * ¿Los precios de una cotización llevan el IVA dentro o no?
 *
 * No se le pregunta al modelo: se decide con aritmética. Si la suma de las
 * partidas cuadra con el neto declarado, los precios son netos; si cuadra con
 * el total, vienen con IVA. Un número no opina.
 *
 * Importa más de lo que parece: equivocarse infla o desinfla una orden de
 * compra un 19%, y eso no se nota mirando —el monto sigue pareciendo
 * razonable— hasta que llega la factura.
 */
final readonly class TaxTreatment
{
    public const NETO = 'net';

    public const CON_IVA = 'gross';

    public const SIN_DETERMINAR = 'unknown';

    private function __construct(
        public string $kind,
        public ?float $rate,
        public string $explanation,
    ) {}

    public function pricesIncludeTax(): ?bool
    {
        return match ($this->kind) {
            self::NETO => false,
            self::CON_IVA => true,
            default => null,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $items  partidas ya verificadas
     */
    public static function infer(array $items, ?float $neto, ?float $iva, ?float $total): self
    {
        $suma = 0.0;

        foreach ($items as $item) {
            if (blank($item['unit_price'] ?? null) || blank($item['quantity'] ?? null)) {
                continue;
            }

            $suma += (float) $item['unit_price'] * (float) str_replace(',', '.', (string) $item['quantity']);
        }

        if ($suma <= 0.0) {
            return new self(self::SIN_DETERMINAR, null, 'Las partidas no traen precio, así que no hay nada que comprobar.');
        }

        // Margen del 1%: los documentos redondean cada línea a su manera.
        $parecido = static fn (?float $a, ?float $b): bool => $a !== null && $b !== null
            && $a > 0 && abs($a - $b) <= max(2.0, $a * 0.01);

        if ($parecido($neto, $suma)) {
            $tasa = ($iva !== null && $neto > 0) ? round($iva / $neto, 4) : null;

            return new self(
                self::NETO,
                $tasa,
                $iva !== null
                    ? sprintf('Las partidas suman el neto del documento y el IVA declarado es un %s%%.', rtrim(rtrim(number_format(($tasa ?? 0) * 100, 1, ',', '.'), '0'), ','))
                    : 'Las partidas suman el neto declarado en el documento.',
            );
        }

        if ($parecido($total, $suma)) {
            return new self(
                self::CON_IVA,
                ($iva !== null && $total > $iva) ? round($iva / ($total - $iva), 4) : null,
                'Las partidas suman el total del documento, así que los precios ya traen el IVA dentro.',
            );
        }

        return new self(
            self::SIN_DETERMINAR,
            null,
            'No se pudo comprobar si los precios llevan IVA: la suma de las partidas no cuadra ni con el neto ni con el total del documento.',
        );
    }

    /** Cuando el documento no declara totales que contrastar. */
    public static function undetermined(string $motivo): self
    {
        return new self(self::SIN_DETERMINAR, null, $motivo);
    }
}
