<?php

use App\Enums\PurchaseRequestStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

/** Una solicitud aprobada y todavía no enviada a Odoo. */
function aprobadaSinEnviar(User $owner, User $reviewer)
{
    $test = test();
    $request = $test->submitPurchaseRequest($owner, $test->createPurchaseRequestDraft($owner));

    $test->actingAs($reviewer)->post(route('purchase_requests.approve', $request), [
        'lock_version' => $request->lock_version,
    ])->assertSessionHasNoErrors();

    return $request->fresh();
}

it('lets the reviewer send an approved request back for correction', function () {
    $owner = User::factory()->create();
    $reviewer = User::factory()->admin()->create();
    $aprobada = aprobadaSinEnviar($owner, $reviewer);

    $this->actingAs($reviewer)->post(route('purchase_requests.request_changes', $aprobada), [
        'lock_version' => $aprobada->lock_version,
        'comment' => 'La bolsa de 20 no corresponde a candados.',
    ])->assertSessionHasNoErrors();

    $devuelta = $aprobada->fresh();

    expect($devuelta->status)->toBe(PurchaseRequestStatus::CHANGES_REQUESTED)
        ->and($devuelta->status->isEditable())->toBeTrue();

    // Y ahora sí se puede corregir, que es todo el objeto del cambio.
    $this->actingAs($owner)
        ->put(route('purchase_requests.update', $devuelta), $this->validPurchaseRequestPayload())
        ->assertSessionHasNoErrors();
});

it('lets the reviewer cancel an approved request that was never sent', function () {
    $owner = User::factory()->create();
    $reviewer = User::factory()->admin()->create();
    $aprobada = aprobadaSinEnviar($owner, $reviewer);

    $this->actingAs($reviewer)->post(route('purchase_requests.cancel', $aprobada), [
        'lock_version' => $aprobada->lock_version,
        'comment' => 'Se pidió por duplicado.',
    ])->assertSessionHasNoErrors();

    expect($aprobada->fresh()->status)->toBe(PurchaseRequestStatus::CANCELLED);
});

it('closes both doors once the request exists in Odoo', function () {
    // Desde que hay cotización en Odoo, el documento que cuenta es el de
    // allá. Corregir o anular aquí dejaría a los dos sistemas diciendo cosas
    // distintas sobre la misma compra.
    $owner = User::factory()->create();
    $reviewer = User::factory()->admin()->create();
    $aprobada = aprobadaSinEnviar($owner, $reviewer);

    $aprobada->forceFill(['odoo_order_id' => 229, 'odoo_exported_at' => now()])->save();

    $this->actingAs($reviewer)->post(route('purchase_requests.request_changes', $aprobada), [
        'lock_version' => $aprobada->lock_version,
        'comment' => 'Quiero cambiarla.',
    ])->assertForbidden();

    $this->actingAs($reviewer)->post(route('purchase_requests.cancel', $aprobada), [
        'lock_version' => $aprobada->lock_version,
        'comment' => 'Quiero anularla.',
    ])->assertForbidden();

    expect($aprobada->fresh()->status)->toBe(PurchaseRequestStatus::APPROVED);
});

it('keeps the reopening out of reach of everyone else', function () {
    $owner = User::factory()->create();
    $reviewer = User::factory()->admin()->create();
    $aprobada = aprobadaSinEnviar($owner, $reviewer);

    // El solicitante no reabre lo suyo: para eso pide la anulación.
    $this->actingAs($owner)->post(route('purchase_requests.request_changes', $aprobada), [
        'lock_version' => $aprobada->lock_version,
        'comment' => 'Me equivoqué.',
    ])->assertForbidden();

    expect($aprobada->fresh()->status)->toBe(PurchaseRequestStatus::APPROVED);
});

it('shows both ways out on the screen of an approved request', function () {
    // La lógica puede estar perfecta y la pantalla no ofrecerla: eso ya pasó
    // antes en este módulo, así que se comprueba lo que se ve.
    $owner = User::factory()->create();
    $reviewer = User::factory()->admin()->create();
    $aprobada = aprobadaSinEnviar($owner, $reviewer);

    $this->actingAs($reviewer)
        ->get(route('purchase_requests.show', $aprobada))
        ->assertOk()
        ->assertSee('Devolver para corregir')
        ->assertSee('Anular solicitud');
});

it('hides both once the request is already in Odoo', function () {
    $owner = User::factory()->create();
    $reviewer = User::factory()->admin()->create();
    $aprobada = aprobadaSinEnviar($owner, $reviewer);

    $aprobada->forceFill(['odoo_order_id' => 229, 'odoo_exported_at' => now()])->save();

    $this->actingAs($reviewer)
        ->get(route('purchase_requests.show', $aprobada))
        ->assertOk()
        ->assertDontSee('Devolver para corregir')
        ->assertDontSee('Anular solicitud');
});
