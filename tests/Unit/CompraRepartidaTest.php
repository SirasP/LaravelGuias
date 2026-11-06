<?php

use App\Services\PurchaseRequests\Quotes\CompraRepartida;

$celda = fn (?float $total) => $total === null ? null : ['total' => $total];

it('splits the purchase where each item is cheapest, against the cheapest supplier that quoted everything', function () use ($celda) {
    $columnas = [['nombre' => 'CLIMENT'], ['nombre' => 'AUSTRAL'], ['nombre' => 'ENTRE RIEGOS']];
    $filas = [
        ['mejor' => 0, 'celdas' => [$celda(100), $celda(120), $celda(130)]],
        ['mejor' => 1, 'celdas' => [null, $celda(50), $celda(80)]],
        ['mejor' => 0, 'celdas' => [$celda(10), $celda(20), $celda(15)]],
    ];
    $r = CompraRepartida::de($columnas, $filas, [['neto' => 110.0], ['neto' => 190.0], ['neto' => 225.0]]);

    expect($r['repartida'])->toBe(160.0)
        ->and($r['grupos'])->toBe([['nombre' => 'CLIMENT', 'productos' => 2, 'neto' => 110.0], ['nombre' => 'AUSTRAL', 'productos' => 1, 'neto' => 50.0]])
        // Climent no cotizó todo: se compara contra Austral, el más barato de los completos.
        ->and($r['referencia'])->toBe(['nombre' => 'AUSTRAL', 'neto' => 190.0])
        ->and($r['ahorro'])->toBe(30.0);
});

it('shows nothing when one supplier already wins everything or an item has no winner', function () use ($celda) {
    $columnas = [['nombre' => 'A'], ['nombre' => 'B']];
    expect(CompraRepartida::de($columnas, [['mejor' => 0, 'celdas' => [$celda(1), $celda(2)]]], [['neto' => 1.0], ['neto' => 2.0]]))->toBeNull()
        ->and(CompraRepartida::de($columnas, [['mejor' => null, 'celdas' => [$celda(1), $celda(1)]]], [['neto' => 1.0], ['neto' => 1.0]]))->toBeNull();
});
