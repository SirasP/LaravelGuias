<?php

namespace App\Support;

/**
 * Cómo se escribe la plata en Chile.
 *
 * Es una convención distinta de la de una cantidad, y ahí está la trampa:
 * «12.500» como cantidad son doce coma cinco, pero como precio son doce mil
 * quinientos. Un punto seguido de exactamente tres cifras es separador de
 * miles cuando hablamos de dinero, y nadie cotiza con milésimas de peso.
 */
final class ChileanMoney
{
    /** «$ 12.500», «12.500,50» o «12500» al mismo número. Null si no hay cifra. */
    public static function parse(string $valor): ?float
    {
        $texto = preg_replace('/[^\d.,]/u', '', $valor) ?? '';

        if ($texto === '') {
            return null;
        }

        $ultimaComa = strrpos($texto, ',');
        $ultimoPunto = strrpos($texto, '.');

        if ($ultimaComa !== false && $ultimoPunto !== false) {
            // Manda el que va último: en «12.500,50» decide la coma.
            $texto = $ultimaComa > $ultimoPunto
                ? str_replace(',', '.', str_replace('.', '', $texto))
                : str_replace(',', '', $texto);
        } elseif ($ultimaComa !== false || $ultimoPunto !== false) {
            $separador = $ultimaComa !== false ? ',' : '.';
            $partes = explode($separador, $texto);
            $ultima = end($partes);

            $texto = (count($partes) > 2 || strlen((string) $ultima) === 3)
                ? str_replace($separador, '', $texto)
                : str_replace($separador, '.', $texto);
        }

        return is_numeric($texto) ? (float) $texto : null;
    }

    /** Como se guarda en la base: punto decimal, dos cifras, sin miles. */
    public static function toDecimalString(string $valor): ?string
    {
        $numero = self::parse($valor);

        return $numero === null ? null : number_format($numero, 2, '.', '');
    }
}
