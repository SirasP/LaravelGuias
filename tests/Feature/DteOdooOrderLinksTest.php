<?php

use App\Models\DteOdooOrderLink;
use App\Models\OdooPurchaseOrder;
use App\Models\User;
use App\Services\DtePurchases\OdooOrderLinks;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 28));
    Http::preventStrayRequests();
    $this->admin = User::factory()->admin()->create();
});

/** An order made straight in Odoo, with its lines [name, qty, subtotal]. */
function odooTabOrder(int $odooId, array $lines, array $values = []): OdooPurchaseOrder
{
    $order = OdooPurchaseOrder::create(array_replace([
        'odoo_id' => $odooId, 'name' => sprintf('P%05d', $odooId), 'state' => 'purchase', 'partner_name' => 'Acenor Aceros Del Norte S.A.',
        'partner_vat' => 'CL96510970-6', 'currency' => 'CLP', 'amount_untaxed' => array_sum(array_column($lines, 2)),
        'date_order' => '2026-10-22 10:00:00', 'created_by_name' => 'Luis Silva Menzel', 'synced_at' => now(),
    ], $values));
    foreach ($lines as $i => [$name, $qty, $subtotal]) {
        $order->lines()->create(['odoo_id' => $odooId * 100 + $i, 'sequence' => $i, 'name' => $name, 'product_qty' => $qty,
            'price_unit' => $subtotal / $qty, 'price_subtotal' => $subtotal]);
    }

    return $order;
}

/** An invoice from the same supplier, with its lines [description, qty, amount]. */
function odooTabInvoice(array $lines, array $values = []): int
{
    $net = array_sum(array_column($lines, 2));
    $id = DB::connection('fuelcontrol')->table('gmail_dte_documents')->insertGetId(array_replace([
        'xml_filename' => 'ficticio.xml', 'hash_unico' => (string) Str::uuid(), 'tipo_dte' => 33,
        'folio' => (string) random_int(100000, 999999), 'proveedor_rut' => '96510970-6', 'proveedor_nombre' => 'ACENOR ACEROS DEL NORTE SPA',
        'fecha_factura' => '2026-10-28', 'monto_neto' => $net, 'monto_iva' => round($net * 0.19), 'monto_total' => round($net * 1.19),
        'workflow_status' => 'aceptado', 'created_at' => now(), 'updated_at' => now(),
    ], $values));
    foreach ($lines as $i => [$description, $qty, $amount]) {
        DB::connection('fuelcontrol')->table('gmail_dte_document_lines')->insert(['document_id' => $id, 'nro_linea' => $i + 1,
            'descripcion' => $description, 'cantidad' => $qty, 'unidad' => 'UN', 'precio_unitario' => $amount / $qty,
            'monto_item' => $amount, 'created_at' => now(), 'updated_at' => now()]);
    }

    return $id;
}

it('links by itself the order that the invoice names in its XML, and the Odoo tab shows it invoiced', function () {
    odooTabOrder(338, [['FLETE CAMION RAMPLA', 1, 1400000]], ['partner_vat' => 'CL76111222-3', 'partner_name' => 'Transportes Oliva Spa']);
    $id = odooTabInvoice([['Flete Osorno - Rio Bueno', 1, 1400000]], ['proveedor_rut' => '76111222-3',
        'xml_raw' => '<DTE><Referencia><NroLinRef>1</NroLinRef><TpoDocRef>801</TpoDocRef><FolioRef>P00338</FolioRef></Referencia></DTE>']);

    $this->actingAs($this->admin)->getJson(route('gmail.dtes.odoo_orders.show', $id))->assertOk()
        ->assertJsonPath('links.0.name', 'P00338')->assertJsonPath('links.0.source', 'reference')
        ->assertJsonPath('links.0.order.invoiced', 1400000);
    $this->get(route('purchase_requests.odoo', ['estado' => 'confirmadas']))->assertOk()->assertSee('Facturada');
    // Nothing goes to Odoo.
    Http::assertNothingSent();
});

it('links by itself the only order with the same products and amount, and leaves the rest as suggestions', function () {
    $right = odooTabOrder(340, [['TUB CUAD NEG 40 X 3.0 MM', 80, 1346400], ['SERVICIO DE FLETE', 1, 33614]]);
    odooTabOrder(341, [['PLANCHA LAC 3 MM', 2, 170000]]);
    $id = odooTabInvoice([['TUB CUAD NEG  40 X 3.0 MM', 80, 1346400], ['SERVICIO DE FLETE', 1, 33614]]);

    $this->actingAs($this->admin)->getJson(route('gmail.dtes.odoo_orders.show', $id))
        ->assertJsonCount(1, 'links')->assertJsonPath('links.0.odoo_id', $right->odoo_id)->assertJsonPath('links.0.source', 'auto')
        // Linked to its order, it suggests nothing else; the search is still there.
        ->assertJsonCount(0, 'candidates');
    $this->getJson(route('gmail.dtes.odoo_orders.candidates', [$id, 'q' => 'P00341']))->assertJsonPath('candidates.0.name', 'P00341');
});

