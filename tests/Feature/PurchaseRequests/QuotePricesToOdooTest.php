<?php

use App\Models\PurchaseProductLink;
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

/** El proveedor de todas estas cotizaciones, tal como está en Odoo. */
const PROVEEDOR = 1833;

/**
 * Un Odoo de mentira que contesta según lo que se le pregunta, no en un orden
 * fijo: llevar una cotización lee órdenes, alternativas y líneas, y el orden
 * de esas lecturas no es lo que se prueba.
 *
 * @param  list<array<string, mixed>>  $ordenes
 * @param  list<array<string, mixed>>  $lineas
 * @param  list<array<string, mixed>>  $productos  lo que Odoo tiene en el catálogo
 */
function odooConOrdenes(array $ordenes, array $lineas, array $productos = []): void
{
    config([
        'purchase_requests.odoo.enabled' => true,
        'purchase_requests.odoo.url' => 'https://odoo.ejemplo.cl',
        'purchase_requests.odoo.db' => 'prueba',
        'purchase_requests.odoo.user' => 'quien@ejemplo.cl',
        'purchase_requests.odoo.password' => 'secreta',
    ]);

    $porId = fn (array $filas): array => array_column($filas, null, 'id');
    $ordenes = $porId($ordenes);
    $lineas = $porId(array_map(fn (array $l): array => $l + ['product_qty' => 1, 'price_unit' => 0, 'name' => '', 'product_uom' => [1, 'Units'], 'display_type' => false], $lineas));
    $productos = $porId($productos);

    Http::preventStrayRequests();
    Http::fake(['*/jsonrpc' => function ($request) use ($ordenes, $lineas, &$productos) {
        $p = $request['params'];
        $r = fn ($result) => Http::response(['jsonrpc' => '2.0', 'result' => $result]);

        if ($p['service'] === 'common') {
            return $r(7);
        }

        [, , , $modelo, $metodo, $args] = $p['args'];

        return $r(match ("$modelo.$metodo") {
            'purchase.order.search_read' => array_values($ordenes),
            'purchase.order.read' => array_values(array_intersect_key($ordenes, array_flip($args[0]))),
            'purchase.order.line.read' => array_values(array_intersect_key($lineas, array_flip($args[0]))),
            'purchase.order.line.search_read' => [['id' => 1, 'taxes_id' => [5], 'date_planned' => '2026-11-01 00:00:00']],
            'purchase.order.line.write' => true,
            'purchase.order.line.create' => 5000,
            'product.product.search_read' => array_values($productos),
            'product.product.create' => 9001,
            'product.product.read' => [['id' => 9001, 'name' => $args[0][0] === 9001 ? 'creado' : '', 'default_code' => false,
                'barcode' => false, 'uom_id' => [1, 'Units'], 'type' => 'consu', 'is_storable' => true,
                'purchase_ok' => true, 'active' => true]],
            default => throw new RuntimeException("Odoo de prueba no sabe contestar $modelo.$metodo"),
        });
    }]);

    app()->bind(PurchaseRequestExporter::class, fn () => new OdooPurchaseRequestExporter(new OdooClient(
        'https://odoo.ejemplo.cl', 'prueba', 'quien@ejemplo.cl', 'secreta',
    )));
}

/** @param array<string, mixed> $extra */
function orden(int $id, string $estado = 'draft', array $lineas = [], int $proveedor = PROVEEDOR, array $extra = []): array
{
    return ['id' => $id, 'name' => sprintf('P%05d', $id), 'state' => $estado, 'partner_id' => [$proveedor, 'Proveedor '.$proveedor],
        'purchase_group_id' => false, 'order_line' => $lineas, 'origin' => 'SC-2026-000001', ...$extra];
}

/** ¿Se escribió eso en esa línea? params.args = [db, uid, clave, modelo, método, args, kwargs]. */
function seEscribio(int $linea, callable $valores): bool
{
    $hubo = false;

    Http::assertSent(function ($r) use ($linea, $valores, &$hubo) {
        $args = $r['params']['args'] ?? [];

        if (($args[3] ?? null) === 'purchase.order.line' && ($args[4] ?? null) === 'write'
            && ($args[5][0] ?? null) === [$linea] && $valores($args[5][1] ?? [])) {
            $hubo = true;
        }

        return true;
    });

    return $hubo;
}

