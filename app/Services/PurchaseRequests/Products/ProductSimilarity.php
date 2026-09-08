<?php

namespace App\Services\PurchaseRequests\Products;

use Illuminate\Support\Str;

/**
 * Cuánto se parecen dos nombres de producto.
 *
 * Copia deliberada de `GmailDteInventoryService::similarityScore`, no préstamo:
 * aquel código escribe en `fuelcontrol` y no se toca. Se asume el costo de
 * tener la idea en dos sitios.
 *
 * Mezcla tres medidas porque cada una falla distinto: similar_text se deja
 * engañar por textos largos, levenshtein por el orden de las palabras, y los
 * tokens ignoran las erratas. Juntas se compensan.
 */
final class ProductSimilarity
{
    /**
     * Tokens que, si difieren, significan que son productos distintos.
     *
     * Del módulo de facturas viene la idea con las tallas: XL no es «parecido»
     * a XXL, es otra prenda. En este catálogo hacen falta además los voltajes
     * y las medidas: un codo de 75 mm y uno de 110 mm se parecen muchísimo en
     * el texto y no sirven para lo mismo.
     *
     * @var list<string>
     */
    private const TALLAS = [
        'xs', 'xp', 's', 'm', 'l', 'xl', 'xxl', 'xxxl',
        'xg', 'xxg', '2xl', '3xl', '4xl', '5xl',
        'xchico', 'chico', 'mediano', 'grande', 'xgrande',
    ];

    public function score(string $izquierda, string $derecha): float
    {
        $a = $this->normalize($izquierda);
        $b = $this->normalize($derecha);

        if ($a === '' || $b === '') {
            return 0.0;
        }

        if ($a === $b) {
            return 1.0;
        }

        $tokensA = $this->tokens($a);
        $tokensB = $this->tokens($b);

        // Los descalificadores van primero: si difieren, no hay nada que medir.
        foreach ([
            fn (): bool => $this->difieren($tokensA, $tokensB, self::TALLAS),
            fn (): bool => $this->difierenMedidas($a, $b),
        ] as $descalifica) {
            if ($descalifica()) {
                return 0.0;
            }
        }

        similar_text($a, $b, $porcentaje);
        $similar = $porcentaje / 100;

        $largo = max(strlen($a), strlen($b), 1);
        $lev = 1 - (levenshtein($a, $b) / $largo);

        $comunes = array_intersect($tokensA, $tokensB);
        $union = array_unique(array_merge($tokensA, $tokensB));
        $porTokens = $union !== [] ? count($comunes) / count($union) : 0.0;

        return ($similar * 0.45) + (max($lev, 0.0) * 0.35) + ($porTokens * 0.20);
    }

    /**
     * El texto reducido a lo comparable: sin tildes, sin mayúsculas, sin
     * puntuación, con las abreviaturas más comunes unificadas.
     */
    public function normalize(string $valor): string
    {
        $texto = Str::of($valor)->ascii()->lower()->value();
        $texto = preg_replace('/[^a-z0-9]+/', ' ', $texto) ?? '';

        $palabras = [];

        foreach (preg_split('/\s+/', trim($texto)) ?: [] as $token) {
            if ($token === '') {
                continue;
            }

            $palabras[] = match ($token) {
                'unid', 'unids', 'unidad', 'unidades', 'un', 'uds' => 'un',
                'lts', 'lt', 'litro', 'litros' => 'lt',
                'kgs', 'kg', 'kilo', 'kilos' => 'kg',
                'mts', 'mt', 'metro', 'metros' => 'mt',
                'cja', 'caja', 'cajas' => 'caja',
                'pza', 'pzas', 'pieza', 'piezas' => 'pza',
                default => $token,
            };
        }

        return implode(' ', $palabras);
    }

    /** @return list<string> */
    private function tokens(string $normalizado): array
    {
        return array_values(array_unique(array_filter(explode(' ', $normalizado))));
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @param  list<string>  $vocabulario
     */
    private function difieren(array $a, array $b, array $vocabulario): bool
    {
        $enA = array_values(array_intersect($a, $vocabulario));
        $enB = array_values(array_intersect($b, $vocabulario));
        sort($enA);
        sort($enB);

        // Si sólo uno declara talla no se puede afirmar que difieran: puede que
        // el otro simplemente no la mencione.
        return $enA !== [] && $enB !== [] && $enA !== $enB;
    }

    /**
     * ¿Hablan de medidas distintas?
     *
     * «Tubo PVC 75 mm» y «Tubo PVC 110 mm» comparten casi todas las letras y
     * no sirven para lo mismo. Igual con 12V y 24V. Pero la comparación sólo
     * concluye algo cuando los dos textos miden lo mismo: «1000 ml» contra
     * «1 KG» no se contradicen, hablan de cosas distintas del mismo envase, y
     * durante meses eso descartó el bloqueador solar con un 0,0000.
     *
     * Un número pegado a letras tampoco es una medida: es un código o una
     * marca. El 3 de «3M» no contradice al 510 de «H510A».
     */
    private function difierenMedidas(string $a, string $b): bool
    {
        $enA = $this->medidas($a);
        $enB = $this->medidas($b);

        foreach ($enA as $familia => $valores) {
            if (! isset($enB[$familia])) {
                // Que uno declare el largo y el otro no, no prueba nada.
                continue;
            }

            if (array_intersect($valores, $enB[$familia]) === []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Las medidas del texto, agrupadas por unidad.
     *
     * La familia vacía son los números sueltos sin unidad —el 75 de un codo,
     * la talla 7 de un guante—, que sólo se comparan entre ellos.
     *
     * @return array<string, list<string>>
     */
    private function medidas(string $normalizado): array
    {
        $unidades = [
            'mm', 'cm', 'mt', 'km', 'lt', 'ml', 'cc', 'kg', 'gr', 'mg',
            'v', 'w', 'kw', 'hp', 'amp', 'psi', 'pulg', 'oz', 'lb',
        ];

        $tokens = array_values(array_filter(explode(' ', $normalizado), fn ($t) => $t !== ''));
        $medidas = [];

        foreach ($tokens as $i => $token) {
            $familia = null;
            $valor = null;

            if (preg_match('/^(\d+)$/', $token, $m) === 1) {
                // «1000 ml»: la unidad viene en la palabra siguiente.
                $siguiente = $tokens[$i + 1] ?? '';
                $familia = in_array($siguiente, $unidades, true) ? $siguiente : '';
                $valor = $m[1];
            } elseif (preg_match('/^(\d+)([a-z]+)$/', $token, $m) === 1 && in_array($m[2], $unidades, true)) {
                // «12v», «75mm». La «m» sola queda fuera a propósito: «3M» es
                // una marca, no tres metros.
                $familia = $m[2];
                $valor = $m[1];
            }

            if ($familia === null || $valor === null) {
                continue;
            }

            $valor = ltrim($valor, '0') === '' ? '0' : ltrim($valor, '0');
            $medidas[$familia][] = $valor;
        }

        return array_map(fn (array $v): array => array_values(array_unique($v)), $medidas);
    }
}
