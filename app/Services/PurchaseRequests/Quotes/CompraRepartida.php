<?php

namespace App\Services\PurchaseRequests\Quotes;

/**
 * Cuánto saldría comprarle a cada proveedor sólo lo que tiene más barato, y
 * contra qué se compara: el más barato de los que cotizaron todo.
 *
 * Lo usan las dos comparaciones —«Quién conviene» de la solicitud y la de la
 * orden de Odoo—, que llegan con la misma forma: columnas (proveedores),
 * filas (partidas) con una celda por columna, y el neto de cada columna.
 * En la SC-2026-000106 era $1.014.781 repartido entre Climent, Austral y la
 * Cooperativa, contra $1.424.648 todo a Entre Riegos.
 */
class CompraRepartida
{
    /**
     * @param  list<array{nombre: string}>  $columnas
     * @param  list<array{celdas: list<?array{total: ?float}>, mejor: ?int}>  $filas
     * @param  list<array{neto: float}>  $totales
     * @return array{grupos: list<array{nombre: string, productos: int, neto: float}>, repartida: float, referencia: array{nombre: string, neto: float}, ahorro: float}|null
     */
    public static function de(array $columnas, array $filas, array $totales): ?array
    {
        if ($filas === [] || count($columnas) < 2) {
            return null;
        }

        $grupos = [];
        $repartida = 0.0;

        foreach ($filas as $fila) {
            $i = $fila['mejor'];

            // Una partida sin ganador claro (empate, una sola oferta) no se puede repartir.
            if ($i === null || ($fila['celdas'][$i]['total'] ?? null) === null) {
                return null;
            }

            $grupos[$i] ??= ['nombre' => (string) $columnas[$i]['nombre'], 'productos' => 0, 'neto' => 0.0];
            $grupos[$i]['productos']++;
            $grupos[$i]['neto'] += (float) $fila['celdas'][$i]['total'];
            $repartida += (float) $fila['celdas'][$i]['total'];
        }

        // Contra el más barato de los que cotizaron todas las partidas.
        $completas = array_filter(array_keys($columnas), fn (int $i): bool => collect($filas)->every(fn (array $f): bool => ($f['celdas'][$i]['total'] ?? null) !== null));
        $referencia = collect($completas)->sortBy(fn (int $i): float => (float) $totales[$i]['neto'])->first();

        if ($referencia === null || count($grupos) < 2 || $repartida >= (float) $totales[$referencia]['neto'] - 1) {
            return null;
        }

        usort($grupos, fn (array $a, array $b): int => $b['neto'] <=> $a['neto']);

        return [
            'grupos' => array_values($grupos),
            'repartida' => $repartida,
            'referencia' => ['nombre' => (string) $columnas[$referencia]['nombre'], 'neto' => (float) $totales[$referencia]['neto']],
            'ahorro' => (float) $totales[$referencia]['neto'] - $repartida,
        ];
    }
}
