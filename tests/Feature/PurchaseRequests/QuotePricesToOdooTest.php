<?php

use App\Models\PurchaseProductLink;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestIngestion;
use App\Models\User;
use App\Services\PurchaseRequests\Odoo\OdooClient;
use App\Services\PurchaseRequests\Odoo\OdooPurchaseRequestExporter;
use App\Services\PurchaseRequests\Odoo\PurchaseRequestExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function odooContesta(array $porTurno): void
{
    config([
        'purchase_requests.odoo.enabled' => true,
        'purchase_requests.odoo.url' => 'https://odoo.ejemplo.cl',
        'purchase_requests.odoo.db' => 'prueba',
        'purchase_requests.odoo.user' => 'quien@ejemplo.cl',
        'purchase_requests.odoo.password' => 'secreta',
    ]);

    Http::preventStrayRequests();
    Http::fake(['*/jsonrpc' => Http::sequence(
        array_map(fn ($r) => Http::response(['jsonrpc' => '2.0', 'result' => $r]), $porTurno),
    )]);

    app()->bind(PurchaseRequestExporter::class, fn () => new OdooPurchaseRequestExporter(new OdooClient(
        'https://odoo.ejemplo.cl', 'prueba', 'quien@ejemplo.cl', 'secreta',
    )));
}

/** La situación real: partida sin precio, cotización con precio, orden en Odoo. */
function solicitudConCotizacion(User $owner, ?int $precioCotizado = 65174): array
{
    $solicitud = PurchaseRequest::factory()->forUser($owner)->approved()->create([
        'odoo_order_id' => 240, 'odoo_reference' => 'P00240', 'odoo_exported_at' => now(),
    ]);
    $solicitud->items()->create([
        'sort_order' => 1, 'product_service' => 'curva de 200 mm x 90',
        'quantity' => 2, 'unit' => 'Unidades', 'unit_price' => null,
    ]);

    PurchaseProductLink::query()->create([
        'company_code' => 'EHE', 'odoo_partner_id' => null,
        'source_text' => 'curva de 200 mm x 90',
        'normalized_text' => PurchaseProductLink::normalizar('curva de 200 mm x 90'),
        'odoo_product_id' => 8723, 'odoo_product_name' => 'CURVA PVC HIDRAUL. 200x90 CEMENTAR',
        'source' => PurchaseProductLink::CONFIRMADO,
    ]);
    PurchaseProductLink::query()->create([
        'company_code' => 'EHE', 'odoo_partner_id' => null,
        'source_text' => 'CURVA PVC HIDRAUL. 200x90 CEMENTAR',
        'normalized_text' => PurchaseProductLink::normalizar('CURVA PVC HIDRAUL. 200x90 CEMENTAR'),
        'odoo_product_id' => 8723, 'odoo_product_name' => 'CURVA PVC HIDRAUL. 200x90 CEMENTAR',
        'source' => PurchaseProductLink::CONFIRMADO,
    ]);

    $lectura = PurchaseRequestIngestion::query()->create([
        'user_id' => $owner->getKey(), 'uploader_name_snapshot' => $owner->name,
        'compared_request_id' => $solicitud->getKey(), 'disk' => 'local',
        'path' => 'c.pdf', 'original_name' => 'COTIZACION Nro. 11315.pdf',
        'mime_type' => 'application/pdf', 'size' => 10, 'sha256' => str_repeat('a', 64),
        'status' => PurchaseRequestIngestion::NEEDS_REVIEW,
        'extracted' => ['items' => [[
            'product_service' => 'CURVA PVC HIDRAUL. 200x90 CEMENTAR', 'specification' => null,
            'quantity' => '2.00', 'unit' => 'Unidades', 'unit_price' => $precioCotizado,
        ]]],
    ]);

    return [$solicitud, $lectura];
}

it('writes the quoted price on the Odoo line that carries the same product', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = solicitudConCotizacion($revisor);

    odooContesta([
        7,                                                            // autenticación
        [['id' => 240, 'state' => 'draft', 'order_line' => [811]]],   // la orden
        [['id' => 811, 'product_id' => [8723, 'CURVA PVC'], 'price_unit' => 0]],
        true,                                                         // el write
    ]);

    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))
        ->assertSessionHas('success', fn (string $m) => str_contains($m, 'P00240'));

    // params.args = [db, uid, clave, modelo, método, args, kwargs].
    Http::assertSent(function ($request) {
        return ($request['params']['args'][3] ?? null) === 'purchase.order.line'
            && ($request['params']['args'][4] ?? null) === 'write'
            && ($request['params']['args'][5][0] ?? null) === [811]
            && ($request['params']['args'][5][1]['price_unit'] ?? null) === 65174.0;
    });
});

it('refuses to touch an order that Odoo already confirmed', function () {
    // Confirmada hay recepciones y facturas detrás: cambiarle el precio por
    // detrás sería mover algo que allá ya se dio por cerrado.
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = solicitudConCotizacion($revisor);

    odooContesta([7, [['id' => 240, 'state' => 'purchase', 'order_line' => [811]]]]);

    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'ya no está en borrador'));

    Http::assertNotSent(fn ($r) => ($r['params']['args'][4] ?? null) === 'write');
});

it('does not rewrite a price that already matches', function () {
    // Escribir el mismo número ensucia el historial de Odoo sin cambiar nada.
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = solicitudConCotizacion($revisor);

    odooContesta([
        7,
        [['id' => 240, 'state' => 'draft', 'order_line' => [811]]],
        [['id' => 811, 'product_id' => [8723, 'CURVA PVC'], 'price_unit' => 65174]],
    ]);

    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))
        ->assertSessionHas('success', fn (string $m) => str_contains($m, 'ya coincidían'));

    Http::assertNotSent(fn ($r) => ($r['params']['args'][4] ?? null) === 'write');
});

it('carries nothing when the quotation has no price', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = solicitudConCotizacion($revisor, null);

    odooContesta([7]);

    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'Ninguna partida tiene precio'));
});

it('keeps the price update for whoever can send to Odoo', function () {
    $dueno = User::factory()->create();
    [$solicitud, $lectura] = solicitudConCotizacion($dueno);

    $this->actingAs($dueno)
        ->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))
        ->assertForbidden();
});
