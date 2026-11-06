<?php

use App\Enums\PurchaseRequestStatus;
use App\Models\OdooPurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestEvent;
use App\Models\User;
use App\Services\PurchaseRequests\Odoo\AdoptOdooQuotations;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Las tres cotizaciones que Luis Silva mandó desde Odoo el 05-11-2026, sin solicitud. */
function grupoDeLuis(array $cambios = []): void
{
    $base = array_replace(['purchase_group_id' => 35, 'state' => 'sent', 'created_by_name' => 'Luis Silva Menzel', 'currency' => 'CLP',
        'notes' => 'Plazo 30 días', 'date_order' => '2026-11-05 15:25:13', 'odoo_created_at' => '2026-11-05 15:25:13', 'synced_at' => now()], $cambios);
    foreach ([380 => 'Entre Riegos del Sur SpA', 381 => 'COMERCIAL CLIMENT SPA, Ricardo Soto', 382 => 'AUSTRAL RIEGO SPA'] as $id => $proveedor) {
        $orden = OdooPurchaseOrder::create($base + ['odoo_id' => $id, 'name' => 'P00'.$id, 'partner_name' => $proveedor]);
        foreach ([['CODO PVC 200 MM X 90° CEMENTAR', 4], ["UNION MANGUITO PVC  200 MM\nCon falange volante cementar", 2]] as $i => [$nombre, $cantidad]) {
            $orden->lines()->create(['odoo_id' => $id * 10 + $i, 'sequence' => $i, 'name' => $nombre, 'product_qty' => $cantidad, 'uom_name' => 'Units', 'price_unit' => 0, 'price_subtotal' => 0]);
        }
    }
}

it('turns a group of quotations made in Odoo into one approved request of its own', function () {
    $sebastian = User::factory()->admin()->create();
    grupoDeLuis();

    $creadas = app(AdoptOdooQuotations::class)->adoptar();

    expect($creadas)->toHaveCount(1);
    $solicitud = PurchaseRequest::with('items', 'events')->sole();
    expect($solicitud)
        ->user_id->toBe($sebastian->id)
        ->status->toBe(PurchaseRequestStatus::APPROVED)
        ->odoo_order_id->toBe(380)
        ->odoo_reference->toBe('P00380')
        ->requested_for_name->toBe('Luis Silva Menzel')
        ->folio->toBe(sprintf('SC-2026-%06d', $solicitud->id))
        ->and($solicitud->reason)->toContain('P00380 Entre Riegos del Sur SpA, P00381 COMERCIAL CLIMENT SPA y P00382 AUSTRAL RIEGO SPA')
        ->and($solicitud->items->map->only(['product_service', 'unit', 'odoo_order_id', 'odoo_line_id'])->all())->toBe([
            ['product_service' => 'CODO PVC 200 MM X 90° CEMENTAR', 'unit' => 'Unidades', 'odoo_order_id' => 380, 'odoo_line_id' => 3800],
            ['product_service' => 'UNION MANGUITO PVC 200 MM Con falange volante cementar', 'unit' => 'Unidades', 'odoo_order_id' => 380, 'odoo_line_id' => 3801],
        ])
        ->and($solicitud->isFromOdoo())->toBeTrue()
        ->and($solicitud->events->sole()->label())->toBe('Creada en Odoo y traída a Laravel');

    // Cada cinco minutos vuelve a mirar: no la crea otra vez.
    expect(app(AdoptOdooQuotations::class)->adoptar())->toBe([])
        ->and(PurchaseRequest::count())->toBe(1);

    // Queda en «Mis solicitudes» con su sticker, y la orden de Odoo lleva a ella.
    $this->actingAs($sebastian)->get(route('purchase_requests.index'))->assertOk()
        ->assertSee($solicitud->folio)->assertSee('Creada en Odoo');
    $this->get(route('purchase_requests.show', $solicitud))->assertOk()->assertSee('Creada en Odoo');
    $this->get(route('purchase_requests.odoo.show', 381))->assertOk()
        ->assertSee(route('purchase_requests.show', $solicitud))->assertSee($solicitud->folio.' · creada en Odoo');
});

