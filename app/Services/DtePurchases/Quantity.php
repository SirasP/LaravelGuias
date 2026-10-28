<?php

namespace App\Services\DtePurchases;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

final class Quantity
{
    public static function of(mixed $value): BigDecimal
    {
        try {
            return BigDecimal::of((string) ($value ?? '0'))->toScale(6, RoundingMode::UNNECESSARY);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['quantity' => 'Usa una cantidad válida con hasta seis decimales.']);
        }
    }

    public static function unit(?string $unit): string
    {
        $unit = mb_strtolower(trim((string) $unit));

        return match ($unit) {
            'un', 'un.', 'unidad', 'unidades', 'unit', 'units', 'units.', 'ud', 'uds', 'pza', 'pzas' => 'un',
            'kg', 'kgs', 'kilo', 'kilos', 'kilogramo', 'kilogramos' => 'kg',
            'l', 'lt', 'lts', 'litro', 'litros' => 'l',
            'm', 'mt', 'mts', 'metro', 'metros' => 'm',
            default => $unit,
        };
    }

    public static function sameUnit(?string $a, ?string $b): bool
    {
        return self::unit($a) !== '' && self::unit($a) === self::unit($b);
    }

    public static function packaged(string $description): bool
    {
        return (bool) preg_match('/\b(cajas?|packs?|paquetes?|docenas?|bidones?|rollos?)\b/iu', $description);
    }
}
