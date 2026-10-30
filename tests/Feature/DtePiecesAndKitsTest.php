<?php

use App\Models\DtePurchaseReference;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestIngestion;
use App\Models\User;
use App\Services\DtePurchases\PurchaseReferences;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Una partida puede llegar en varias líneas —la válvula de pie, como válvula y
 * canastillo— y varias partidas en una sola —un kit—. Ninguna de las dos debe
 * contar dos veces ni dejar nada «sin solicitud».
 */
beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 28));
    Http::preventStrayRequests();
    $this->admin = User::factory()->admin()->create();
});

/** @param list<array{0: string, 1: float, 2: float}> $lineas descripción, cantidad, precio */
function facturaConLineas(array $lineas, string $rut = '82392600-6'): array
{
    $neto = array_sum(array_map(fn ($l) => $l[1] * $l[2], $lineas));
    $id = DB::connection('fuelcontrol')->table('gmail_dte_documents')->insertGetId([
        'xml_filename' => 'f.xml', 'hash_unico' => (string) Str::uuid(), 'tipo_dte' => 33,
        'folio' => (string) random_int(1000, 99999), 'proveedor_rut' => $rut, 'proveedor_nombre' => 'COOPRINSEM',
        'fecha_factura' => '2026-10-28', 'monto_neto' => $neto, 'monto_iva' => round($neto * 0.19), 'monto_total' => round($neto * 1.19),
        'workflow_status' => 'aceptado', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $ids = [];
    foreach ($lineas as $n => [$descripcion, $cantidad, $precio]) {
        $ids[] = DB::connection('fuelcontrol')->table('gmail_dte_document_lines')->insertGetId([
            'document_id' => $id, 'nro_linea' => $n + 1, 'descripcion' => $descripcion, 'cantidad' => $cantidad,
            'unidad' => 'UN', 'precio_unitario' => $precio, 'monto_item' => $cantidad * $precio,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return [$id, $ids];
}

function vinculo(int $documento, int $linea, int $partida, array $extra = []): array
{
    $service = app(PurchaseReferences::class);
    $item = App\Models\PurchaseRequestItem::find($partida);

    return array_replace([
        'document_line_id' => $linea, 'purchase_request_item_id' => $partida,
        'invoice_quantity' => '1', 'request_quantity' => '1',
        'version' => $service->fingerprint($service->document($documento)),
        'line_version' => $service->lineFingerprint($service->lines($documento)->firstWhere('id', $linea)),
        'item_version' => $service->itemFingerprint($item),
        'operation_key' => (string) Str::uuid(),
    ], $extra);
}

it('bills the foot valve once, with its strainer as a piece, and prices both together', function () {
    $solicitud = PurchaseRequest::factory()->approved()->create(['request_date' => '2026-10-27']);
    $valvula = $solicitud->items()->create(['sort_order' => 1, 'product_service' => 'válvula de pie con canastillo 10"',
        'quantity' => 1, 'unit' => 'Unidades', 'unit_price' => 744375]);

    // Cooprinsem la cotizó en dos renglones, y alguien dijo que el canastillo es parte de ella.
    PurchaseRequestIngestion::create([
        'company_code' => 'EHE', 'user_id' => $solicitud->user_id, 'uploader_name_snapshot' => 'Compras',
        'compared_request_id' => $solicitud->id, 'disk' => 'local', 'path' => 'c.pdf', 'original_name' => 'c.pdf',
        'mime_type' => 'application/pdf', 'size' => 1, 'sha256' => hash('sha256', 'coop'), 'status' => 'completed',
        'supplier_name' => 'COOPRINSEM', 'supplier_tax_id' => '82392600-6',
        'confirmed_pairings' => ['pares' => [0 => $valvula->id], 'piezas' => [1 => $valvula->id]],
        'extracted' => ['items' => [
            ['product_service' => 'VALVULA RET. HDPE 10"', 'quantity' => '1', 'unit' => 'Unidades', 'unit_price' => '464750'],
            ['product_service' => 'CANASTILLO SUCCION HDPE 10"', 'quantity' => '1', 'unit' => 'Unidades', 'unit_price' => '279625'],
        ]],
    ]);

    [$factura] = facturaConLineas([['VALVULA RET. HDPE 10"', 1, 464750], ['CANASTILLO SUCCION HDPE 10"', 1, 279625]]);

    $this->actingAs($this->admin)->getJson(route('gmail.dtes.purchase_references.show', $factura))
        ->assertOk()
        ->assertJsonPath('classification', 'linked')
        ->assertJsonPath('prices.requested', 744375)
        ->assertJsonPath('prices.invoiced', 744375)
        ->assertJsonPath('prices.change', 0)
        ->assertJsonPath('unbilled.'.$solicitud->id, []);

    $pieza = DtePurchaseReference::get()->first(fn ($r) => ($r->snapshot['kind'] ?? null) === 'part');
    expect(DtePurchaseReference::count())->toBe(2)
        ->and($pieza?->snapshot['invoice_product'])->toBe('CANASTILLO SUCCION HDPE 10"')
        ->and((float) $pieza?->request_quantity)->toBe(0.0)
        ->and((float) app(PurchaseReferences::class)->usedOnItem($valvula->id))->toBe(1.0);
});

it('takes a kit line that brings several items, priced together', function () {
    $solicitud = PurchaseRequest::factory()->approved()->create(['request_date' => '2026-10-27']);
    $taladro = $solicitud->items()->create(['sort_order' => 1, 'product_service' => 'taladro percutor', 'quantity' => 1, 'unit' => 'Unidades', 'unit_price' => 50000]);
    $brocas = $solicitud->items()->create(['sort_order' => 2, 'product_service' => 'juego de brocas', 'quantity' => 1, 'unit' => 'Unidades', 'unit_price' => 10000]);
    [$factura, [$linea]] = facturaConLineas([['KIT TALADRO PERCUTOR + BROCAS', 1, 60000]]);

    $this->actingAs($this->admin)->postJson(route('gmail.dtes.purchase_references.store', $factura), vinculo($factura, $linea, $taladro->id))->assertCreated();
    $this->postJson(route('gmail.dtes.purchase_references.store', $factura), vinculo($factura, $linea, $brocas->id, [
        'invoice_quantity' => '0', 'kind' => 'kit',
    ]))->assertCreated();

    $this->getJson(route('gmail.dtes.purchase_references.show', $factura))
        ->assertOk()
        ->assertJsonPath('classification', 'linked')
        ->assertJsonPath('prices.requested', 60000)
        ->assertJsonPath('prices.invoiced', 60000)
        ->assertJsonPath('unbilled.'.$solicitud->id, [])
        ->assertJsonPath('references.0.kit.0', 'juego de brocas');
});

it('refuses a kit item before the kit line has its main item', function () {
    $solicitud = PurchaseRequest::factory()->approved()->create(['request_date' => '2026-10-27']);
    $brocas = $solicitud->items()->create(['sort_order' => 1, 'product_service' => 'juego de brocas', 'quantity' => 1, 'unit' => 'Unidades']);
    [$factura, [$linea]] = facturaConLineas([['KIT TALADRO PERCUTOR + BROCAS', 1, 60000]]);

    $this->actingAs($this->admin)->postJson(route('gmail.dtes.purchase_references.store', $factura), vinculo($factura, $linea, $brocas->id, [
        'invoice_quantity' => '0', 'kind' => 'kit',
    ]))->assertUnprocessable()->assertJsonFragment(['Primero vincula la línea del kit con su partida principal; después, las demás que trae.']);
});

it('keeps a piece from adding quantity to the item', function () {
    $solicitud = PurchaseRequest::factory()->approved()->create(['request_date' => '2026-10-27']);
    $valvula = $solicitud->items()->create(['sort_order' => 1, 'product_service' => 'válvula de pie con canastillo 10"', 'quantity' => 1, 'unit' => 'Unidades']);
    [$factura, [, $canastillo]] = facturaConLineas([['VALVULA RET. HDPE 10"', 1, 464750], ['CANASTILLO SUCCION HDPE 10"', 1, 279625]]);

    $this->actingAs($this->admin)->postJson(route('gmail.dtes.purchase_references.store', $factura), vinculo($factura, $canastillo, $valvula->id, [
        'kind' => 'part',
    ]))->assertUnprocessable()->assertJsonFragment(['Una pieza lleva cantidad en la factura y cero en la solicitud.']);
});