it('leaves alone what already has a request, what is no longer a quotation and what is older', function (array $cambios) {
    User::factory()->admin()->create();
    grupoDeLuis($cambios);

    expect(app(AdoptOdooQuotations::class)->adoptar())->toBe([])
        ->and(PurchaseRequest::count())->toBe(0);
})->with([
    'names a request in its origin' => [['origin' => 'SC-2026-000106']],
    'already bought' => [['state' => 'purchase']],
    'made before the date' => [['odoo_created_at' => '2026-11-04 10:00:00']],
]);

it('does not adopt a group one of whose orders came from Laravel', function () {
    User::factory()->admin()->create();
    grupoDeLuis();
    PurchaseRequest::factory()->approved()->create(['odoo_order_id' => 381]);

    expect(app(AdoptOdooQuotations::class)->adoptar())->toBe([]);
});

it('does nothing when the date is cleared', function () {
    User::factory()->admin()->create();
    grupoDeLuis();
    config(['purchase_requests.odoo.adopt_since' => null]);

    expect(app(AdoptOdooQuotations::class)->adoptar())->toBe([])
        ->and(PurchaseRequestEvent::count())->toBe(0);
});

it('compares the Odoo orders by the request items, with a quoted piece inside its item', function () {
    $sebastian = User::factory()->admin()->create();
    grupoDeLuis();
    $solicitud = app(AdoptOdooQuotations::class)->adoptar()[0];
    $codo = $solicitud->items->firstWhere('odoo_line_id', 3800);
    // Los precios ya en Odoo: cada proveedor con sus nombres; Climent cotizó aparte la flange del codo.
    foreach ([3800 => 26377, 3801 => 52700, 3810 => 23429, 3811 => 15232, 3820 => 24237, 3821 => 18553] as $linea => $precio) {
        App\Models\OdooPurchaseOrderLine::where('odoo_id', $linea)->update(['price_unit' => $precio, 'price_subtotal' => $precio * ($linea % 10 === 0 ? 4 : 2), 'product_odoo_id' => 9000 + $linea % 10]);
    }
    App\Models\OdooPurchaseOrderLine::where('odoo_id', 3800)->update(['name' => 'CODO PVC 200MM x 90░ CEMENTAR']);
    OdooPurchaseOrder::where('odoo_id', 381)->first()->lines()->create(['odoo_id' => 3812, 'sequence' => 3, 'name' => 'FLANGE VOLANTE PVC-H 200MM', 'product_odoo_id' => 9100, 'product_qty' => 2, 'uom_name' => 'Units', 'price_unit' => 12879, 'price_subtotal' => 25758]);
    OdooPurchaseOrder::where('odoo_id', 382)->first()->lines()->create(['odoo_id' => 3822, 'sequence' => 3, 'name' => 'FLETE', 'product_odoo_id' => 9200, 'product_qty' => 1, 'uom_name' => 'Units', 'price_unit' => 5000, 'price_subtotal' => 5000]);
    App\Models\PurchaseRequestIngestion::create([
        'company_code' => 'EHE', 'user_id' => $sebastian->id, 'uploader_name_snapshot' => 'S', 'compared_request_id' => $solicitud->id,
        'disk' => 'local', 'path' => 'c.pdf', 'original_name' => 'climent.pdf', 'mime_type' => 'application/pdf', 'size' => 1,
        'sha256' => str_repeat('d', 64), 'status' => 'needs_review', 'supplier_name' => 'COMERCIAL CLIMENT SPA',
        'extracted' => ['items' => [['product_service' => 'CODO PVC-H', 'quantity' => '4', 'unit_price' => '23429'], ['product_service' => 'FLANGE VOLANTE PVC-H 200MM', 'quantity' => '2', 'unit_price' => '12879']]],
        'confirmed_pairings' => ['pares' => [], 'piezas' => [1 => $codo->id]],
    ]);

    $page = $this->actingAs($sebastian)->get(route('purchase_requests.odoo.show', 380))->assertOk();

    // Las filas llevan el nombre de la partida, no el del proveedor; la flange va dentro del codo.
    $page->assertSee('CODO PVC 200 MM X 90° CEMENTAR')->assertDontSee('90░')
        ->assertSee('incluye piezas $25.758')
        ->assertSee('Líneas que agregó un proveedor y no se pidieron')->assertSee('FLETE')
        ->assertDontSee('Cotizó 2 de 3')->assertDontSee('Cotizó 2 de 4');
});