function nadaSeEscribio(): void
{
    Http::assertNotSent(fn ($r) => in_array($r['params']['args'][4] ?? null, ['write', 'create'], true));
}

/**
 * Una solicitud con su cotización leída.
 *
 * @param  list<array<string, mixed>>  $partidas
 * @param  list<array<string, mixed>>  $renglones
 * @param  array<int, int>|null  $parejas  renglón => número de partida (1, 2…); null = todas por posición
 */
function cotizacion(User $owner, array $partidas, array $renglones, ?array $parejas = null, int $orden = 240): array
{
    PurchaseSupplier::query()->firstOrCreate(['tax_id' => '82392600-6'], [
        'company_code' => 'EHE', 'name' => 'COOPRINSEM', 'odoo_partner_id' => PROVEEDOR, 'source' => 'odoo', 'is_active' => true,
    ]);

    $solicitud = PurchaseRequest::factory()->forUser($owner)->approved()->create([
        'odoo_order_id' => $orden, 'odoo_reference' => sprintf('P%05d', $orden), 'odoo_exported_at' => now(),
    ]);

    $items = [];

    foreach ($partidas as $i => $p) {
        $items[$i + 1] = $solicitud->items()->create($p + ['sort_order' => $i + 1, 'unit' => 'Unidades', 'unit_price' => null]);
    }

    $parejas ??= array_combine(array_keys($renglones), range(1, count($renglones)));

    $lectura = PurchaseRequestIngestion::query()->create([
        'user_id' => $owner->getKey(), 'uploader_name_snapshot' => $owner->name,
        'compared_request_id' => $solicitud->getKey(), 'disk' => 'local',
        'path' => 'c.pdf', 'original_name' => 'COT. PPTO 00357.pdf',
        'mime_type' => 'application/pdf', 'size' => 10, 'sha256' => str_repeat('a', 64),
        'status' => PurchaseRequestIngestion::NEEDS_REVIEW,
        'supplier_name' => 'COOPERATIVA AGRICOLA Y DE SERVICIOS LTDA', 'supplier_tax_id' => '82392600-6',
        'extracted' => ['items' => array_map(fn (array $r): array => $r + ['specification' => null, 'unit' => 'Unidades'], $renglones)],
        'confirmed_pairings' => array_map(fn (int $n): int => $items[$n]->getKey(), array_filter($parejas)),
    ]);

    return [$solicitud->fresh(), $lectura, $items];
}

/** La de siempre: una curva, cotizada con su nombre real y un producto que alguien enseñó. */
function curva(User $owner, ?int $precio = 65174): array
{
    foreach (['curva de 200 mm x 90', 'CURVA PVC HIDRAUL. 200x90 CEMENTAR'] as $texto) {
        PurchaseProductLink::query()->create([
            'company_code' => 'EHE', 'odoo_partner_id' => null, 'source_text' => $texto,
            'normalized_text' => PurchaseProductLink::normalizar($texto),
            'odoo_product_id' => 8723, 'odoo_product_name' => 'CURVA PVC HIDRAUL. 200x90 CEMENTAR',
            'source' => PurchaseProductLink::CONFIRMADO,
        ]);
    }

    return cotizacion($owner,
        [['product_service' => 'curva de 200 mm x 90', 'quantity' => 2]],
        [['product_service' => 'CURVA PVC HIDRAUL. 200x90 CEMENTAR', 'quantity' => '2.00', 'unit_price' => $precio]],
    );
}

it('writes the quoted price on the Odoo line that carries the same product', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = curva($revisor);

    odooConOrdenes([orden(240, 'draft', [811])], [['id' => 811, 'order_id' => [240, 'P00240'], 'product_id' => [8723, 'CURVA PVC'], 'product_qty' => 2]]);

    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))
        ->assertSessionHas('success', fn (string $m) => str_contains($m, 'P00240'));

    expect(seEscribio(811, fn ($v) => ($v['price_unit'] ?? null) === 65174.0))->toBeTrue();
});

