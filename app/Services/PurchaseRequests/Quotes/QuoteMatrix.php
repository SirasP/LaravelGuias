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
 *
 * Las piezas sí van dentro de su partida: Climent cotizó la válvula de la
 * SC-2026-000114 en $61.716 y aparte las dos flanges volantes que lleva, a
 * $12.879. Sin sumarlas, su válvula parecía la más barata por $25.758 menos
 * de lo que de verdad cuesta, y su total quedaba corto en lo mismo.
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

                $porPartida[$orden]['ofertas'][$columna] = self::oferta($fila, $comparacion['resultado']->piezasDe((int) $fila->pedida->getKey()));
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
     * Lo que dibuja la comparación compartida (<x-compras.comparacion>), con
     * la misma forma que la de la orden de Odoo. El más conveniente es el más
     * barato de los que cotizaron todo: uno al que le faltan partidas suma
     * menos sin ser más barato.
     *
     * @return array{columnas: list<array<string, mixed>>, filas: list<array<string, mixed>>, totales: list<array<string, mixed>>, mejor: ?int}
     */
    public function paraVista(): array
    {
        $totales = array_map(function (array $t): array {
            $iva = round($t['total'] * 0.19);
            $notas = [];
            if ($t['faltan'] > 0) {
                $notas[] = 'sin cotizar: '.$t['faltan'].' '.($t['faltan'] === 1 ? 'partida' : 'partidas');
            }
            if ($t['porConfirmar'] > 0) {
                $notas[] = 'incluye '.$t['porConfirmar'].' '.($t['porConfirmar'] === 1 ? 'pareja' : 'parejas').' por confirmar';
            }

            return ['neto' => $t['total'], 'iva' => $iva, 'total' => $t['total'] + $iva, 'notas' => $notas];
        }, $this->totales);

        $completas = array_filter(array_keys($this->totales), fn (int $i): bool => $this->totales[$i]['faltan'] === 0 && $this->totales[$i]['total'] > 0);
        $mejor = collect($completas)->sortBy(fn (int $i): float => $this->totales[$i]['total'])->first();

        return [
            'columnas' => array_map(fn (array $p): array => ['nombre' => $p['nombre'], 'detalle' => $p['archivo']], $this->proveedores),
            'filas' => array_map(fn (array $f): array => [
                'nombre' => $f['partida'], 'cantidad' => $f['cantidad'], 'unidad' => $f['unidad'], 'mejor' => $f['masBarato'],
                'celdas' => array_map(fn (?array $o): ?array => $o === null ? null : [
                    'cantidad' => $o['cantidad'], 'unitario' => $o['unitarioLinea'], 'total' => $o['total'],
                    'piezas' => $o['piezas'], 'precio' => $o['unitario'], 'porConfirmar' => $o['porConfirmar'],
                ], $f['ofertas']),
            ], $this->filas),
            'totales' => $totales,
            'mejor' => $mejor,
        ];
    }

    /**
     * Lo que ese proveedor ofreció para esa partida, o nada si no la cotizó.
     *
     * Con sus piezas: el total las suma, y el unitario es lo que cuesta cada
     * unidad de la partida con ellas, que es lo que se compara entre
     * proveedores. `piezas` es lo que suman, para decirlo en la celda.
     *
     * @param  list<array{linea: array<string, mixed>}>  $piezas
     * @return array{cantidad: ?float, unitario: ?float, unitarioLinea: ?float, total: ?float, piezas: float, porConfirmar: bool}|null
     */
    private static function oferta(QuotationComparisonRow $fila, array $piezas = []): ?array
    {
        if ($fila->cotizada === null) {
            return null;
        }

        $cantidad = self::numero($fila->cotizada['quantity'] ?? null);
        $unitario = self::numero($fila->cotizada['unit_price'] ?? null);
        $total = self::total($fila->cotizada);
        $dePiezas = (float) array_sum(array_map(fn (array $p): float => self::total($p['linea']) ?? 0.0, $piezas));

        if ($dePiezas > 0 && $total !== null) {
            $total += $dePiezas;
            $unitario = $cantidad > 0 ? $total / $cantidad : $total;
        }

        return [
            'cantidad' => $cantidad,
            'unitario' => $unitario,
            'unitarioLinea' => self::numero($fila->cotizada['unit_price'] ?? null),
            'total' => $total,
            'piezas' => $dePiezas,
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

    /** Con la coma decimal chilena, como la escribe el modelo. */
    private static function numero(mixed $valor): ?float
    {
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }

        return is_string($valor) ? \App\Support\ChileanMoney::parse($valor) : null;
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
