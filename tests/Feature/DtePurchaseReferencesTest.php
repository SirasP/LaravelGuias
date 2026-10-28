<?php

use App\Models\DtePurchaseReference;
use App\Models\OdooAccountMove;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestIngestion;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use App\Services\DtePurchases\OdooPurchaseReferenceSync;
use App\Services\DtePurchases\PurchaseReferences;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 28));
    Http::preventStrayRequests();
    $this->admin = User::factory()->admin()->create();
});

function referenceInvoice(array $overrides = [], array $line = []): array
{
    // Distinct invoice numbers unless a test deliberately asks for a duplicate.
    $folio = (string) (79820 + DB::connection('fuelcontrol')->table('gmail_dte_documents')->count());
    $id = DB::connection('fuelcontrol')->table('gmail_dte_documents')->insertGetId(array_replace([
        'xml_filename' => 'ficticio.xml', 'hash_unico' => (string) Str::uuid(), 'tipo_dte' => 33,
        'folio' => $folio, 'proveedor_rut' => '76569041-2', 'proveedor_nombre' => 'PROVEEDOR A',
        'fecha_factura' => '2026-10-28', 'monto_neto' => 400, 'monto_iva' => 76, 'monto_total' => 476,
        'workflow_status' => 'aceptado', 'created_at' => now(), 'updated_at' => now(),
    ], $overrides));
    $lineId = DB::connection('fuelcontrol')->table('gmail_dte_document_lines')->insertGetId(array_replace([
        'document_id' => $id, 'nro_linea' => 1, 'descripcion' => 'SPRAY BLANCO', 'cantidad' => 4,
        'unidad' => 'UN', 'precio_unitario' => 100, 'monto_item' => 400, 'created_at' => now(), 'updated_at' => now(),
    ], $line));

    return [$id, $lineId];
}

function referenceItem(?string $rut = '76569041-2', array $values = [], ?PurchaseRequest $request = null): PurchaseRequestItem
{
    $request ??= PurchaseRequest::factory()->approved()->create(['request_date' => '2026-10-27']);
    $item = $request->items()->create(array_replace([
        'sort_order' => $request->items()->count() + 1, 'product_service' => 'SPRAY BLANCO',
        'quantity' => 10, 'unit' => 'Unidades',
    ], $values));
    if ($rut) {
        PurchaseRequestIngestion::create([
            'company_code' => 'EHE', 'user_id' => $request->user_id, 'uploader_name_snapshot' => 'Compras',
            'compared_request_id' => $request->id, 'disk' => 'local', 'path' => 'ficticio.pdf',
            'original_name' => 'ficticio.pdf', 'mime_type' => 'application/pdf', 'size' => 1,
            'sha256' => hash('sha256', (string) Str::uuid()), 'status' => 'completed',
            'supplier_name' => 'PROVEEDOR', 'supplier_tax_id' => $rut,
            'confirmed_pairings' => [0 => $item->id],
            'extracted' => ['items' => [['product_service' => $item->product_service, 'quantity' => $item->quantity, 'unit' => $item->unit]]],
        ]);
    }

    return $item->fresh();
}

function referencePayload(int $document, int $line, PurchaseRequestItem $item, array $overrides = []): array
{
    $service = app(PurchaseReferences::class);

    return array_replace([
        'document_line_id' => $line, 'purchase_request_item_id' => $item->id,
        'invoice_quantity' => '4', 'request_quantity' => '4',
        'version' => $service->fingerprint($service->document($document)),
        'line_version' => $service->lineFingerprint($service->lines($document)->firstWhere('id', $line)),
        'item_version' => $service->itemFingerprint($item),
        'operation_key' => (string) Str::uuid(), 'acknowledge' => true, 'note' => 'Compra verificada con los antecedentes del proveedor.',
    ], $overrides);
}

function referencePost(int $document, int $line, PurchaseRequestItem $item, array $overrides = [])
{
    return test()->actingAs(test()->admin)->postJson(route('gmail.dtes.purchase_references.store', $document), referencePayload($document, $line, $item, $overrides));
}

