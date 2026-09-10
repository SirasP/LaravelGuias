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

it('empties the first order: what he sells moves out, and the rest waits', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = compraRepartida($revisor);

    odooDice([
        7,                                                                  // autenticación
        [['id' => 250, 'state' => 'draft', 'order_line' => [900, 901, 902, 903, 904]]],
        true,                                                               // el unlink de las cinco
        251,                                                                // la orden nueva
        [['id' => 251, 'name' => 'P00251', 'order_line' => [910, 911, 912]]],
    ]);

    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.split', [$solicitud, $lectura]));

    // P00250 se queda sin nada: las tres de A se mudaron a la suya y las otras
    // dos quedan en espera, a la vista y listas para la cotización del segundo.
    Http::assertSent(fn ($r) => ($r['params']['args'][3] ?? null) === 'purchase.order.line'
        && ($r['params']['args'][4] ?? null) === 'unlink'
        && ($r['params']['args'][5][0] ?? null) === [900, 901, 902, 903, 904]);

    expect($solicitud->items()->whereNull('odoo_order_id')->pluck('product_service')->sort()->values()->all())
        ->toBe(['GALON PINTURA', 'ROLLO FILM']);

    // Y las tres de A viven ahora en la orden de A, no en las dos a la vez.
    expect($solicitud->items()->where('odoo_order_id', 251)->count())->toBe(3)
        ->and($solicitud->items()->where('odoo_order_id', 250)->count())->toBe(0);

    // Un reparto no son alternativas: en Odoo, confirmar una alternativa te
    // ofrece anular las otras, y ahí anularías la compra del otro proveedor.
    Http::assertNotSent(fn ($r) => ($r['params']['args'][3] ?? null) === 'purchase.order.group');
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

it('stops proposing a pairing that somebody rejected', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = compraRepartida($revisor);
    $cinta = $solicitud->items()->where('product_service', 'CINTA PELIGRO')->firstOrFail();

    // La propuesta sale de un cálculo que se rehace en cada carga: sin dejar
    // el rechazo escrito, «CINTA PELIGRO» volvía a aparecer emparejada con lo
    // que no era a la siguiente visita.
    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.reject', [$solicitud, $lectura]), [
            'item_id' => $cinta->getKey(),
            'line_index' => 2,
        ])
        ->assertSessionHas('success');

    $lectura->refresh();

    expect($lectura->parejasRechazadas())->toBe([[2, $cinta->getKey()]]);

    $r = app(App\Services\PurchaseRequests\Quotes\QuotationComparison::class)->comparar(
        $solicitud->fresh(),
        array_values($lectura->extracted['items']),
        null,
        $lectura->parejasConfirmadas(),
        $lectura->parejasRechazadas(),
    );

    // Esa partida queda sin cotizar y esa pareja no vuelve nunca.
    //
    // El renglón puede acabar propuesto contra otra partida: el texto no
    // distingue —«CINTA PELIGRO» contra «GALON PINTURA» da 0,2192 y «Filtro 3M
    // 6003» contra «CARTUCHO AIR F600MP3», que sí era correcto, da 0,2016—.
    // Por eso son propuestas y no hechos, y por eso el «No es» se puede apretar
    // las veces que haga falta: cada rechazo queda escrito.
    expect($r->filas[2]->estado)->toBe('sin_cotizar')
        ->and($r->filas[2]->cotizada)->toBeNull();
});

it('lets a request be detached from its Odoo order, without touching Odoo', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud] = compraRepartida($revisor);

    config(['purchase_requests.odoo.enabled' => true]);
    Http::preventStrayRequests();

    $this->actingAs($revisor)
        ->post(route('purchase_requests.odoo.detach', $solicitud))
        ->assertSessionHas('success', fn ($m) => str_contains((string) $m, 'sigue existiendo en Odoo'));

    $solicitud->refresh();

    // La solicitud vuelve a estar libre para repartirse bien...
    expect($solicitud->odoo_order_id)->toBeNull()
        ->and($solicitud->items()->whereNotNull('odoo_order_id')->count())->toBe(0)
        // ...y a Odoo no se le dijo ni una palabra: la orden de allá se anula
        // allá, que es donde cuelgan sus recepciones y sus facturas.
        ->and($solicitud->events()->where('comment', 'like', '%sigue en Odoo%')->exists())->toBeTrue();
});

it('lets you say who signs a quotation that came with no tax id', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = compraRepartida($revisor);

    // Una cotización dictada a mano no trae RUT, y el buscador de la tarjeta
    // de Odoo resuelve el proveedor de la SOLICITUD, que es otra cosa: una
    // solicitud puede comprarse a dos. Sin esto, «comprarle a este proveedor»
    // no tenía a nombre de quién crear la orden y no había forma de decirlo.
    $lectura->forceFill(['supplier_tax_id' => null, 'supplier_name' => 'HARCHA'])->save();

    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.supplier', [$solicitud, $lectura]), [
            'odoo_partner_id' => 4120,
            'name' => 'COMERCIAL HARCHA LIMITADA',
            'vat' => '77.118.278-K',
        ])
        ->assertSessionHas('success');

    $lectura->refresh();

    expect($lectura->supplier_tax_id)->toBe('77118278-K')
        ->and($lectura->supplier_name)->toBe('COMERCIAL HARCHA LIMITADA')
        // Y queda aprendido: no se vuelve a preguntar por este proveedor.
        ->and(App\Models\PurchaseSupplier::where('tax_id', '77118278-K')->value('odoo_partner_id'))
        ->toBe(4120);
});

