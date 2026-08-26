<?php

namespace App\Services;

/**
 * Detecta el factor de conversión (volumen/masa) de un producto
 * a partir de su nombre, usando patrones regex comunes en inventarios de taller.
 *
 * Ejemplos:
 *   "NUTO H 68 X 208 LT"       → factor: 208,  unidad_consumo: Ltrs, unidad_compra: tambor
 *   "ACEITE MOTOR 15W40 20L"   → factor: 20,   unidad_consumo: Ltrs, unidad_compra: bidón
 *   "COOLANT ANTIFREEZE 3.78L" → factor: 3.78, unidad_consumo: Ltrs, unidad_compra: envase
 *   "GRASA LI EP2 400 GR"      → factor: 400,  unidad_consumo: gr,   unidad_compra: cartucho
 *   "FILTRO ACEITE JD 6120"    → null (sin volumen en el nombre)
 */
class ProductVolumeDetector
{
    /**
     * @return array{factor: float, unidad_consumo: string, unidad_compra: string, auto_detected: true}|null
     */
    public static function detect(string $nombre): ?array
    {
        // Normalizar: mayúsculas, coma decimal → punto
        $n = strtoupper(trim($nombre));
        $n = (string) preg_replace('/(\d),(\d)/', '$1.$2', $n);

        // ── Patrones ordenados por especificidad ─────────────────────────
        $matchers = [
            // Litros — "208 LT", "208 LTS", "208 LTRS", "208 LITROS", "20L", "X 208 LT"
            [
                'regex' => '/(?:X\s*)?(\d+(?:\.\d+)?)\s*(?:LITROS?|LTRS?|LTS?|(?<![A-Z])L(?![A-Z\d]))/u',
                'unidad' => 'Ltrs',
                'tipo' => 'vol',
            ],
            // Mililitros — "500 ML", "500ML"
            [
                'regex' => '/(\d+(?:\.\d+)?)\s*ML\b/u',
                'unidad' => 'ml',
                'tipo' => 'vol',
            ],
            // Kilogramos — "25 KG", "25KG", "25 KILOS"
            [
                'regex' => '/(\d+(?:\.\d+)?)\s*(?:KGS?|KILOS?)\b/u',
                'unidad' => 'kg',
                'tipo' => 'masa',
            ],
            // Gramos — "400 GR", "400GR", "400 GRS"
            [
                'regex' => '/(\d+(?:\.\d+)?)\s*GRS?\b/u',
                'unidad' => 'gr',
                'tipo' => 'masa',
            ],
        ];

        foreach ($matchers as $m) {
            if (preg_match($m['regex'], $n, $hits)) {
                $valor = (float) $hits[1];
                if ($valor <= 0 || $valor > 99999) {
                    continue; // descartar valores absurdos
                }

                return [
                    'factor' => $valor,
                    'unidad_consumo' => $m['unidad'],
                    'unidad_compra' => self::guessContainer($valor, $m['unidad']),
                    'auto_detected' => true,
                ];
            }
        }

        return null;
    }

    /**
     * Intenta detectar en una colección de nombres y devuelve
     * solo los que tienen coincidencia, como [product_id => detección].
     *
     * @param  iterable<object{id: int, nombre: string}>  $products
     * @return array<int, array>
     */
    public static function detectMany(iterable $products): array
    {
        $result = [];
        foreach ($products as $p) {
            $det = self::detect((string) $p->nombre);
            if ($det !== null) {
                $det['nombre'] = $p->nombre;
                $det['product_id'] = $p->id;
                $result[$p->id] = $det;
            }
        }

        return $result;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private static function guessContainer(float $valor, string $unidad): string
    {
        return match ($unidad) {
            'ml' => $valor >= 900 ? 'litro' : 'frasco',
            'gr' => $valor >= 1000 ? 'kilo' : ($valor >= 400 ? 'cartucho' : 'sobre'),
            'kg' => $valor >= 20 ? 'saco' : 'bolsa',
            // Ltrs
            default => match (true) {
                $valor >= 150 => 'tambor',
                $valor >= 20 => 'bidón',
                $valor >= 4 => 'galón',
                default => 'envase',
            },
        };
    }
}