it('keeps an unmatched invoice unidentified and makes reads without side effects', function () {
    [$id] = referenceInvoice();
    $this->actingAs($this->admin)->getJson(route('gmail.dtes.purchase_references.show', $id))
        ->assertOk()->assertJsonPath('classification', 'unidentified')->assertJsonCount(0, 'references');
    expect(DB::table('dte_purchase_decisions')->count())->toBe(0)
        ->and(DB::table('dte_purchase_events')->count())->toBe(0);
    Http::assertNothingSent();
});

it('does not expose purchasing relationships or mutations to other roles', function (string $role) {
    [$id, $line] = referenceInvoice();
    $item = referenceItem();
    $user = User::factory()->create(['role' => $role]);
    $this->actingAs($user)->getJson(route('gmail.dtes.purchase_references.show', $id))->assertForbidden();
    $this->getJson(route('gmail.dtes.purchase_references.candidates', $id))->assertForbidden();
    $this->postJson(route('gmail.dtes.purchase_references.store', $id), referencePayload($id, $line, $item))->assertForbidden();
    $this->postJson(route('gmail.dtes.purchase_references.classify', $id), [])->assertForbidden();
})->with(['bodeguero', 'viewer', 'comprador', 'auditor']);

it('does not suggest another supplier just because spray blanco appears in both documents', function () {
    [$id] = referenceInvoice();
    referenceItem('76637999-0');
    referenceItem(null);
    $this->actingAs($this->admin)->getJson(route('gmail.dtes.purchase_references.candidates', $id))
        ->assertOk()->assertJsonCount(0, 'candidates');
    $correct = referenceItem();
    $this->getJson(route('gmail.dtes.purchase_references.candidates', $id))
        ->assertOk()->assertJsonCount(1, 'candidates')->assertJsonPath('candidates.0.item_id', $correct->id);
    expect(DtePurchaseReference::count())->toBe(0);
});

it('respects the supplier of each item when one request is split in two', function () {
    $request = PurchaseRequest::factory()->approved()->create(['request_date' => '2026-10-27']);
    $pvc = referenceItem('76637999-0', ['product_service' => 'UNION PVC 50MM'], $request);
    $spray = referenceItem('76569041-2', [], $request);
    [$a, $aLine] = referenceInvoice(['proveedor_rut' => '76637999-0'], ['descripcion' => 'UNION PVC 50MM']);
    [$b, $bLine] = referenceInvoice();
    $this->actingAs($this->admin)->getJson(route('gmail.dtes.purchase_references.candidates', $a))
        ->assertJsonCount(1, 'candidates')->assertJsonPath('candidates.0.item_id', $pvc->id);
    $this->getJson(route('gmail.dtes.purchase_references.candidates', $b))
        ->assertJsonCount(1, 'candidates')->assertJsonPath('candidates.0.item_id', $spray->id);
    referencePost($a, $aLine, $pvc)->assertCreated();
    referencePost($b, $bLine, $spray)->assertCreated();
    $invoices = app(PurchaseReferences::class)->forRequest($request);
    expect($invoices)->toHaveCount(2)
        ->and($invoices[0]['references'])->toHaveCount(1)
        ->and($invoices[1]['references'])->toHaveCount(1);
});

it('allocates one invoice between requests without claiming the whole invoice twice', function () {
    [$id, $line] = referenceInvoice([], ['cantidad' => 10]);
    $first = referenceItem(null, ['quantity' => 4]);
    $second = referenceItem(null, ['quantity' => 6]);
    referencePost($id, $line, $first)->assertCreated();
    $this->getJson(route('gmail.dtes.purchase_references.show', $id))->assertJsonPath('classification', 'partial');
    referencePost($id, $line, $second, ['invoice_quantity' => '6', 'request_quantity' => '6'])->assertCreated();
    $this->getJson(route('gmail.dtes.purchase_references.show', $id))->assertJsonPath('classification', 'linked')->assertJsonCount(2, 'references');
    referencePost($id, $line, referenceItem(), ['invoice_quantity' => '1', 'request_quantity' => '1'])->assertUnprocessable();
});

