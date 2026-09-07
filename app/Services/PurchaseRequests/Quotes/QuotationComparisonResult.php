<?php

namespace App\Services\PurchaseRequests\Quotes;

/** El resultado completo: las partidas pedidas y lo que el proveedor añadió. */
class QuotationComparisonResult
{
    /**
     * @param  list<QuotationComparisonRow>  $filas
     * @param  list<QuotationComparisonRow>  $sobrantes
     */
    public function __construct(
        public readonly array $filas,
        public readonly array $sobrantes = [],
    ) {}

    /** @return list<QuotationComparisonRow> */
    public function todas(): array
    {
        return [...$this->filas, ...$this->sobrantes];
    }

    public function cuadra(): bool
    {
        foreach ($this->todas() as $fila) {
            if (! $fila->estaBien()) {
                return false;
            }
        }

        return true;
    }

    public function conDiferencias(): int
    {
        return count(array_filter($this->todas(), fn (QuotationComparisonRow $f) => ! $f->estaBien()));
    }

    /**
     * Las filas ordenadas por lo que hay que hacer con ellas.
     *
     * Mezcladas, una cotización de diecinueve partidas contra un documento de
     * dieciocho líneas da treinta y siete filas en las que hay que ir a buscar
     * cuáles piden algo. Primero van las que el proveedor trajo y no cruzaron
     * —esas se enseñan una vez y quedan aprendidas—, después lo que no cotizó,
     * después lo que difiere, y al final lo que ya cuadra.
     *
     * @return list<QuotationComparisonRow>
     */
    public function ordenadas(): array
    {
        $peso = [
            'no_pedida' => 0,
            'sin_cotizar' => 1,
            'difiere' => 2,
            'igual' => 3,
        ];

        $filas = $this->todas();

        usort($filas, fn (QuotationComparisonRow $a, QuotationComparisonRow $b): int => ($peso[$a->estado] ?? 9) <=> ($peso[$b->estado] ?? 9));

        return $filas;
    }

    /**
     * ¿El documento no aportó ni una partida?
     *
     * No es lo mismo que estar vacío: si pediste tres cosas, la comparación
     * trae tres filas aunque el PDF fuera ilegible, todas marcadas como no
     * cotizadas. Contarlas como diferencias culparía al proveedor de un
     * documento que no se pudo leer.
     */
    public function elDocumentoNoAporto(): bool
    {
        if ($this->sobrantes !== []) {
            return false;
        }

        foreach ($this->filas as $fila) {
            if ($fila->cotizada !== null) {
                return false;
            }
        }

        return true;
    }

    /** Un resumen en una frase, que es lo primero que se lee. */
    public function resumen(): string
    {
        if ($this->filas === [] && $this->sobrantes === []) {
            return 'No se pudo leer ninguna partida del documento.';
        }

        if ($this->cuadra()) {
            return 'La cotización coincide con lo que pediste.';
        }

        return match ($n = $this->conDiferencias()) {
            1 => 'Hay 1 diferencia con lo que pediste.',
            default => sprintf('Hay %d diferencias con lo que pediste.', $n),
        };
    }
}
