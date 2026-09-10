<?php

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseProductLink;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\PurchaseRequests\Odoo\OdooClient;
use App\Services\PurchaseRequests\Odoo\OdooPurchaseRequestExporter;
use App\Services\PurchaseRequests\Odoo\PurchaseRequestExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function aprobadaConPartida(User $owner, ?float $precio = null): PurchaseRequest
{
    $solicitud = PurchaseRequest::factory()->forUser($owner)->approved()->create([
        'odoo_order_id' => 241, 'odoo_reference' => 'P00241', 'odoo_exported_at' => now(),
    ]);
    $solicitud->items()->create([
        'sort_order' => 1, 'product_service' => 'cemento',
        'quantity' => 10, 'unit' => 'Unidades', 'unit_price' => $precio,
    ]);

    PurchaseProductLink::query()->create([
        'company_code' => 'EHE', 'odoo_partner_id' => null,
        'source_text' => 'cemento', 'normalized_text' => PurchaseProductLink::normalizar('cemento'),
        'odoo_product_id' => 6644, 'odoo_product_name' => 'CEMENTO 25 KG',
        'source' => PurchaseProductLink::CONFIRMADO,
    ]);

    return $solicitud->fresh();
}

it('sets the price of an approved request without reopening it', function () {
    // Aprobada no es editable a propósito, pero el precio es lo único que no
    // se sabía al aprobar. Devolverla, corregirla y reaprobarla eran tres
    // pasos para escribir un número.
    $revisor = User::factory()->admin()->create();
    $solicitud = aprobadaConPartida($revisor);
    $partida = $solicitud->items->first();

    $this->actingAs($revisor)
        ->post(route('purchase_requests.prices.update', $solicitud), [
            'prices' => [$partida->getKey() => '4747.9'],
        ])
        ->assertSessionHasNoErrors();

    expect((float) $partida->fresh()->unit_price)->toBe(4747.9)
        // Y sigue aprobada: no cambió qué se compra, sólo cuánto cuesta.
        ->and($solicitud->fresh()->status)->toBe(PurchaseRequestStatus::APPROVED);
});

it('leaves a trace of who put the price', function () {
    $revisor = User::factory()->admin()->create();
    $solicitud = aprobadaConPartida($revisor);

    $this->actingAs($revisor)->post(route('purchase_requests.prices.update', $solicitud), [
        'prices' => [$solicitud->items->first()->getKey() => '4747.9'],
    ]);

    expect($solicitud->fresh()->events()->where('event_type', 'updated')->exists())->toBeTrue();
});

it('refuses a price that is not a number', function () {
    $revisor = User::factory()->admin()->create();
    $solicitud = aprobadaConPartida($revisor);

    $this->actingAs($revisor)
        ->post(route('purchase_requests.prices.update', $solicitud), [
            'prices' => [$solicitud->items->first()->getKey() => 'como diez lucas'],
        ])
        ->assertSessionHasErrors();

    expect($solicitud->fresh()->items->first()->unit_price)->toBeNull();
});

it('carries the typed prices to the Odoo order', function () {
    $revisor = User::factory()->admin()->create();
    $solicitud = aprobadaConPartida($revisor, 4747.9);

    config([
        'purchase_requests.odoo.enabled' => true,
        'purchase_requests.odoo.url' => 'https://odoo.ejemplo.cl',
        'purchase_requests.odoo.db' => 'prueba',
        'purchase_requests.odoo.user' => 'quien@ejemplo.cl',
        'purchase_requests.odoo.password' => 'secreta',
    ]);
    Http::preventStrayRequests();
    Http::fake(['*/jsonrpc' => Http::sequence([
        Http::response(['jsonrpc' => '2.0', 'result' => 7]),
        Http::response(['jsonrpc' => '2.0', 'result' => [['id' => 241, 'state' => 'draft', 'order_line' => [901]]]]),
        Http::response(['jsonrpc' => '2.0', 'result' => [['id' => 901, 'product_id' => [6644, 'CEMENTO 25 KG'], 'price_unit' => 0]]]),
        Http::response(['jsonrpc' => '2.0', 'result' => true]),
    ])]);
    app()->bind(PurchaseRequestExporter::class, fn () => new OdooPurchaseRequestExporter(new OdooClient(
        'https://odoo.ejemplo.cl', 'prueba', 'quien@ejemplo.cl', 'secreta',
    )));

    $this->actingAs($revisor)
        ->post(route('purchase_requests.prices.push', $solicitud))
        ->assertSessionHas('success', fn (string $m) => str_contains($m, 'P00241'));

    Http::assertSent(fn ($r) => ($r['params']['args'][4] ?? null) === 'write'
        && ($r['params']['args'][5][1]['price_unit'] ?? null) === 4747.9);
});

