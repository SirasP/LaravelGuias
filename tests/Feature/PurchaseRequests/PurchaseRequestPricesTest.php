<?php

use App\Models\User;
use App\Services\PurchaseRequests\Reading\LineVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

it('reads Chilean amounts however they are written', function () {
    $v = new LineVerifier;

    expect($v->aNumeroCanonico('12.500'))->toBe(12500.0)      // miles con punto
        ->and($v->aNumeroCanonico('$ 1.234.567'))->toBe(1234567.0)
        ->and($v->aNumeroCanonico('12.500,50'))->toBe(12500.5) // coma decimal
        ->and($v->aNumeroCanonico('12,500.50'))->toBe(12500.5) // formato gringo
        ->and($v->aNumeroCanonico('0,75'))->toBe(0.75)
        ->and($v->aNumeroCanonico('890'))->toBe(890.0)
        ->and($v->aNumeroCanonico('sin números'))->toBeNull();
});

it('keeps a price that is written in the document and drops one that is not', function () {
    $v = new LineVerifier;

    $leer = fn (string $documento, ?string $precio) => $v->verificarContraElDocumento(
        [['product_service' => 'correas', 'specification' => null, 'quantity' => '2', 'unit' => 'Unidades', 'unit_price' => $precio]],
        $documento,
        ['Unidades'],
        false,
        referenciaEsUnaFrase: true,
    );

    // El documento lo escribe con puntos de miles; el modelo lo devuelve limpio.
    // Son el mismo número y tienen que reconocerse como tal.
    [$items] = $leer('2 correas a $ 12.500 cada una', '12500');
    expect($items[0]['unit_price'])->toBe(12500.0);

    // Un precio que no está escrito no entra, por razonable que parezca.
    [$items, $avisos] = $leer('2 correas para el tractor', '12500');
    expect($items[0]['unit_price'])->toBeNull()
        ->and($avisos[0])->toContain('no aparece en el documento');

    // Y sin precio no pasa nada: pedir sin cotizar es lo normal.
    [$items, $avisos] = $leer('2 correas para el tractor', null);
    expect($items[0]['unit_price'])->toBeNull()
        ->and(collect($avisos)->filter(fn ($a) => str_contains($a, 'precio')))->toBeEmpty();
});

it('adds up only the lines that have a price, and says when some do not', function () {
    $owner = User::factory()->create();

    $request = $this->createPurchaseRequestDraft($owner, ['items' => [
        $this->validPurchaseRequestItem(['product_service' => 'correas', 'quantity' => '2', 'unit' => 'Unidades', 'unit_price' => '12500']),
        $this->validPurchaseRequestItem(['product_service' => 'aceite', 'quantity' => '3', 'unit' => 'Litros', 'unit_price' => '8000']),
        $this->validPurchaseRequestItem(['product_service' => 'grasa', 'quantity' => '1', 'unit' => 'Unidades']),
    ]]);

    $request->load('items');

    expect($request->items[0]->lineTotal())->toBe(25000.0)
        ->and($request->total())->toBe(49000.0)
        // Un total que sólo cubre parte de las partidas engaña más que ayuda.
        ->and($request->hasPartialPricing())->toBeTrue();
});

it('leaves the total empty when nothing has been quoted yet', function () {
    $request = $this->createPurchaseRequestDraft(User::factory()->create());
    $request->load('items');

    // «$0» diría algo falso sobre lo que cuesta.
    expect($request->total())->toBeNull()
        ->and($request->hasPartialPricing())->toBeFalse();
});

it('stores a price typed the way a person types it', function () {
    $owner = User::factory()->create();

    $request = $this->createPurchaseRequestDraft($owner, ['items' => [
        $this->validPurchaseRequestItem(['quantity' => '2', 'unit' => 'Unidades', 'unit_price' => '12.500']),
    ]]);

    expect((float) $request->items()->first()->unit_price)->toBe(12500.0);
});

it('shows the prices in the PDF, and no empty columns when there are none', function () {
    $owner = User::factory()->create();

    $conPrecio = $this->createPurchaseRequestDraft($owner, ['items' => [
        $this->validPurchaseRequestItem(['product_service' => 'correas', 'quantity' => '2', 'unit' => 'Unidades', 'unit_price' => '12500']),
    ]]);
    $sinPrecio = $this->createPurchaseRequestDraft($owner);

    $render = fn ($solicitud) => view('purchase_requests.pdf', [
        'purchaseRequest' => $solicitud->load('items'),
        'items' => null,
        'revision' => null,
        'blankRows' => 0,
    ])->render();

    expect($render($conPrecio))->toContain('Precio unit.')
        ->and($render($sinPrecio))->not->toContain('Precio unit.');
});
