<?php

use App\Models\User;
use Tests\Support\InteractsWithPurchaseRequests;

uses(InteractsWithPurchaseRequests::class);

it('allows an admin to approve another users submitted request', function () {
    $requester = User::factory()->viewer()->create();
    $admin = User::factory()->admin()->create();
    $request = $this->submitPurchaseRequest(
        $requester,
        $this->createPurchaseRequestDraft($requester),
    );

    $response = $this
        ->actingAs($admin)
        ->post(route('purchase_requests.approve', $request), [
            'lock_version' => $request->lock_version,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($request->fresh()->status->value)->toBe('approved');
});

it('lets the admin resolve their own request and records who decided', function () {
    // En Agrícola EHE el administrador es la única persona con atribución para
    // decidir, así que también resuelve lo que él mismo solicita. Lo que no se
    // negocia es la trazabilidad: queda escrito quién decidió y cuándo.
    $admin = User::factory()->admin()->create();
    $request = $this->submitPurchaseRequest(
        $admin,
        $this->createPurchaseRequestDraft($admin),
    );

    $this
        ->actingAs($admin)
        ->post(route('purchase_requests.approve', $request), [
            'lock_version' => (string) $request->lock_version,
        ])
        ->assertSessionHasNoErrors();

    $request->refresh();

    expect($request->status->value)->toBe('approved')
        ->and($request->reviewed_by)->toBe($admin->id)
        ->and($request->reviewed_at)->not->toBeNull();

    $decision = $request->events()->where('event_type', 'approved')->firstOrFail();
    expect($decision->actor_id)->toBe($admin->id)
        ->and($decision->actor_name_snapshot)->toBe($admin->name);
});

it('forbids a non admin from reviewing another users request', function () {
    $requester = User::factory()->viewer()->create();
    $reviewer = User::factory()->viewer()->create();
    $request = $this->submitPurchaseRequest(
        $requester,
        $this->createPurchaseRequestDraft($requester),
    );

    $this
        ->actingAs($reviewer)
        ->post(route('purchase_requests.approve', $request), [
            'lock_version' => $request->lock_version,
        ])
        ->assertForbidden();
});

it('requires a comment to request changes and permits a corrected resubmission', function () {
    $requester = User::factory()->viewer()->create();
    $admin = User::factory()->admin()->create();
    $request = $this->submitPurchaseRequest(
        $requester,
        $this->createPurchaseRequestDraft($requester),
    );

    $this
        ->actingAs($admin)
        ->post(route('purchase_requests.request_changes', $request), [
            'lock_version' => $request->lock_version,
        ])
        ->assertSessionHasErrors('comment');

    expect($request->fresh()->status->value)->toBe('submitted');

    $response = $this
        ->actingAs($admin)
        ->post(route('purchase_requests.request_changes', $request), [
            'comment' => 'Especificar la presentación solicitada.',
            'lock_version' => $request->fresh()->lock_version,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($request->fresh()->status->value)->toBe('changes_requested');

    $resubmission = $this
        ->actingAs($requester)
        ->post(route('purchase_requests.submit', $request->fresh()));

    $resubmission
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($request->fresh()->status->value)->toBe('resubmitted');
});

it('requires a comment to reject a submitted request', function () {
    $requester = User::factory()->bodeguero()->create();
    $admin = User::factory()->admin()->create();
    $request = $this->submitPurchaseRequest(
        $requester,
        $this->createPurchaseRequestDraft($requester),
    );

    $this
        ->actingAs($admin)
        ->post(route('purchase_requests.reject', $request), [
            'lock_version' => $request->lock_version,
        ])
        ->assertSessionHasErrors('comment');

    expect($request->fresh()->status->value)->toBe('submitted');

    $response = $this
        ->actingAs($admin)
        ->post(route('purchase_requests.reject', $request), [
            'comment' => 'La necesidad quedó cubierta por existencias internas.',
            'lock_version' => $request->fresh()->lock_version,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($request->fresh()->status->value)->toBe('rejected');
});
