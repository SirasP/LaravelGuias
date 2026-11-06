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

    // El total es lo que suma la línea —precio por cantidad—, no el unitario:
    // decidir a quién comprarle con la suma de los unitarios no significa nada.
    expect($m->totales[0])->toBe(['total' => 157000.0, 'faltan' => 0, 'porConfirmar' => 0])
        ->and($m->totales[1])->toBe(['total' => 39000.0, 'faltan' => 1, 'porConfirmar' => 0]);
});

it('shows quantity, unit price and line total in every cell', function () {
    $m = cuadricula(
        [['CEMENTO 25 KG', 10]],
        [
            ['SODIMAC', [linea('CEMENTO 25 KG', 4500, '10')]],
            ['CONSTRUMART', [linea('CEMENTO 25 KG', 3900, '8')]],
        ],
    );

    // La cantidad del total es la que cotizó el proveedor, no la que se pidió:
    // CONSTRUMART ofrece ocho de las diez, y su total es de ocho.
    expect($m->filas[0]['cantidad'])->toBe(10.0)
        ->and($m->filas[0]['ofertas'][0])->toBe(['cantidad' => 10.0, 'unitario' => 4500.0, 'total' => 45000.0, 'piezas' => 0.0, 'porConfirmar' => false])
        ->and($m->filas[0]['ofertas'][1])->toBe(['cantidad' => 8.0, 'unitario' => 3900.0, 'total' => 31200.0, 'piezas' => 0.0, 'porConfirmar' => false]);
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

    // Va fuera de la cuadrícula: dentro hacía que la columna de SODIMAC
    // dijera «no cotizó» sobre algo que nadie le pidió.
    expect($m->filas)->toHaveCount(1)
        ->and($m->agregadas)->toHaveCount(1)
        ->and($m->agregadas[0]['texto'])->toBe('FLETE A RIO BUENO')
        ->and($m->agregadas[0]['proveedor'])->toBe('CONSTRUMART')
        ->and($m->agregadas[0]['total'])->toBe(35000.0);

    // Y a SODIMAC no le falta nada, ni el flete infla el total de nadie.
    expect($m->totales[0]['faltan'])->toBe(0)
        ->and($m->totales[1]['faltan'])->toBe(0)
        ->and($m->totales[1]['total'])->toBe(39000.0);
});

it('adds the pieces a supplier quoted apart to their item, in the cell and in the total', function () {
    // SC-2026-000114: Climent cotizó la válvula y aparte las 2 flanges volantes que lleva.
    $owner = User::factory()->create();
    $solicitud = test()->createPurchaseRequestDraft($owner);
    $solicitud->items()->delete();
    $valvula = $solicitud->items()->create(['sort_order' => 1, 'product_service' => 'Válvula mariposa con volante de 200 mm', 'quantity' => 1, 'unit' => 'Unidades']);
    $comparador = app(QuotationComparison::class);
    $lectura = fn (int $i, string $proveedor) => PurchaseRequestIngestion::query()->create([
        'user_id' => $owner->getKey(), 'uploader_name_snapshot' => $owner->name, 'compared_request_id' => $solicitud->getKey(),
        'disk' => 'local', 'path' => "q{$i}.pdf", 'original_name' => "q{$i}.pdf", 'mime_type' => 'application/pdf', 'size' => 10,
        'sha256' => str_repeat((string) $i, 64), 'status' => PurchaseRequestIngestion::COMPLETED, 'supplier_name' => $proveedor,
    ]);

    $matriz = QuoteMatrix::de([
        ['ingestion' => $lectura(1, 'AUSTRAL'), 'resultado' => $comparador->comparar($solicitud->fresh(), [linea('VALVULA MARIPOSA CON VOLANTE 8"', 80000)])],
        ['ingestion' => $lectura(2, 'CLIMENT'), 'resultado' => $comparador->comparar($solicitud->fresh(),
            [linea('VALVULA DE MARIPOSA 200MM', 61716), linea('FLANGE VOLANTE PVC-H 200MM', 12879, '2')], null, [0 => $valvula->id], [], [1 => $valvula->id])],
    ]);

    $climent = $matriz->filas[0]['ofertas'][1];
    expect($climent['total'])->toBe(87474.0)
        ->and($climent['piezas'])->toBe(25758.0)
        ->and($climent['unitario'])->toBe(87474.0)
        ->and($matriz->totales[1]['total'])->toBe(87474.0)
        // Con las flanges, Climent ya no es el más barato: lo es Austral.
        ->and($matriz->filas[0]['masBarato'])->toBe(0);
});

it('adds a piece to its item in the quote total too', function () {
    $owner = User::factory()->create();
    $solicitud = test()->createPurchaseRequestDraft($owner);
    $solicitud->items()->delete();
    $valvula = $solicitud->items()->create(['sort_order' => 1, 'product_service' => 'Válvula mariposa con volante de 200 mm', 'quantity' => 1, 'unit' => 'Unidades']);
    $solicitud->items()->create(['sort_order' => 2, 'product_service' => 'CODO PVC 200 MM X 90° CEMENTAR', 'quantity' => 4, 'unit' => 'Unidades']);

    $r = app(QuotationComparison::class)->comparar($solicitud->fresh(), [
        linea('VALVULA DE MARIPOSA 200MM', 61716), linea('FLANGE VOLANTE PVC-H 200MM', 12879, '2,00'), linea('CODO PVC-H CEM 200MM X 90º', 23429, '4,00'),
    ], null, [0 => $valvula->id], [], [1 => $valvula->id]);

    $fila = collect($r->filas)->first(fn ($f) => $f->pedida?->is($valvula));
    expect($r->montoDe($fila))->toBe(['linea' => 61716.0, 'piezas' => 25758.0, 'total' => 87474.0])
        ->and($r->sumaCotizada())->toBe(87474.0 + 93716.0);
});