it('writes on a quotation already sent to the supplier', function () {
    // «Cotización enviada» sigue siendo cotización: es justo cuando llega la
    // respuesta del proveedor, y la P00357 estaba así.
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = curva($revisor);

    odooConOrdenes([orden(240, 'sent', [811])], [['id' => 811, 'order_id' => [240, 'P00240'], 'product_id' => [8723, 'CURVA PVC'], 'product_qty' => 2]]);

    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))
        ->assertSessionHas('success');

    expect(seEscribio(811, fn ($v) => ($v['price_unit'] ?? null) === 65174.0))->toBeTrue();
});

it('refuses to touch an order that Odoo already confirmed', function () {
    // Confirmada hay recepciones y facturas detrás: cambiarle el precio por
    // detrás sería mover algo que allá ya se dio por cerrado.
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = curva($revisor);

    odooConOrdenes([orden(240, 'purchase', [811])], [['id' => 811, 'order_id' => [240, 'P00240'], 'product_id' => [8723, 'CURVA PVC']]]);

    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'P00240') && str_contains($m, 'no se tocan'));

    nadaSeEscribio();
});

it('does not rewrite a line that already says the same', function () {
    // Escribir el mismo número ensucia el historial de Odoo sin cambiar nada.
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = curva($revisor);

    odooConOrdenes([orden(240, 'draft', [811])], [['id' => 811, 'order_id' => [240, 'P00240'], 'product_id' => [8723, 'CURVA PVC'],
        'product_qty' => 2, 'price_unit' => 65174, 'name' => 'CURVA PVC HIDRAUL. 200x90 CEMENTAR']]);

    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))
        ->assertSessionHas('success', fn (string $m) => str_contains($m, 'ya decía lo mismo'));

    nadaSeEscribio();
});

it('carries the real name even when the quotation brought no price', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = curva($revisor, null);

    odooConOrdenes([orden(240, 'draft', [811])], [['id' => 811, 'order_id' => [240, 'P00240'], 'product_id' => [8723, 'CURVA PVC'],
        'product_qty' => 2, 'name' => 'curva 200 90']]);

    $this->actingAs($revisor)->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))->assertSessionHas('success');

    expect(seEscribio(811, fn ($v) => ($v['name'] ?? null) === 'CURVA PVC HIDRAUL. 200x90 CEMENTAR'
        && ! array_key_exists('price_unit', $v)))->toBeTrue();
});

it('keeps sending to Odoo for whoever is allowed to', function () {
    $dueno = User::factory()->create();
    [$solicitud, $lectura] = curva($dueno);

    $this->actingAs($dueno)->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))->assertForbidden();
    $this->actingAs($dueno)->get(route('purchase_requests.quotes.prices.preview', [$solicitud, $lectura]))->assertForbidden();
});

it('warns that the real names must travel before Odoo closes the order', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud] = curva($revisor);

    // Este programa aprende de cada cotización; Odoo no.
    $this->actingAs($revisor)
        ->get(route('purchase_requests.show', $solicitud))
        ->assertOk()
        ->assertSee('trae el nombre real del proveedor', false)
        ->assertSee('Revisar y llevar a Odoo');
});

