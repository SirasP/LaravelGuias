<?php

namespace App\Support;

class AgrakNormalizer
{
    public static function patente(?string $v): ?string
    {
        if (!$v)
            return null;
        $v = strtoupper($v);
        $v = preg_replace('/[^A-Z0-9]/', '', $v);
        return $v ?: null;
    }

    public static function nombre(?string $v): ?string
    {
        if (!$v)
            return null;
        $v = mb_strtolower(trim($v));
        $v = preg_replace('/\s+/', ' ', $v);
        return mb_convert_case($v, MB_CASE_TITLE, 'UTF-8');
    }

    public static function exportadora(?string $v1, ?string $v2 = null): ?string
    {
        $raw = trim((string) ($v1 ?: $v2));
        if ($raw === '')
            return null;

        $key = mb_strtolower($raw);

        $map = [
            'vita food' => 'Vitafoods Spa',
            'vitafood' => 'Vitafoods Spa',
            'vitafoods' => 'Vitafoods Spa',
            'rio futuro' => 'Rio Futuro Procesos SpA',
        ];

        return $map[$key] ?? mb_convert_case($raw, MB_CASE_TITLE, 'UTF-8');
    }
}
