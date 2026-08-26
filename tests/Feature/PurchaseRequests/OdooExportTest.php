<?php

use App\Models\PurchaseRequest;
use App\Models\PurchaseSupplier;
use App\Models\User;
use App\Services\PurchaseRequests\Odoo\OdooClient;
use App\Services\PurchaseRequests\Odoo\OdooPurchaseRequestExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

/** Odoo contestando lo que corresponde, sin salir a la red. */
function odooResponde(array $porTurno): void
{
    config([
        'purchase_requests.odoo.enabled' => true,
        'purchase_requests.odoo.url' => 'https://odoo.example.test',
        'purchase_requests.odoo.db' => 'prueba',
        'purchase_requests.odoo.user' => 'quien@ejemplo.cl',
        'purchase_requests.odoo.password' => 'secreta',
        'purchase_requests.odoo.picking_type_id' => 1,
    ]);

    Http::preventStrayRequests();
    Http::fake(['*/jsonrpc' => Http::sequence(
        array_map(fn ($r) => Http::response(['jsonrpc' => '2.0', 'result' => $r]), $porTurno),
    )]);
}

function exportador(): OdooPurchaseRequestExporter
{
    return new OdooPurchaseRequestExporter(new OdooClient(
        (string) config('purchase_requests.odoo.url'),
        (string) config('purchase_requests.odoo.db'),
        (string) config('purchase_requests.odoo.user'),
        (string) config('purchase_requests.odoo.password'),
    ));
}

function solicitudAprobadaCon(array $atributos = []): PurchaseRequest
{
    $owner = User::factory()->create();

    return PurchaseRequest::factory()->forUser($owner)->approved()->create($atributos);
}

it('creates one draft RFQ and links it back', function () {
    // login, search del proveedor, create, read del nombre
    odooResponde([8, [3528], 219, [['id' => 219, 'name' => 'P00219']]]);

    $solicitud = solicitudAprobadaCon(['suggested_suppliers' => ['RODASERVIC SPA (RUT 77.045.469-7)']]);
    $solicitud->items()->create([
        'sort_order' => 1, 'product_service' => 'Rodamiento 6202',
        'quantity' => '5', 'unit' => 'Unidades', 'unit_price' => '4500',
    ]);

    $resultado = exportador()->exportApproved($solicitud);

    expect($resultado->performed)->toBeTrue()
        ->and($resultado->status)->toBe('created')
        ->and($resultado->remoteReference)->toBe('P00219');

    // El vínculo se guarda, que es lo que hace posible no duplicar.
    expect($solicitud->fresh()->odoo_order_id)->toBe(219)
        ->and($solicitud->fresh()->odoo_reference)->toBe('P00219');
});

it('sends the lines as text, without inventing products or units', function () {
    odooResponde([8, [3528], 219, [['id' => 219, 'name' => 'P00219']]]);

    $solicitud = solicitudAprobadaCon(['suggested_suppliers' => ['RUT 77.045.469-7']]);
    $solicitud->items()->create([
        'sort_order' => 1, 'product_service' => 'Rodamiento 6202',
        'specification' => '2RS C3', 'quantity' => '5', 'unit' => 'Cajas', 'unit_price' => '4500',
    ]);

    exportador()->exportApproved($solicitud);

    Http::assertSent(function ($request) {
        $args = $request['params']['args'] ?? [];

        if (($args[3] ?? null) !== 'purchase.order' || ($args[4] ?? null) !== 'create') {
            return false;
        }

        $linea = $args[5][0]['order_line'][0][2];

        // Ni product_id ni product_uom: crearlos desde texto escrito a mano
        // llenaría el catálogo de Odoo de duplicados.
        return ! array_key_exists('product_id', $linea)
            && ! array_key_exists('product_uom', $linea)
            && str_contains($linea['name'], 'Rodamiento 6202')
            && str_contains($linea['name'], '2RS C3')
            && str_contains($linea['name'], 'Cajas')   // la unidad viaja en el texto
            && $linea['product_qty'] === 5.0
            && $linea['price_unit'] === 4500.0;
    });
});

it('never creates a second RFQ for the same request', function () {
    odooResponde([8, [3528], 219, [['id' => 219, 'name' => 'P00219']]]);

    $solicitud = solicitudAprobadaCon([
        'suggested_suppliers' => ['RUT 77.045.469-7'],
        'odoo_order_id' => 219,
        'odoo_reference' => 'P00219',
    ]);
    $solicitud->items()->create(['sort_order' => 1, 'product_service' => 'x', 'quantity' => '1', 'unit' => 'Unidades']);

    $resultado = exportador()->exportApproved($solicitud);

    expect($resultado->status)->toBe('already_exported')
        ->and($resultado->performed)->toBeFalse();

    Http::assertNothingSent();
});