it('writes in the order of the quoting supplier, not in the one Laravel created', function () {
    // La SC-2026-000103: Laravel creó la P00357 a nombre de Cooprinsem y Luis
    // cotizó en Odoo a tres más, con las mismas líneas. La cotización de
    // Entre Riegos va a la de Entre Riegos.
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura, $items] = cotizacion($revisor,
        [['product_service' => 'válvula mariposa con volante 8"', 'quantity' => 1]],
        [['product_service' => 'VALVULA MARIPOSA 8" C/VOLANTE', 'quantity' => '1', 'unit_price' => '150000']],
        orden: 357,
    );
    $items[1]->forceFill(['odoo_order_id' => 357, 'odoo_line_id' => 2395])->save();
    PurchaseSupplier::query()->where('tax_id', '82392600-6')->update(['odoo_partner_id' => 3476]);

    $grupo = ['purchase_group_id' => [33, 'grupo']];
    odooConOrdenes(
        [orden(357, 'sent', [2395], PROVEEDOR, $grupo), orden(364, 'sent', [2433], 3476, $grupo)],
        [
            ['id' => 2395, 'order_id' => [357, 'P00357'], 'product_id' => [8947, 'Válvula mariposa']],
            ['id' => 2433, 'order_id' => [364, 'P00364'], 'product_id' => [8947, 'Válvula mariposa']],
        ],
    );

    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))
        ->assertSessionHas('success', fn (string $m) => str_contains($m, 'P00364'));

    expect(seEscribio(2433, fn ($v) => ($v['price_unit'] ?? null) === 150000.0))->toBeTrue()
        ->and(seEscribio(2395, fn ($v) => true))->toBeFalse();
});

it('writes nothing when the quoting supplier has no order of its own', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = curva($revisor);

    odooConOrdenes([orden(240, 'draft', [811], 9999)], [['id' => 811, 'order_id' => [240, 'P00240'], 'product_id' => [8723, 'CURVA PVC']]]);

    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'no tiene orden en Odoo'));

    nadaSeEscribio();
});

it('finds the line it travelled to, whatever it is called in Odoo now', function () {
    // Allá se lee «Válvula de pie con canastillo de 10"» y aquí «válvula de
    // pie con canastillo 10"». La línea guardada al viajar no depende de eso.
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura, $items] = cotizacion($revisor,
        [['product_service' => 'válvula de pie con canastillo 10"', 'quantity' => 1]],
        [['product_service' => 'VALVULA RET. HDPE 10"', 'quantity' => '1', 'unit_price' => '464750']],
    );
    $items[1]->forceFill(['odoo_order_id' => 240, 'odoo_line_id' => 2384])->save();

    odooConOrdenes([orden(240, 'sent', [2384])], [['id' => 2384, 'order_id' => [240, 'P00240'],
        'product_id' => [8936, 'Válvula de pie'], 'name' => 'Válvula de pie con canastillo de 10"']]);

    $this->actingAs($revisor)->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))->assertSessionHas('success');

    expect(seEscribio(2384, fn ($v) => ($v['price_unit'] ?? null) === 464750.0 && ($v['name'] ?? null) === 'VALVULA RET. HDPE 10"'))->toBeTrue();
});

it('keeps the unit of the order when the quotation comes by the metre', function () {
    // Se pidieron 2 tubos y cotizan 12 metros a 56.446. Escribir 12 en una
    // línea que mide en unidades serían 12 tubos: la orden sigue en 2, a 6
    // metros cada uno, con el mismo total.
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura, $items] = cotizacion($revisor,
        [['product_service' => 'tubos fierro 10" /5mm', 'quantity' => 2]],
        [['product_service' => 'CAÑERIA ACERO 10" 5 MM - MT SOLDAR', 'quantity' => '12', 'unit' => 'Metros', 'unit_price' => '56446']],
    );
    $items[1]->forceFill(['odoo_order_id' => 240, 'odoo_line_id' => 2389])->save();

    odooConOrdenes([orden(240, 'sent', [2389])], [['id' => 2389, 'order_id' => [240, 'P00240'], 'product_id' => [8941, 'Tubos'], 'product_qty' => 2]]);

    $this->actingAs($revisor)->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))->assertSessionHas('success');

    expect(seEscribio(2389, fn ($v) => ($v['price_unit'] ?? null) === 338676.0
        && ! array_key_exists('product_qty', $v)
        && str_contains((string) ($v['name'] ?? ''), '12 m cotizados')))->toBeTrue();
});

