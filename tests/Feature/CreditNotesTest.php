<?php

use App\Models\DtePurchaseReference;
use App\Models\OdooPurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\DtePurchases\PurchaseReferences;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 28));
    Http::preventStrayRequests();
    $this->admin = User::factory()->admin()->create();
});

/** A document of the supplier with its lines [description, qty, amount] and XML references [type, folio, code]. */
function creditDocument(int $type, string $folio, array $lines, array $references = [], string $rut = '76555444-3'): int
{
    $net = array_sum(array_column($lines, 2));
    $xml = '<DTE>'.implode('', array_map(fn ($r) => "<Referencia><TpoDocRef>{$r[0]}</TpoDocRef><FolioRef>{$r[1]}</FolioRef>"
        .(isset($r[2]) ? "<CodRef>{$r[2]}</CodRef>" : '').'<RazonRef>'.($r[3] ?? 'Corrige montos').'</RazonRef></Referencia>', $references)).'</DTE>';
    $id = DB::connection('fuelcontrol')->table('gmail_dte_documents')->insertGetId([
        'xml_filename' => 'ficticio.xml', 'hash_unico' => (string) Str::uuid(), 'tipo_dte' => $type, 'folio' => $folio,
        'proveedor_rut' => $rut, 'proveedor_nombre' => 'SUPPLYTECH INGENIERIA E INSUMOS SPA', 'fecha_factura' => '2026-10-20',
        'monto_neto' => $net, 'monto_iva' => round($net * 0.19), 'monto_total' => round($net * 1.19),
        'workflow_status' => 'aceptado', 'xml_raw' => $xml, 'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach ($lines as $i => [$description, $qty, $amount]) {
        DB::connection('fuelcontrol')->table('gmail_dte_document_lines')->insert(['document_id' => $id, 'nro_linea' => $i + 1,
            'descripcion' => $description, 'cantidad' => $qty, 'unidad' => 'UN', 'precio_unitario' => round($amount / $qty, 4),
            'monto_item' => $amount, 'created_at' => now(), 'updated_at' => now()]);
    }

    return $id;
}

function creditOrder(int $odooId, float $net): void
{
    $order = OdooPurchaseOrder::create(['odoo_id' => $odooId, 'name' => sprintf('P%05d', $odooId), 'state' => 'purchase',
        'partner_name' => 'Supplytech', 'partner_vat' => '76555444-3', 'currency' => 'CLP', 'amount_untaxed' => $net,
        'date_order' => '2026-10-10 10:00:00', 'synced_at' => now()]);
    $order->lines()->create(['odoo_id' => $odooId * 100, 'name' => 'SENSOR NIVEL', 'product_qty' => 1, 'price_unit' => $net, 'price_subtotal' => $net]);
}

it('does not count twice an order whose first invoice a credit note annulled', function () {
    creditOrder(184, 1343405);
    $annulled = creditDocument(33, '3274', [['SENSOR NIVEL', 1, 1343405]], [[801, 'P00184']]);
    creditDocument(61, '92', [['SENSOR NIVEL', 1, 1343405]], [[33, '3274', 1, 'ANULA DOCUMENTO DE LA REFERENCIA'], [801, 'P00184']]);
    $replacement = creditDocument(33, '3278', [['SENSOR NIVEL', 1, 1343405]], [[801, 'P00184']]);

    $this->actingAs($this->admin)->getJson(route('gmail.dtes.odoo_orders.show', $annulled))
        ->assertJsonCount(0, 'links');   // an annulled invoice paid nothing: it does not link by itself
    $this->postJson(route('gmail.dtes.odoo_orders.store', $annulled), ['odoo_order_id' => 184])->assertCreated();
    $this->getJson(route('gmail.dtes.odoo_orders.show', $replacement))
        ->assertJsonPath('links.0.source', 'reference')->assertJsonPath('links.0.order.invoiced', 1343405);
    $this->getJson(route('gmail.dtes.odoo_orders.show', $annulled))
        ->assertJsonPath('links.0.credit_notes.0.annuls', true)->assertJsonPath('links.0.credited', 1343405);
    $this->get(route('purchase_requests.odoo', ['estado' => 'confirmadas']))->assertSee('Facturada')->assertDontSee('200 %');
    $this->get(route('purchase_requests.odoo.show', 184))->assertSee('100 %')->assertSee('Anulada por la nota de crédito N° 92');
});

it('subtracts a credit note that corrects amounts, and ignores one that only corrects text', function () {
    creditOrder(213, 535686);
    $invoice = creditDocument(33, '97351', [['METAL BANCADA STD GRANDE', 8, 535686]], [[801, 'P00213']]);
    creditDocument(61, '5341', [['METAL BANCADA STD GRANDE', 4, 144362]], [[33, '97351', 3]]);
    creditDocument(61, '5342', [['GLOSA', 1, 1]], [[33, '97351', 2, 'Corrige texto']]);

    $this->actingAs($this->admin)->getJson(route('gmail.dtes.odoo_orders.show', $invoice))
        ->assertJsonPath('links.0.order.invoiced', 391324)->assertJsonCount(1, 'links.0.credit_notes');
});

it('puts back as pending the units a credit note returned', function () {
    $request = PurchaseRequest::factory()->approved()->create(['request_date' => '2026-10-20']);
    $item = $request->items()->create(['sort_order' => 1, 'product_service' => 'GRASA BLANCA LUBRICANTE MULTIUSO SPRAY 300ML', 'quantity' => 20, 'unit' => 'Unidades']);
    // As Odoo writes it, with the brand stuck on: 0.84 alike with the note's line.
    $invoice = creditDocument(33, '7100520', [['GRASA BLANCA LUBRICANTE MULTIUSO SPRAY 300MLEXCELENTE', 17, 45571], ['FLETE', 1, 5000]], [], '78924030-2');
    $service = app(PurchaseReferences::class);
    $document = $service->document($invoice);
    $line = $service->lines($invoice)->first();
    $service->link($invoice, ['document_line_id' => $line->id, 'purchase_request_item_id' => $item->id, 'invoice_quantity' => '17',
        'request_quantity' => '17', 'version' => $service->fingerprint($document), 'line_version' => $service->lineFingerprint($line),
        'item_version' => $service->itemFingerprint($item)], $this->admin);
    expect((string) $service->usedOnItem($item->id))->toBe('17.000000');

    creditDocument(61, '361827', [['GRASA BLANCA LUBRICANTE MULTIUSO SPRAY 300ML', 2, 5361]], [[33, '7100520', 3]], '78924030-2');
    app()->forgetScopedInstances();

    expect((string) app(PurchaseReferences::class)->usedOnItem($item->id))->toBe('15.000000')
        ->and(app(PurchaseReferences::class)->unbilled($request->fresh())[0]['remaining'])->toBe('5.000000');
    $this->actingAs($this->admin)->getJson(route('gmail.dtes.purchase_references.show', $invoice))
        ->assertJsonPath('references.0.returned', '2.000000')->assertJsonPath('credit_notes.0.folio', '361827');
    expect(DtePurchaseReference::sole()->request_quantity)->toBe('17.000000');
});