it('reserves quantities across partial invoices and releases them when a reference is revoked', function () {
    $item = referenceItem();
    [$first, $lineA] = referenceInvoice([], ['cantidad' => 6]);
    [$second, $lineB] = referenceInvoice();
    [$third, $lineC] = referenceInvoice([], ['cantidad' => 1]);
    $id = referencePost($first, $lineA, $item, ['invoice_quantity' => '6', 'request_quantity' => '6'])->assertCreated()->json('id');
    referencePost($second, $lineB, $item)->assertCreated();
    referencePost($third, $lineC, $item, ['invoice_quantity' => '1', 'request_quantity' => '1'])->assertUnprocessable();
    $this->deleteJson(route('gmail.dtes.purchase_references.destroy', [$first, $id]), ['note' => 'Se vinculó otra compra.'])->assertOk();
    referencePost($third, $lineC, $item, ['invoice_quantity' => '1', 'request_quantity' => '1'])->assertCreated();
    expect(DtePurchaseReference::find($id)->revoked_at)->not->toBeNull()
        ->and(DB::table('dte_purchase_events')->where('event', 'revoked')->count())->toBe(1);
});

it('is idempotent when the browser retries a partial allocation', function () {
    [$id, $line] = referenceInvoice([], ['cantidad' => 10]);
    $item = referenceItem();
    $payload = referencePayload($id, $line, $item, ['invoice_quantity' => '3', 'request_quantity' => '3']);
    $url = route('gmail.dtes.purchase_references.store', $id);
    $first = $this->actingAs($this->admin)->postJson($url, $payload)->assertCreated()->json('id');
    $this->postJson($url, $payload)->assertCreated()->assertJsonPath('id', $first);
    expect(DtePurchaseReference::count())->toBe(1)->and(DB::table('dte_purchase_events')->count())->toBe(1);
});

it('counts fractional quantities exactly', function () {
    [$id, $line] = referenceInvoice([], ['cantidad' => '0.3']);
    $item = referenceItem(null, ['quantity' => '0.3']);
    referencePost($id, $line, $item, ['invoice_quantity' => '0.1', 'request_quantity' => '0.1'])->assertCreated();
    referencePost($id, $line, $item, ['invoice_quantity' => '0.2', 'request_quantity' => '0.2'])->assertCreated();
    $this->getJson(route('gmail.dtes.purchase_references.show', $id))->assertJsonPath('classification', 'linked');
});

it('requires explicit supplier and presentation confirmation when the evidence is missing', function () {
    [$id, $line] = referenceInvoice();
    $item = referenceItem(null, ['product_service' => 'cajas de 12 spray blanco', 'unit' => 'cajas']);
    referencePost($id, $line, $item, ['acknowledge' => false, 'note' => ''])->assertUnprocessable();
    referencePost($id, $line, $item, ['request_quantity' => '0.333333', 'acknowledge' => true, 'note' => 'Se compraron cuatro unidades sueltas, equivalentes a un tercio de caja.'])->assertCreated();
    expect(DtePurchaseReference::first()->invoice_quantity)->toBe('4.000000')
        ->and(DtePurchaseReference::first()->request_quantity)->toBe('0.333333');
});

it('does not allocate a duplicate import of the same invoice twice', function () {
    [$id, $line] = referenceInvoice(['folio' => '111']);
    [$duplicate, $duplicateLine] = referenceInvoice(['folio' => '111']);
    $item = referenceItem();
    referencePost($id, $line, $item)->assertCreated();
    referencePost($duplicate, $duplicateLine, $item)->assertUnprocessable();
});

it('recognizes the same issuer folio despite leading zeros or conflicting date and total', function () {
    [$id, $line] = referenceInvoice(['folio' => '111']);
    [$duplicate, $duplicateLine] = referenceInvoice(['folio' => '000111', 'fecha_factura' => '2026-10-29', 'monto_total' => 999]);
    $item = referenceItem();
    referencePost($id, $line, $item)->assertCreated();
    referencePost($duplicate, $duplicateLine, referenceItem())->assertUnprocessable();
});

