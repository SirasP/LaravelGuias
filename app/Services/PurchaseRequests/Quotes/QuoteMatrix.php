<?php

namespace App\Services\PurchaseRequests\Quotes;

/**
 * Las cotizaciones recibidas, unas al lado de otras.
 *
 * Con dos o tres proveedores, mirar tabla por tabla obliga a comparar de
 * memoria: cuál trae más barato el cemento, quién no cotizó la arena, cuánto
 * suma cada uno. Eso es una cuadrícula, y una cuadrícula se lee de un vistazo.
 *
 * Cada celda dice tres cosas —cuánto cotizó, a qué precio y cuánto suma esa
 * línea—, porque decidir a quién comprarle con sólo el unitario obliga a
 * multiplicar de cabeza diecinueve veces. El más barato de cada partida se
 * marca con aritmética, no con criterio: es el precio unitario más bajo entre
 * los que sí la cotizaron. Empate no marca a nadie, porque señalar a uno sería
 * inventar una diferencia.
 *
 * Las filas son las partidas de la solicitud, en su orden. Lo que un proveedor
 * agregó por su cuenta va aparte: mezclarlo hacía que las demás columnas
 * dijeran «no cotizó» sobre algo que nadie les pidió.
 */
class QuoteMatrix
{
    /**
     * @param  list<array{nombre: string, archivo: string}>  $proveedores
     * @param  list<array{partida: string, cantidad: ?float, unidad: string, ofertas: list<?array{cantidad: ?float, unitario: ?float, total: ?float, porConfirmar: bool}>, masBarato: ?int}>  $filas
     * @param  list<array{texto: string, columna: int, proveedor: string, cantidad: ?float, unitario: ?float, total: ?float}>  $agregadas
     * @param  list<array{total: float, faltan: int, porConfirmar: int}>  $totales
     */
    private function __construct(
        public readonly array $proveedores,
        public readonly array $filas,
        public readonly array $agregadas,
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

        $cuantas = count($comparaciones);
        $proveedores = [];
        $porPartida = [];
        $agregadas = [];

        foreach ($comparaciones as $columna => $comparacion) {
            $lectura = $comparacion['ingestion'];
            $nombreProveedor = (string) ($lectura->supplier_name ?: 'Proveedor sin identificar');
            $proveedores[] = ['nombre' => $nombreProveedor, 'archivo' => (string) $lectura->original_name];

            foreach ($comparacion['resultado']->filas as $orden => $fila) {
                if ($fila->pedida === null) {
                    continue;
                }

                $porPartida[$orden] ??= [
                    'partida' => (string) $fila->pedida->product_service,
                    'cantidad' => is_numeric($fila->pedida->quantity) ? (float) $fila->pedida->quantity : null,
                    'unidad' => (string) $fila->pedida->unit,
                    'ofertas' => array_fill(0, $cuantas, null),
                ];

                $porPartida[$orden]['ofertas'][$columna] = self::oferta($fila);
            }

            foreach ($comparacion['resultado']->sobrantes as $fila) {
                $agregadas[] = [
                    'texto' => (string) ($fila->cotizada['product_service'] ?? ''),
                    'columna' => $columna,
                    'proveedor' => $nombreProveedor,
                    'cantidad' => self::numero($fila->cotizada['quantity'] ?? null),
                    'unitario' => self::numero($fila->cotizada['unit_price'] ?? null),
                    'total' => self::total($fila->cotizada),
                ];
            }
        }

        ksort($porPartida);

        $filas = [];
        $totales = array_fill(0, $cuantas, ['total' => 0.0, 'faltan' => 0, 'porConfirmar' => 0]);

        foreach ($porPartida as $datos) {
            $unitarios = array_map(
                fn (?array $oferta): ?float => $oferta['unitario'] ?? null,
                $datos['ofertas'],
            );

            $filas[] = [
                'partida' => $datos['partida'],
                'cantidad' => $datos['cantidad'],
                'unidad' => $datos['unidad'],
                'ofertas' => $datos['ofertas'],
                'masBarato' => self::indiceDelMasBarato($unitarios),
            ];

            foreach ($datos['ofertas'] as $i => $oferta) {
                if ($oferta === null) {
                    $totales[$i]['faltan']++;

                    continue;
                }

                $totales[$i]['total'] += $oferta['total'] ?? 0.0;

                if ($oferta['porConfirmar']) {
                    $totales[$i]['porConfirmar']++;
                }
            }
        }

        return new self($proveedores, $filas, $agregadas, $totales);
    }

    /**
     * Lo que ese proveedor ofreció para esa partida, o nada si no la cotizó.
     *
     * @return array{cantidad: ?float, unitario: ?float, total: ?float, porConfirmar: bool}|null
     */
    private static function oferta(QuotationComparisonRow $fila): ?array
    {
        if ($fila->cotizada === null) {
            return null;
        }

        return [
            'cantidad' => self::numero($fila->cotizada['quantity'] ?? null),
            'unitario' => self::numero($fila->cotizada['unit_price'] ?? null),
            'total' => self::total($fila->cotizada),
            'porConfirmar' => $fila->esPropuesta(),
        ];
    }

    /**
     * Lo que suma esa línea: precio por cantidad.
     *
     * Se calcula con la cantidad que el proveedor cotizó, no con la que se
     * pidió. Si ofreció 288 de las 300 que pediste, su total es de 288: fingir
     * lo contrario compararía dos cosas distintas.
     *
     * @param  array<string, mixed>|null  $cotizada
     */
    private static function total(?array $cotizada): ?float
    {
        $unitario = self::numero($cotizada['unit_price'] ?? null);
        $cantidad = self::numero($cotizada['quantity'] ?? null);

        if ($unitario === null) {
            return null;
        }

        return $cantidad === null ? $unitario : $unitario * $cantidad;
    }

    private static function numero(mixed $valor): ?float
    {
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
