<?php

use App\Models\OdooPurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function odooOrder(int $id, array $values = []): array
{
    return array_replace([
        'id' => $id, 'name' => sprintf('P%05d', $id), 'state' => 'draft', 'partner_id' => [7, 'Acenor Aceros Del Norte S.A.'],
        'partner_ref' => false, 'origin' => false, 'currency_id' => [2, 'CLP'], 'amount_untaxed' => 100000, 'amount_total' => 119000,
        'create_uid' => [5, 'Luis Silva Menzel'], 'user_id' => [5, 'Luis Silva Menzel'], 'date_order' => '2026-10-28 18:16:39',
        'invoice_status' => 'no', 'receipt_status' => false, 'notes' => '<p>Para: Luis Silva</p>',
        'create_date' => '2026-10-28 18:16:39', 'write_date' => '2026-10-28 18:20:00',
    ], $values);
}

function odooLine(int $id, int $order, array $values = []): array
{
    return array_replace([
        'id' => $id, 'order_id' => [$order, sprintf('P%05d', $order)], 'sequence' => 10, 'display_type' => false,
        'product_id' => [99, 'TUB CUAD NEG 40 X 3.0 MM'], 'name' => 'TUB CUAD NEG 40 X 3.0 MM', 'product_qty' => 80,
        'product_uom' => [1, 'Unidades'], 'price_unit' => 1250, 'discount' => 0, 'price_subtotal' => 100000,
        'qty_received' => 0, 'qty_invoiced' => 0,
    ], $values);
}

/** Each run is [orders, lines]: Odoo answers the login, the orders and, if any, their lines. */
function odooRuns(array ...$runs): void
{
    config([
        'purchase_requests.odoo.url' => 'https://odoo.example.test', 'purchase_requests.odoo.db' => 'prueba',
        'purchase_requests.odoo.user' => 'quien@ejemplo.cl', 'purchase_requests.odoo.password' => 'secreta',
    ]);
    $responses = [];
    foreach ($runs as $run) {
        [$orders, $lines] = $run;
        // Each supplier's RUT, and the companies of contacts that have none.
        $partners = $run[2] ?? [['id' => 7, 'vat' => '78924030-2', 'commercial_partner_id' => [7, 'Acenor']]];
        $responses[] = Http::response(['jsonrpc' => '2.0', 'result' => 8]);
        $responses[] = Http::response(['jsonrpc' => '2.0', 'result' => $orders]);
        if ($orders !== []) {
            $responses[] = Http::response(['jsonrpc' => '2.0', 'result' => $lines]);
            $responses[] = Http::response(['jsonrpc' => '2.0', 'result' => $partners]);
            if (isset($run[3])) {
                $responses[] = Http::response(['jsonrpc' => '2.0', 'result' => $run[3]]);
            }
        }
    }
    Http::preventStrayRequests();
    Http::fake(['*/jsonrpc' => Http::sequence($responses)]);
}

function odooWithOrders(array $orders, array $lines = [], ?array $partners = null, ?array $companies = null): void
{
    odooRuns(array_values(array_filter([$orders, $lines, $partners, $companies], fn ($value) => $value !== null)));
}

it('copies every order Laravel did not send, including the quotes made in Odoo from a request', function () {
    PurchaseRequest::factory()->approved()->create(['odoo_order_id' => 337]);
    $alternative = PurchaseRequest::factory()->approved()->create();
    // Laravel sent this one from a line item of a request split between suppliers.
    $alternative->items()->create(['sort_order' => 1, 'product_service' => 'Tubo', 'quantity' => 1, 'unit' => 'Unidades', 'odoo_order_id' => 340]);
    odooWithOrders([
        odooOrder(337),
        odooOrder(340, ['origin' => $alternative->folio]),
        // The boss quoted the same request to another supplier straight in Odoo.
        odooOrder(362, ['origin' => $alternative->folio, 'state' => 'sent']),
        odooOrder(350, ['state' => 'purchase', 'receipt_status' => 'pending']),
        odooOrder(351, ['origin' => 'SC-2026-999999']),
    ], [odooLine(9001, 350), odooLine(9002, 350, ['sequence' => 5, 'display_type' => 'line_section', 'product_id' => false, 'name' => 'Fierros'])]);

    $this->artisan('odoo:sync-purchase-orders')->assertSuccessful();

    expect(OdooPurchaseOrder::orderBy('odoo_id')->pluck('odoo_id')->all())->toBe([350, 351, 362])
        ->and(OdooPurchaseOrder::where('odoo_id', 362)->sole()->requestFolio())->toBe($alternative->folio);
    $order = OdooPurchaseOrder::where('odoo_id', 350)->sole();
    expect($order->partner_name)->toBe('Acenor Aceros Del Norte S.A.')
        ->and($order->created_by_name)->toBe('Luis Silva Menzel')
        ->and($order->currency)->toBe('CLP')
        ->and($order->notes)->toBe('Para: Luis Silva')
        // Odoo guarda en UTC; Chile va tres horas atrás en octubre.
        ->and($order->date_order->format('Y-m-d H:i'))->toBe('2026-10-28 15:16')
        ->and($order->lines)->toHaveCount(2)
        ->and($order->lines->first()->isNote())->toBeTrue();
    Http::assertSent(fn ($request) => ! in_array($request['params']['args'][4] ?? null, ['create', 'write', 'unlink'], true));
});

