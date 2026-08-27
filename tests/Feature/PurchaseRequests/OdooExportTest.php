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

        // Sin product_id: crear productos desde texto escrito a mano llenaría
        // el catálogo de Odoo de duplicados con faltas de ortografía.
        //
        // Con product_uom, en cambio, sí: dejarlo fuera no hacía que Odoo
        // pusiera «Units» por defecto, sino que la línea entrara literalmente
        // sin unidad. «Cajas» no tiene equivalente en Odoo, así que entra con
        // la de por defecto y la real viaja en la descripción.
        return ! array_key_exists('product_id', $linea)
            && $linea['product_uom'] === 1
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
        // Sin candidatos, la salida es el buscador; darlo de alta se hace en
        // Odoo, que es donde vive el maestro de proveedores.
        ->and($resultado->message)->toContain('Búscalo aquí abajo');

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

it('only lets Compras press the button, and only on an approved request', function () {
    config(['purchase_requests.odoo.enabled' => false]);

    $solicitante = User::factory()->create(['role' => 'user']);
    $solicitud = PurchaseRequest::factory()->forUser($solicitante)->approved()->create();

    // Quien pide no decide, y menos escribe en Odoo.
    $this->actingAs($solicitante)
        ->post(route('purchase_requests.odoo.export', $solicitud))
        ->assertForbidden();

    // Compras sí, aunque con la integración apagada no ocurra nada.
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->post(route('purchase_requests.odoo.export', $solicitud))
        ->assertRedirect();
});

it('shows the button on the page only once the request is approved', function () {
    config([
        'purchase_requests.odoo.enabled' => true,
        'purchase_requests.odoo.url' => 'https://odoo.example.test',
        'purchase_requests.odoo.db' => 'prueba',
    ]);

    $compras = User::factory()->create(['role' => 'admin']);

    $aprobada = PurchaseRequest::factory()->forUser($compras)->approved()->create();
    $this->actingAs($compras)->get(route('purchase_requests.show', $aprobada))
        ->assertOk()->assertSee('Enviar a Odoo');

    // Antes de aprobar no hay nada que enviar.
    $borrador = PurchaseRequest::factory()->forUser($compras)->create();
    $this->actingAs($compras)->get(route('purchase_requests.show', $borrador))
        ->assertOk()->assertDontSee('Enviar a Odoo');
});

it('does not offer to send it twice', function () {
    config([
        'purchase_requests.odoo.enabled' => true,
        'purchase_requests.odoo.url' => 'https://odoo.example.test',
        'purchase_requests.odoo.db' => 'prueba',
    ]);

    $compras = User::factory()->create(['role' => 'admin']);
    $solicitud = PurchaseRequest::factory()->forUser($compras)->approved()->create([
        'odoo_order_id' => 219, 'odoo_reference' => 'P00219', 'odoo_exported_at' => now(),
    ]);

    // Dos cotizaciones para la misma compra y nadie sabría cuál vale.
    $this->actingAs($compras)->get(route('purchase_requests.show', $solicitud))
        ->assertOk()
        ->assertSee('P00219')
        ->assertDontSee('Enviar a Odoo');
});

it('adds the tax only when the prices are known to be net', function () {
    config(['purchase_requests.odoo.tax_id' => 2]);

    $impuestoDeLaLinea = function (): mixed {
        $enviado = null;
        Http::assertSent(function ($request) use (&$enviado) {
            $args = $request['params']['args'] ?? [];
            if (($args[3] ?? null) === 'purchase.order' && ($args[4] ?? null) === 'create') {
                $enviado = $args[5][0]['order_line'][0][2];
            }

            return true;
        });

        return $enviado;
    };

    // Precios netos: hay que sumar el 19%, o la orden entra sin IVA.
    odooResponde([8, [3528], 219, [['id' => 219, 'name' => 'P00219']]]);
    $neta = solicitudAprobadaCon(['suggested_suppliers' => ['RUT 77.045.469-7'], 'prices_include_tax' => false]);
    $neta->items()->create(['sort_order' => 1, 'product_service' => 'x', 'quantity' => '1', 'unit' => 'Unidades', 'unit_price' => '1000']);
    exportador()->exportApproved($neta);

    expect($impuestoDeLaLinea()['taxes_id'])->toBe([[6, 0, [2]]]);
});