it('carries the metres as they come when the person asks for it', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura, $items] = cotizacion($revisor,
        [['product_service' => 'tubos fierro 10" /5mm', 'quantity' => 2]],
        [['product_service' => 'CAÑERIA ACERO 10" 5 MM - MT SOLDAR', 'quantity' => '12', 'unit' => 'Metros', 'unit_price' => '56446']],
    );
    $items[1]->forceFill(['odoo_order_id' => 240, 'odoo_line_id' => 2389])->save();

    odooConOrdenes([orden(240, 'sent', [2389])], [['id' => 2389, 'order_id' => [240, 'P00240'], 'product_id' => [8941, 'Tubos'], 'product_qty' => 2]]);

    $this->actingAs($revisor)->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]), [
        'vista_previa' => 1, 'filas' => [$items[1]->getKey()], 'sin_convertir' => [$items[1]->getKey()],
    ])->assertSessionHas('success');

    expect(seEscribio(2389, fn ($v) => ($v['price_unit'] ?? null) === 56446.0 && ($v['product_qty'] ?? null) === 12.0))->toBeTrue();
});

it('puts the quoted quantity on the order when it is the same kind of unit', function () {
    // Pidieron diez y cotizaron cinco: la orden dice cinco, que es lo que se
    // va a recibir y facturar. «3,20MT» es el largo de la tabla, no la unidad.
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = cotizacion($revisor,
        [['product_service' => 'Pino verde 1x4', 'quantity' => 10]],
        [['product_service' => 'MADERA PINO BRUTO 1 X 4 X 3,20MT', 'quantity' => '5', 'unit_price' => '2990']],
    );

    odooConOrdenes([orden(240, 'draft', [3001])], [['id' => 3001, 'order_id' => [240, 'P00240'], 'product_id' => false,
        'product_qty' => 10, 'name' => 'Pino verde 1x4']], [['id' => 8767, 'name' => 'MADERA PINO BRUTO 1 X 4 X 3,20MT', 'uom_id' => [1, 'Units']]]);

    $this->actingAs($revisor)->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))->assertSessionHas('success');

    expect(seEscribio(3001, fn ($v) => ($v['product_qty'] ?? null) === 5.0 && ($v['price_unit'] ?? null) === 2990.0))->toBeTrue();
});

it('leaves out a pair whose measures do not match unless someone ticks it', function () {
    // Cooprinsem: la empaquetadura de 5" quedó con la de 75 MM. Con 5" son
    // 127 mm, así que es la de 125; ésa no se escribe sola.
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura, $items] = cotizacion($revisor,
        [['product_service' => 'empaquetadura plana 5"', 'quantity' => 1]],
        [['product_service' => 'EMPAQUETADURA GOMA 75 MM', 'quantity' => '1', 'unit_price' => '804']],
    );
    $items[1]->forceFill(['odoo_order_id' => 240, 'odoo_line_id' => 2391])->save();

    odooConOrdenes([orden(240, 'sent', [2391])], [['id' => 2391, 'order_id' => [240, 'P00240'], 'product_id' => [8943, 'Empaquetadura']]]);

    $this->actingAs($revisor)
        ->get(route('purchase_requests.quotes.prices.preview', [$solicitud, $lectura]))
        ->assertOk()
        ->assertSee('Las medidas no calzan');

    $this->actingAs($revisor)->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))->assertSessionHas('success');

    nadaSeEscribio();
});

