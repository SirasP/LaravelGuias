<?php

namespace App\Support;

/**
 * RUT chileno: normalización y validación del dígito verificador.
 *
 * Se resuelve con código y no con el modelo: un RUT es un patrón fijo con una
 * comprobación matemática. Pedírselo a una IA sólo agrega la posibilidad de
 * que lo invente.
 */
final class Rut
{
    /** Deja el RUT en formato canónico «77415879-0», o null si no es válido. */
    public static function normalize(?string $valor): ?string
    {
        if (blank($valor)) {
            return null;
        }

        $limpio = strtoupper(preg_replace('/[^0-9kK]/', '', $valor) ?? '');

        if (mb_strlen($limpio) < 7 || mb_strlen($limpio) > 9) {
            return null;
        }

        $cuerpo = substr($limpio, 0, -1);
        $digito = substr($limpio, -1);

        if (! ctype_digit($cuerpo) || self::digitoVerificador($cuerpo) !== $digito) {
            return null;
        }

        return $cuerpo.'-'.$digito;
    }

    public static function isValid(?string $valor): bool
    {
        return self::normalize($valor) !== null;
    }

    /** Formato para mostrar: «77.415.879-0». */
    public static function format(?string $valor): ?string
    {
        $normalizado = self::normalize($valor);

        if ($normalizado === null) {
            return null;
        }

        [$cuerpo, $digito] = explode('-', $normalizado);

        return number_format((int) $cuerpo, 0, '', '.').'-'.$digito;
    }

    /**
     * Encuentra todos los RUT válidos de un texto, en orden de aparición y
     * sin repetir.
     *
     * @return list<array{rut: string, posicion: int}>
     */
    public static function findAll(string $texto): array
    {
        // Acepta «R.U.T.: 77.045.469-7», «RUT 77045469-7» y el número suelto.
        preg_match_all('/\b(\d{1,3}(?:\.\d{3})*|\d{7,8})\s*-\s*([0-9kK])\b/u', $texto, $coincidencias, PREG_OFFSET_CAPTURE);

        $encontrados = [];
        $vistos = [];

        foreach ($coincidencias[0] as $indice => [$bruto, $offset]) {
            $normalizado = self::normalize($bruto);

            if ($normalizado === null || isset($vistos[$normalizado])) {
                continue;
            }

            $vistos[$normalizado] = true;
            $encontrados[] = ['rut' => $normalizado, 'posicion' => $offset];
        }

        return $encontrados;
    }

    private static function digitoVerificador(string $cuerpo): string
    {
        $suma = 0;
        $multiplicador = 2;

        for ($i = strlen($cuerpo) - 1; $i >= 0; $i--) {
            $suma += (int) $cuerpo[$i] * $multiplicador;
            $multiplicador = $multiplicador === 7 ? 2 : $multiplicador + 1;
        }

        $resto = 11 - ($suma % 11);

        return match ($resto) {
            11 => '0',
            10 => 'K',
            default => (string) $resto,
        };
    }
}
