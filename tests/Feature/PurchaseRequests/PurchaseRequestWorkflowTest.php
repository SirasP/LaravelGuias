<?php

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestEvent;
use App\Models\PurchaseRequestRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

it('prevents two reviewers from reaching incompatible decisions', function () {
    $owner = User::factory()->create();
    $first = User::factory()->admin()->create();
    $second = User::factory()->comprador()->create();

    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));
    $staleVersion = $request->lock_version;

    // El primer revisor aprueba con la versión vigente.
    $this->actingAs($first)
        ->post(route('purchase_requests.approve', $request), ['lock_version' => $staleVersion])
        ->assertSessionHasNoErrors();

    // El segundo llega tarde. La máquina de estados lo frena antes incluso de
    // llegar a comparar versiones: desde `approved` no hay transición posible.
    $this->actingAs($second)
        ->post(route('purchase_requests.reject', $request), [
            'lock_version' => $staleVersion,
            'comment' => 'Prefiero rechazarla.',
        ])
        ->assertForbidden();

    $request->refresh();
    expect($request->status)->toBe(PurchaseRequestStatus::APPROVED)
        ->and($request->reviewed_by)->toBe($first->id);
});

it('rejects a decision sent with a stale lock version', function () {
    $owner = User::factory()->create();
    $reviewer = User::factory()->admin()->create();

    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));
    $staleVersion = $request->lock_version;

    // Otra acción avanzó la versión mientras el revisor tenía la página
    // abierta; la solicitud sigue siendo revisable, así que la policy permite
    // pasar y el freno tiene que ser la comprobación de versión.
    $request->forceFill(['lock_version' => $staleVersion + 1])->save();

    $this->actingAs($reviewer)
        ->post(route('purchase_requests.approve', $request), ['lock_version' => $staleVersion])
        ->assertSessionHasErrors('lock_version');

    expect($request->fresh()->status)->toBe(PurchaseRequestStatus::SUBMITTED);
});

it('keeps the history append only', function () {
    $owner = User::factory()->create();
    $request = $this->createPurchaseRequestDraft($owner);
    $event = $request->events()->firstOrFail();

    expect(fn () => $event->update(['comment' => 'reescrito']))
        ->toThrow(LogicException::class);

    expect(fn () => $event->delete())
        ->toThrow(LogicException::class);

    expect($event->fresh()->comment)->toBeNull();
});

it('freezes a revision on submit and never rewrites it afterwards', function () {
    $owner = User::factory()->create();
    $reviewer = User::factory()->admin()->create();

    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    $revision = PurchaseRequestRevision::query()
        ->where('purchase_request_id', $request->id)
        ->where('revision_number', 1)
        ->firstOrFail();

    expect($revision->item_count)->toBe(1)
        ->and($revision->header_snapshot['reason'])->toBe('Reposición de materiales operacionales');

    // El revisor devuelve y el solicitante corrige con otro motivo.
    $this->actingAs($reviewer)->post(route('purchase_requests.request_changes', $request), [
        'lock_version' => $request->lock_version,
        'comment' => 'Corrige el motivo.',
    ])->assertSessionHasNoErrors();

    $this->actingAs($owner)->put(route('purchase_requests.update', $request), $this->validPurchaseRequestPayload([
        'reason' => 'Motivo corregido tras la devolución',
    ]))->assertSessionHasNoErrors();

    $this->submitPurchaseRequest($owner, $request->fresh());

    // La revisión 1 conserva el motivo original; la 2 trae el corregido.
    expect($revision->fresh()->header_snapshot['reason'])
        ->toBe('Reposición de materiales operacionales');

    expect($request->fresh()->revision_number)->toBe(2);
    expect(PurchaseRequestRevision::query()->where('purchase_request_id', $request->id)->count())->toBe(2);
});

it('refuses to mutate the content of a stored revision', function () {
    $owner = User::factory()->create();
    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));
    $revision = $request->revisions()->firstOrFail();

    expect(fn () => $revision->update(['item_count' => 99]))
        ->toThrow(LogicException::class);

    expect(fn () => $revision->delete())
        ->toThrow(LogicException::class);
});

