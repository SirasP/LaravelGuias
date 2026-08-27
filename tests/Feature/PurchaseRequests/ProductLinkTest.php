<?php

use App\Models\OdooProduct;
use App\Models\PurchaseProductLink;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function enlace(array $atributos = []): PurchaseProductLink
{
    return PurchaseProductLink::create(array_merge([
        'company_code' => 'EHE',
        'source_text' => 'RODAMIENTO 6202 2RS2 C3 NKE',
        'normalized_text' => PurchaseProductLink::normalizar('RODAMIENTO 6202 2RS2 C3 NKE'),
        'odoo_product_id' => 8687,
        'odoo_product_name' => 'RODAMIENTO 6202 2RS2 C3 NKE',
        'source' => PurchaseProductLink::CONFIRMADO,
    ], $atributos));
}

it('reduces a text to what makes it comparable', function () {
    // Tildes, mayúsculas y puntuación no cambian de qué producto se habla.
    expect(PurchaseProductLink::normalizar('Cerámica 30x20, Blanca.'))->toBe('ceramica 30x20 blanca')
        ->and(PurchaseProductLink::normalizar('  RODAMIENTO   6202  '))->toBe('rodamiento 6202')
        ->and(PurchaseProductLink::normalizar('Jabón-Manos'))->toBe('jabon manos')
        ->and(PurchaseProductLink::normalizar('...'))->toBe('');
});

it('finds the link however the text was capitalised or accented', function () {
    enlace(['odoo_partner_id' => 3528, 'partner_name' => 'RODASERVIC SPA']);

    expect(PurchaseProductLink::para('rodamiento 6202 2rs2 c3 nke', 3528)?->odoo_product_id)->toBe(8687);
    expect(PurchaseProductLink::para('RODAMIENTO  6202  2RS2  C3  NKE', 3528)?->odoo_product_id)->toBe(8687);
});

it('prefers the supplier own link over the general one', function () {
    // «jabon manos» de Unimarc puede no ser el mismo producto que el de otro
    // proveedor: quien lo confirmó sabía de quién hablaba.
    enlace(['source_text' => 'jabon manos', 'normalized_text' => 'jabon manos',
        'odoo_partner_id' => null, 'odoo_product_id' => 111]);
    enlace(['source_text' => 'jabon manos', 'normalized_text' => 'jabon manos',
        'odoo_partner_id' => 3528, 'odoo_product_id' => 222]);

    expect(PurchaseProductLink::para('jabon manos', 3528)?->odoo_product_id)->toBe(222)
        // Sin proveedor, o con uno que no tiene enlace propio, cae al general.
        ->and(PurchaseProductLink::para('jabon manos', null)?->odoo_product_id)->toBe(111)
        ->and(PurchaseProductLink::para('jabon manos', 9999)?->odoo_product_id)->toBe(111);
});

it('says nothing when the text was never resolved', function () {
    // Sin enlace no se devuelve un parecido: eso es trabajo del buscador, y
    // siempre pasa por una persona.
    expect(PurchaseProductLink::para('algo que nadie emparejó', 3528))->toBeNull();
});

it('knows when it points at a product Odoo no longer has', function () {
    $link = enlace(['odoo_partner_id' => 3528]);

    // Todavía no está sincronizado: no se puede afirmar que siga vigente.
    expect($link->productoVigente())->toBeFalse();

    OdooProduct::create(['odoo_id' => 8687, 'name' => 'RODAMIENTO 6202', 'purchase_ok' => true, 'active_in_odoo' => true]);
    expect($link->fresh()->productoVigente())->toBeTrue();

    // Y si desaparece de Odoo, el enlace tiene que poder decirlo en vez de
    // mandar un id muerto en la cotización.
    OdooProduct::where('odoo_id', 8687)->update(['missing_since' => now()]);
    expect($link->fresh()->productoVigente())->toBeFalse();
});

it('refuses two different answers for the same supplier and text', function () {
    enlace(['odoo_partner_id' => 3528]);

    // El diccionario no admite grados: un texto de un proveedor apunta a una
    // sola cosa, o deja de ser un diccionario.
    expect(fn () => enlace(['odoo_partner_id' => 3528, 'odoo_product_id' => 9999]))
        ->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});