it('forgets an order deleted in Odoo, but not after an empty read', function () {
    OdooPurchaseOrder::create(['odoo_id' => 10, 'name' => 'P00010', 'state' => 'draft']);
    odooRuns([[odooOrder(11)], []], [[], []]);
    $this->artisan('odoo:sync-purchase-orders')->assertSuccessful();
    expect(OdooPurchaseOrder::pluck('odoo_id')->all())->toBe([11]);

    $this->artisan('odoo:sync-purchase-orders')->assertSuccessful();
    expect(OdooPurchaseOrder::pluck('odoo_id')->all())->toBe([11]);
});

it('saves nothing in a dry run', function () {
    odooWithOrders([odooOrder(11)]);
    $this->artisan('odoo:sync-purchase-orders', ['--dry-run' => true])->assertSuccessful();
    expect(OdooPurchaseOrder::count())->toBe(0);
});

it('shows administration the quotations made in Odoo, and hides the tab from everyone else', function () {
    OdooPurchaseOrder::create(['odoo_id' => 1, 'name' => 'P00001', 'state' => 'sent', 'partner_name' => 'COMERCIAL CLIMENT SPA',
        'created_by_name' => 'Luis Silva Menzel', 'odoo_created_at' => now(), 'synced_at' => now()]);
    OdooPurchaseOrder::create(['odoo_id' => 2, 'name' => 'P00002', 'state' => 'purchase', 'partner_name' => 'Transportes Oliva Spa', 'synced_at' => now()]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('purchase_requests.odoo'))->assertOk()
        ->assertSee('COMERCIAL CLIMENT SPA')->assertSee('Nueva')->assertDontSee('Transportes Oliva Spa');
    $this->get(route('purchase_requests.odoo', ['estado' => 'confirmadas']))->assertOk()
        ->assertSee('Transportes Oliva Spa')->assertDontSee('COMERCIAL CLIMENT SPA');
    $this->get(route('purchase_requests.index'))->assertSee(route('purchase_requests.odoo'));

    $other = User::factory()->create();
    $this->actingAs($other)->get(route('purchase_requests.odoo'))->assertForbidden();
});

it('keeps the RUT of each supplier, taking the company RUT for a contact without one', function () {
    odooWithOrders([odooOrder(355, ['partner_id' => [40, 'Coagra S.A., Catalina Zuñiga']])], [],
        [['id' => 40, 'vat' => false, 'commercial_partner_id' => [41, 'Coagra S.A.']]],
        [['id' => 41, 'vat' => 'CL96547510-K']]);

    $this->artisan('odoo:sync-purchase-orders')->assertSuccessful();

    expect(OdooPurchaseOrder::sole()->partner_vat)->toBe('CL96547510-K');
});

it('keeps together the quotations of one purchase sent to several suppliers', function () {
    odooWithOrders([
        odooOrder(352, ['state' => 'purchase', 'purchase_group_id' => [32, 'purchase.order.group,32'], 'partner_id' => [7, 'MARTINEZ Y VALDIVIESO S.A']]),
        odooOrder(355, ['state' => 'cancel', 'purchase_group_id' => [32, 'purchase.order.group,32'], 'partner_id' => [8, 'Coagra S.A.']]),
        odooOrder(338, ['state' => 'purchase', 'partner_id' => [9, 'Transportes Oliva Spa']]),
    ], [], [['id' => 7, 'vat' => '1-9', 'commercial_partner_id' => [7, 'x']], ['id' => 8, 'vat' => '2-7', 'commercial_partner_id' => [8, 'y']], ['id' => 9, 'vat' => '3-5', 'commercial_partner_id' => [9, 'z']]]);
    $this->artisan('odoo:sync-purchase-orders')->assertSuccessful();
    expect(OdooPurchaseOrder::where('odoo_id', 355)->value('purchase_group_id'))->toBe(32);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('purchase_requests.odoo', ['estado' => 'confirmadas']))->assertOk()
        // The cancelled one is not "confirmed", but it travels with its group.
        ->assertSeeInOrder(['se cotizó a 2 proveedores y se compró a 1', '1 cancelada', 'MARTINEZ Y VALDIVIESO S.A', 'Coagra S.A.'])
        ->assertSee('Transportes Oliva Spa');
});

