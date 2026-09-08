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
        [['id' => 811, 'product_id' => [8723, 'CURVA PVC'],
            'price_unit' => 65174, 'name' => 'CURVA PVC HIDRAUL. 200x90 CEMENTAR']],
    ]);

    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))
        ->assertSessionHas('success', fn (string $m) => str_contains($m, 'ya decía lo mismo'));

    Http::assertNotSent(fn ($r) => ($r['params']['args'][4] ?? null) === 'write');
});

it('carries the real name even when the quotation brought no price', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = solicitudConCotizacion($revisor, null);

    odooContesta([
        7,
        [['id' => 240, 'state' => 'draft', 'order_line' => [811]]],
        [['id' => 811, 'product_id' => [8723, 'CURVA PVC'], 'price_unit' => 0, 'name' => 'curva 200 90']],
        true,
    ]);

    // La solicitud se escribe con lo que uno tiene en la cabeza; la cotización
    // llega con el nombre de verdad. Ese segundo es el que sirve para volver a
    // pedirlo, y no depende de que además traiga precio.
    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))
        ->assertSessionHas('success');

    Http::assertSent(fn ($r) => ($r['params']['args'][4] ?? null) === 'write'
        && ($r['params']['args'][5][1]['name'] ?? null) === 'CURVA PVC HIDRAUL. 200x90 CEMENTAR'
        && ! array_key_exists('price_unit', $r['params']['args'][5][1] ?? []));
});

it('keeps the price update for whoever can send to Odoo', function () {
    $dueno = User::factory()->create();
    [$solicitud, $lectura] = solicitudConCotizacion($dueno);

    $this->actingAs($dueno)
        ->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))
        ->assertForbidden();
});

it('warns that the real names must travel before Odoo closes the order', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = solicitudConCotizacion($revisor);

    // El aviso importa más que el botón: en cuanto la orden se confirma en
    // Odoo, el nombre genérico con que nació queda ahí para siempre. Este
    // programa aprende de cada cotización; Odoo no.
    $this->actingAs($revisor)
        ->get(route('purchase_requests.show', $solicitud))
        ->assertOk()
        ->assertSee('trae el nombre real del proveedor', false)
        ->assertSee('antes de confirmar la orden en Odoo');
});

it('explains what was lost when the order already left draft', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = solicitudConCotizacion($revisor);

    odooContesta([
        7,
        [['id' => 240, 'state' => 'purchase', 'order_line' => [811]]],
    ]);

    // Un «no se puede» a secas no enseña nada. Hay que decir qué quedó como
    // estaba y por qué no se toca.
    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))
        ->assertSessionHas('error', fn ($m) => str_contains((string) $m, 'se quedó')
            && str_contains((string) $m, 'P00240'));
});

/** Una solicitud cuya línea viajó a Odoo como texto, sin producto. */
function solicitudSinProductoEnOdoo(User $owner): array
{
    $solicitud = PurchaseRequest::factory()->forUser($owner)->approved()->create([
        'odoo_order_id' => 243, 'odoo_reference' => 'P00243', 'odoo_exported_at' => now(),
    ]);
    $solicitud->items()->create([
        'sort_order' => 1, 'product_service' => 'Corchetera',
        'quantity' => 2, 'unit' => 'Unidades', 'unit_price' => null,
    ]);

    $lectura = PurchaseRequestIngestion::query()->create([
        'user_id' => $owner->getKey(), 'uploader_name_snapshot' => $owner->name,
        'compared_request_id' => $solicitud->getKey(), 'disk' => 'local',
        'path' => 'c.txt', 'original_name' => 'Cotización escrita a mano.txt',
        'mime_type' => 'text/plain', 'size' => 10, 'sha256' => str_repeat('c', 64),
        'status' => PurchaseRequestIngestion::COMPLETED,
        'extracted' => ['items' => [[
            'product_service' => 'CORCHETERA PLASTICA 20 HJ 24081 AUCA TOR111', 'specification' => null,
            'quantity' => '2,00', 'unit' => 'Unidades', 'unit_price' => '4958',
        ]]],
        'confirmed_pairings' => [0 => $solicitud->items()->value('id')],
    ]);

    return [$solicitud->fresh(), $lectura];
}