it('does not add tax to prices that already carry it', function () {
    config(['purchase_requests.odoo.tax_id' => 2]);

    // Sumarle el 19% a un precio que ya lo trae infla la orden un 19%, y el
    // monto sigue pareciendo razonable hasta que llega la factura.
    odooResponde([8, [3528], 219, [['id' => 219, 'name' => 'P00219']]]);
    $bruta = solicitudAprobadaCon(['suggested_suppliers' => ['RUT 77.045.469-7'], 'prices_include_tax' => true]);
    $bruta->items()->create(['sort_order' => 1, 'product_service' => 'x', 'quantity' => '1', 'unit' => 'Unidades', 'unit_price' => '1190']);
    exportador()->exportApproved($bruta);

    Http::assertSent(function ($request) {
        $args = $request['params']['args'] ?? [];
        if (($args[3] ?? null) !== 'purchase.order' || ($args[4] ?? null) !== 'create') {
            return true;
        }

        return ! array_key_exists('taxes_id', $args[5][0]['order_line'][0][2]);
    });
});

it('does not guess the tax when nobody could tell', function () {
    config(['purchase_requests.odoo.tax_id' => 2]);

    // prices_include_tax en null significa «no se pudo determinar», que no es
    // lo mismo que «no lo lleva»: ante la duda no se toca el monto.
    odooResponde([8, [3528], 219, [['id' => 219, 'name' => 'P00219']]]);
    $duda = solicitudAprobadaCon(['suggested_suppliers' => ['RUT 77.045.469-7'], 'prices_include_tax' => null]);
    $duda->items()->create(['sort_order' => 1, 'product_service' => 'x', 'quantity' => '1', 'unit' => 'Unidades', 'unit_price' => '1000']);
    exportador()->exportApproved($duda);

    Http::assertSent(function ($request) {
        $args = $request['params']['args'] ?? [];
        if (($args[3] ?? null) !== 'purchase.order' || ($args[4] ?? null) !== 'create') {
            return true;
        }

        return ! array_key_exists('taxes_id', $args[5][0]['order_line'][0][2]);
    });
});





it('lets a person search Odoo directly, by name or by RUT', function () {
    odooResponde([8, [['id' => 1721, 'name' => 'ARIDOS VICAT SUR SPA', 'vat' => '76893540-8']]]);

    expect(exportador()->buscarProveedores('aridos'))->toHaveCount(1);

    // Escribiendo un RUT se busca por RUT: es exacto y no se confunde con
    // nombres parecidos.
    Http::assertSent(function ($r) {
        $args = $r['params']['args'] ?? [];

        return ($args[3] ?? null) !== 'res.partner' || $args[5][0][0][0] === 'name';
    });
});

it('never writes to the supplier master in Odoo', function () {
    // El maestro de proveedores se administra en Odoo. Este programa lo lee
    // para reconocer a quién se le compra, y nada más: una ficha creada desde
    // aquí, aunque los datos vinieran del documento, es una ficha que nadie
    // revisó en el sistema donde vive.
    $exportador = new ReflectionClass(OdooPurchaseRequestExporter::class);

    foreach ($exportador->getMethods() as $metodo) {
        expect($metodo->getName())->not->toContain('crearProveedor')
            ->and($metodo->getName())->not->toContain('ponerRut');
    }

    odooResponde([8, [], []]);

    $solicitud = solicitudAprobadaCon(['suggested_suppliers' => ['DESCONOCIDA SPA (RUT 77.118.278-K)']]);
    $solicitud->items()->create(['sort_order' => 1, 'product_service' => 'x', 'quantity' => '1', 'unit' => 'Unidades']);

    exportador()->exportApproved($solicitud);

    // Ni create ni write sobre res.partner, con RUT válido o sin él.
    Http::assertSent(function ($request) {
        $args = $request['params']['args'] ?? [];

        return ($args[3] ?? null) !== 'res.partner'
            || ! in_array($args[4] ?? null, ['create', 'write'], true);
    });
});
