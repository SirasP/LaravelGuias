<?php

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestEvent;
use App\Models\PurchaseRequestIngestion;
use App\Models\User;
use App\Services\PurchaseRequests\Reading\PurchaseRequestSourceKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

beforeEach(fn () => Storage::fake('local'));

/** Crea una solicitud como lo hace el formulario después de dictarle al asistente. */
function crearDictada(array $extra, array $item = []): PurchaseRequest
{
    test()->actingAs(User::factory()->create())
        ->post(route('purchase_requests.store'), test()->validPurchaseRequestPayload(array_merge([
            'reason' => 'ASPA VENTILADOR',
            'items' => [test()->validPurchaseRequestItem(array_merge([
                'product_service' => 'ASPA VENTILADOR', 'specification' => null,
                'quantity' => '6', 'unit' => 'Unidades', 'unit_price' => '49594',
            ], $item))],
        ], $extra)))
        ->assertRedirect();

    return PurchaseRequest::query()->latest('id')->firstOrFail();
}

it('marks a request whose items the assistant wrote, and keeps what was said', function () {
    // SC-2026-000111: se escribió en el formulario y no quedó rastro de eso.
    $solicitud = crearDictada(['drafted_text' => '6 aspas de ventilador a 49.594']);

    $evento = $solicitud->events()->where('event_type', PurchaseRequestEvent::DICTATED)->first();

    expect($solicitud->isDictated())->toBeTrue()
        ->and($evento?->comment)->toBe('6 aspas de ventilador a 49.594')
        ->and($evento?->label())->toBe('Dictada al asistente');

    $this->get(route('purchase_requests.show', $solicitud))->assertSee('Dictada');
});

it('saves what was dictated as that supplier\'s quotation when it names one and has prices', function () {
    $solicitud = crearDictada([
        'drafted_text' => '6 aspas de ventilador a Corsud, a 49.594 cada una',
        'drafted_supplier' => 'Corsud',
    ]);

    $cotizacion = PurchaseRequestIngestion::query()->where('compared_request_id', $solicitud->getKey())->sole();

    expect($cotizacion->supplier_name)->toBe('Corsud')
        ->and($cotizacion->source_kind)->toBe(PurchaseRequestSourceKind::TEXT)
        ->and($cotizacion->status)->toBe(PurchaseRequestIngestion::COMPLETED)
        ->and($cotizacion->extracted['items'])->toBe([[
            'product_service' => 'ASPA VENTILADOR', 'specification' => null,
            'quantity' => '6', 'unit' => 'Unidades', 'unit_price' => '49594',
        ]])
        ->and(Storage::disk('local')->get($cotizacion->path))->toBe('6 aspas de ventilador a Corsud, a 49.594 cada una');
});

it('does not invent a quotation without a supplier or without prices', function () {
    // Sin proveedor: es lo que se necesita, no lo que alguien ofreció.
    $sinProveedor = crearDictada(['drafted_text' => '6 aspas de ventilador a 49.594']);
    // Sin precio: tampoco hay nada que respaldar.
    $sinPrecio = crearDictada(['drafted_text' => '6 aspas de ventilador en Corsud', 'drafted_supplier' => 'Corsud'], ['unit_price' => null]);

    expect(PurchaseRequestIngestion::query()->whereIn('compared_request_id', [$sinProveedor->getKey(), $sinPrecio->getKey()])->count())->toBe(0)
        ->and($sinPrecio->isDictated())->toBeTrue();
});

it('leaves a request filled by hand exactly as before', function () {
    $solicitud = crearDictada([]);

    expect($solicitud->isDictated())->toBeFalse()
        ->and(PurchaseRequestIngestion::query()->count())->toBe(0);
});
