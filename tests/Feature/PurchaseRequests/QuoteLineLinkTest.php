<?php

use App\Models\PurchaseProductLink;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestIngestion;
use App\Models\User;
use App\Services\PurchaseRequests\Quotes\QuotationComparison;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

/** Una solicitud con una partida ya enlazada a un producto de Odoo. */
function solicitudConPartidaEnlazada(User $owner, string $texto, int $odooProductId): PurchaseRequest
{
    $solicitud = test()->createPurchaseRequestDraft($owner);
    $solicitud->items()->delete();
    $solicitud->items()->create([
        'sort_order' => 1, 'product_service' => $texto,
        'quantity' => 1, 'unit' => 'Unidades',
    ]);

    PurchaseProductLink::query()->create([
        'company_code' => 'EHE',
        'odoo_partner_id' => null,
        'source_text' => $texto,
        'normalized_text' => PurchaseProductLink::normalizar($texto),
        'odoo_product_id' => $odooProductId,
        'odoo_product_name' => 'VALVULA DE MARIPOSA 200MM',
        'source' => 'confirmed',
    ]);

    return $solicitud->fresh();
}

function cotizacionDe(PurchaseRequest $solicitud, array $lineas, string $hash = 'a'): PurchaseRequestIngestion
{
    return PurchaseRequestIngestion::query()->create([
        'user_id' => $solicitud->user_id, 'uploader_name_snapshot' => 'Quien sea',
        'compared_request_id' => $solicitud->getKey(), 'disk' => 'local',
        'path' => 'c.pdf', 'original_name' => 'cotizacion.pdf',
        'mime_type' => 'application/pdf', 'size' => 10, 'sha256' => str_repeat($hash, 64),
        'status' => PurchaseRequestIngestion::COMPLETED,
        'extracted' => ['items' => $lineas],
    ]);
}

it('does not pair two lines that share no words', function () {
    // Caso real de la SC-2026-000031: el proveedor llama «ANTEOJO POLICARB.
    // BASIC GRIS» a lo que se pidió como «Lente Oscuro Con Protector UV
    // basic». Se parecen un 34%: ningún umbral razonable las cruza, y para
    // eso está el emparejado a mano.
    $owner = User::factory()->create();
    $solicitud = solicitudConPartidaEnlazada($owner, 'Lente Oscuro Con Protector UV basic', 7423);

    $r = app(QuotationComparison::class)->comparar($solicitud, [
        ['product_service' => 'ANTEOJO POLICARB. BASIC GRIS -', 'specification' => null,
            'quantity' => '300', 'unit' => 'Unidades', 'unit_price' => 510],
    ]);

    expect($r->filas[0]->estado)->toBe('sin_cotizar')
        ->and($r->sobrantes)->toHaveCount(1);
});

it('pairs them once somebody says they are the same product', function () {
    $owner = User::factory()->create();
    $solicitud = solicitudConPartidaEnlazada($owner, 'Lente Oscuro Con Protector UV basic', 7423);
    $lectura = cotizacionDe($solicitud, [
        ['product_service' => 'ANTEOJO POLICARB. BASIC GRIS -', 'specification' => null,
            'quantity' => '300', 'unit' => 'Unidades', 'unit_price' => 510],
    ]);

    $this->actingAs($owner)
        ->post(route('purchase_requests.quotes.link', [$solicitud, $lectura]), [
            'quote_line' => 'ANTEOJO POLICARB. BASIC GRIS -',
            'item_id' => $solicitud->items->first()->getKey(),
        ])
        ->assertSessionHasNoErrors();

    // Lo aprendido apunta al mismo producto de Odoo que la partida.
    expect(PurchaseProductLink::para('ANTEOJO POLICARB. BASIC GRIS -', null)?->odoo_product_id)
        ->toBe(7423);

    // Y ahora la comparación las cruza, sin parecerse más que antes.
    $r = app(QuotationComparison::class)->comparar($solicitud, [
        ['product_service' => 'ANTEOJO POLICARB. BASIC GRIS -', 'specification' => null,
            'quantity' => '1', 'unit' => 'Unidades', 'unit_price' => 510],
    ]);

    expect($r->sobrantes)->toBeEmpty()
        ->and($r->filas[0]->estado)->not->toBe('sin_cotizar')
        ->and($r->filas[0]->confianza)->toBe(1.0)
        ->and($r->filas[0]->diferencias[0])->toContain('Cotizado en');
});

