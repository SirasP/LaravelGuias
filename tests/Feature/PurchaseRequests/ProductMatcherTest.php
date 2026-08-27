<?php

use App\Models\OdooProduct;
use App\Models\OdooSupplierProduct;
use App\Models\PurchaseProductLink;
use App\Services\PurchaseRequests\Products\ProductMatch;
use App\Services\PurchaseRequests\Products\ProductMatcher;
use App\Services\PurchaseRequests\Products\ProductSimilarity;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function producto(int $id, string $nombre, array $extra = []): OdooProduct
{
    return OdooProduct::create(array_merge([
        'odoo_id' => $id, 'name' => $nombre,
        'purchase_ok' => true, 'active_in_odoo' => true,
    ], $extra));
}

function emparejador(): ProductMatcher
{
    return new ProductMatcher(new ProductSimilarity);
}

it('answers straight away what somebody already resolved', function () {
    producto(8687, 'RODAMIENTO 6202 2RS2 C3 NKE');

    PurchaseProductLink::create([
        'company_code' => 'EHE', 'odoo_partner_id' => 3528,
        'source_text' => 'RODAMIENTO 6202 2RS2 C3 NKE',
        'normalized_text' => PurchaseProductLink::normalizar('RODAMIENTO 6202 2RS2 C3 NKE'),
        'odoo_product_id' => 8687, 'odoo_product_name' => 'RODAMIENTO 6202 2RS2 C3 NKE',
        'source' => PurchaseProductLink::CONFIRMADO,
    ]);

    $r = emparejador()->match('rodamiento 6202 2rs2 c3 nke', 3528);

    expect($r->kind)->toBe(ProductMatch::CIERTO)
        ->and($r->odooProductId)->toBe(8687)
        ->and($r->resolved())->toBeTrue();
});

it('warns instead of sending a product that Odoo no longer has', function () {
    // Alguien lo archivó o lo fusionó allá desde que se emparejó. Mandar el id
    // igual haría fallar la cotización sin explicar por qué.
    producto(8687, 'RODAMIENTO 6202', ['missing_since' => now()]);

    PurchaseProductLink::create([
        'company_code' => 'EHE', 'odoo_partner_id' => 3528,
        'source_text' => 'rodamiento 6202', 'normalized_text' => 'rodamiento 6202',
        'odoo_product_id' => 8687, 'odoo_product_name' => 'RODAMIENTO 6202',
        'source' => PurchaseProductLink::CONFIRMADO,
    ]);

    $r = emparejador()->match('rodamiento 6202', 3528);

    expect($r->kind)->toBe(ProductMatch::SIN_IDEA)
        ->and($r->reason)->toContain('ya no está disponible en Odoo');
});

it('trusts an exact code over anything else', function () {
    producto(8687, 'Un nombre que no se parece en nada', ['default_code' => 'ROD-6202']);

    $r = emparejador()->match('rodamiento seis dos cero dos', null, 'ROD-6202');

    expect($r->kind)->toBe(ProductMatch::CIERTO)
        ->and($r->odooProductId)->toBe(8687);
});

it('suggests but never decides on its own', function () {
    producto(1, 'MONOMANDO LAVATORIO ECO - TAUMM 010100102');
    producto(2, 'MONOMANDO LAVAPLATO PARED');

    // «Monomando Lavamano» se parece a los dos y no es ninguno. Con 2.347
    // candidatos siempre habrá alguno que puntúe alto sin ser el correcto, y
    // un producto equivocado en una recepción mueve stock real.
    $r = emparejador()->match('Monomando Lavamano', null);

    expect($r->kind)->toBe(ProductMatch::SUGERENCIA)
        ->and($r->odooProductId)->toBeNull()
        ->and($r->resolved())->toBeFalse()
        ->and(count($r->candidates))->toBeGreaterThan(0);
});

it('looks first among what that supplier has sold before', function () {
    // Uno del catálogo general que se parece más por texto…
    producto(1, 'RODAMIENTO 6202 GENERICO');
    // …y otro que este proveedor sí vende.
    producto(2, 'RODAMIENTO 6202 2RS2 C3 NKE');

    OdooSupplierProduct::create([
        'odoo_id' => 55, 'partner_id' => 3528, 'partner_name' => 'RODASERVIC SPA',
        'product_id' => 2,
    ]);

    $r = emparejador()->match('RODAMIENTO 6202 2RS2 C3 NKE', 3528);

    // Acertar entre los ocho de un proveedor es otra cosa que entre 2.347.
    expect($r->candidates[0]['odoo_id'] ?? $r->odooProductId)->toBe(2);
});

it('says it has no idea rather than offering noise', function () {
    producto(1, 'TORNILLO AUTOPERFORANTE 8X1/2');

    $r = emparejador()->match('cemento a granel', null);

    expect($r->kind)->toBe(ProductMatch::SIN_IDEA)
        ->and($r->candidates)->toBeEmpty();
});

it('never offers a product that is archived or not for purchase', function () {
    producto(1, 'GUANTES LATEX', ['active_in_odoo' => false]);
    producto(2, 'GUANTES LATEX', ['purchase_ok' => false]);
    producto(3, 'GUANTES LATEX', ['missing_since' => now()]);

    expect(emparejador()->match('guantes latex', null)->kind)->toBe(ProductMatch::SIN_IDEA);
});
