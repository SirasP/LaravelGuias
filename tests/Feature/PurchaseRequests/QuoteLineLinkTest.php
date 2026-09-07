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

it('does not pair two lines that only look alike', function () {
    // «valvula mariposa» y «VALVULA MARIPOSA 8" 200MM C/PALANCA» se parecen un
    // 52%: por debajo de cualquier umbral que no cruce también «CODOS PVC 63»
    // con «CODO PVC 40MM», que son productos distintos.
    $owner = User::factory()->create();
    $solicitud = solicitudConPartidaEnlazada($owner, 'valvula mariposa', 7423);

    $r = app(QuotationComparison::class)->comparar($solicitud, [
        ['product_service' => 'VALVULA MARIPOSA 8" 200MM C/PALANCA', 'specification' => null,
            'quantity' => '1.00', 'unit' => 'Unidades', 'unit_price' => 130625],
    ]);

    expect($r->filas[0]->estado)->toBe('sin_cotizar')
        ->and($r->sobrantes)->toHaveCount(1);
});

it('pairs them once somebody says they are the same product', function () {
    $owner = User::factory()->create();
    $solicitud = solicitudConPartidaEnlazada($owner, 'valvula mariposa', 7423);
    $lectura = cotizacionDe($solicitud, [
        ['product_service' => 'VALVULA MARIPOSA 8" 200MM C/PALANCA', 'specification' => null,
            'quantity' => '1.00', 'unit' => 'Unidades', 'unit_price' => 130625],
    ]);

    $this->actingAs($owner)
        ->post(route('purchase_requests.quotes.link', [$solicitud, $lectura]), [
            'quote_line' => 'VALVULA MARIPOSA 8" 200MM C/PALANCA',
            'item_id' => $solicitud->items->first()->getKey(),
        ])
        ->assertSessionHasNoErrors();

    // Lo aprendido apunta al mismo producto de Odoo que la partida.
    expect(PurchaseProductLink::para('VALVULA MARIPOSA 8" 200MM C/PALANCA', null)?->odoo_product_id)
        ->toBe(7423);

    // Y ahora la comparación las cruza, sin parecerse más que antes.
    $r = app(QuotationComparison::class)->comparar($solicitud, [
        ['product_service' => 'VALVULA MARIPOSA 8" 200MM C/PALANCA', 'specification' => null,
            'quantity' => '1.00', 'unit' => 'Unidades', 'unit_price' => 130625],
    ]);

    expect($r->sobrantes)->toBeEmpty()
        ->and($r->filas[0]->estado)->not->toBe('sin_cotizar')
        ->and($r->filas[0]->confianza)->toBe(1.0)
        ->and($r->filas[0]->diferencias[0])->toContain('Trae precio');
});

it('refuses to learn against a line that points at no Odoo product', function () {
    // Sin producto al que apuntar, guardar el alias sería guardar un enlace a
    // ninguna parte y romper el emparejado de la próxima vez.
    $owner = User::factory()->create();
    $solicitud = $this->createPurchaseRequestDraft($owner);
    $solicitud->items()->delete();
    $solicitud->items()->create([
        'sort_order' => 1, 'product_service' => 'algo nuevo sin enlazar',
        'quantity' => 1, 'unit' => 'Unidades',
    ]);
    $lectura = cotizacionDe($solicitud->fresh(), [], 'b');

    $this->actingAs($owner)
        ->post(route('purchase_requests.quotes.link', [$solicitud, $lectura]), [
            'quote_line' => 'OTRA COSA',
            'item_id' => $solicitud->items()->first()->getKey(),
        ])
        ->assertSessionHas('error');

    expect(PurchaseProductLink::para('OTRA COSA', null))->toBeNull();
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
