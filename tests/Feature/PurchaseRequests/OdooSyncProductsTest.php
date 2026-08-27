<?php

use App\Models\OdooProduct;
use App\Models\OdooSupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function odooConProductos(array $productos, array $proveedorProducto = []): void
{
    config([
        'purchase_requests.odoo.url' => 'https://odoo.example.test',
        'purchase_requests.odoo.db' => 'prueba',
        'purchase_requests.odoo.user' => 'quien@ejemplo.cl',
        'purchase_requests.odoo.password' => 'secreta',
    ]);

    Http::preventStrayRequests();
    Http::fake(['*/jsonrpc' => Http::sequence([
        Http::response(['jsonrpc' => '2.0', 'result' => 8]),               // login
        Http::response(['jsonrpc' => '2.0', 'result' => $productos]),      // product.product
        Http::response(['jsonrpc' => '2.0', 'result' => $proveedorProducto]), // supplierinfo
    ])]);
}

it('copies the catalogue into a local table without writing anything back', function () {
    odooConProductos([
        ['id' => 8687, 'name' => 'RODAMIENTO 6202 2RS2 C3 NKE', 'default_code' => 'ROD-6202',
            'barcode' => false, 'uom_id' => [1, 'Units'], 'type' => 'consu',
            'is_storable' => true, 'purchase_ok' => true, 'active' => true],
    ]);

    $this->artisan('odoo:sync-products')->assertSuccessful();

    $p = OdooProduct::firstOrFail();
    expect($p->odoo_id)->toBe(8687)
        ->and($p->default_code)->toBe('ROD-6202')
        ->and($p->uom_name)->toBe('Units')
        ->and($p->is_storable)->toBeTrue()
        // Odoo devuelve `false` en los campos vacíos, no null.
        ->and($p->barcode)->toBeNull();

    // Sólo lectura: ni un create ni un write hacia Odoo.
    Http::assertSent(fn ($r) => ! in_array($r['params']['args'][4] ?? null, ['create', 'write', 'unlink'], true));
});

it('marks what disappeared instead of deleting it', function () {
    // Un alias podría apuntar a este producto: borrarlo lo dejaría huérfano
    // sin rastro de por qué.
    odooConProductos([
        ['id' => 1, 'name' => 'Sigue existiendo', 'default_code' => false, 'barcode' => false,
            'uom_id' => [1, 'Units'], 'type' => 'consu', 'is_storable' => true,
            'purchase_ok' => true, 'active' => true],
    ]);

    OdooProduct::create(['odoo_id' => 99, 'name' => 'Lo fusionaron en Odoo', 'synced_at' => now()->subDay()]);

    $this->artisan('odoo:sync-products')->assertSuccessful();

    expect(OdooProduct::count())->toBe(2);

    $ido = OdooProduct::where('odoo_id', 99)->firstOrFail();
    expect($ido->missing_since)->not->toBeNull()
        ->and($ido->stillInOdoo())->toBeFalse();

    // Y el que sí vino queda utilizable.
    expect(OdooProduct::query()->usable()->pluck('odoo_id')->all())->toBe([1]);
});

it('clears the mark when a product comes back', function () {
    odooConProductos([
        ['id' => 99, 'name' => 'Volvió', 'default_code' => false, 'barcode' => false,
            'uom_id' => [1, 'Units'], 'type' => 'consu', 'is_storable' => true,
            'purchase_ok' => true, 'active' => true],
    ]);

    OdooProduct::create([
        'odoo_id' => 99, 'name' => 'Volvió',
        'missing_since' => now()->subWeek(), 'synced_at' => now()->subWeek(),
    ]);

    $this->artisan('odoo:sync-products')->assertSuccessful();

    expect(OdooProduct::where('odoo_id', 99)->firstOrFail()->missing_since)->toBeNull();
});

it('records which products each supplier sells, so the search is not against the whole catalogue', function () {
    odooConProductos(
        [['id' => 8687, 'name' => 'RODAMIENTO 6202', 'default_code' => false, 'barcode' => false,
            'uom_id' => [1, 'Units'], 'type' => 'consu', 'is_storable' => true,
            'purchase_ok' => true, 'active' => true]],
        [['id' => 55, 'partner_id' => [3528, 'RODASERVIC SPA'], 'product_id' => [8687, 'RODAMIENTO 6202'],
            'product_tmpl_id' => [900, 'RODAMIENTO 6202'], 'product_name' => false,
            'product_code' => false, 'price' => 4500.0]],
    );

    $this->artisan('odoo:sync-products')->assertSuccessful();

    $s = OdooSupplierProduct::firstOrFail();
    expect($s->partner_id)->toBe(3528)
        ->and($s->partner_name)->toBe('RODASERVIC SPA')
        ->and($s->product_id)->toBe(8687)
        // Hoy Odoo los trae vacíos; el día que se llenen es el mejor cruce.
        ->and($s->product_name)->toBeNull();
});

it('refuses to run without knowing which Odoo it is talking to', function () {
    config(['purchase_requests.odoo.url' => null, 'purchase_requests.odoo.db' => null]);

    Http::preventStrayRequests();

    // Sincronizar contra una instancia y exportar a otra guardaría ids que
    // allá no existen, sin que ningún error lo delate.
    $this->artisan('odoo:sync-products')->assertFailed();
});

it('shows what it would bring without saving anything', function () {
    odooConProductos([
        ['id' => 1, 'name' => 'Un producto', 'default_code' => false, 'barcode' => false,
            'uom_id' => [1, 'Units'], 'type' => 'consu', 'is_storable' => true,
            'purchase_ok' => true, 'active' => true],
    ]);

    $this->artisan('odoo:sync-products', ['--dry-run' => true])->assertSuccessful();

    expect(OdooProduct::count())->toBe(0);
});
