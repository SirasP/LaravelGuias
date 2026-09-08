<?php

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

/**
 * Dar la compra por cerrada.
 *
 * Sin esto una solicitud aprobada se quedaba «Aprobada» para siempre, y la
 * lista no distinguía la que sigue en marcha de la que ya llegó.
 */
function aprobadaDe(User $owner): PurchaseRequest
{
    return PurchaseRequest::factory()->forUser($owner)->approved()->create();
}

it('closes an approved request and leaves it in the history', function () {
    $compras = User::factory()->create(['role' => 'admin']);
    $solicitud = aprobadaDe($compras);

    $this->actingAs($compras)
        ->post(route('purchase_requests.complete', $solicitud), ['comment' => 'Llegó completa el viernes.'])
        ->assertSessionHas('success');

    $solicitud->refresh();

    expect($solicitud->status)->toBe(PurchaseRequestStatus::COMPLETED)
        ->and($solicitud->siguientePaso()[0])->toBe('Terminada')
        ->and($solicitud->events()->where('event_type', PurchaseRequestEvent::COMPLETED)->value('comment'))
        ->toBe('Llegó completa el viernes.');
});

it('can be reopened, because closing by mistake happens', function () {
    $compras = User::factory()->create(['role' => 'admin']);
    $solicitud = aprobadaDe($compras);

    $this->actingAs($compras)->post(route('purchase_requests.complete', $solicitud));
    $this->actingAs($compras)->post(route('purchase_requests.reopen', $solicitud))
        ->assertSessionHas('success');

    expect($solicitud->refresh()->status)->toBe(PurchaseRequestStatus::APPROVED)
        ->and($solicitud->events()->where('event_type', PurchaseRequestEvent::REOPENED)->exists())->toBeTrue();
});

it('only lets Compras close it', function () {
    $owner = User::factory()->create();
    $solicitud = aprobadaDe($owner);

    $this->actingAs(User::factory()->create())
        ->post(route('purchase_requests.complete', $solicitud))
        ->assertForbidden();

    expect($solicitud->refresh()->status)->toBe(PurchaseRequestStatus::APPROVED);
});

it('refuses to close what was never approved', function () {
    $compras = User::factory()->create(['role' => 'admin']);
    $borrador = PurchaseRequest::factory()->forUser($compras)->create();

    // La máquina de estados manda: sólo de aprobada se pasa a terminada.
    $this->actingAs($compras)
        ->post(route('purchase_requests.complete', $borrador))
        ->assertForbidden();
});

it('shows the button on an approved request and not on a draft', function () {
    $compras = User::factory()->create(['role' => 'admin']);

    $this->actingAs($compras)
        ->get(route('purchase_requests.show', aprobadaDe($compras)))
        ->assertOk()
        ->assertSee('Terminada');

    $this->actingAs($compras)
        ->get(route('purchase_requests.show', PurchaseRequest::factory()->forUser($compras)->create()))
        ->assertOk()
        ->assertDontSee('Volver a abrir');
});

it('takes a closed request out of the working list', function () {
    $compras = User::factory()->create(['role' => 'admin']);
    $viva = aprobadaDe($compras);
    $cerrada = aprobadaDe($compras);

    $this->actingAs($compras)->post(route('purchase_requests.complete', $cerrada));

    // La lista sin filtro es el trabajo vivo: una compra cerrada ya no pide
    // nada a nadie y sólo estorba para ver qué queda pendiente.
    $this->actingAs($compras)->get(route('purchase_requests.index'))
        ->assertOk()
        ->assertSee($viva->folio)
        ->assertDontSee($cerrada->folio);

    // Pero sigue estando, en su propio filtro.
    $this->actingAs($compras)->get(route('purchase_requests.index', ['status' => 'completed']))
        ->assertOk()
        ->assertSee($cerrada->folio)
        ->assertDontSee($viva->folio);

    // Y a la vista completa se llega pidiéndola.
    $this->actingAs($compras)
        ->get(route('purchase_requests.index', ['status' => PurchaseRequestStatus::GROUP_ALL]))
        ->assertOk()
        ->assertSee($viva->folio)
        ->assertSee($cerrada->folio);
});

it('counts what the list actually shows', function () {
    $compras = User::factory()->create(['role' => 'admin']);
    aprobadaDe($compras);
    $cerrada = aprobadaDe($compras);

    $this->actingAs($compras)->post(route('purchase_requests.complete', $cerrada));

    // Un número que dijera «2» sobre una lista de una fila estaría mintiendo.
    $this->actingAs($compras)->get(route('purchase_requests.index'))
        ->assertOk()
        ->assertSee('Activas')
        ->assertSee('sin las terminadas');
});

it('gives the closed ones their own tab', function () {
    $compras = User::factory()->create(['role' => 'admin']);
    $cerrada = aprobadaDe($compras);
    $this->actingAs($compras)->post(route('purchase_requests.complete', $cerrada));

    // La pestaña lleva a su lista y trae la cuenta, para saber cuántas hay sin
    // entrar a mirar.
    $this->actingAs($compras)->get(route('purchase_requests.index'))
        ->assertOk()
        ->assertSee('Terminadas')
        ->assertSee(route('purchase_requests.index', ['status' => 'completed']), false);
});
