<?php

namespace App\Services\PurchaseRequests\Quotes;

/**
 * ¿Hablan de la misma medida lo pedido y lo cotizado?
 *
 * Los nombres no se parecen —«empaquetadura plana 5"» contra «EMPAQUETADURA
 * GOMA 125 MM»— y aun así son lo mismo, porque 5 pulgadas son 127 mm. Lo que
 * delata una pareja cruzada son los números: en la cotización de Cooprinsem de
 * la SC-2026-000103 la de 5" quedó con la de 75 MM, el perfil 100/100/5 con el
 * de 50x50x3 y el perno de ⅜ con el de ½, y de ahí iban derecho a Odoo.
 *
 * No afirma que la pareja esté mal: avisa para que una persona la mire antes
 * de que el precio de una cosa quede escrito en otra.
 */
final class QuoteMeasures
{
    /** Las fracciones de un solo carácter, como las escribe la gente. */
    private const FRACCIONES = [
        '¼' => ' 1/4', '½' => ' 1/2', '¾' => ' 3/4', '⅛' => ' 1/8',
        '⅜' => ' 3/8', '⅝' => ' 5/8', '⅞' => ' 7/8', '⅓' => ' 1/3', '⅔' => ' 2/3',
    ];

    private const MM_POR_PULGADA = 25.4;

    /** Cuánto pueden diferir dos medidas y seguir siendo la misma: 5" son 127 mm y se venden como 125. */
    private const TOLERANCIA = 0.04;

    /** Uno de los dos lleva una medida que el otro no tiene, y viceversa. */
    public static function chocan(?string $pedido, ?string $cotizado): bool
    {
        $a = self::de($pedido);
        $b = self::de($cotizado);

        if ($a === [] || $b === []) {
            return false;
        }

        [$sobranA, $sobranB] = self::cruzar($a, $b);

        // Que uno diga más que el otro —«2" /10 cms» contra «2"»— no es
        // contradecirse. Sólo cuando a los dos les sobra algo.
        return $sobranA > 0 && $sobranB > 0;
    }

    /**
     * Las dos llevan al menos dos medidas y las del más corto están todas en el
     * otro: «100/100/5/6.000» y «100x100x5MM». Eso dice más que las palabras.
     */
    public static function calzan(?string $pedido, ?string $cotizado): bool
    {
        $a = self::de($pedido);
        $b = self::de($cotizado);

        if (count($a) < 2 || count($b) < 2) {
            return false;
        }

        [$sobranA, $sobranB] = self::cruzar($a, $b);

        return $sobranA === 0 || $sobranB === 0;
    }

    /**
     * Las medidas de un nombre. Cada una con sus equivalentes: la que va en
     * pulgadas vale también en milímetros.
     *
     * @return list<list<float>>
     */
    public static function de(?string $texto): array
    {
        $texto = strtr(html_entity_decode((string) $texto, ENT_QUOTES | ENT_HTML5, 'UTF-8'), self::FRACCIONES);
        $texto = str_replace(['”', '“', '″', "''"], '"', $texto);

        // Una fracción sólo es fracción con denominador de pulgada y numerador
        // menor: «100/100/5» es un perfil, no cien centésimos.
        $patron = '/(?<![\d.,])(?:(\d)\s+)?(\d{1,2})\s*\/\s*(2|4|8|16|32|64)(?![\d])(\s*")?'
            .'|(\d{1,3}(?:\.\d{3})+(?![\d,])|\d+(?:[.,]\d+)?)(\s*")?/u';

        preg_match_all($patron, $texto, $encontradas, PREG_SET_ORDER);

        $medidas = [];

        foreach ($encontradas as $m) {
            if (($m[3] ?? '') !== '' && (int) $m[2] < (int) $m[3]) {
                $valor = (int) $m[2] / (int) $m[3] + (($m[1] ?? '') !== '' ? (int) $m[1] : 0);
                $pulgadas = true;
            } elseif (($m[3] ?? '') !== '') {
                // «3/2»: no es una fracción de pulgada, son dos números.
                $medidas[] = [(float) $m[2]];
                $medidas[] = [(float) $m[3]];

                continue;
            } else {
                $numero = (string) ($m[5] ?? '');

                if ($numero === '') {
                    continue;
                }

                $valor = preg_match('/^\d{1,3}(\.\d{3})+$/', $numero)
                    ? (float) str_replace('.', '', $numero)
                    : (float) str_replace(',', '.', $numero);
                $pulgadas = trim((string) ($m[6] ?? '')) === '"';
            }

            $medidas[] = $pulgadas ? [$valor, $valor * self::MM_POR_PULGADA] : [$valor];
        }

        return $medidas;
    }

    /**
     * Tacha, una por una, las medidas que aparecen en los dos lados.
     *
     * @param  list<list<float>>  $a
     * @param  list<list<float>>  $b
     * @return array{0: int, 1: int} cuántas le sobran a cada uno
     */
    private static function cruzar(array $a, array $b): array
    {
        $libres = $b;
        $sobranA = 0;

        foreach ($a as $medida) {
            $encontrada = null;

            foreach ($libres as $j => $otra) {
                if (self::iguales($medida, $otra)) {
                    $encontrada = $j;

                    break;
                }
            }

            if ($encontrada === null) {
                $sobranA++;
            } else {
                unset($libres[$encontrada]);
            }
        }

        return [$sobranA, count($libres)];
    }

    /** @param list<float> $a @param list<float> $b */
    private static function iguales(array $a, array $b): bool
    {
        foreach ($a as $x) {
            foreach ($b as $y) {
                $mayor = max(abs($x), abs($y));

                if ($mayor === 0.0 || abs($x - $y) <= $mayor * self::TOLERANCIA) {
                    return true;
                }
            }
        }

        return false;
    }
}