it('keeps the price editing for whoever can send to Odoo', function () {
    $dueno = User::factory()->create();
    $solicitud = aprobadaConPartida($dueno);

    $this->actingAs($dueno)
        ->post(route('purchase_requests.prices.update', $solicitud), [
            'prices' => [$solicitud->items->first()->getKey() => '100'],
        ])
        ->assertForbidden();
});

it('offers the price form whether or not the request is already in Odoo', function () {
    // La primera versión lo puso dentro de la rama de «todavía no enviada»,
    // así que justo en el caso que lo motivó —ya en Odoo, sin precio— no
    // aparecía.
    config(['purchase_requests.odoo.enabled' => true]);

    $revisor = User::factory()->admin()->create();

    $enOdoo = aprobadaConPartida($revisor);
    $this->actingAs($revisor)->get(route('purchase_requests.show', $enOdoo))
        ->assertOk()
        ->assertSee('Precios unitarios')
        // Escrita a mano: lleva precios y nada más. El botón lo dice.
        ->assertSee('Llevar precios a P00241')
        ->assertDontSee('Llevar precios y productos');

    $sinEnviar = PurchaseRequest::factory()->forUser($revisor)->approved()->create();
    $sinEnviar->items()->create([
        'sort_order' => 1, 'product_service' => 'arena', 'quantity' => 2, 'unit' => 'Unidades',
    ]);

    $this->actingAs($revisor)->get(route('purchase_requests.show', $sinEnviar))
        ->assertOk()
        ->assertSee('Precios unitarios')
        // Sin orden en Odoo no hay líneas que actualizar.
        ->assertDontSee('Llevar precios a P00241');
});

it('carries prices for a line that Odoo matched by name, with no alias saved', function () {
    $revisor = User::factory()->admin()->create();
    $solicitud = PurchaseRequest::factory()->forUser($revisor)->approved()->create([
        'odoo_order_id' => 241, 'odoo_reference' => 'P00241', 'odoo_exported_at' => now(),
    ]);
    $solicitud->items()->create([
        'sort_order' => 1, 'product_service' => 'CEMENTO 25 KG',
        'quantity' => 10, 'unit' => 'Unidades', 'unit_price' => 4747.9,
    ]);

    // Sin alias: el producto se resolvió por nombre idéntico, que es como
    // cruza la mayoría. Buscar sólo entre los alias aprendidos dejaba fuera
    // todo eso y la pantalla decía «ninguna partida tiene precio que llevar»
    // sobre una solicitud con precios en todas.
    App\Models\OdooProduct::query()->create([
        'company_code' => 'EHE', 'odoo_id' => 6644, 'name' => 'CEMENTO 25 KG',
        'purchase_ok' => true, 'active' => true,
    ]);

    config([
        'purchase_requests.odoo.enabled' => true,
        'purchase_requests.odoo.url' => 'https://odoo.ejemplo.cl',
        'purchase_requests.odoo.db' => 'prueba',
        'purchase_requests.odoo.user' => 'quien@ejemplo.cl',
        'purchase_requests.odoo.password' => 'secreta',
    ]);
    Http::preventStrayRequests();
    Http::fake(['*/jsonrpc' => Http::sequence([
        Http::response(['jsonrpc' => '2.0', 'result' => 7]),
        Http::response(['jsonrpc' => '2.0', 'result' => [['id' => 241, 'state' => 'draft', 'order_line' => [901]]]]),
        Http::response(['jsonrpc' => '2.0', 'result' => [['id' => 901, 'product_id' => [6644, 'CEMENTO 25 KG'], 'price_unit' => 0]]]),
        Http::response(['jsonrpc' => '2.0', 'result' => true]),
    ])]);
    app()->bind(PurchaseRequestExporter::class, fn () => new OdooPurchaseRequestExporter(new OdooClient(
        'https://odoo.ejemplo.cl', 'prueba', 'quien@ejemplo.cl', 'secreta',
    )));

    $this->actingAs($revisor)
        ->post(route('purchase_requests.prices.push', $solicitud->fresh()))
        ->assertSessionHas('success');

    Http::assertSent(fn ($r) => ($r['params']['args'][4] ?? null) === 'write'
        && ($r['params']['args'][5][1]['price_unit'] ?? null) === 4747.9);
});