it('rejects a source line from another invoice and invalid decimal quantities', function () {
    [$id, $line] = referenceInvoice();
    [, $otherLine] = referenceInvoice();
    $item = referenceItem();
    referencePost($id, $line, $item, ['document_line_id' => $otherLine])->assertUnprocessable();
    foreach (['0', '-1', '1.0000001', 'NaN', 'INF'] as $quantity) {
        referencePost($id, $line, $item, ['invoice_quantity' => $quantity])->assertUnprocessable();
    }
});

it('requires a fresh review when the invoice or requested item changes', function () {
    [$id, $line] = referenceInvoice();
    $item = referenceItem();
    $payload = referencePayload($id, $line, $item);
    $item->update(['product_service' => 'SPRAY ROJO']);
    $this->actingAs($this->admin)->postJson(route('gmail.dtes.purchase_references.store', $id), $payload)->assertUnprocessable();
    $item->refresh();
    $payload = referencePayload($id, $line, $item);
    DB::connection('fuelcontrol')->table('gmail_dte_document_lines')->where('id', $line)->update(['cantidad' => 3]);
    $this->postJson(route('gmail.dtes.purchase_references.store', $id), $payload)->assertUnprocessable();
});

it('flags existing references for review if their source changes and keeps the audit trail', function () {
    [$id, $line] = referenceInvoice();
    $item = referenceItem();
    referencePost($id, $line, $item)->assertCreated();
    $item->update(['quantity' => 3]);
    $this->getJson(route('gmail.dtes.purchase_references.show', $id))
        ->assertJsonPath('references.0.stale', true)->assertJsonPath('classification', 'partial');
    $item->delete();
    $this->getJson(route('gmail.dtes.purchase_references.show', $id))->assertOk()->assertJsonPath('references.0.stale', true);
});

it('requires orphaned allocations to be resolved before linking replacement request items', function () {
    [$id, $line] = referenceInvoice();
    $item = referenceItem();
    $request = $item->purchaseRequest;
    referencePost($id, $line, $item)->assertCreated();
    $item->delete();
    $replacement = referenceItem(null, [], $request);
    [$next, $nextLine] = referenceInvoice();
    referencePost($next, $nextLine, $replacement)->assertUnprocessable();
});

it('distinguishes a confirmed direct purchase from an unsuccessful search and allows reconsideration', function () {
    [$id, $line] = referenceInvoice();
    $service = app(PurchaseReferences::class);
    $payload = ['classification' => 'without_request', 'version' => $service->fingerprint($service->document($id)), 'note' => 'Compra directa confirmada por Compras.'];
    $url = route('gmail.dtes.purchase_references.classify', $id);
    $this->actingAs($this->admin)->postJson($url, $payload)->assertOk();
    $this->getJson(route('gmail.dtes.purchase_references.show', $id))->assertJsonPath('classification', 'without_request');
    $item = referenceItem();
    referencePost($id, $line, $item)->assertUnprocessable();
    $this->postJson($url, array_replace($payload, ['classification' => 'unidentified', 'note' => 'Se encontró una solicitud y debe revisarse.']))->assertOk();
    referencePost($id, $line, $item)->assertCreated();
    $this->postJson($url, $payload)->assertUnprocessable();
});

it('marks a direct-purchase decision stale when the invoice identity changes', function () {
    [$id] = referenceInvoice();
    $service = app(PurchaseReferences::class);
    $this->actingAs($this->admin)->postJson(route('gmail.dtes.purchase_references.classify', $id), [
        'classification' => 'without_request', 'version' => $service->fingerprint($service->document($id)), 'note' => 'Compra directa confirmada.',
    ])->assertOk();
    DB::connection('fuelcontrol')->table('gmail_dte_documents')->where('id', $id)->update(['folio' => '12345']);
    $this->getJson(route('gmail.dtes.purchase_references.show', $id))->assertJsonPath('decision_stale', true);
});