it('adds what the supplier quoted apart only when it is ticked', function () {
    // La válvula de pie llegó como válvula de retención más canastillo.
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura, $items] = cotizacion($revisor,
        [['product_service' => 'válvula de pie con canastillo 10"', 'quantity' => 1]],
        [
            ['product_service' => 'VALVULA RET. HDPE 10"', 'quantity' => '1', 'unit_price' => '464750'],
            ['product_service' => 'CANASTILLO SUCCION HDPE 10"', 'quantity' => '1', 'unit_price' => '279625'],
        ],
        [0 => 1],
    );
    $items[1]->forceFill(['odoo_order_id' => 240, 'odoo_line_id' => 2384])->save();

    odooConOrdenes([orden(240, 'sent', [2384])], [['id' => 2384, 'order_id' => [240, 'P00240'], 'product_id' => [8936, 'Válvula de pie']]],
        [['id' => 8990, 'name' => 'CANASTILLO SUCCION HDPE 10"', 'uom_id' => [1, 'Units']]]);

    $this->actingAs($revisor)
        ->get(route('purchase_requests.quotes.prices.preview', [$solicitud, $lectura]))
        ->assertOk()
        ->assertSee('CANASTILLO SUCCION HDPE 10&quot;', false);

    $this->actingAs($revisor)->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]), [
        'vista_previa' => 1, 'filas' => [$items[1]->getKey()], 'agregar' => [1],
    ])->assertSessionHas('success', fn (string $m) => str_contains($m, 'agregó 1 línea nueva'));

    Http::assertSent(fn ($r) => ($r['params']['args'][3] ?? null) === 'purchase.order.line'
        && ($r['params']['args'][4] ?? null) === 'create'
        && ($r['params']['args'][5][0]['order_id'] ?? null) === 240
        && ($r['params']['args'][5][0]['product_id'] ?? null) === 8990
        && ($r['params']['args'][5][0]['price_unit'] ?? null) === 279625.0
        && ($r['params']['args'][5][0]['taxes_id'] ?? null) === [[6, 0, [5]]]);
    Http::assertNotSent(fn ($r) => ($r['params']['args'][3] ?? null) === 'product.product'
        && ($r['params']['args'][4] ?? null) === 'create');
});

it('writes only the rows left ticked in the preview', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura, $items] = cotizacion($revisor,
        [['product_service' => 'tuercas ¾" galvanizadas', 'quantity' => 116], ['product_service' => 'golillas planas galvanizadas', 'quantity' => 116]],
        [['product_service' => 'TUERCA HEXAGONAL ZINC 3/4"', 'quantity' => '116', 'unit_price' => '316'],
            ['product_service' => 'GOLILLA PLANA ZINC 3/4"', 'quantity' => '116', 'unit_price' => '97']],
    );
    $items[1]->forceFill(['odoo_order_id' => 240, 'odoo_line_id' => 2403])->save();
    $items[2]->forceFill(['odoo_order_id' => 240, 'odoo_line_id' => 2404])->save();

    odooConOrdenes([orden(240, 'sent', [2403, 2404])], [
        ['id' => 2403, 'order_id' => [240, 'P00240'], 'product_id' => [8955, 'Tuercas'], 'product_qty' => 116],
        ['id' => 2404, 'order_id' => [240, 'P00240'], 'product_id' => [8956, 'Golillas'], 'product_qty' => 116],
    ]);

    $this->actingAs($revisor)->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]), [
        'vista_previa' => 1, 'filas' => [$items[2]->getKey()],
    ])->assertSessionHas('success');

    expect(seEscribio(2404, fn ($v) => ($v['price_unit'] ?? null) === 97.0))->toBeTrue()
        ->and(seEscribio(2403, fn ($v) => true))->toBeFalse();
});

/** Una solicitud cuya línea viajó a Odoo como texto, sin producto. */
function corchetera(User $owner): array
{
    return cotizacion($owner,
        [['product_service' => 'Corchetera', 'quantity' => 2]],
        [['product_service' => 'CORCHETERA PLASTICA 20 HJ 24081 AUCA TOR111', 'quantity' => '2,00', 'unit_price' => '4958']],
        orden: 243,
    );
}

it('links a product that Odoo already had, instead of creating a duplicate', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = corchetera($revisor);

    // Odoo sí lo tiene: lo creó alguien hoy y la copia local, que se
    // sincroniza de noche, todavía no lo sabe.
    odooConOrdenes([orden(243, 'draft', [1969])], [['id' => 1969, 'order_id' => [243, 'P00243'], 'product_id' => false, 'name' => 'Corchetera', 'product_qty' => 2]],
        [['id' => 8724, 'name' => 'CORCHETERA PLASTICA 20 HJ 24081 AUCA TOR111', 'default_code' => false, 'barcode' => false,
            'uom_id' => [1, 'Units'], 'type' => 'consu', 'is_storable' => true, 'purchase_ok' => true, 'active' => true]]);

    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))
        ->assertSessionHas('success', fn ($m) => ! str_contains((string) $m, 'dio de alta'));

    Http::assertNotSent(fn ($r) => ($r['params']['args'][4] ?? null) === 'create');
    expect(seEscribio(1969, fn ($v) => ($v['product_id'] ?? null) === 8724 && ($v['product_uom'] ?? null) === 1))->toBeTrue()
        ->and(App\Models\OdooProduct::where('odoo_id', 8724)->value('name'))->toBe('CORCHETERA PLASTICA 20 HJ 24081 AUCA TOR111');
});