it('suggests only orders with something in their favour, still pending, without mixing currencies', function () {
    odooTabOrder(319, [['ALAMBRE GALVANIZADO 14', 10, 1125]], ['currency' => 'USD', 'partner_vat' => '76222333-4']);
    odooTabOrder(320, [['UREA GRANULADA SACO 25KG', 20, 560]], ['currency' => 'USD', 'partner_vat' => '76222333-4']);
    odooTabOrder(330, [['SULFATO DE POTASIO', 10, 500000]], ['partner_vat' => '76222333-4']);
    $paid = odooTabInvoice([['SULFATO DE POTASIO', 10, 500000]], ['proveedor_rut' => '76222333-4']);
    $this->actingAs($this->admin)->getJson(route('gmail.dtes.odoo_orders.show', $paid))->assertJsonPath('links.0.name', 'P00330');

    $id = odooTabInvoice([['UREA GRANULADA SACO 25KG', 20, 552737]], ['proveedor_rut' => '76222333-4']);
    $this->getJson(route('gmail.dtes.odoo_orders.show', $id))
        // P00319 is only the same supplier; P00330 is already fully invoiced.
        ->assertJsonCount(1, 'candidates')->assertJsonPath('candidates.0.name', 'P00320')
        ->assertJsonPath('candidates.0.remaining', null)->assertJsonPath('candidates.0.partial', false);
});

it('does not link by itself when two orders fit equally, a measure differs, or only part matches', function () {
    odooTabOrder(350, [['TUB CUAD NEG 40 X 3.0 MM', 80, 1346400]]);
    odooTabOrder(351, [['TUB CUAD NEG 40 X 3.0 MM', 80, 1346400]]);
    $twins = odooTabInvoice([['TUB CUAD NEG 40 X 3.0 MM', 80, 1346400]]);
    odooTabOrder(352, [['TUB CUAD NEG 40 X 3.0 MM', 10, 200000]], ['partner_vat' => '77000111-2']);
    $thinner = odooTabInvoice([['TUB CUAD NEG 40 X 2.0 MM', 10, 200000]], ['proveedor_rut' => '77000111-2']);

    $this->actingAs($this->admin)->getJson(route('gmail.dtes.odoo_orders.show', $twins))->assertJsonCount(0, 'links')->assertJsonCount(2, 'candidates');
    $this->getJson(route('gmail.dtes.odoo_orders.show', $thinner))->assertJsonCount(0, 'links')
        ->assertJsonPath('candidates.0.coverage', 0)->assertJsonPath('candidates.0.same_amount', true);
});

it('links by hand, and never relinks by itself what a person withdrew', function () {
    odooTabOrder(360, [['TUB CUAD NEG 40 X 3.0 MM', 80, 1346400]]);
    $id = odooTabInvoice([['TUB CUAD NEG 40 X 3.0 MM', 80, 1346400]]);
    $this->actingAs($this->admin)->getJson(route('gmail.dtes.odoo_orders.show', $id))->assertJsonCount(1, 'links');
    $link = DteOdooOrderLink::sole();

    $this->deleteJson(route('gmail.dtes.odoo_orders.destroy', [$id, $link->id]), ['note' => 'Era otra compra.'])->assertOk();
    $this->getJson(route('gmail.dtes.odoo_orders.show', $id))->assertJsonCount(0, 'links');

    $this->postJson(route('gmail.dtes.odoo_orders.store', $id), ['odoo_order_id' => 360, 'amount' => 600000])->assertCreated();
    $this->getJson(route('gmail.dtes.odoo_orders.show', $id))->assertJsonPath('links.0.source', 'manual')
        ->assertJsonPath('links.0.amount', 600000)->assertJsonPath('links.0.order.invoiced', 600000);
    $this->postJson(route('gmail.dtes.odoo_orders.store', $id), ['odoo_order_id' => 360])->assertUnprocessable();
});

it('links recent invoices of suppliers with orders after the nightly sync', function () {
    odooTabOrder(370, [['TUB CUAD NEG 40 X 3.0 MM', 80, 1346400]]);
    odooTabInvoice([['TUB CUAD NEG 40 X 3.0 MM', 80, 1346400]]);
    odooTabInvoice([['OTRA COSA', 1, 5000]], ['proveedor_rut' => '11111111-1']);

    expect(app(OdooOrderLinks::class)->linkRecent())->toBe(1)
        ->and(app(OdooOrderLinks::class)->invoicesFor([370])[370])->toHaveCount(1);
});

it('keeps the links and the screen for administration only', function () {
    $id = odooTabInvoice([['TUB', 1, 1000]]);
    $this->actingAs(User::factory()->create())->getJson(route('gmail.dtes.odoo_orders.show', $id))->assertForbidden();
});

it('lists the invoices of an order in dollars without comparing them with pesos', function () {
    odooTabOrder(352, [['UREA GRANULADA SACO 25KG', 400, 20043]], ['currency' => 'USD', 'partner_vat' => '76222333-4']);
    $id = odooTabInvoice([['UREA GRANULADA', 400, 19663786]], ['proveedor_rut' => '76222333-4',
        'xml_raw' => '<DTE><Referencia><TpoDocRef>801</TpoDocRef><FolioRef>OC P00352</FolioRef></Referencia></DTE>']);

    $this->actingAs($this->admin)->getJson(route('gmail.dtes.odoo_orders.show', $id))
        ->assertJsonPath('links.0.order.currency', 'USD')->assertJsonPath('links.0.source', 'reference');
    $this->get(route('purchase_requests.odoo', ['estado' => 'confirmadas']))->assertSee('Con 1 factura')->assertDontSee('Facturada');
});

it('tells the request block that the purchase went through an Odoo order', function () {
    odooTabOrder(262, [['FLETE', 1, 124400]], ['partner_vat' => '76999888-1']);
    $id = odooTabInvoice([['FLETE', 1, 124400]], ['proveedor_rut' => '76999888-1',
        'xml_raw' => '<DTE><Referencia><TpoDocRef>801</TpoDocRef><FolioRef>P00262</FolioRef></Referencia></DTE>']);
    $this->actingAs($this->admin)->getJson(route('gmail.dtes.odoo_orders.show', $id))->assertJsonPath('links.0.name', 'P00262');

    $this->getJson(route('gmail.dtes.purchase_references.show', $id))->assertJsonPath('odoo_orders', ['P00262']);
});
