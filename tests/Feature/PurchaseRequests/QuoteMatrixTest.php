<?php

use App\Models\PurchaseRequestIngestion;
use App\Models\User;
use App\Services\PurchaseRequests\Quotes\QuotationComparison;
use App\Services\PurchaseRequests\Quotes\QuoteMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

/** Una solicitud con partidas y las cotizaciones que se indiquen. */
function cuadricula(array $partidas, array $cotizaciones): ?QuoteMatrix
{
    $owner = User::factory()->create();
    $solicitud = test()->createPurchaseRequestDraft($owner);
    $solicitud->items()->delete();

    foreach ($partidas as $i => $p) {
        $solicitud->items()->create([
            'sort_order' => $i + 1, 'product_service' => $p[0],
            'quantity' => $p[1], 'unit' => 'Unidades',
        ]);
    }

    $comparador = app(QuotationComparison::class);
    $comparaciones = [];

    foreach ($cotizaciones as $i => [$proveedor, $lineas]) {
        $lectura = PurchaseRequestIngestion::query()->create([
            'user_id' => $owner->getKey(), 'uploader_name_snapshot' => $owner->name,
            'compared_request_id' => $solicitud->getKey(), 'disk' => 'local',
            'path' => "q{$i}.pdf", 'original_name' => "q{$i}.pdf",
            'mime_type' => 'application/pdf', 'size' => 10,
            'sha256' => str_repeat((string) $i, 64),
            'status' => PurchaseRequestIngestion::COMPLETED,
            'supplier_name' => $proveedor,
        ]);

        $comparaciones[] = [
            'ingestion' => $lectura,
            'resultado' => $comparador->comparar($solicitud->fresh(), $lineas),
        ];
    }

    return QuoteMatrix::de($comparaciones);
}

function linea(string $nombre, ?int $precio, string $cantidad = '1'): array
{
    return ['product_service' => $nombre, 'specification' => null,
        'quantity' => $cantidad, 'unit' => 'Unidades', 'unit_price' => $precio];
}

it('does not build a grid for a single quotation', function () {
    // Una columna sola no compara nada: su propia tabla ya lo dice todo.
    expect(cuadricula(
        [['CEMENTO 25 KG', 10]],
        [['SODIMAC', [linea('CEMENTO 25 KG', 4500, '10')]]],
    ))->toBeNull();
});

it('puts the suppliers side by side and marks the cheapest of each line', function () {
    $m = cuadricula(
        [['CEMENTO 25 KG', 10], ['ARENA GRUESA', 4]],
        [
            ['SODIMAC', [linea('CEMENTO 25 KG', 4500, '10'), linea('ARENA GRUESA', 28000, '4')]],
            ['CONSTRUMART', [linea('CEMENTO 25 KG', 3900, '10'), linea('ARENA GRUESA', 31000, '4')]],
        ],
    );

    expect($m->proveedores)->toHaveCount(2)
        ->and(collect($m->filas)->firstWhere('partida', 'CEMENTO 25 KG')['masBarato'])->toBe(1)
        ->and(collect($m->filas)->firstWhere('partida', 'ARENA GRUESA')['masBarato'])->toBe(0);
});

it('counts what each supplier left out instead of pretending it is free', function () {
    // Si CONSTRUMART no cotizó la arena, su total es menor porque le falta
    // una partida, no porque sea más conveniente. Hay que decirlo.
    $m = cuadricula(
        [['CEMENTO 25 KG', 10], ['ARENA GRUESA', 4]],
        [
            ['SODIMAC', [linea('CEMENTO 25 KG', 4500, '10'), linea('ARENA GRUESA', 28000, '4')]],
            ['CONSTRUMART', [linea('CEMENTO 25 KG', 3900, '10')]],
        ],
    );

    expect($m->totales[0])->toBe(['total' => 32500.0, 'faltan' => 0])
        ->and($m->totales[1])->toBe(['total' => 3900.0, 'faltan' => 1]);
});

it('marks nobody when two suppliers tie', function () {
    // Señalar a uno de dos iguales sería inventar una diferencia.
    $m = cuadricula(
        [['CEMENTO 25 KG', 10]],
        [
            ['SODIMAC', [linea('CEMENTO 25 KG', 4500, '10')]],
            ['CONSTRUMART', [linea('CEMENTO 25 KG', 4500, '10')]],
        ],
    );

    expect($m->filas[0]['masBarato'])->toBeNull();
});

it('includes what a supplier added on its own', function () {
    $m = cuadricula(
        [['CEMENTO 25 KG', 10]],
        [
            ['SODIMAC', [linea('CEMENTO 25 KG', 4500, '10')]],
            ['CONSTRUMART', [linea('CEMENTO 25 KG', 3900, '10'), linea('FLETE A RIO BUENO', 35000)]],
        ],
    );

    $flete = collect($m->filas)->firstWhere('partida', 'FLETE A RIO BUENO');

    expect($flete)->not->toBeNull()
        ->and($flete['precios'][0])->toBeNull()
        ->and($flete['precios'][1])->toBe(35000.0)
        // Con un solo precio no hay competencia que ganar.
        ->and($flete['masBarato'])->toBeNull()
        ->and($flete['pedida'])->toBeFalse();

    // Y a SODIMAC no le falta nada: el flete no estaba en la solicitud.
    expect($m->totales[0]['faltan'])->toBe(0)
        ->and($m->totales[1]['faltan'])->toBe(0);
});