it('creates the product Odoo really does not have, in Units and tracking inventory', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = corchetera($revisor);

    odooConOrdenes([orden(243, 'draft', [1969])], [['id' => 1969, 'order_id' => [243, 'P00243'], 'product_id' => false, 'name' => 'Corchetera', 'product_qty' => 2]]);

    $this->actingAs($revisor)
        ->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))
        ->assertSessionHas('success', fn ($m) => str_contains((string) $m, 'dio de alta'));

    Http::assertSent(fn ($r) => ($r['params']['args'][3] ?? null) === 'product.product'
        && ($r['params']['args'][4] ?? null) === 'create'
        && ($r['params']['args'][5][0]['name'] ?? null) === 'CORCHETERA PLASTICA 20 HJ 24081 AUCA TOR111'
        && ($r['params']['args'][5][0]['is_storable'] ?? null) === true
        && ($r['params']['args'][5][0]['uom_id'] ?? null) === 1);
});

it('creates nothing at all when the switch is off', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = corchetera($revisor);
    config(['purchase_requests.odoo.create_missing_products' => false]);

    odooConOrdenes([orden(243, 'draft', [1969])], [['id' => 1969, 'order_id' => [243, 'P00243'], 'product_id' => false, 'name' => 'Corchetera', 'product_qty' => 2]]);
    config(['purchase_requests.odoo.create_missing_products' => false]);

    // Sin producto, la línea recibe igual su precio y su nombre: apagar el
    // interruptor frena lo permanente, no todo lo demás.
    $this->actingAs($revisor)->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))->assertSessionHas('success');

    Http::assertNotSent(fn ($r) => ($r['params']['args'][4] ?? null) === 'create');
    expect(seEscribio(1969, fn ($v) => ($v['price_unit'] ?? null) === 4958.0))->toBeTrue();
});

it('finds the line by the description it actually travelled with', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura] = cotizacion($revisor,
        [['product_service' => 'TUB CUAD NEG. 2,0 X 2,0 MM', 'specification' => 'ECU202', 'quantity' => 1]],
        [['product_service' => 'TUBO CUADRADO NEGRO 20X20X2', 'quantity' => '1', 'unit_price' => '8990']],
        orden: 244,
    );

    // Así se llama de verdad allá: la partida con su especificación pegada.
    odooConOrdenes([orden(244, 'draft', [2001])], [['id' => 2001, 'order_id' => [244, 'P00244'], 'product_id' => false,
        'name' => 'TUB CUAD NEG. 2,0 X 2,0 MM · ECU202']]);

    // «2,0 X 2,0» contra «20X20X2» merece una mirada: va sólo si alguien la marca.
    $this->actingAs($revisor)->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]), [
        'vista_previa' => 1, 'filas' => [$solicitud->items()->value('id')],
    ])->assertSessionHas('success');

    expect(seEscribio(2001, fn ($v) => ($v['price_unit'] ?? null) === 8990.0))->toBeTrue();
});

