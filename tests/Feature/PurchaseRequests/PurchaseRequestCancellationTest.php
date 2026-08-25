<?php

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequestEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

it('does not register the same cancellation request twice', function () {
    // Encontrado en producción: un trabajador pidió anular dos veces con
    // cuatro segundos de diferencia y quedaron dos eventos idénticos.
    $owner = User::factory()->create();
    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    foreach (range(1, 3) as $intento) {
        $this->actingAs($owner)
            ->post(route('purchase_requests.request_cancellation', $request), ['comment' => 'Ya no la necesito.'])
            ->assertSessionHasNoErrors();
    }

    $eventos = $request->events()
        ->where('event_type', PurchaseRequestEvent::CANCELLATION_REQUESTED)
        ->count();

    expect($eventos)->toBe(1);
});

it('warns the reviewer that a cancellation was requested', function () {
    // El caso real: se aprobó una solicitud 30 segundos después de que el
    // solicitante pidiera anularla, sin que la pantalla lo advirtiera.
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    $this->actingAs($owner)->post(route('purchase_requests.request_cancellation', $request), [
        'comment' => 'Me equivoqué al pedirla.',
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->get(route('purchase_requests.show', $request))
        ->assertOk()
        ->assertSee('pidió anular esta solicitud', false)
        ->assertSee('Me equivoqué al pedirla.');
});

it('clears a pending cancellation request once the request is resolved', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    $this->actingAs($owner)->post(route('purchase_requests.request_cancellation', $request), [
        'comment' => 'Ya no la necesito.',
    ]);

    expect($request->fresh()->cancellation_requested_at)->not->toBeNull();

    // El revisor decide aprobar de todos modos: la petición deja de estar
    // pendiente, porque ya fue resuelta.
    $this->actingAs($admin)->post(route('purchase_requests.approve', $request), [
        'lock_version' => (string) $request->fresh()->lock_version,
    ])->assertSessionHasNoErrors();

    $request->refresh();

    expect($request->status)->toBe(PurchaseRequestStatus::APPROVED)
        ->and($request->cancellation_requested_at)->toBeNull();

    // Pero la petición sigue registrada en el historial.
    expect($request->events()->where('event_type', PurchaseRequestEvent::CANCELLATION_REQUESTED)->exists())
        ->toBeTrue();
});

it('lets the requester withdraw a cancellation request', function () {
    $owner = User::factory()->create();
    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    $this->actingAs($owner)->post(route('purchase_requests.request_cancellation', $request), [
        'comment' => 'Me apuré.',
    ]);

    expect($request->fresh()->cancellation_requested_at)->not->toBeNull();

    $this->actingAs($owner)
        ->post(route('purchase_requests.withdraw_cancellation', $request))
        ->assertSessionHasNoErrors();

    expect($request->fresh()->cancellation_requested_at)->toBeNull();
});

it('does not accept a cancellation request on a resolved request', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    $this->actingAs($admin)->post(route('purchase_requests.approve', $request), [
        'lock_version' => (string) $request->lock_version,
    ]);

    $this->actingAs($owner)
        ->post(route('purchase_requests.request_cancellation', $request), ['comment' => 'Tarde.'])
        ->assertForbidden();

    expect($request->fresh()->cancellation_requested_at)->toBeNull();
});