it('rejects drafts, future requests, other companies and cancelled invoices', function (string $case) {
    [$id, $line] = referenceInvoice();
    $item = referenceItem();
    match ($case) {
        'draft' => $item->purchaseRequest->update(['status' => 'draft']),
        'future' => $item->purchaseRequest->update(['request_date' => '2026-10-29']),
        'company' => $item->purchaseRequest->update(['company_code' => 'OTHER']),
        'cancelled' => DB::connection('fuelcontrol')->table('gmail_dte_documents')->where('id', $id)->update(['workflow_status' => 'anulado']),
    };
    referencePost($id, $line, $item)->assertUnprocessable();
})->with(['draft', 'future', 'company', 'cancelled']);

it('writes relationships only in the requests database, leaving DTE and inventory data untouched', function () {
    [$id, $line] = referenceInvoice();
    $item = referenceItem();
    $fuelWrites = [];
    DB::listen(function ($query) use (&$fuelWrites) {
        if ($query->connectionName === 'fuelcontrol' && preg_match('/^\s*(insert|update|delete|alter|drop)/i', $query->sql)) {
            $fuelWrites[] = $query->sql;
        }
    });
    referencePost($id, $line, $item)->assertCreated();
    expect($fuelWrites)->toBe([]);
    Http::assertNothingSent();
});

function referenceOdoo(array $change = []): array
{
    [$id, $line] = referenceInvoice();
    $item = referenceItem(null, ['odoo_order_id' => 800, 'odoo_line_id' => 900]);
    OdooAccountMove::create([
        'odoo_id' => 500, 'name' => 'FAC 79820', 'folio' => 79820, 'move_type' => 'in_invoice',
        'state' => 'posted', 'partner_vat' => '76569041-2', 'invoice_date' => '2026-10-28', 'amount_total' => 476,
    ]);
    config([
        'dte_purchase_references.odoo_sync_enabled' => true,
        'odoo.url' => 'https://odoo.example.test', 'odoo.db' => 'qa', 'odoo.user' => 'local@example.test', 'odoo.password' => 'fake-test-only',
        'purchase_requests.odoo.url' => 'https://odoo.example.test', 'purchase_requests.odoo.db' => 'qa',
    ]);
    $fixtures = array_replace_recursive([
        'account.move' => [[
            'id' => 500, 'move_type' => 'in_invoice', 'state' => 'posted', 'partner_id' => [11, 'PROVEEDOR A'],
            'company_id' => [1, 'EHE'], 'currency_id' => [1, 'CLP'], 'invoice_date' => '2026-10-28',
            'amount_total' => 476, 'invoice_line_ids' => [700], 'l10n_latam_document_number' => '79820', 'l10n_latam_document_type_id' => [33, 'Factura'],
        ]],
        'res.partner' => [['id' => 11, 'vat' => 'CL765690412']],
        'res.company' => [['id' => 1, 'vat' => '77415879-0']],
        'l10n_latam.document.type' => [['id' => 33, 'code' => '33']],
        'account.move.line' => [[
            'id' => 700, 'name' => 'SPRAY BLANCO', 'quantity' => 4, 'price_unit' => 100,
            'product_uom_id' => [1, 'Units'], 'purchase_line_id' => [900, 'SPRAY'], 'product_id' => [99, 'SPRAY'],
        ]],
        'purchase.order.line' => [[
            'id' => 900, 'order_id' => [800, 'P00800'], 'product_uom' => [1, 'Units'], 'product_qty' => 10, 'product_id' => [99, 'SPRAY'],
        ]],
        'purchase.order' => [[
            'id' => 800, 'name' => 'P00800', 'origin' => $item->purchaseRequest->folio,
            'partner_id' => [11, 'PROVEEDOR A'], 'company_id' => [1, 'EHE'], 'state' => 'purchase',
        ]],
    ], $change);
    Http::fake(['https://odoo.example.test/jsonrpc' => function ($request) use ($fixtures) {
        if ($request['params']['service'] === 'common') {
            return Http::response(['result' => 7]);
        }
        $args = $request['params']['args'];
        expect($args[4])->toBe('read');

        return Http::response(['result' => $fixtures[$args[3]]]);
    }]);

    return [$id, $line, $item];
}

