<?php

use App\Models\PurchaseProductLink;
use App\Models\PurchaseRequestIngestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

/**
 * Lo que «Confirmar todas» enseña vale para todas las cotizaciones futuras del
 * proveedor. Con Cooprinsem enseñó el perfil de 50x50x3 como el de 100/100/5.
 */
it('does not confirm, nor learn, a pair whose measures cross', function () {
    $owner = User::factory()->create();
    $solicitud = test()->createPurchaseRequestDraft($owner);
    $solicitud->items()->delete();

    foreach (['perfil cuadrado fierro 100/100/5/6.000mm.', 'perfil cuadrado 50/50/3/6.000'] as $i => $texto) {
        $solicitud->items()->create(['sort_order' => $i + 1, 'product_service' => $texto, 'quantity' => 1, 'unit' => 'Unidades']);
    }

    // Ninguno de los dos calza: el espesor no es el pedido, y el programa
    // igual los propone por posición y cantidad.
    $lectura = PurchaseRequestIngestion::query()->create([
        'user_id' => $owner->getKey(), 'uploader_name_snapshot' => $owner->name,
        'compared_request_id' => $solicitud->getKey(), 'disk' => 'local',
        'path' => 'coop.pdf', 'original_name' => 'coop.pdf', 'mime_type' => 'application/pdf',
        'size' => 10, 'sha256' => str_repeat('c', 64),
        'status' => PurchaseRequestIngestion::COMPLETED,
        'extracted' => ['items' => array_map(fn (string $nombre): array => [
            'product_service' => $nombre, 'specification' => null,
            'quantity' => '1', 'unit' => 'Unidades', 'unit_price' => '6981',
        ], ['PERFIL 50x50x2MM - MT', 'PERFIL 100x100x4MM - MT'])],
    ]);

    $this->actingAs($owner)
        ->post(route('purchase_requests.quotes.confirm', [$solicitud, $lectura]))
        ->assertSessionHas('info', fn (string $m) => str_contains($m, 'medidas cruzadas'));

    expect(PurchaseProductLink::para('PERFIL 50x50x2MM - MT', null))->toBeNull()
        ->and(PurchaseProductLink::para('PERFIL 100x100x4MM - MT', null))->toBeNull();
});

it('keeps the fractions in what it learns', function () {
    // `ascii()` las volvía un espacio: el perno de ⅜ y el de ½ quedaban los
    // dos en «pernos anclaje 3», el mismo para lo aprendido.
    expect(PurchaseProductLink::normalizar('pernos anclaje ⅜/3"'))->toBe('pernos anclaje 3 8 3')
        ->and(PurchaseProductLink::normalizar('pernos anclaje ½"/3"'))->toBe('pernos anclaje 1 2 3')
        ->and(PurchaseProductLink::normalizar('tuercas¾" galvanizadas'))->toBe('tuercas 3 4 galvanizadas')
        ->and(PurchaseProductLink::normalizar('TUERCA 3/4"'))->toBe('tuerca 3 4');
});
