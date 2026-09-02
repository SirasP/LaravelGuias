<?php

use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\PurchaseRequests\Quotes\QuotationComparison;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

/** Una solicitud con las partidas que se le indiquen. */
function solicitudCon(array $partidas): PurchaseRequest
{
    $request = test()->createPurchaseRequestDraft(User::factory()->create());
    $request->items()->delete();

    foreach ($partidas as $i => $p) {
        $request->items()->create([
            'sort_order' => $i + 1,
            'product_service' => $p[0],
            'specification' => $p[1] ?? null,
            'quantity' => $p[2] ?? 1,
            'unit' => $p[3] ?? 'Unidades',
            'unit_price' => $p[4] ?? null,
        ]);
    }

    return $request->fresh();
}

function comparar(PurchaseRequest $solicitud, array $lineas)
{
    return app(QuotationComparison::class)->comparar($solicitud, $lineas);
}

it('says everything matches when the quotation is what was asked for', function () {
    $solicitud = solicitudCon([
        ['ANILLO PISTON STD', 'KU0214-014047', 4, 'Unidades', 70290],
    ]);

    $r = comparar($solicitud, [
        ['product_service' => 'ANILLO PISTON STD', 'specification' => 'KU0214-014047',
            'quantity' => '4', 'unit' => 'Unidades', 'unit_price' => 70290],
    ]);

    expect($r->cuadra())->toBeTrue()
        ->and($r->resumen())->toBe('La cotización coincide con lo que pediste.');
});

it('catches the quantity the supplier changed on its own', function () {
    $solicitud = solicitudCon([['CORREA A-42', null, 4, 'Unidades', 12500]]);

    $r = comparar($solicitud, [
        ['product_service' => 'CORREA A-42', 'specification' => null,
            'quantity' => '2', 'unit' => 'Unidades', 'unit_price' => 12500],
    ]);

    expect($r->cuadra())->toBeFalse()
        ->and($r->filas[0]->diferencias[0])->toContain('Pediste 4 y cotizaron 2');
});

it('says how much the price moved, and in which direction', function () {
    $solicitud = solicitudCon([['CORREA A-42', null, 1, 'Unidades', 10000]]);

    $r = comparar($solicitud, [
        ['product_service' => 'CORREA A-42', 'specification' => null,
            'quantity' => '1', 'unit' => 'Unidades', 'unit_price' => 12500],
    ]);

    expect($r->filas[0]->diferencias[0])
        ->toContain('El precio subió')
        ->toContain('$ 10.000')
        ->toContain('$ 12.500')
        ->toContain('25,0%');
});

it('flags what the supplier never quoted', function () {
    $solicitud = solicitudCon([
        ['CANDADO GRIPPLE', null, 3, 'Unidades', null],
        ['CORREA A-42', null, 2, 'Unidades', null],
    ]);

    $r = comparar($solicitud, [
        ['product_service' => 'CORREA A-42', 'specification' => null,
            'quantity' => '2', 'unit' => 'Unidades', 'unit_price' => 12500],
    ]);

    expect($r->filas[0]->estado)->toBe('sin_cotizar')
        ->and($r->filas[0]->diferencias[0])->toContain('No aparece en la cotización');
});

it('flags what the supplier added that nobody asked for', function () {
    $solicitud = solicitudCon([['CORREA A-42', null, 2, 'Unidades', null]]);

    $r = comparar($solicitud, [
        ['product_service' => 'CORREA A-42', 'specification' => null, 'quantity' => '2', 'unit' => 'Unidades', 'unit_price' => 12500],
        ['product_service' => 'FLETE A RIO BUENO', 'specification' => null, 'quantity' => '1', 'unit' => 'Unidades', 'unit_price' => 35000],
    ]);

    expect($r->sobrantes)->toHaveCount(1)
        ->and($r->sobrantes[0]->estado)->toBe('no_pedida')
        ->and($r->sobrantes[0]->diferencias[0])->toContain('El proveedor la agregó');
});

it('matches the same product written differently by each side', function () {
    // Nadie escribe igual en los dos papeles. Si el emparejado exigiera texto
    // idéntico, la comparación diría «no cotizado» en casi todas las líneas.
    $solicitud = solicitudCon([['Candado gripple', null, 3, 'Unidades', null]]);

    $r = comparar($solicitud, [
        ['product_service' => 'CANDADO TIPO GRIPPLE', 'specification' => null,
            'quantity' => '3', 'unit' => 'Unidades', 'unit_price' => 4500],
    ]);

    expect($r->filas[0]->estado)->not->toBe('sin_cotizar')
        ->and($r->filas[0]->diferencias[0])->toContain('Trae precio y tu solicitud no tenía ninguno');
});

it('trusts the supplier code over the names when both papers carry it', function () {
    $solicitud = solicitudCon([['Metal de biela', 'KU0214-180020', 4, 'Unidades', null]]);

    $r = comparar($solicitud, [
        ['product_service' => 'METAL BIELA STD', 'specification' => 'KU0214-180020',
            'quantity' => '4', 'unit' => 'Unidades', 'unit_price' => 27540],
    ]);

    expect($r->filas[0]->confianza)->toBe(1.0)
        ->and($r->filas[0]->estado)->toBe('difiere');
});

it('never matches two request lines against the same quoted line', function () {
    // Si lo hiciera, cotizar la mitad se vería como cotizar todo.
    $solicitud = solicitudCon([
        ['CORREA A-42', null, 2, 'Unidades', null],
        ['CORREA A-42', null, 3, 'Unidades', null],
    ]);

    $r = comparar($solicitud, [
        ['product_service' => 'CORREA A-42', 'specification' => null, 'quantity' => '2', 'unit' => 'Unidades', 'unit_price' => 12500],
    ]);

    expect($r->filas[0]->estado)->not->toBe('sin_cotizar')
        ->and($r->filas[1]->estado)->toBe('sin_cotizar');
});
