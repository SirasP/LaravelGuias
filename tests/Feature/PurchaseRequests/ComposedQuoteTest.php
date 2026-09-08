<?php

use App\Models\PurchaseRequestIngestion;
use App\Models\User;
use App\Services\PurchaseRequests\Drafting\DraftSuggestion;
use App\Services\PurchaseRequests\Drafting\PurchaseRequestDrafter;
use App\Services\PurchaseRequests\Quotes\QuotationComparison;
use App\Services\PurchaseRequests\Reading\PurchaseRequestSourceKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

/**
 * La cotización dictada.
 *
 * A veces la compra ya está hecha y la factura está en la mano. Obligar a
 * escanear un papel para anotar cuatro precios que ya se conocen es trabajo
 * inventado, así que se escribe en prosa y el asistente la ordena.
 */
function asistenteQueDevuelve(DraftSuggestion $sugerencia): void
{
    app()->bind(PurchaseRequestDrafter::class, fn () => new class($sugerencia) implements PurchaseRequestDrafter
    {
        public function __construct(private readonly DraftSuggestion $sugerencia) {}

        public function isEnabled(): bool
        {
            return true;
        }

        public function draftFromText(string $text, array $knownUnits = []): DraftSuggestion
        {
            return $this->sugerencia;
        }
    });
}

function solicitudDeDosPartidas(User $owner)
{
    $solicitud = test()->createPurchaseRequestDraft($owner);
    $solicitud->items()->delete();
    $solicitud->items()->create([
        'sort_order' => 1, 'product_service' => 'Correa de ventilador',
        'quantity' => 3, 'unit' => 'Unidades',
    ]);
    $solicitud->items()->create([
        'sort_order' => 2, 'product_service' => 'Filtro de aceite',
        'quantity' => 2, 'unit' => 'Unidades',
    ]);

    return $solicitud->fresh();
}

it('turns what somebody dictated into a quotation that compares', function () {
    Storage::fake('local');
    asistenteQueDevuelve(DraftSuggestion::of(
        reason: null,
        requestedForName: null,
        items: [
            ['product_service' => 'correa de ventilador', 'specification' => null,
                'quantity' => '3', 'unit' => 'Unidades', 'unit_price' => '12500'],
            ['product_service' => 'filtro de aceite', 'specification' => null,
                'quantity' => '2', 'unit' => 'Unidades', 'unit_price' => '8900'],
        ],
    ));

    $owner = User::factory()->create();
    $solicitud = solicitudDeDosPartidas($owner);

    $this->actingAs($owner)
        ->post(route('purchase_requests.quotes.compose', $solicitud), [
            'text' => '3 correas a 12.500 cada una y 2 filtros de aceite a 8.900',
            'supplier_name' => 'MAX SERVICE',
            'document_number' => 'Factura 12345',
            'kind' => 'factura',
        ])
        ->assertSessionHas('success');

    $lectura = PurchaseRequestIngestion::query()->where('compared_request_id', $solicitud->getKey())->firstOrFail();

    expect($lectura->source_kind)->toBe(PurchaseRequestSourceKind::TEXT)
        ->and($lectura->supplier_name)->toBe('MAX SERVICE')
        ->and($lectura->extracted['already_purchased'])->toBeTrue()
        ->and($lectura->extracted['document_number'])->toBe('Factura 12345')
        // Lo dictado se guarda tal cual, igual que se guarda el PDF: si mañana
        // un precio no cuadra, hay dónde ir a mirar qué se dijo.
        ->and(Storage::disk('local')->get($lectura->path))
        ->toBe('3 correas a 12.500 cada una y 2 filtros de aceite a 8.900');

    // Y de ahí en adelante es una cotización como cualquier otra.
    $comparacion = app(QuotationComparison::class)->comparar($solicitud, $lectura->extracted['items']);

    expect($comparacion->cruzadas())->toBe(2)
        ->and($comparacion->filas[0]->cotizada['unit_price'])->toBe('12500');
});

it('says plainly when it understood no line at all', function () {
    Storage::fake('local');
    asistenteQueDevuelve(DraftSuggestion::of(reason: null, requestedForName: null, items: []));

    $owner = User::factory()->create();
    $solicitud = solicitudDeDosPartidas($owner);

    // Nada de inventar partidas para no dejar la pantalla vacía.
    $this->actingAs($owner)
        ->post(route('purchase_requests.quotes.compose', $solicitud), ['text' => 'hola qué tal'])
        ->assertSessionHas('error');

    expect(PurchaseRequestIngestion::query()->count())->toBe(0);
});

it('does not read the same dictation twice', function () {
    Storage::fake('local');
    asistenteQueDevuelve(DraftSuggestion::of(
        reason: null, requestedForName: null,
        items: [['product_service' => 'correa de ventilador', 'specification' => null,
            'quantity' => '3', 'unit' => 'Unidades', 'unit_price' => '12500']],
    ));

    $owner = User::factory()->create();
    $solicitud = solicitudDeDosPartidas($owner);
    $texto = ['text' => '3 correas a 12.500 cada una'];

    $this->actingAs($owner)->post(route('purchase_requests.quotes.compose', $solicitud), $texto);
    $this->actingAs($owner)->post(route('purchase_requests.quotes.compose', $solicitud), $texto)
        ->assertSessionHas('info');

    expect(PurchaseRequestIngestion::query()->count())->toBe(1);
});

it('keeps the dictation out of reach of a request that is not yours', function () {
    asistenteQueDevuelve(DraftSuggestion::of(reason: null, requestedForName: null, items: []));

    $solicitud = solicitudDeDosPartidas(User::factory()->create());

    $this->actingAs(User::factory()->create())
        ->post(route('purchase_requests.quotes.compose', $solicitud), ['text' => 'lo que sea'])
        ->assertForbidden();
});

it('does not call a partial purchase a pile of differences', function () {
    Storage::fake('local');
    asistenteQueDevuelve(DraftSuggestion::of(
        reason: null, requestedForName: null,
        items: [['product_service' => 'correa de ventilador', 'specification' => null,
            'quantity' => '3', 'unit' => 'Unidades', 'unit_price' => '12500']],
    ));

    $owner = User::factory()->create();
    $solicitud = solicitudDeDosPartidas($owner);

    $this->actingAs($owner)->post(route('purchase_requests.quotes.compose', $solicitud), [
        'text' => '3 correas a 12.500', 'kind' => 'factura',
    ]);

    $lectura = PurchaseRequestIngestion::query()->firstOrFail();
    $comparacion = app(QuotationComparison::class)->comparar($solicitud, $lectura->extracted['items']);

    // Una factura que cubre una de dos partidas no tiene una diferencia:
    // tiene una partida que ese documento no toca.
    expect($comparacion->estadoCorto())->toBe('1 sin cotizar');
});