it('learns the pairing even when the request never went to Odoo', function () {
    $owner = User::factory()->create();
    $solicitud = $this->createPurchaseRequestDraft($owner);
    $solicitud->items()->delete();
    $solicitud->items()->create([
        'sort_order' => 1, 'product_service' => 'Bloqueador solar 1000 ml',
        'quantity' => 7, 'unit' => 'Unidades',
    ]);
    $solicitud = $solicitud->fresh();
    $lectura = cotizacionDe($solicitud, [
        ['product_service' => 'PROTECTOR SOLAR FPS50 B.BOAT', 'specification' => null,
            'quantity' => '7', 'unit' => 'UN', 'unit_price' => 10200],
    ], 'b');

    $this->actingAs($owner)
        ->post(route('purchase_requests.quotes.link', [$solicitud, $lectura]), [
            'quote_line' => 'PROTECTOR SOLAR FPS50 B.BOAT',
            'item_id' => $solicitud->items()->first()->getKey(),
        ])
        ->assertSessionHas('success');

    // Nadie enseñó un producto de Odoo, y aun así la equivalencia queda dicha.
    expect(PurchaseProductLink::para('PROTECTOR SOLAR FPS50 B.BOAT', null)?->canonical_text)
        ->toBe(PurchaseProductLink::normalizar('Bloqueador solar 1000 ml'));

    $r = app(QuotationComparison::class)->comparar($solicitud, [
        ['product_service' => 'PROTECTOR SOLAR FPS50 B.BOAT', 'specification' => null,
            'quantity' => '7', 'unit' => 'UN', 'unit_price' => 10200],
    ]);

    expect($r->filas[0]->cruzo())->toBeTrue()
        ->and($r->filas[0]->estado)->toBe('igual')
        ->and($r->filas[0]->confianza)->toBe(1.0);
});

it('keeps the pairing out of reach of a request that is not yours', function () {
    $owner = User::factory()->create();
    $ajeno = User::factory()->create();
    $solicitud = solicitudConPartidaEnlazada($owner, 'valvula mariposa', 7423);
    $lectura = cotizacionDe($solicitud, [], 'c');

    $this->actingAs($ajeno)
        ->post(route('purchase_requests.quotes.link', [$solicitud, $lectura]), [
            'quote_line' => 'VALVULA MARIPOSA', 'item_id' => $solicitud->items->first()->getKey(),
        ])
        ->assertForbidden();
});

it('offers the selector on the screen for a line nobody asked for', function () {
    $owner = User::factory()->create();
    $solicitud = solicitudConPartidaEnlazada($owner, 'valvula mariposa', 7423);
    cotizacionDe($solicitud, [
        ['product_service' => 'FLETE A RIO BUENO', 'specification' => null,
            'quantity' => '1', 'unit' => 'Unidades', 'unit_price' => 35000],
    ]);

    $this->actingAs($owner)
        ->get(route('purchase_requests.show', $solicitud))
        ->assertOk()
        ->assertSee('¿Es alguna de tus partidas?')
        ->assertSee('Es la misma');
});

it('undoes a pairing that somebody taught by mistake', function () {
    $owner = User::factory()->create();
    $solicitud = solicitudConPartidaEnlazada($owner, 'valvula mariposa', 7423);
    $lectura = cotizacionDe($solicitud, [
        ['product_service' => 'FLETE A RIO BUENO', 'specification' => null,
            'quantity' => '1', 'unit' => 'Unidades', 'unit_price' => 35000],
    ], 'd');

    $this->actingAs($owner)->post(route('purchase_requests.quotes.link', [$solicitud, $lectura]), [
        'quote_line' => 'FLETE A RIO BUENO',
        'item_id' => $solicitud->items->first()->getKey(),
    ])->assertSessionHas('success');

    // Un clic equivocado vale para todas las cotizaciones futuras de ese
    // proveedor, así que tiene que poder borrarse con la misma facilidad.
    $this->actingAs($owner)->post(route('purchase_requests.quotes.unlink', [$solicitud, $lectura]), [
        'quote_line' => 'FLETE A RIO BUENO',
    ])->assertSessionHas('success');

    expect(PurchaseProductLink::para('FLETE A RIO BUENO', null))->toBeNull();
});

it('says plainly when there was nothing taught to undo', function () {
    $owner = User::factory()->create();
    $solicitud = solicitudConPartidaEnlazada($owner, 'valvula mariposa', 7423);
    $lectura = cotizacionDe($solicitud, [], 'e');

    $this->actingAs($owner)->post(route('purchase_requests.quotes.unlink', [$solicitud, $lectura]), [
        'quote_line' => 'ALGO QUE NADIE ENSEÑÓ',
    ])->assertSessionHas('info');
});
