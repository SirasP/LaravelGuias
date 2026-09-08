<?php

namespace App\Services\PurchaseRequests\Quotes;

/** El resultado del emparejado: qué está decidido y qué está propuesto. */
final class QuoteMatching
{
    /**
     * @param  array<int, int>  $seguras  Índice de partida => índice de línea.
     * @param  array<int, int>  $probables  Lo mismo, pero pendiente de que alguien lo mire.
     * @param  array<string, float>  $puntajes
     */
    public function __construct(
        public readonly array $seguras,
        public readonly array $probables,
        private readonly array $puntajes,
    ) {}

    public function lineaDe(int $item): ?int
    {
        return $this->seguras[$item] ?? $this->probables[$item] ?? null;
    }

    public function esProbable(int $item): bool
    {
        return isset($this->probables[$item]);
    }

    public function confianza(int $item, int $linea): float
    {
        return $this->puntajes[$item.':'.$linea] ?? 0.0;
    }

    /** Las líneas que ninguna partida reclamó. */
    public function lineasLibres(int $cuantas): array
    {
        if ($cuantas < 1) {
            return [];
        }

        $usadas = array_flip($this->seguras + $this->probables);

        return array_values(array_filter(range(0, $cuantas - 1), fn (int $j): bool => ! isset($usadas[$j])));
    }
}