it('automatically links only a complete verified Odoo provenance and remains idempotent', function () {
    [$id] = referenceOdoo();
    $service = app(OdooPurchaseReferenceSync::class);
    expect($service->sync($id)['linked'])->toBe(1)
        ->and($service->sync($id)['linked'])->toBe(0)
        ->and(DtePurchaseReference::count())->toBe(1)
        ->and(DtePurchaseReference::first()->source)->toBe('odoo');
    $this->actingAs($this->admin)->getJson(route('gmail.dtes.purchase_references.show', $id))
        ->assertOk()->assertJsonPath('references.0.stale', false);
});

it('does not recreate an automatic relationship a person revoked', function () {
    [$id] = referenceOdoo();
    $service = app(OdooPurchaseReferenceSync::class);
    $service->sync($id);
    $ref = DtePurchaseReference::first();
    app(PurchaseReferences::class)->revoke($id, $ref->id, $this->admin, 'La factura pertenece a otra compra.');
    expect($service->sync($id)['linked'])->toBe(0)
        ->and(app(PurchaseReferences::class)->active()->count())->toBe(0)
        ->and(DtePurchaseReference::count())->toBe(1);
});

it('runs automatic verification in dry-run without persisting decisions or audit events', function () {
    [$id] = referenceOdoo();
    expect(app(OdooPurchaseReferenceSync::class)->sync($id, true)['linked'])->toBe(1)
        ->and(DtePurchaseReference::count())->toBe(0)
        ->and(DB::table('dte_purchase_decisions')->count())->toBe(0)
        ->and(DB::table('dte_purchase_events')->count())->toBe(0);
});

it('refuses automatic inference when a provenance check fails', function (array $change) {
    [$id] = referenceOdoo($change);
    expect(app(OdooPurchaseReferenceSync::class)->sync($id)['linked'])->toBe(0)
        ->and(DtePurchaseReference::count())->toBe(0);
})->with([
    'supplier' => [['res.partner' => [['vat' => '76637999-0']]]],
    'company' => [['res.company' => [['vat' => '76569041-2']]]],
    'document type' => [['l10n_latam.document.type' => [['code' => '61']]]],
    'date' => [['account.move' => [['invoice_date' => '2026-10-27']]]],
    'amount' => [['account.move' => [['amount_total' => 477]]]],
    'draft invoice' => [['account.move' => [['state' => 'draft']]]],
    'order supplier' => [['purchase.order' => [['partner_id' => [12, 'OTHER']]]]],
    'request origin' => [['purchase.order' => [['origin' => 'SC-2026-999999']]]],
    'changed order quantity' => [['purchase.order.line' => [['product_qty' => 9]]]],
    'different units' => [['account.move.line' => [['product_uom_id' => [2, 'Cajas']]]]],
]);

it('does not mix IDs from QA and production', function () {
    [$id] = referenceOdoo();
    config(['purchase_requests.odoo.db' => 'production']);
    expect(app(OdooPurchaseReferenceSync::class)->sync($id)['linked'])->toBe(0);
    Http::assertNothingSent();
});

it('requires review when an automatic verification becomes old or its origin is changed', function () {
    [$id] = referenceOdoo();
    app(OdooPurchaseReferenceSync::class)->sync($id);
    $this->travel(8)->days();
    expect(app(PurchaseReferences::class)->report($id)['references'][0]['stale'])->toBeTrue();
    $this->travelBack();
    Http::swap(new \Illuminate\Http\Client\Factory);
    Http::preventStrayRequests();
    Http::fake(['https://odoo.example.test/jsonrpc' => Http::sequence()->push(['result' => 7])->push(['result' => [[
        'id' => 500, 'partner_id' => [11, 'A'], 'company_id' => [1, 'EHE'], 'l10n_latam_document_type_id' => [33, 'Factura'],
        'move_type' => 'in_invoice', 'state' => 'cancel',
    ]]])->push(['result' => [['id' => 11, 'vat' => '76569041-2']]])->push(['result' => [['id' => 1, 'vat' => '77415879-0']]])->push(['result' => [['id' => 33, 'code' => '33']]])]);
    app(OdooPurchaseReferenceSync::class)->sync($id);
    expect(app(PurchaseReferences::class)->report($id)['references'][0]['stale'])->toBeTrue();
});