it('cancels a draft only with a reason and leaves a trace', function () {
    $owner = User::factory()->create();
    $request = $this->createPurchaseRequestDraft($owner);

    // Sin motivo no se anula.
    $this->actingAs($owner)
        ->post(route('purchase_requests.cancel', $request), ['lock_version' => $request->lock_version])
        ->assertSessionHasErrors('comment');

    expect($request->fresh()->status)->toBe(PurchaseRequestStatus::DRAFT);

    $this->actingAs($owner)
        ->post(route('purchase_requests.cancel', $request), [
            'lock_version' => $request->lock_version,
            'comment' => 'Se compró con caja chica.',
        ])
        ->assertSessionHasNoErrors();

    $request->refresh();
    expect($request->status)->toBe(PurchaseRequestStatus::CANCELLED)
        ->and($request->cancellation_reason)->toBe('Se compró con caja chica.')
        ->and($request->cancelled_by)->toBe($owner->id);

    expect($request->events()->where('event_type', PurchaseRequestEvent::CANCELLED)->exists())->toBeTrue();
});

it('does not let the requester cancel an already submitted request', function () {
    $owner = User::factory()->create();
    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    $this->actingAs($owner)
        ->post(route('purchase_requests.cancel', $request), [
            'lock_version' => $request->lock_version,
            'comment' => 'Ya no la necesito.',
        ])
        ->assertForbidden();

    // Puede, en cambio, dejar constancia de que pide anularla.
    $this->actingAs($owner)
        ->post(route('purchase_requests.request_cancellation', $request), [
            'comment' => 'Ya no la necesito.',
        ])
        ->assertSessionHasNoErrors();

    $request->refresh();
    expect($request->status)->toBe(PurchaseRequestStatus::SUBMITTED)
        ->and($request->cancellation_requested_at)->not->toBeNull();
});

it('forbids every transition out of a final state', function () {
    $owner = User::factory()->create();
    $reviewer = User::factory()->admin()->create();
    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    $this->actingAs($reviewer)->post(route('purchase_requests.approve', $request), [
        'lock_version' => $request->lock_version,
    ])->assertSessionHasNoErrors();

    $approved = $request->fresh();
    expect($approved->status->isFinal())->toBeTrue()
        ->and($approved->status->allowedTransitions())->toBe([]);

    $this->actingAs($reviewer)->post(route('purchase_requests.reject', $approved), [
        'lock_version' => $approved->lock_version,
        'comment' => 'Me arrepentí.',
    ])->assertForbidden();

    $this->actingAs($owner)->put(route('purchase_requests.update', $approved),
        $this->validPurchaseRequestPayload())->assertForbidden();
});

it('requires the required date to be on or after the request date', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->post(route('purchase_requests.store'), $this->validPurchaseRequestPayload([
            'required_date' => now()->subDay()->toDateString(),
        ]))
        ->assertSessionHasErrors('required_date');

    expect(PurchaseRequest::query()->count())->toBe(0);
});

it('approves from a real browser form where every value arrives as text', function () {
    $owner = User::factory()->create();
    $reviewer = User::factory()->admin()->create();

    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    // Un <input type="hidden"> manda SIEMPRE texto. Enviar aquí un entero
    // ocultaba el fallo: la comparación estricta contra el entero del modelo
    // hacía imposible aprobar desde el navegador.
    $this->actingAs($reviewer)
        ->post(route('purchase_requests.approve', $request), [
            'lock_version' => (string) $request->lock_version,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(PurchaseRequestStatus::APPROVED);
});

it('still blocks a stale version sent as text', function () {
    $owner = User::factory()->create();
    $reviewer = User::factory()->admin()->create();

    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));
    $stale = (string) $request->lock_version;
    $request->forceFill(['lock_version' => $request->lock_version + 1])->save();

    $this->actingAs($reviewer)
        ->post(route('purchase_requests.approve', $request), ['lock_version' => $stale])
        ->assertSessionHasErrors('lock_version');

    expect($request->fresh()->status)->toBe(PurchaseRequestStatus::SUBMITTED);
});