it('shows the order, what changes and what stays in the preview', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura, $items] = cotizacion($revisor,
        [['product_service' => 'tubos fierro 10" /5mm', 'quantity' => 2]],
        [['product_service' => 'CAÑERIA ACERO 10" 5 MM - MT SOLDAR', 'quantity' => '12', 'unit' => 'Metros', 'unit_price' => '56446']],
    );
    $items[1]->forceFill(['odoo_order_id' => 240, 'odoo_line_id' => 2389])->save();

    odooConOrdenes([orden(240, 'sent', [2389])], [['id' => 2389, 'order_id' => [240, 'P00240'], 'product_id' => [8941, 'Tubos'], 'product_qty' => 2]]);

    $this->actingAs($revisor)
        ->get(route('purchase_requests.quotes.prices.preview', [$solicitud, $lectura]))
        ->assertOk()
        ->assertSee('P00240')
        ->assertSee('Cotización enviada')
        ->assertSee('$338.676')
        ->assertSee('Escribir en P00240');

    nadaSeEscribio();
});

it('sends a piece of an item to the order as its own line', function () {
    // El canastillo es parte de la válvula de pie: entra a la orden en su
    // propia línea, que es como Cooprinsem lo va a facturar.
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura, $items] = cotizacion($revisor,
        [['product_service' => 'válvula de pie con canastillo 10"', 'quantity' => 1]],
        [
            ['product_service' => 'VALVULA RET. HDPE 10"', 'quantity' => '1', 'unit_price' => '464750'],
            ['product_service' => 'CANASTILLO SUCCION HDPE 10"', 'quantity' => '1', 'unit_price' => '279625'],
        ],
        [0 => 1],
    );
    $items[1]->forceFill(['odoo_order_id' => 240, 'odoo_line_id' => 2384])->save();
    $lectura->guardarDecisiones($lectura->parejasConfirmadas(), null, [1 => $items[1]->getKey()]);

    odooConOrdenes([orden(240, 'sent', [2384])], [['id' => 2384, 'order_id' => [240, 'P00240'], 'product_id' => [8936, 'Válvula de pie']]],
        [['id' => 8990, 'name' => 'CANASTILLO SUCCION HDPE 10"', 'uom_id' => [1, 'Units']]]);

    $this->actingAs($revisor)
        ->get(route('purchase_requests.quotes.prices.preview', [$solicitud, $lectura]))
        ->assertOk()->assertSee('Piezas de una partida');

    $this->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))
        ->assertSessionHas('success', fn (string $m) => str_contains($m, 'agregó 1 línea nueva'));

    Http::assertSent(fn ($r) => ($r['params']['args'][4] ?? null) === 'create'
        && ($r['params']['args'][3] ?? null) === 'purchase.order.line'
        && ($r['params']['args'][5][0]['product_id'] ?? null) === 8990
        && ($r['params']['args'][5][0]['price_unit'] ?? null) === 279625.0);
});

it('updates the piece line already in the order instead of adding it twice', function () {
    $revisor = User::factory()->admin()->create();
    [$solicitud, $lectura, $items] = cotizacion($revisor,
        [['product_service' => 'válvula de pie con canastillo 10"', 'quantity' => 1]],
        [
            ['product_service' => 'VALVULA RET. HDPE 10"', 'quantity' => '1', 'unit_price' => '464750'],
            ['product_service' => 'CANASTILLO SUCCION HDPE 10"', 'quantity' => '1', 'unit_price' => '279625'],
        ],
        [0 => 1],
    );
    $items[1]->forceFill(['odoo_order_id' => 240, 'odoo_line_id' => 2384])->save();
    $lectura->guardarDecisiones($lectura->parejasConfirmadas(), null, [1 => $items[1]->getKey()]);

    odooConOrdenes([orden(240, 'sent', [2384, 9934])], [
        ['id' => 2384, 'order_id' => [240, 'P00240'], 'product_id' => [8936, 'Válvula de pie']],
        ['id' => 9934, 'order_id' => [240, 'P00240'], 'product_id' => [8990, 'Canastillo'], 'name' => 'CANASTILLO SUCCION HDPE 10"', 'price_unit' => 250000],
    ]);

    $this->actingAs($revisor)->post(route('purchase_requests.quotes.prices', [$solicitud, $lectura]))->assertSessionHas('success');

    expect(seEscribio(9934, fn ($v) => ($v['price_unit'] ?? null) === 279625.0))->toBeTrue();
    Http::assertNotSent(fn ($r) => ($r['params']['args'][4] ?? null) === 'create');
});
