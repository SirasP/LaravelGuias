<?php

use App\Jobs\ReadQuotationDocument;
use App\Models\PurchaseSupplier;
use App\Services\PurchaseRequests\Odoo\OdooClient;
use App\Services\PurchaseRequests\Odoo\OdooPurchaseRequestExporter;
use App\Services\PurchaseRequests\Odoo\PurchaseRequestExporter;
use App\Services\PurchaseRequests\Reading\QuotationReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Hay cotizaciones cuyo encabezado es una imagen: en el texto no queda más
 * que el RUT. Como Odoo ya conoce a casi todos los proveedores, preguntarle
 * evita dejarle el campo vacío a quien pidió la lectura.
 */
function odooQueContesta(array $respuestas): void
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
        array_map(fn ($r) => Http::response(['jsonrpc' => '2.0', 'result' => $r]), $respuestas),
    )]);

    app()->bind(PurchaseRequestExporter::class, fn () => new OdooPurchaseRequestExporter(new OdooClient(
        (string) config('purchase_requests.odoo.url'),
        (string) config('purchase_requests.odoo.db'),
        (string) config('purchase_requests.odoo.user'),
        (string) config('purchase_requests.odoo.password'),
    )));
}

/** Llama al paso que resuelve el proveedor, sin montar toda la lectura. */
function proveedorResuelto(QuotationReading $lectura): array
{
    $metodo = new ReflectionMethod(ReadQuotationDocument::class, 'proveedorSugerido');
    $job = (new ReflectionClass(ReadQuotationDocument::class))->newInstanceWithoutConstructor();

    return $metodo->invoke($job, $lectura);
}

/** Una lectura con RUT pero sin nombre: el caso de RODASERVIC. */
function lecturaSinNombre(string $rut): QuotationReading
{
    return QuotationReading::of(items: [], supplier: null, supplierTaxId: $rut);
}

it('asks Odoo for the supplier name when the document only shows the tax id', function () {
    odooQueContesta([
        7,                                                        // autenticación
        [['id' => 3528, 'name' => 'RODASERVIC SPA', 'vat' => '77045469-7']],
    ]);

    proveedorResuelto(lecturaSinNombre('77045469-7'));

    expect(PurchaseSupplier::query()->where('tax_id', '77045469-7')->value('name'))
        ->toBe('RODASERVIC SPA');
});

it('leaves the name empty instead of failing when Odoo does not answer', function () {
    config([
        'purchase_requests.odoo.enabled' => true,
        'purchase_requests.odoo.url' => 'https://odoo.ejemplo.cl',
        'purchase_requests.odoo.db' => 'prueba',
        'purchase_requests.odoo.user' => 'quien@ejemplo.cl',
        'purchase_requests.odoo.password' => 'secreta',
    ]);

    Http::preventStrayRequests();
    Http::fake(fn () => throw new ConnectionException('Odoo no responde'));

    app()->bind(PurchaseRequestExporter::class, fn () => new OdooPurchaseRequestExporter(new OdooClient(
        'https://odoo.ejemplo.cl', 'prueba', 'quien@ejemplo.cl', 'secreta',
    )));

    // Lo que importa: la lectura no se cae por culpa de Odoo.
    proveedorResuelto(lecturaSinNombre('77045469-7'));

    $proveedor = PurchaseSupplier::query()->where('tax_id', '77045469-7')->first();

    expect($proveedor)->not->toBeNull()
        ->and($proveedor->name)->toBeNull()
        ->and($proveedor->notes)->toContain('Falta ponerle nombre');
});

it('does not ask Odoo when the document already named the supplier', function () {
    config(['purchase_requests.odoo.enabled' => true]);
    Http::preventStrayRequests();

    // Sin Http::fake: cualquier llamada a Odoo haría fallar la prueba.
    proveedorResuelto(QuotationReading::of(
        items: [], supplier: 'IVAN RUDY CANCINO DIAZ', supplierTaxId: '10855569-6',
    ));

    expect(PurchaseSupplier::query()->where('tax_id', '10855569-6')->value('name'))
        ->toBe('IVAN RUDY CANCINO DIAZ');
});