it('opens each order on its own page, with the prices of its group side by side', function () {
    $group = ['purchase_group_id' => 32, 'currency' => 'USD', 'state' => 'purchase', 'synced_at' => now()];
    $cheap = OdooPurchaseOrder::create($group + ['odoo_id' => 353, 'name' => 'P00353', 'partner_odoo_id' => 2, 'partner_name' => 'COPEVAL S.A.', 'amount_untaxed' => 5548.4]);
    $dear = OdooPurchaseOrder::create($group + ['odoo_id' => 352, 'name' => 'P00352', 'partner_odoo_id' => 1, 'partner_name' => 'MARTINEZ Y VALDIVIESO S.A', 'amount_untaxed' => 20043]);
    OdooPurchaseOrder::create(array_replace($group, ['state' => 'cancel']) + ['odoo_id' => 355, 'name' => 'P00355', 'partner_odoo_id' => 3, 'partner_name' => 'Coagra S.A., Catalina Zuñiga', 'amount_untaxed' => 0]);
    $cheap->lines()->create(['odoo_id' => 1, 'name' => 'UREA GRANULADA SACO 25KG', 'product_odoo_id' => 77, 'product_qty' => 100, 'price_unit' => 55.484, 'price_subtotal' => 5548.4]);
    $dear->lines()->create(['odoo_id' => 2, 'name' => 'UREA GRANULADA SACO 25KG', 'product_odoo_id' => 77, 'product_qty' => 400, 'price_unit' => 50.1075, 'price_subtotal' => 20043]);

    $page = $this->actingAs(User::factory()->admin()->create())->get(route('purchase_requests.odoo.show', 353))->assertOk()
        ->assertSee('Cotizada a 3 proveedores')->assertSeeInOrder(['MARTINEZ', 'COPEVAL', 'Coagra']);
    // Per unit, Martínez is the cheapest even though its order is the largest.
    expect($page->getContent())->toMatch('/bg-emerald-100[^"]*">USD 50,11</');
    $this->get(route('purchase_requests.odoo.show', 999))->assertNotFound();
});

it('marks the quotations left open in a group that was already bought', function () {
    $base = ['purchase_group_id' => 29, 'currency' => 'USD', 'synced_at' => now(), 'amount_untaxed' => 4000];
    OdooPurchaseOrder::create($base + ['odoo_id' => 302, 'name' => 'P00302', 'state' => 'purchase', 'partner_odoo_id' => 1, 'partner_name' => 'Florin Y Cia. S.A., Francisca Alvarez']);
    OdooPurchaseOrder::create($base + ['odoo_id' => 300, 'name' => 'P00300', 'state' => 'sent', 'partner_odoo_id' => 2, 'partner_name' => 'COPEVAL S.A.']);
    OdooPurchaseOrder::create($base + ['odoo_id' => 303, 'name' => 'P00303', 'state' => 'sent', 'partner_odoo_id' => 3, 'partner_name' => 'Cooperativa Agricola']);
    // A quotation still waiting for a decision, in a group with nothing bought.
    OdooPurchaseOrder::create(['odoo_id' => 323, 'name' => 'P00323', 'state' => 'draft', 'partner_name' => 'PLASTIGEN SPA', 'purchase_group_id' => 40, 'synced_at' => now()]);
    $admin = User::factory()->admin()->create();

    expect(OdooPurchaseOrder::pendingQuotations()->pluck('name')->all())->toBe(['P00323'])
        ->and(OdooPurchaseOrder::leftOpen()->orderBy('name')->pluck('name')->all())->toBe(['P00300', 'P00303']);
    $this->actingAs($admin)->get(route('purchase_requests.odoo', ['estado' => 'abiertas']))->assertOk()
        ->assertSee('se cotizó a 3 proveedores y se compró a 1 · 2 quedaron abiertas')->assertSee('Quedó abierta')->assertDontSee('PLASTIGEN');
    $this->get(route('purchase_requests.odoo.show', 300))->assertSee('Esta cotización quedó abierta.')->assertSee('P00302 (Florin Y Cia. S.A.)');
    $this->get(route('purchase_requests.odoo.show', 323))->assertDontSee('quedó abierta');
});

it('shows which request a quote made in Odoo came from', function () {
    $request = PurchaseRequest::factory()->approved()->create();
    $base = ['purchase_group_id' => 34, 'origin' => $request->folio, 'state' => 'sent', 'synced_at' => now()];
    OdooPurchaseOrder::create($base + ['odoo_id' => 371, 'name' => 'P00371', 'partner_odoo_id' => 1, 'partner_name' => 'AUSTRAL RIEGO SPA']);
    OdooPurchaseOrder::create($base + ['odoo_id' => 373, 'name' => 'P00373', 'partner_odoo_id' => 2, 'partner_name' => 'COMERCIAL CLIMENT SPA']);

    $this->actingAs(User::factory()->admin()->create())->get(route('purchase_requests.odoo'))->assertOk()
        ->assertSee('de la '.$request->folio)->assertSee(route('purchase_requests.show', $request));
    $this->get(route('purchase_requests.odoo.show', 371))->assertSee(route('purchase_requests.show', $request));
});