it('links a product that Odoo already had, instead of creating a duplicate', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = solicitudSinProductoEnOdoo($revisor);

    odooContesta([
        7,
        [['id' => 243, 'state' => 'draft', 'order_line' => [1969]]],
        [['id' => 1969, 'product_id' => false, 'name' => 'Corchetera', 'price_unit' => 0]],
        // Odoo sí lo tiene: lo creó alguien hoy y la copia local, que se
        // sincroniza de noche, todavía no lo sabe. Tres de las ocho partidas
        // de la SC-2026-000029 estaban así el primer día.
        [['id' => 8724, 'name' => 'CORCHETERA PLASTICA 20 HJ 24081 AUCA TOR111',
            'default_code' => false, 'barcode' => false, 'uom_id' => [1, 'Units'],
            'type' => 'consu', 'is_storable' => true, 'purchase_ok' => true, 'active' => true]],
        true,
    ]);

    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))
        ->assertSessionHas('success', fn ($m) => ! str_contains((string) $m, 'dio de alta'));

    Http::assertNotSent(fn ($r) => ($r['params']['args'][4] ?? null) === 'create');
    Http::assertSent(fn ($r) => ($r['params']['args'][4] ?? null) === 'write'
        && ($r['params']['args'][5][1]['product_id'] ?? null) === 8724
        && ($r['params']['args'][5][1]['product_uom'] ?? null) === 1);

    // Y queda en la copia local, para no volver a salir a preguntar.
    expect(App\Models\OdooProduct::where('odoo_id', 8724)->value('name'))
        ->toBe('CORCHETERA PLASTICA 20 HJ 24081 AUCA TOR111');
});

it('creates the product Odoo really does not have, tracking inventory', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = solicitudSinProductoEnOdoo($revisor);

    odooContesta([
        7,
        [['id' => 243, 'state' => 'draft', 'order_line' => [1969]]],
        [['id' => 1969, 'product_id' => false, 'name' => 'Corchetera', 'price_unit' => 0]],
        [],      // Odoo no lo tiene
        9001,    // el create
        [['id' => 9001, 'name' => 'CORCHETERA PLASTICA 20 HJ 24081 AUCA TOR111',
            'default_code' => false, 'barcode' => false, 'uom_id' => [1, 'Units'],
            'type' => 'consu', 'is_storable' => true, 'purchase_ok' => true, 'active' => true]],
        true,
    ]);

    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))
        ->assertSessionHas('success', fn ($m) => str_contains((string) $m, 'dio de alta'));

    // Con el rastreo de inventario encendido, que es lo que Sebastián marca a
    // mano cada vez. Categoría y unidad las pone Odoo por defecto.
    Http::assertSent(fn ($r) => ($r['params']['args'][3] ?? null) === 'product.product'
        && ($r['params']['args'][4] ?? null) === 'create'
        && ($r['params']['args'][5][0]['name'] ?? null) === 'CORCHETERA PLASTICA 20 HJ 24081 AUCA TOR111'
        && ($r['params']['args'][5][0]['is_storable'] ?? null) === true
        && ($r['params']['args'][5][0]['purchase_ok'] ?? null) === true);
});

it('creates nothing at all when the switch is off', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = solicitudSinProductoEnOdoo($revisor);

    odooContesta([
        7,
        [['id' => 243, 'state' => 'draft', 'order_line' => [1969]]],
        [['id' => 1969, 'product_id' => false, 'name' => 'Corchetera', 'price_unit' => 0]],
        [],
        true,
    ]);
    config(['purchase_requests.odoo.create_missing_products' => false]);

    // Sin producto, la línea recibe igual su precio y su nombre: apagar el
    // interruptor frena lo permanente, no todo lo demás.
    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))
        ->assertSessionHas('success');

    Http::assertNotSent(fn ($r) => ($r['params']['args'][4] ?? null) === 'create');
});

it('still recognises a line after its name was already replaced', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = solicitudSinProductoEnOdoo($revisor);

    // Segundo clic: la línea ya no se llama «Corchetera» porque el primero le
    // puso el nombre del proveedor. Reconocerla sólo por el texto original la
    // volvía irreconocible, y la orden no admitía ni una corrección más.
    odooContesta([
        7,
        [['id' => 243, 'state' => 'draft', 'order_line' => [1969]]],
        [['id' => 1969, 'product_id' => false, 'price_unit' => 4958,
            'name' => 'CORCHETERA PLASTICA 20 HJ 24081 AUCA TOR111']],
        [['id' => 8724, 'name' => 'CORCHETERA PLASTICA 20 HJ 24081 AUCA TOR111',
            'default_code' => false, 'barcode' => false, 'uom_id' => [1, 'Units'],
            'type' => 'consu', 'is_storable' => true, 'purchase_ok' => true, 'active' => true]],
        true,
    ]);

    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))
        ->assertSessionHas('success');

    // Y lo que faltaba —el producto— sí se escribe.
    Http::assertSent(fn ($r) => ($r['params']['args'][4] ?? null) === 'write'
        && ($r['params']['args'][5][1]['product_id'] ?? null) === 8724);
});
