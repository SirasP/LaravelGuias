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

    /** Cuántas partidas pediste: la tabla tiene siempre exactamente estas filas. */
    public function partidas(): int
    {
        return count($this->filas);
    }

    /** Cuántas cruzaron con un renglón del proveedor, firmes o propuestas. */
    public function cruzadas(): int
    {
        return count(array_filter($this->filas, fn (QuotationComparisonRow $f) => $f->cruzo()));
    }

    /** Las parejas que el programa propone y nadie ha confirmado. */
    public function porConfirmar(): int
    {
        return count(array_filter($this->filas, fn (QuotationComparisonRow $f) => $f->esPropuesta()));
    }

    /** Lo que se pidió y el proveedor no cotizó. */
    public function sinCotizar(): int
    {
        return count(array_filter($this->filas, fn (QuotationComparisonRow $f) => $f->estado === 'sin_cotizar'));
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
            'propuesta' => 0,
            'no_pedida' => 1,
            'sin_cotizar' => 2,
            'difiere' => 3,
            'igual' => 4,
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

        $partes = [];

        if (($n = $this->porConfirmar()) > 0) {
            $partes[] = $n === 1 ? '1 pareja por confirmar' : sprintf('%d parejas por confirmar', $n);
        }

        if (($n = $this->sinCotizar()) > 0) {
            $partes[] = $n === 1 ? '1 partida sin cotizar' : sprintf('%d partidas sin cotizar', $n);
        }

        if (($n = count(array_filter($this->filas, fn (QuotationComparisonRow $f) => $f->estado === 'difiere'))) > 0) {
            $partes[] = $n === 1 ? '1 diferencia' : sprintf('%d diferencias', $n);
        }

        if (($n = count($this->sobrantes)) > 0) {
            $partes[] = $n === 1 ? '1 línea que no pediste' : sprintf('%d líneas que no pediste', $n);
        }

        return $partes === []
            ? 'La cotización coincide con lo que pediste.'
            : ucfirst(implode(', ', $partes)).'.';
    }
}