it('stops instead of inventing a supplier Odoo does not know', function () {
    // El RUT está escrito, así que sí se busca por RUT; Odoo no lo tiene. Y no
    // quedan palabras con que buscar parecidos: «RUT» es ruido y los números
    // no son un nombre.
    odooResponde([8, []]);

    $solicitud = solicitudAprobadaCon(['suggested_suppliers' => ['RUT 77.045.469-7']]);
    $solicitud->items()->create(['sort_order' => 1, 'product_service' => 'x', 'quantity' => '1', 'unit' => 'Unidades']);

    $resultado = exportador()->exportApproved($solicitud);

    expect($resultado->status)->toBe('needs_supplier')
        ->and($resultado->candidates)->toBeEmpty()
        ->and($resultado->message)->toContain('Regístralo allá');

    expect($solicitud->fresh()->odoo_order_id)->toBeNull();
});

it('finds the supplier through the catalogue when only the name was written', function () {
    odooResponde([8, [3528], 219, [['id' => 219, 'name' => 'P00219']]]);

    PurchaseSupplier::create(['tax_id' => '77045469-7', 'name' => 'RODASERVIC SPA']);

    $solicitud = solicitudAprobadaCon(['suggested_suppliers' => ['Rodaservic SPA']]);
    $solicitud->items()->create(['sort_order' => 1, 'product_service' => 'x', 'quantity' => '1', 'unit' => 'Unidades']);

    expect(exportador()->exportApproved($solicitud)->status)->toBe('created');

    // Y busca en Odoo con el RUT sin puntos, que es como Odoo lo guarda.
    Http::assertSent(fn ($r) => ($r['params']['args'][3] ?? null) !== 'res.partner'
        || $r['params']['args'][5][0][0][2] === '77045469-7');
});

it('refuses anything that is not approved, and touches nothing', function () {
    odooResponde([8]);

    $solicitud = PurchaseRequest::factory()->forUser(User::factory()->create())->create();

    expect(exportador()->exportApproved($solicitud)->status)->toBe('skipped');
    Http::assertNothingSent();
});

it('offers the suppliers Odoo does have instead of guessing', function () {
    // Sin RUT escrito ni alias conocido no hay nada que buscar por RUT: la
    // primera llamada tras el login ya es la búsqueda por nombre.
    odooResponde([8, [['id' => 1721, 'name' => 'ARIDOS VICAT SUR SPA', 'vat' => '76893540-8']]]);

    // Tal como lo escribe una persona: sin RUT y sin el nombre legal.
    $solicitud = solicitudAprobadaCon(['suggested_suppliers' => ['Vicat']]);
    $solicitud->items()->create(['sort_order' => 1, 'product_service' => 'arena', 'quantity' => '4', 'unit' => 'Cubos']);

    $resultado = exportador()->exportApproved($solicitud);

    expect($resultado->status)->toBe('needs_supplier')
        ->and($resultado->performed)->toBeFalse()
        ->and($resultado->candidates)->toHaveCount(1)
        ->and($resultado->candidates[0]['name'])->toBe('ARIDOS VICAT SUR SPA')
        ->and($resultado->candidates[0]['vat'])->toBe('76893540-8');

    // Nada se creó ni se vinculó mientras no haya respuesta humana.
    expect($solicitud->fresh()->odoo_order_id)->toBeNull();
});

it('remembers the answer so nobody is asked twice', function () {
    // Una sola secuencia para las dos exportaciones del test: login y búsqueda
    // por nombre la primera vez; login, búsqueda por RUT, creación y lectura
    // la segunda, que ya no pregunta nada.
    odooResponde([
        8, [['id' => 1721, 'name' => 'ARIDOS VICAT SUR SPA', 'vat' => '76893540-8']],
        8, [1721], 219, [['id' => 219, 'name' => 'P00219']],
    ]);

    $solicitud = solicitudAprobadaCon(['suggested_suppliers' => ['Vicat']]);
    $solicitud->items()->create(['sort_order' => 1, 'product_service' => 'arena', 'quantity' => '4', 'unit' => 'Cubos']);

    $candidato = exportador()->exportApproved($solicitud)->candidates[0];

    // Una persona confirma cuál era.
    $proveedor = app(App\Services\PurchaseRequests\Odoo\ConfirmOdooSupplier::class)(
        $solicitud, $candidato['id'], $candidato['name'], $candidato['vat'],
    );

    expect($proveedor->tax_id)->toBe('76893540-8')
        ->and($proveedor->odoo_partner_id)->toBe(1721)
        // «Vicat» queda anotado como una forma válida de nombrarlo.
        ->and($proveedor->aliases)->toContain('vicat');

    // Y otra solicitud que diga «VICAT» resuelve sola, sin volver a preguntar.
    $otra = solicitudAprobadaCon(['suggested_suppliers' => ['VICAT']]);
    $otra->items()->create(['sort_order' => 1, 'product_service' => 'bolones', 'quantity' => '5', 'unit' => 'Cubos', 'unit_price' => '32130']);

    expect(exportador()->exportApproved($otra)->status)->toBe('created');
});
