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

it('copies only the orders that did not come from a request, without writing to Odoo', function () {
    PurchaseRequest::factory()->approved()->create(['odoo_order_id' => 337]);
    $alternative = PurchaseRequest::factory()->approved()->create();
    odooWithOrders([
        odooOrder(337),
        odooOrder(362, ['origin' => $alternative->folio, 'state' => 'sent']),
        odooOrder(350, ['state' => 'purchase', 'receipt_status' => 'pending']),
        odooOrder(351, ['origin' => 'SC-2026-999999']),
    ], [odooLine(9001, 350), odooLine(9002, 350, ['sequence' => 5, 'display_type' => 'line_section', 'product_id' => false, 'name' => 'Fierros'])]);

    $this->artisan('odoo:sync-purchase-orders')->assertSuccessful();

    expect(OdooPurchaseOrder::orderBy('odoo_id')->pluck('odoo_id')->all())->toBe([350, 351]);
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
        ->assertSeeInOrder(['Cotizada a 2 proveedores', 'MARTINEZ Y VALDIVIESO S.A', 'Coagra S.A.'])
        ->assertSee('Transportes Oliva Spa');
});
