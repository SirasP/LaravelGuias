<?php

namespace App\Services\DtePurchases;

/**
 * The numbers in a product name are its measures. «TUB CUAD NEG 40 X 3.0 MM»
 * and «TUB CUAD NEG 40 X 2.0 MM» read 92 % alike, yet they are different tubes.
 */
final class Measures
{
    /**
     * Each number with its text: «1 1/2"» is 1.5, «3.0» is 3 and «1.000 ML» is 1000.
     *
     * @return list<array{text: string, value: string}>
     */
    public static function of(?string $name): array
    {
        preg_match_all('/\b[1-9]\s+\d+\s*\/\s*\d+|\d+\s*\/\s*\d+|\d+(?:[.,]\d+)*/', PurchaseReferences::text($name), $matches);

        return array_map(fn (string $text): array => ['text' => $text, 'value' => self::value($text)], $matches[0]);
    }

    /**
     * What each side has that the other lacks, or null when they agree.
     *
     * One side may say more than the other —«40 X 3.0» and «40 X 3.0 X 6 MTS»—
     * and a name without numbers says nothing; only two different sets disagree.
     *
     * @return array{invoice: list<string>, request: list<string>}|null
     */
    public static function difference(?string $invoice, ?string $request): ?array
    {
        $left = self::of($invoice);
        $right = self::of($request);
        if ($left === [] || $right === []) {
            return null;
        }
        $onlyLeft = self::without($left, $right);
        $onlyRight = self::without($right, $left);
        if ($onlyLeft === [] || $onlyRight === []) {
            return null;
        }

        return ['invoice' => array_column($onlyLeft, 'text'), 'request' => array_column($onlyRight, 'text')];
    }

    public static function differ(?string $invoice, ?string $request): bool
    {
        return self::difference($invoice, $request) !== null;
    }

    /** The numbers of `$from` left after crossing out, one by one, those of `$other`. */
    private static function without(array $from, array $other): array
    {
        $pending = array_count_values(array_column($other, 'value'));
        $left = [];
        foreach ($from as $number) {
            if (($pending[$number['value']] ?? 0) > 0) {
                $pending[$number['value']]--;
            } else {
                $left[] = $number;
            }
        }

        return $left;
    }

    private static function value(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', trim($text));
        if (preg_match('/^(\d+) (\d+) ?\/ ?(\d+)$/', $text, $m)) {
            $value = (int) $m[3] > 0 ? $m[1] + $m[2] / $m[3] : (float) $m[1];
        } elseif (preg_match('/^(\d+) ?\/ ?(\d+)$/', $text, $m)) {
            $value = (int) $m[2] > 0 ? $m[1] / $m[2] : 0.0;
        } elseif (str_contains($text, ',') && str_contains($text, '.')) {
            $value = (float) str_replace(['.', ','], ['', '.'], $text);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $text)) {
            // Chilean thousands: «1.000 ML».
            $value = (float) str_replace('.', '', $text);
        } else {
            $value = (float) str_replace(',', '.', $text);
        }

        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }
}
