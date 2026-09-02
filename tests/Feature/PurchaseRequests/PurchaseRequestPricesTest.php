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

it('carries the price from the reading through to the request', function () {
    // El precio se leía bien, se mostraba bien en la tabla de revisión, y
    // desaparecía al confirmar: la validación devuelve sólo los campos que
    // declara, así que omitir uno lo descarta sin decir nada.
    $owner = User::factory()->create();

    $ingestion = App\Models\PurchaseRequestIngestion::create([
        'user_id' => $owner->getKey(),
        'uploader_name_snapshot' => $owner->name,
        'disk' => 'local',
        'path' => 'cotizaciones/x.pdf',
        'original_name' => 'cotizacion.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1024,
        'sha256' => hash('sha256', uniqid()),
        'status' => App\Models\PurchaseRequestIngestion::COMPLETED,
        'prices_include_tax' => false,
        'extracted' => ['items' => [[
            'product_service' => 'MARCADOR TACOM DIESEL',
            'specification' => 'AR019CVD3500',
            'quantity' => '1',
            'unit' => 'Unidades',
            'unit_price' => 124370,
        ]]],
    ]);

    $this->actingAs($owner)->post(route('purchase_requests.ingestions.confirm', $ingestion), [
        'items' => [[
            'product_service' => 'MARCADOR TACOM DIESEL',
            'specification' => 'AR019CVD3500',
            'quantity' => '1',
            'unit' => 'Unidades',
            'unit_price' => '124370',
        ]],
    ])->assertRedirect();

    $partida = $ingestion->fresh()->purchaseRequest->items()->first();

    expect((float) $partida->unit_price)->toBe(124370.0)
        // Y lo que se concluyó sobre el IVA viaja con la solicitud.
        ->and($ingestion->fresh()->purchaseRequest->prices_include_tax)->toBeFalse();
});

it('accepts a unit price backed by the line total when the document prints no unit price', function () {
    $v = new LineVerifier;

    // El presupuesto de un rectificador de motores: la columna «VALOR» es el
    // total de la partida, no el unitario. El unitario correcto —63.000 entre
    // 9— no está escrito en ninguna parte, y exigirlo al pie de la letra
    // borraba precios ciertos, que es justo lo que pasaba en producción.
    $documento = "CANT.   DESCRIPCION                 VALOR\n"
        ."   9    PULIR CIGÜEÑAL           \$  63.000\n"
        ."   4    ENCAMISAR CILINDROS      \$ 304.000\n";

    $leer = fn (string $producto, string $cantidad, ?string $precio) => $v->verificarContraElDocumento(
        [['product_service' => $producto, 'specification' => null, 'quantity' => $cantidad, 'unit' => 'Unidades', 'unit_price' => $precio]],
        $documento,
        ['Unidades'],
        false,
        referenciaEsUnaFrase: true,
    );

    [$items, $avisos] = $leer('PULIR CIGÜEÑAL', '9', '7000');
    expect($items[0]['unit_price'])->toBe(7000.0)
        ->and(collect($avisos)->filter(fn ($a) => str_contains($a, 'precio')))->toBeEmpty();

    [$items] = $leer('ENCAMISAR CILINDROS', '4', '76000');
    expect($items[0]['unit_price'])->toBe(76000.0);

    // Sigue siendo verificación, no confianza: un unitario que no cuadra con
    // ningún total impreso se descarta igual que antes.
    [$items, $avisos] = $leer('PULIR CIGÜEÑAL', '9', '8000');
    expect($items[0]['unit_price'])->toBeNull()
        ->and($avisos[0])->toContain('no aparece en el documento');
});

it('does not let quantity one turn any invented price into a valid one', function () {
    $v = new LineVerifier;

    // Con cantidad 1 el «total» sería el propio unitario. Si se aceptara por
    // esa vía, el control quedaría anulado para toda partida de una unidad.
    [$items, $avisos] = $v->verificarContraElDocumento(
        [['product_service' => 'correa', 'specification' => null, 'quantity' => '1', 'unit' => 'Unidades', 'unit_price' => '99000']],
        '1 correa para el tractor, sin precio',
        ['Unidades'],
        false,
        referenciaEsUnaFrase: true,
    );

    expect($items[0]['unit_price'])->toBeNull()
        ->and($avisos[0])->toContain('no aparece en el documento');
});