it('creates the split order already carrying the quoted names and prices', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = compraRepartida($revisor);

    // Sin orden previa: la de este proveedor es la primera.
    $solicitud->forceFill(['odoo_order_id' => null, 'odoo_reference' => null])->save();
    $solicitud->items()->update(['odoo_order_id' => null, 'odoo_line_id' => null]);

    odooDice([
        7,      // autenticación
        [],     // productos que Odoo confirma: ninguno
        251,    // la orden creada
        [['id' => 251, 'name' => 'P00251', 'order_line' => [950, 951, 952]]],
    ]);

    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.split', [$solicitud->fresh(), $lectura]))
        ->assertSessionHas('success');

    // Nace con el precio y el nombre de la cotización, no en cero y con el
    // nombre genérico esperando a que alguien la corrija después.
    Http::assertSent(function ($r) {
        if (($r['params']['args'][4] ?? null) !== 'create') {
            return false;
        }

        $lineas = collect($r['params']['args'][5][0]['order_line'] ?? [])->map(fn ($l) => $l[2]);

        return $lineas->every(fn (array $l): bool => (float) $l['price_unit'] === 1000.0)
            && $lineas->pluck('name')->contains('SPRAY BLANCO');
    });
});

it('finds a contact that Odoo does not yet call a supplier', function () {
    odooDice([
        7,
        [['id' => 3597, 'name' => 'MAXSERVICE SPA', 'vat' => '76821142-6', 'supplier_rank' => 0]],
    ]);

    // Odoo pone supplier_rank en cuanto se le confirma una compra, así que
    // exigirlo escondía justo a los que hace falta buscar: los nuevos.
    // MAXSERVICE SPA estaba en Odoo con su RUT y la búsqueda juraba que no.
    $encontrados = app(PurchaseRequestExporter::class)->buscarProveedores('maxservice');

    expect($encontrados)->toHaveCount(1)
        ->and($encontrados[0]['name'])->toBe('MAXSERVICE SPA')
        ->and($encontrados[0]['es_proveedor'])->toBeFalse();
});

it('unsticks a request whose Odoo order was deleted over there', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = compraRepartida($revisor);

    odooDice([
        7,
        [],     // la orden 250 ya no existe en Odoo: read no devuelve nada
        251,    // la orden nueva
        [['id' => 251, 'name' => 'P00251', 'order_line' => [950, 951, 952]]],
    ]);

    // Borrar la orden allá dejaba a la solicitud atrapada: el programa creía
    // que había una orden que respetar y decía «ya no está en borrador».
    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.split', [$solicitud, $lectura]))
        ->assertSessionHas('success');

    expect($solicitud->fresh()->odoo_reference)->toBe('P00251');
});

/**
 * La SC-2026-000037 tenía su orden P00246 creada antes de que se guardara el
 * número de línea de cada partida. Recortarla habría dejado la lista de «las
 * que se quedan» vacía, y el recorte le habría borrado hasta la última línea
 * a una orden que nadie pidió vaciar.
 */
it('does not empty an order whose lines it cannot identify', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = compraRepartida($revisor);

    $solicitud->items()->update(['odoo_order_id' => null, 'odoo_line_id' => null]);

    odooDice([
        7,                                                                  // autenticación
        251,                                                                // la orden nueva
        [['id' => 251, 'name' => 'P00251', 'order_line' => [910, 911, 912]]],
        [['id' => 250, 'order_line' => [900, 901]]],                        // ¿le queda algo a la vieja?
        [],                                                                 // grupo existente
        7788,                                                               // el grupo nuevo
        true,                                                               // las dos apuntan al grupo
    ]);

    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.split', [$solicitud, $lectura]));

    // A P00250 no se le quita ni una línea.
    Http::assertNotSent(fn ($r) => ($r['params']['args'][4] ?? null) === 'unlink');

    // Y como allá sigue estando lo que acabamos de pedirle a este proveedor,
    // las dos se pelean lo mismo: eso sí son alternativas.
    Http::assertSent(fn ($r) => ($r['params']['args'][3] ?? null) === 'purchase.order'
        && ($r['params']['args'][4] ?? null) === 'write'
        && ($r['params']['args'][5][1]['purchase_group_id'] ?? null) === 7788);

    // Y la orden anterior sigue siendo la de la solicitud: no se perdió.
    expect((int) $solicitud->fresh()->odoo_order_id)->toBe(250);
});

/**
 * Dos proveedores cotizando la única partida de la SC-2026-000037: el botón
 * pedía que la cotización cubriera menos de lo pedido, así que desaparecía
 * justo en el caso donde más falta hace, que es cuando hay que elegir a uno.
 */
