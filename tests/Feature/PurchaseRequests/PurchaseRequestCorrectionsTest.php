<?php

use App\Enums\PurchaseRequestCorrection;
use App\Enums\PurchaseRequestStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

it('records the exact points the reviewer marked when returning a request', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner, [
        'items' => [
            $this->validPurchaseRequestItem(['product_service' => 'Tubo PVC 75 mm']),
            $this->validPurchaseRequestItem(['product_service' => 'Codo 90°']),
        ],
    ]));

    $this->actingAs($admin)
        ->post(route('purchase_requests.request_changes', $request), [
            'lock_version' => (string) $request->lock_version,
            'comment' => 'Falta la marca y la partida 2 está mal.',
            'corrections' => ['reason', PurchaseRequestCorrection::itemKey(2)],
        ])
        ->assertSessionHasNoErrors();

    $request->refresh();

    expect($request->status)->toBe(PurchaseRequestStatus::CHANGES_REQUESTED)
        ->and($request->requested_corrections)->toBe(['reason', 'item:2']);

    // Y queda por escrito en el historial, no sólo en la columna de trabajo.
    $event = $request->events()->where('event_type', 'changes_requested')->firstOrFail();
    expect($event->metadata['corrections'])->toBe(['reason', 'item:2']);
});

it('shows the requester exactly what to fix', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    $this->actingAs($admin)->post(route('purchase_requests.request_changes', $request), [
        'lock_version' => (string) $request->lock_version,
        'comment' => 'Revisa la fecha.',
        'corrections' => ['required_date', 'reason'],
    ])->assertSessionHasNoErrors();

    // En el detalle ve las etiquetas legibles, no las claves técnicas.
    $this->actingAs($owner)
        ->get(route('purchase_requests.show', $request))
        ->assertOk()
        ->assertSee('Puntos a corregir')
        ->assertSee('Fecha requerida')
        ->assertSee('Motivo de la compra');

    // Y al editar, el aviso encabeza el formulario.
    $this->actingAs($owner)
        ->get(route('purchase_requests.edit', $request))
        ->assertOk()
        ->assertSee('Compras pidió corregir estos puntos')
        ->assertSee('Fecha requerida');
});

it('clears the marks once the request is corrected and resent', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    $this->actingAs($admin)->post(route('purchase_requests.request_changes', $request), [
        'lock_version' => (string) $request->lock_version,
        'comment' => 'Corrige el motivo.',
        'corrections' => ['reason'],
    ]);

    expect($request->fresh()->requested_corrections)->toBe(['reason']);

    $this->actingAs($owner)->put(route('purchase_requests.update', $request),
        $this->validPurchaseRequestPayload(['reason' => 'Motivo corregido']));
    $this->submitPurchaseRequest($owner, $request->fresh());

    $request->refresh();

    // Las marcas pendientes desaparecen: ya se actuó sobre ellas.
    expect($request->requested_corrections)->toBeNull()
        ->and($request->status)->toBe(PurchaseRequestStatus::RESUBMITTED);

    // Pero lo que se pidió corregir sigue registrado en el historial.
    $event = $request->events()->where('event_type', 'changes_requested')->firstOrFail();
    expect($event->metadata['corrections'])->toBe(['reason']);
});

it('ignores marks on an approval, where there is nothing to fix', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    $this->actingAs($admin)->post(route('purchase_requests.approve', $request), [
        'lock_version' => (string) $request->lock_version,
        'corrections' => ['reason'],
    ])->assertSessionHasNoErrors();

    $request->refresh();

    expect($request->status)->toBe(PurchaseRequestStatus::APPROVED)
        ->and($request->requested_corrections)->toBeNull();
});

it('rejects a mark that does not correspond to any real point', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    $this->actingAs($admin)
        ->post(route('purchase_requests.request_changes', $request), [
            'lock_version' => (string) $request->lock_version,
            'comment' => 'Intento con un punto inventado.',
            'corrections' => ['borrar_todo'],
        ])
        ->assertSessionHasErrors('corrections.0');

    expect($request->fresh()->status)->toBe(PurchaseRequestStatus::SUBMITTED);
});

it('still requires a comment even when points are marked', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    // Marcar dónde no reemplaza explicar por qué.
    $this->actingAs($admin)
        ->post(route('purchase_requests.request_changes', $request), [
            'lock_version' => (string) $request->lock_version,
            'corrections' => ['reason'],
        ])
        ->assertSessionHasErrors('comment');

    expect($request->fresh()->status)->toBe(PurchaseRequestStatus::SUBMITTED);
});

it('offers the reviewer a checkbox for every field and every line', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner, [
        'items' => [
            $this->validPurchaseRequestItem(['product_service' => 'Tubo PVC 75 mm']),
            $this->validPurchaseRequestItem(['product_service' => 'Codo 90 grados']),
        ],
    ]));

    $response = $this->actingAs($admin)->get(route('purchase_requests.show', $request))->assertOk();

    $response->assertSee('¿Qué hay que corregir?', false)
        ->assertSee('name="corrections[]"', false)
        ->assertSee('value="reason"', false)
        ->assertSee('value="item:1"', false)
        ->assertSee('value="item:2"', false);
});