it('prices a line that went to Odoo as plain text, with no product at all', function () {
    $revisor = User::factory()->admin()->create();
    $solicitud = PurchaseRequest::factory()->forUser($revisor)->approved()->create([
        'odoo_order_id' => 243, 'odoo_reference' => 'P00243', 'odoo_exported_at' => now(),
    ]);
    $solicitud->items()->create([
        'sort_order' => 1, 'product_service' => 'Corchetera',
        'quantity' => 2, 'unit' => 'Unidades', 'unit_price' => 3990,
    ]);

    config([
        'purchase_requests.odoo.enabled' => true,
        'purchase_requests.odoo.url' => 'https://odoo.ejemplo.cl',
        'purchase_requests.odoo.db' => 'prueba',
        'purchase_requests.odoo.user' => 'quien@ejemplo.cl',
        'purchase_requests.odoo.password' => 'secreta',
    ]);
    Http::preventStrayRequests();
    Http::fake(['*/jsonrpc' => Http::sequence([
        Http::response(['jsonrpc' => '2.0', 'result' => 7]),
        Http::response(['jsonrpc' => '2.0', 'result' => [['id' => 243, 'state' => 'draft', 'order_line' => [1969]]]]),
        // Odoo acepta una línea con sólo la descripción, y así viaja toda
        // partida cuyo producto nadie resolvió: sin product_id no había forma
        // de encontrarla, y su precio no llegaba nunca.
        Http::response(['jsonrpc' => '2.0', 'result' => [['id' => 1969, 'product_id' => false, 'name' => 'Corchetera', 'price_unit' => 0]]]),
        Http::response(['jsonrpc' => '2.0', 'result' => true]),
    ])]);
    app()->bind(PurchaseRequestExporter::class, fn () => new OdooPurchaseRequestExporter(new OdooClient(
        'https://odoo.ejemplo.cl', 'prueba', 'quien@ejemplo.cl', 'secreta',
    )));

    $this->actingAs($revisor)
        ->post(route('purchase_requests.prices.push', $solicitud->fresh()))
        ->assertSessionHas('success');

    Http::assertSent(fn ($r) => ($r['params']['args'][4] ?? null) === 'write'
        && ($r['params']['args'][5][0] ?? null) === [1969]
        && ($r['params']['args'][5][1]['price_unit'] ?? null) === 3990.0);
});

