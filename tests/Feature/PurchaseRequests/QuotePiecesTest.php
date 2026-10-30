<?php

use App\Models\PurchaseProductLink;
use App\Models\PurchaseRequestIngestion;
use App\Models\User;
use App\Services\PurchaseRequests\Quotes\QuotationComparison;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

/**
 * Pediste «válvula de pie con canastillo 10"» y Cooprinsem la cotizó en dos
 * renglones. La válvula es la partida; el canastillo, parte de ella.
 */
function valvulaDePie(User $owner, string $hash = 'a'): array
{
    $solicitud = test()->createPurchaseRequestDraft($owner);
    $solicitud->items()->delete();
    $valvula = $solicitud->items()->create(['sort_order' => 1, 'product_service' => 'válvula de pie con canastillo 10"', 'quantity' => 1, 'unit' => 'Unidades']);

    $lectura = PurchaseRequestIngestion::query()->create([
        'user_id' => $owner->getKey(), 'uploader_name_snapshot' => $owner->name,
        'compared_request_id' => $solicitud->getKey(), 'disk' => 'local',
        'path' => 'coop.pdf', 'original_name' => 'coop.pdf', 'mime_type' => 'application/pdf',
        'size' => 10, 'sha256' => str_repeat($hash, 64),
        'status' => PurchaseRequestIngestion::COMPLETED,
        'confirmed_pairings' => ['pares' => [0 => $valvula->getKey()]],
        'extracted' => ['items' => [
            ['product_service' => 'VALVULA RET. HDPE 10"', 'quantity' => '1', 'unit' => 'Unidades', 'unit_price' => '464750'],
            ['product_service' => 'CANASTILLO SUCCION HDPE 10"', 'quantity' => '1', 'unit' => 'Unidades', 'unit_price' => '279625'],
        ]],
    ]);

    return [$solicitud, $lectura, $valvula];
}

function compararLectura($solicitud, PurchaseRequestIngestion $lectura)
{
    return app(QuotationComparison::class)->comparar($solicitud, $lectura->extracted['items'], null,
        $lectura->parejasConfirmadas(), $lectura->parejasRechazadas(), $lectura->piezasConfirmadas());
}

it('attaches a quoted piece to its item instead of leaving it as an extra', function () {
    $owner = User::factory()->create();
    [$solicitud, $lectura, $valvula] = valvulaDePie($owner);

    expect(compararLectura($solicitud, $lectura)->sobrantes)->toHaveCount(1);

    $this->actingAs($owner)
        ->post(route('purchase_requests.quotes.piece', [$solicitud, $lectura]), [
            'quote_line' => 'CANASTILLO SUCCION HDPE 10"', 'item_id' => $valvula->getKey(), 'line_index' => 1,
        ])
        ->assertSessionHas('success', fn (string $m) => str_contains($m, 'es parte de'));

    $resultado = compararLectura($solicitud, $lectura->fresh());

    expect($resultado->sobrantes)->toBeEmpty()
        ->and($resultado->piezasDe($valvula->getKey()))->toHaveCount(1)
        ->and($resultado->filas[0]->cotizada['product_service'])->toBe('VALVULA RET. HDPE 10"')
        // Una pieza no es un sinónimo: el canastillo no es la válvula.
        ->and(PurchaseProductLink::para('CANASTILLO SUCCION HDPE 10"', null))->toBeNull();

    $this->actingAs($owner)->get(route('purchase_requests.show', $solicitud))
        ->assertOk()->assertSee('pieza');
});

it('learns the piece for the next quotation of the same supplier', function () {
    $owner = User::factory()->create();
    [$solicitud, $lectura, $valvula] = valvulaDePie($owner);
    $this->actingAs($owner)->post(route('purchase_requests.quotes.piece', [$solicitud, $lectura]), [
        'quote_line' => 'CANASTILLO SUCCION HDPE 10"', 'item_id' => $valvula->getKey(), 'line_index' => 1,
    ]);

    [$otra, $nueva, $suValvula] = valvulaDePie($owner, 'b');
    $resultado = compararLectura($otra, $nueva);

    expect($resultado->sobrantes)->toBeEmpty()
        ->and($resultado->piezasDe($suValvula->getKey())[0]['aprendida'] ?? null)->toBeTrue();
});

it('puts a piece back among the extras when undone', function () {
    $owner = User::factory()->create();
    [$solicitud, $lectura, $valvula] = valvulaDePie($owner);
    $this->actingAs($owner)->post(route('purchase_requests.quotes.piece', [$solicitud, $lectura]), [
        'quote_line' => 'CANASTILLO SUCCION HDPE 10"', 'item_id' => $valvula->getKey(), 'line_index' => 1,
    ]);

    $this->post(route('purchase_requests.quotes.unpiece', [$solicitud, $lectura]), [
        'quote_line' => 'CANASTILLO SUCCION HDPE 10"', 'line_index' => 1,
    ])->assertSessionHas('success');

    expect(compararLectura($solicitud, $lectura->fresh())->sobrantes)->toHaveCount(1)
        ->and(PurchaseProductLink::piezaDe('CANASTILLO SUCCION HDPE 10"', null))->toBeNull();
});

it('keeps the rejections and the pieces when a pair is confirmed', function () {
    $owner = User::factory()->create();
    [$solicitud, $lectura, $valvula] = valvulaDePie($owner);
    $lectura->guardarDecisiones([], [[5, 99]], [1 => $valvula->getKey()]);

    $this->actingAs($owner)->post(route('purchase_requests.quotes.link', [$solicitud, $lectura]), [
        'quote_line' => 'VALVULA RET. HDPE 10"', 'item_id' => $valvula->getKey(), 'line_index' => 0,
    ]);

    $lectura->refresh();
    expect($lectura->parejasConfirmadas())->toBe([0 => $valvula->getKey()])
        ->and($lectura->parejasRechazadas())->toBe([[5, 99]])
        ->and($lectura->piezasConfirmadas())->toBe([1 => $valvula->getKey()]);
});
