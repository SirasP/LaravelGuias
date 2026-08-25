<?php

use App\Models\User;
use App\Services\PurchaseRequests\Drafting\DraftSuggestion;
use App\Services\PurchaseRequests\Drafting\NullPurchaseRequestDrafter;
use App\Services\PurchaseRequests\Drafting\PurchaseRequestDrafter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function asistenteDeTexto(DraftSuggestion $sugerencia): void
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

it('turns a sentence into lines without saving anything', function () {
    asistenteDeTexto(DraftSuggestion::of(
        reason: null,
        requestedForName: null,
        items: [
            ['product_service' => 'pañuelos desechables', 'specification' => null, 'quantity' => '2', 'unit' => null],
            ['product_service' => 'confort', 'specification' => null, 'quantity' => '2', 'unit' => null],
        ],
    ));

    $owner = User::factory()->create();

    $respuesta = $this->actingAs($owner)
        ->postJson(route('purchase_requests.ingestions.draft'), ['text' => 'pañuelos desechables 2, confort 2'])
        ->assertOk();

    expect($respuesta->json('available'))->toBeTrue()
        ->and($respuesta->json('items'))->toHaveCount(2)
        ->and($respuesta->json('items.0.product_service'))->toBe('pañuelos desechables');

    // Es una sugerencia en pantalla: no crea nada.
    expect(App\Models\PurchaseRequest::query()->count())->toBe(0);
});

it('requires something to work with', function () {
    asistenteDeTexto(DraftSuggestion::of(null, null, []));

    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->postJson(route('purchase_requests.ingestions.draft'), ['text' => 'ab'])
        ->assertStatus(422);
});

it('says so plainly when the assistant is off', function () {
    app()->bind(PurchaseRequestDrafter::class, fn () => new NullPurchaseRequestDrafter);

    $owner = User::factory()->create();

    $respuesta = $this->actingAs($owner)
        ->postJson(route('purchase_requests.ingestions.draft'), ['text' => 'confort 2'])
        ->assertOk();

    expect($respuesta->json('available'))->toBeFalse()
        ->and($respuesta->json('error'))->toContain('no está habilitado');
});

it('keeps the auditor out', function () {
    asistenteDeTexto(DraftSuggestion::of(null, null, []));

    $auditor = User::factory()->auditor()->create();

    $this->actingAs($auditor)
        ->postJson(route('purchase_requests.ingestions.draft'), ['text' => 'confort 2'])
        ->assertForbidden();
});

it('carries the supplier through when the person named one', function () {
    asistenteDeTexto(DraftSuggestion::of(
        reason: null,
        requestedForName: 'Marco',
        items: [['product_service' => 'cloro', 'specification' => null, 'quantity' => '5', 'unit' => 'Litros']],
        supplier: 'Sodimac',
    ));

    $owner = User::factory()->create();

    $respuesta = $this->actingAs($owner)
        ->postJson(route('purchase_requests.ingestions.draft'), ['text' => '5 litros de cloro en Sodimac, lo pide Marco'])
        ->assertOk();

    expect($respuesta->json('supplier'))->toBe('Sodimac')
        ->and($respuesta->json('requested_for_name'))->toBe('Marco');
});

it('passes the warnings on so nothing is silently wrong', function () {
    asistenteDeTexto(DraftSuggestion::of(
        reason: null,
        requestedForName: null,
        items: [['product_service' => 'escobillones', 'specification' => null, 'quantity' => null, 'unit' => null]],
        warnings: ['Partida N° 1 («escobillones»): falta la cantidad.'],
    ));

    $owner = User::factory()->create();

    $respuesta = $this->actingAs($owner)
        ->postJson(route('purchase_requests.ingestions.draft'), ['text' => 'escobillones'])
        ->assertOk();

    expect($respuesta->json('warnings.0'))->toContain('falta la cantidad');
});