it('leaves alone a line somebody rewrote in Odoo', function () {
    $revisor = User::factory()->admin()->create();
    $solicitud = PurchaseRequest::factory()->forUser($revisor)->approved()->create([
        'odoo_order_id' => 243, 'odoo_reference' => 'P00243', 'odoo_exported_at' => now(),
    ]);
    $solicitud->items()->create([
        'sort_order' => 1, 'product_service' => 'Corchetera',
        'quantity' => 2, 'unit' => 'Unidades', 'unit_price' => 3990,
    ]);

    config([
        'purchase_requests.odoo.enabled' => true,
        'purchase_requests.odoo.url' => 'https://odoo.ejemplo.cl',
        'purchase_requests.odoo.db' => 'prueba',
        'purchase_requests.odoo.user' => 'quien@ejemplo.cl',
        'purchase_requests.odoo.password' => 'secreta',
    ]);
    Http::preventStrayRequests();
    Http::fake(['*/jsonrpc' => Http::sequence([
        Http::response(['jsonrpc' => '2.0', 'result' => 7]),
        Http::response(['jsonrpc' => '2.0', 'result' => [['id' => 243, 'state' => 'draft', 'order_line' => [1969]]]]),
        Http::response(['jsonrpc' => '2.0', 'result' => [['id' => 1969, 'product_id' => false, 'name' => 'Otra cosa distinta', 'price_unit' => 0]]]),
    ])]);
    app()->bind(PurchaseRequestExporter::class, fn () => new OdooPurchaseRequestExporter(new OdooClient(
        'https://odoo.ejemplo.cl', 'prueba', 'quien@ejemplo.cl', 'secreta',
    )));

    // Esa línea ya no es la que salió de aquí. No se toca, y se dice por qué
    // en vez de informar de un éxito que no ocurrió.
    $this->actingAs($revisor)
        ->post(route('purchase_requests.prices.push', $solicitud->fresh()))
        ->assertSessionHas('error', fn ($m) => str_contains((string) $m, 'reescrito'));
});

/**
 * La SC-2026-000039 nació del PDF de RODASERVIC: sus cuatro nombres son los del
 * proveedor y ninguno existía en Odoo. La P00252 salió con las cuatro líneas
 * sin producto, y el botón que prometía «precios y nombres» llevaba sólo el
 * precio —que además ya estaba bien—, así que no hacía absolutamente nada.
 */