it('offers to buy from a supplier that quoted every single line', function () {
    $revisor = User::factory()->admin()->create();

    odooDice([]);

    $solicitud = PurchaseRequest::factory()->forUser($revisor)->approved()->create([
        'odoo_order_id' => 246, 'odoo_reference' => 'P00246', 'odoo_exported_at' => now(),
    ]);
    $solicitud->items()->create([
        'sort_order' => 1, 'product_service' => 'Casco seguridad Amarillo con Barbiquejo',
        'quantity' => 5, 'unit' => 'Unidades',
    ]);

    PurchaseSupplier::query()->create([
        'company_code' => 'EHE', 'name' => 'MAXSERVICE SPA',
        'tax_id' => '76821142-6', 'odoo_partner_id' => 3597,
    ]);

    PurchaseRequestIngestion::query()->create([
        'user_id' => $revisor->getKey(), 'uploader_name_snapshot' => $revisor->name,
        'compared_request_id' => $solicitud->getKey(), 'disk' => 'local',
        'path' => 'n.pdf', 'original_name' => 'NV_0000679894.pdf', 'mime_type' => 'application/pdf',
        'size' => 10, 'sha256' => str_repeat('b', 64),
        'status' => PurchaseRequestIngestion::COMPLETED,
        'supplier_tax_id' => '76821142-6', 'supplier_name' => 'MAXSERVICE SPA',
        'extracted' => ['items' => [[
            'product_service' => 'Casco seguridad Amarillo con Barbiquejo',
            'specification' => 'CASCO ECO CONSTRUCTOR',
            'quantity' => '5', 'unit' => 'Unidades', 'unit_price' => '1695',
        ]]],
    ]);

    $this->actingAs($revisor)
        ->get(route('purchase_requests.show', $solicitud))
        ->assertOk()
        ->assertSee('Comprarle a MAXSERVICE SPA')
        ->assertSee('Comprarle a este proveedor la partida');
});

/**
 * El segundo proveedor del reparto no le toca nada al primero.
 *
 * Repartir dos veces era el momento peligroso: la orden del primero era ahora
 * «la orden anterior», y quitarle lo que el segundo no cotizó le habría
 * desarmado la compra al primero.
 */
it('leaves the first supplier order alone when the second one is split off', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = compraRepartida($revisor);

    // Como queda la solicitud después del primer reparto: P00250 vacía y
    // todavía es la de la solicitud, lo de A en P00251, y dos en espera.
    $solicitud->items()->whereIn('product_service', ['SPRAY BLANCO', 'GRASA LIQUIDA', 'CINTA PELIGRO'])
        ->update(['odoo_order_id' => 251, 'odoo_line_id' => 910]);
    $solicitud->items()->whereIn('product_service', ['ROLLO FILM', 'GALON PINTURA'])
        ->update(['odoo_order_id' => null, 'odoo_line_id' => null]);

    PurchaseSupplier::query()->create([
        'company_code' => 'EHE', 'name' => 'PROVEEDOR B',
        'tax_id' => '77071100-2', 'odoo_partner_id' => 1732,
    ]);

    $segunda = PurchaseRequestIngestion::query()->create([
        'user_id' => $revisor->getKey(), 'uploader_name_snapshot' => $revisor->name,
        'compared_request_id' => $solicitud->getKey(), 'disk' => 'local',
        'path' => 'b.pdf', 'original_name' => 'cot B.pdf', 'mime_type' => 'application/pdf',
        'size' => 10, 'sha256' => str_repeat('c', 64),
        'status' => PurchaseRequestIngestion::COMPLETED,
        'supplier_tax_id' => '77071100-2', 'supplier_name' => 'PROVEEDOR B',
        'extracted' => ['items' => array_map(fn (string $n): array => [
            'product_service' => $n, 'specification' => null,
            'quantity' => '1', 'unit' => 'Unidades', 'unit_price' => '2000',
        ], ['ROLLO FILM', 'GALON PINTURA'])],
    ]);

    odooDice([
        7,                                                                  // autenticación
        252,                                                                // la orden de B
        [['id' => 252, 'name' => 'P00252', 'order_line' => [920, 921]]],
        [['id' => 250, 'order_line' => []]],                                // la vieja quedó vacía
    ]);

    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.split', [$solicitud, $segunda]));

    // Ni una línea quitada en ningún lado, y menos en la orden del primero.
    Http::assertNotSent(fn ($r) => ($r['params']['args'][4] ?? null) === 'unlink');

    // Las tres del primero siguen donde estaban.
    expect($solicitud->items()->where('odoo_order_id', 251)->count())->toBe(3)
        ->and($solicitud->items()->where('odoo_order_id', 252)->count())->toBe(2)
        ->and($solicitud->items()->whereNull('odoo_order_id')->count())->toBe(0);

    // Y una orden vacía no se pelea nada con nadie: no son alternativas.
    Http::assertNotSent(fn ($r) => ($r['params']['args'][3] ?? null) === 'purchase.order.group');
});
