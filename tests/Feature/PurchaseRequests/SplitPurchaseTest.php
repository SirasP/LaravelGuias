<?php

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestIngestion;
use App\Models\PurchaseSupplier;
use App\Models\User;
use App\Services\PurchaseRequests\Odoo\OdooClient;
use App\Services\PurchaseRequests\Odoo\OdooPurchaseRequestExporter;
use App\Services\PurchaseRequests\Odoo\PurchaseRequestExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Una compra repartida entre dos proveedores.
 *
 * La SC-2026-000024 se compró en dos sitios y Odoo tenía sus nueve partidas en
 * una sola orden a nombre de uno solo. Como allá la recepción cuelga de la
 * orden, ese dato falso llegaba derecho al stock.
 */
function odooDice(array $porTurno): void
{
    config([
        'purchase_requests.odoo.enabled' => true,
        'purchase_requests.odoo.url' => 'https://odoo.ejemplo.cl',
        'purchase_requests.odoo.db' => 'prueba',
        'purchase_requests.odoo.user' => 'quien@ejemplo.cl',
        'purchase_requests.odoo.password' => 'secreta',
        'purchase_requests.odoo.picking_type_id' => 1,
    ]);

    Http::preventStrayRequests();
    Http::fake(['*/jsonrpc' => Http::sequence(
        array_map(fn ($r) => Http::response(['jsonrpc' => '2.0', 'result' => $r]), $porTurno),
    )]);

    app()->bind(PurchaseRequestExporter::class, fn () => new OdooPurchaseRequestExporter(new OdooClient(
        'https://odoo.ejemplo.cl', 'prueba', 'quien@ejemplo.cl', 'secreta',
    )));
}

/** Solicitud de 5 partidas ya en Odoo, y una cotización que cubre 3. */
function compraRepartida(User $owner): array
{
    $solicitud = PurchaseRequest::factory()->forUser($owner)->approved()->create([
        'odoo_order_id' => 250, 'odoo_reference' => 'P00250', 'odoo_exported_at' => now(),
    ]);

    $nombres = ['SPRAY BLANCO', 'GRASA LIQUIDA', 'CINTA PELIGRO', 'ROLLO FILM', 'GALON PINTURA'];

    foreach ($nombres as $n => $nombre) {
        $solicitud->items()->create([
            'sort_order' => $n + 1, 'product_service' => $nombre,
            'quantity' => 1, 'unit' => 'Unidades',
            'odoo_order_id' => 250, 'odoo_line_id' => 900 + $n,
        ]);
    }

    PurchaseSupplier::query()->create([
        'company_code' => 'EHE', 'name' => 'PROVEEDOR A',
        'tax_id' => '76569041-2', 'odoo_partner_id' => 3557,
    ]);

    $lectura = PurchaseRequestIngestion::query()->create([
        'user_id' => $owner->getKey(), 'uploader_name_snapshot' => $owner->name,
        'compared_request_id' => $solicitud->getKey(), 'disk' => 'local',
        'path' => 'a.pdf', 'original_name' => 'cot A.pdf', 'mime_type' => 'application/pdf',
        'size' => 10, 'sha256' => str_repeat('a', 64),
        'status' => PurchaseRequestIngestion::COMPLETED,
        'supplier_tax_id' => '76569041-2', 'supplier_name' => 'PROVEEDOR A',
        'extracted' => ['items' => array_map(fn (string $n): array => [
            'product_service' => $n, 'specification' => null,
            'quantity' => '1', 'unit' => 'Unidades', 'unit_price' => '1000',
        ], ['SPRAY BLANCO', 'GRASA LIQUIDA', 'CINTA PELIGRO'])],
    ]);

    return [$solicitud->fresh(), $lectura];
}

it('leaves in the first order only what that supplier sells, and parks the rest', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = compraRepartida($revisor);

    odooDice([
        7,                                                                  // autenticación
        [['id' => 250, 'state' => 'draft', 'order_line' => [900, 901, 902, 903, 904]]],
        true,                                                               // el unlink de las 2 que sobran
        [],                                                                 // productos que Odoo confirma
        251,                                                                // la orden nueva… (no llega aquí)
    ]);

    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.split', [$solicitud, $lectura]));

    // Las tres que cotizó A siguen en P00250; las otras dos quedan en espera,
    // sin orden, a la vista y listas para la cotización del segundo.
    expect($solicitud->items()->whereNull('odoo_order_id')->pluck('product_service')->sort()->values()->all())
        ->toBe(['GALON PINTURA', 'ROLLO FILM']);

    Http::assertSent(fn ($r) => ($r['params']['args'][3] ?? null) === 'purchase.order.line'
        && ($r['params']['args'][4] ?? null) === 'unlink'
        && ($r['params']['args'][5][0] ?? null) === [903, 904]);
});

it('refuses to touch an order Odoo already confirmed', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = compraRepartida($revisor);

    odooDice([7, [['id' => 250, 'state' => 'purchase', 'order_line' => [900, 901, 902, 903, 904]]]]);

    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.split', [$solicitud, $lectura]))
        ->assertSessionHas('error', fn ($m) => str_contains((string) $m, 'ya no está en borrador'));

    // Y nada se movió: las cinco siguen donde estaban.
    expect($solicitud->items()->whereNull('odoo_order_id')->count())->toBe(0);
    Http::assertNotSent(fn ($r) => ($r['params']['args'][4] ?? null) === 'unlink');
});

it('says who the supplier is before creating anything', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = compraRepartida($revisor);
    $lectura->forceFill(['supplier_tax_id' => null])->save();

    odooDice([7]);

    // Sin proveedor resuelto no hay a nombre de quién crear la orden, y una
    // orden sin proveedor correcto es peor que ninguna.
    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.split', [$solicitud, $lectura->fresh()]))
        ->assertSessionHas('error', fn ($m) => str_contains((string) $m, 'quién es este proveedor'));
});
