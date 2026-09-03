<?php

namespace App\Services\PurchaseRequests\Quotes;

/**
 * Las cotizaciones recibidas, unas al lado de otras.
 *
 * Con dos o tres proveedores, mirar tabla por tabla obliga a comparar de
 * memoria: cuál trae más barato el cemento, quién no cotizó la arena, cuánto
 * suma cada uno. Eso es una cuadrícula, y una cuadrícula se lee de un vistazo.
 *
 * El más barato de cada partida se marca con aritmética, no con criterio: es
 * el precio unitario más bajo entre los que sí la cotizaron. Empate no marca
 * a nadie, porque señalar a uno sería inventar una diferencia.
 */
class QuoteMatrix
{
    /**
     * @param  list<array{nombre: string, archivo: string}>  $proveedores
     * @param  list<array{partida: string, pedida: bool, precios: list<?float>, masBarato: ?int}>  $filas
     * @param  list<array{total: float, faltan: int}>  $totales
     */
    private function __construct(
        public readonly array $proveedores,
        public readonly array $filas,
        public readonly array $totales,
    ) {}

    /**
     * @param  list<array{ingestion: mixed, resultado: QuotationComparisonResult}>  $comparaciones
     */
    public static function de(array $comparaciones): ?self
    {
        // Con una sola cotización no hay nada que cruzar: su propia tabla ya
        // lo dice todo, y una cuadrícula de una columna sólo repite.
        if (count($comparaciones) < 2) {
            return null;
        }

        $proveedores = [];
        $porPartida = [];

        foreach ($comparaciones as $columna => $comparacion) {
            $lectura = $comparacion['ingestion'];
            $proveedores[] = [
                'nombre' => (string) ($lectura->supplier_name ?: 'Proveedor sin identificar'),
                'archivo' => (string) $lectura->original_name,
            ];

            foreach ($comparacion['resultado']->todas() as $fila) {
                $nombre = $fila->pedida?->product_service
                    ?? (string) ($fila->cotizada['product_service'] ?? '');

                if ($nombre === '') {
                    continue;
                }

                $clave = mb_strtolower(trim($nombre));
                $porPartida[$clave] ??= ['partida' => $nombre, 'pedida' => false, 'precios' => []];
                $porPartida[$clave]['pedida'] = $porPartida[$clave]['pedida'] || $fila->pedida !== null;
                $porPartida[$clave]['precios'][$columna] = self::precio($fila->cotizada);
            }
        }

        $cuantas = count($comparaciones);
        $filas = [];
        $totales = array_fill(0, $cuantas, ['total' => 0.0, 'faltan' => 0]);

        foreach ($porPartida as $datos) {
            $precios = [];

            for ($i = 0; $i < $cuantas; $i++) {
                $precios[] = $datos['precios'][$i] ?? null;
            }

            $filas[] = [
                'partida' => $datos['partida'],
                'pedida' => $datos['pedida'],
                'precios' => $precios,
                'masBarato' => self::indiceDelMasBarato($precios),
            ];

            foreach ($precios as $i => $precio) {
                if ($precio !== null) {
                    $totales[$i]['total'] += $precio;

                    continue;
                }

                // Sólo cuenta como faltante lo que sí se pidió. Un flete que
                // otro proveedor agregó por su cuenta no es algo que éste
                // haya dejado de cotizar.
                if ($datos['pedida']) {
                    $totales[$i]['faltan']++;
                }
            }
        }

        return new self($proveedores, $filas, $totales);
    }

    /** @param array<string, mixed>|null $cotizada */
    private static function precio(?array $cotizada): ?float
    {
        $valor = $cotizada['unit_price'] ?? null;

        return is_numeric($valor) ? (float) $valor : null;
    }

    /**
     * El más barato, sólo si gana solo.
     *
     * @param  list<?float>  $precios
     */
    private static function indiceDelMasBarato(array $precios): ?int
    {
        $conPrecio = array_filter($precios, fn (?float $p): bool => $p !== null);

        if (count($conPrecio) < 2) {
            return null;
        }

        $minimo = min($conPrecio);

        $ganadores = array_keys(array_filter(
            $conPrecio,
            fn (float $p): bool => abs($p - $minimo) < 0.005,
        ));

        return count($ganadores) === 1 ? $ganadores[0] : null;
    }
}