it('gives Odoo the product when the names came off a document', function () {
    $revisor = User::factory()->admin()->create();

    $solicitud = PurchaseRequest::factory()->forUser($revisor)->approved()->create([
        'odoo_order_id' => 252, 'odoo_reference' => 'P00252', 'odoo_exported_at' => now(),
    ]);
    $solicitud->items()->create([
        'sort_order' => 1, 'product_service' => 'RET-NITRILO 65X90X12 WLK',
        'specification' => 'A10190', 'quantity' => 6, 'unit' => 'Unidades', 'unit_price' => 6723,
    ]);

    // Nació de un papel leído: eso es lo que autoriza dar de alta el producto.
    App\Models\PurchaseRequestIngestion::query()->create([
        'user_id' => $revisor->getKey(), 'uploader_name_snapshot' => $revisor->name,
        'purchase_request_id' => $solicitud->getKey(), 'disk' => 'local',
        'path' => '582.pdf', 'original_name' => '582.pdf', 'mime_type' => 'application/pdf',
        'size' => 10, 'sha256' => str_repeat('d', 64),
        'status' => App\Models\PurchaseRequestIngestion::COMPLETED,
    ]);

    config([
        'purchase_requests.odoo.enabled' => true,
        'purchase_requests.odoo.url' => 'https://odoo.ejemplo.cl',
        'purchase_requests.odoo.db' => 'prueba',
        'purchase_requests.odoo.user' => 'quien@ejemplo.cl',
        'purchase_requests.odoo.password' => 'secreta',
    ]);
    Http::preventStrayRequests();
    Http::fake(['*/jsonrpc' => Http::sequence([
        Http::response(['jsonrpc' => '2.0', 'result' => 7]),
        Http::response(['jsonrpc' => '2.0', 'result' => [['id' => 252, 'state' => 'draft', 'order_line' => [2009]]]]),
        Http::response(['jsonrpc' => '2.0', 'result' => [[
            'id' => 2009, 'product_id' => false, 'price_unit' => 6723, 'product_qty' => 6,
            'name' => 'RET-NITRILO 65X90X12 WLK · A10190',
        ]]]),
        Http::response(['jsonrpc' => '2.0', 'result' => []]),          // Odoo no lo tiene
        Http::response(['jsonrpc' => '2.0', 'result' => 9001]),         // se da de alta
        Http::response(['jsonrpc' => '2.0', 'result' => [[
            'id' => 9001, 'name' => 'RET-NITRILO 65X90X12 WLK', 'default_code' => false, 'barcode' => false,
            'uom_id' => [1, 'Units'], 'type' => 'consu', 'is_storable' => true, 'purchase_ok' => true, 'active' => true,
        ]]]),
        Http::response(['jsonrpc' => '2.0', 'result' => true]),         // la línea recibe su producto
    ])]);
    app()->bind(PurchaseRequestExporter::class, fn () => new OdooPurchaseRequestExporter(new OdooClient(
        'https://odoo.ejemplo.cl', 'prueba', 'quien@ejemplo.cl', 'secreta',
    )));

    $this->actingAs($revisor)
        ->post(route('purchase_requests.prices.push', $solicitud))
        ->assertSessionHas('success', fn (string $m) => str_contains($m, 'dio de alta 1 producto'));

    // Se da de alta con el nombre limpio: el código del proveedor va en la
    // línea, no en el catálogo.
    Http::assertSent(fn ($r) => ($r['params']['args'][3] ?? null) === 'product.product'
        && ($r['params']['args'][4] ?? null) === 'create'
        && ($r['params']['args'][5][0]['name'] ?? null) === 'RET-NITRILO 65X90X12 WLK'
        && ($r['params']['args'][5][0]['is_storable'] ?? null) === true);

    // Y la línea de Odoo queda apuntando al producto, sin que le reescriban
    // el nombre: allá ya decía lo que corresponde.
    Http::assertSent(fn ($r) => ($r['params']['args'][3] ?? null) === 'purchase.order.line'
        && ($r['params']['args'][4] ?? null) === 'write'
        && ($r['params']['args'][5][1]['product_id'] ?? null) === 9001
        && ! array_key_exists('name', $r['params']['args'][5][1] ?? []));
});

/** Y lo contrario: escrita a mano, no se le da de alta nada a nadie. */
it('refuses to put hand-typed words into the Odoo catalogue', function () {
    $revisor = User::factory()->admin()->create();
    $solicitud = aprobadaConPartida($revisor, 4747.9);

    config([
        'purchase_requests.odoo.enabled' => true,
        'purchase_requests.odoo.url' => 'https://odoo.ejemplo.cl',
        'purchase_requests.odoo.db' => 'prueba',
        'purchase_requests.odoo.user' => 'quien@ejemplo.cl',
        'purchase_requests.odoo.password' => 'secreta',
    ]);
    Http::preventStrayRequests();
    Http::fake(['*/jsonrpc' => Http::sequence([
        Http::response(['jsonrpc' => '2.0', 'result' => 7]),
        Http::response(['jsonrpc' => '2.0', 'result' => [['id' => 241, 'state' => 'draft', 'order_line' => [901]]]]),
        Http::response(['jsonrpc' => '2.0', 'result' => [[
            'id' => 901, 'product_id' => false, 'price_unit' => 0, 'product_qty' => 10, 'name' => 'cemento',
        ]]]),
        Http::response(['jsonrpc' => '2.0', 'result' => true]),
    ])]);
    app()->bind(PurchaseRequestExporter::class, fn () => new OdooPurchaseRequestExporter(new OdooClient(
        'https://odoo.ejemplo.cl', 'prueba', 'quien@ejemplo.cl', 'secreta',
    )));

    $this->actingAs($revisor)->post(route('purchase_requests.prices.push', $solicitud));

    Http::assertNotSent(fn ($r) => ($r['params']['args'][3] ?? null) === 'product.product'
        && ($r['params']['args'][4] ?? null) === 'create');
});
