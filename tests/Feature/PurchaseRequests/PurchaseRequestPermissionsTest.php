<?php

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

it('lets Compras see everything but never decide', function () {
    $owner = User::factory()->create();
    $buyer = User::factory()->comprador()->create();

    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    // Compras acompaña el proceso: ve la solicitud ajena y su PDF.
    $this->actingAs($buyer)->get(route('purchase_requests.show', $request))->assertOk();
    $this->actingAs($buyer)->get(route('purchase_requests.pdf', $request))->assertOk();

    // Pero el visto bueno es atribución exclusiva del administrador.
    foreach (['approve', 'reject', 'request_changes'] as $action) {
        $this->actingAs($buyer)
            ->post(route('purchase_requests.'.$action, $request), [
                'lock_version' => (string) $request->lock_version,
                'comment' => 'Intento de decisión sin atribución.',
            ])
            ->assertForbidden();
    }

    $request->refresh();
    expect($request->reviewed_by)->toBeNull()
        ->and($request->status->value)->toBe('submitted');
});

it('forbids Compras from deciding on its own request either', function () {
    $buyer = User::factory()->comprador()->create();
    $request = $this->submitPurchaseRequest($buyer, $this->createPurchaseRequestDraft($buyer));

    $this->actingAs($buyer)
        ->post(route('purchase_requests.approve', $request), ['lock_version' => (string) $request->lock_version])
        ->assertForbidden();

    expect($request->fresh()->reviewed_by)->toBeNull();
});

it('lets only the admin decide', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    $this->actingAs($admin)
        ->post(route('purchase_requests.approve', $request), ['lock_version' => (string) $request->lock_version])
        ->assertSessionHasNoErrors();

    expect($request->fresh()->reviewed_by)->toBe($admin->id);
});

it('gives the auditor read access without any power to act', function () {
    $owner = User::factory()->create();
    $auditor = User::factory()->auditor()->create();

    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    // Lee la solicitud, su PDF y la bandeja.
    $this->actingAs($auditor)->get(route('purchase_requests.show', $request))->assertOk();
    $this->actingAs($auditor)->get(route('purchase_requests.pdf', $request))->assertOk();
    $this->actingAs($auditor)->get(route('purchase_requests.index'))->assertOk();

    // Pero no decide, no edita y no origina solicitudes.
    $this->actingAs($auditor)
        ->post(route('purchase_requests.approve', $request), ['lock_version' => $request->lock_version])
        ->assertForbidden();

    $this->actingAs($auditor)
        ->put(route('purchase_requests.update', $request), $this->validPurchaseRequestPayload())
        ->assertForbidden();

    $this->actingAs($auditor)->get(route('purchase_requests.create'))->assertForbidden();

    $this->actingAs($auditor)
        ->post(route('purchase_requests.store'), $this->validPurchaseRequestPayload())
        ->assertForbidden();

    expect(PurchaseRequest::query()->where('user_id', $auditor->id)->count())->toBe(0);
});

it('hides other peoples requests from a plain requester', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $request = $this->createPurchaseRequestDraft($owner);

    // Acceso directo por URL manipulada.
    $this->actingAs($other)->get(route('purchase_requests.show', $request))->assertForbidden();
    $this->actingAs($other)->get(route('purchase_requests.pdf', $request))->assertForbidden();
    $this->actingAs($other)->get(route('purchase_requests.edit', $request))->assertForbidden();

    // Y tampoco aparece en su bandeja.
    $this->actingAs($other)
        ->get(route('purchase_requests.index'))
        ->assertOk()
        ->assertDontSee($request->folio);
});

it('shows every request to a reviewer and only their own to a requester', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $buyer = User::factory()->comprador()->create();

    $mine = $this->createPurchaseRequestDraft($owner);
    $theirs = $this->createPurchaseRequestDraft($other);

    $this->actingAs($buyer)
        ->get(route('purchase_requests.index'))
        ->assertOk()
        ->assertSee($mine->folio)
        ->assertSee($theirs->folio);

    $this->actingAs($owner)
        ->get(route('purchase_requests.index'))
        ->assertOk()
        ->assertSee($mine->folio)
        ->assertDontSee($theirs->folio);
});

it('shows the admin the decision panel on their own submitted request', function () {
    // Reproduce lo reportado desde el navegador: el administrador miraba su
    // propia solicitud y la pantalla no le ofrecía ninguna acción, porque la
    // vista reimplementaba la regla en vez de preguntarle a la policy.
    $admin = User::factory()->admin()->create();
    $request = $this->submitPurchaseRequest($admin, $this->createPurchaseRequestDraft($admin));

    $this->actingAs($admin)
        ->get(route('purchase_requests.show', $request))
        ->assertOk()
        ->assertSee('Revisión de Compras')
        ->assertSee('Aprobar')
        ->assertSee('Solicitar cambios')
        ->assertSee('Rechazar');
});

it('offers the draft owner the send action and hides it from everyone else', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $draft = $this->createPurchaseRequestDraft($owner);

    $this->actingAs($owner)
        ->get(route('purchase_requests.show', $draft))
        ->assertOk()
        ->assertSee('Lista para enviar');

    // El administrador ve el borrador ajeno, pero no puede enviarlo por otro.
    $this->actingAs($admin)
        ->get(route('purchase_requests.show', $draft))
        ->assertOk()
        ->assertDontSee('Lista para enviar');

    $this->actingAs($admin)
        ->post(route('purchase_requests.submit', $draft))
        ->assertForbidden();
});

it('never offers cancel and request-cancellation at the same time', function () {
    $admin = User::factory()->admin()->create();
    $request = $this->submitPurchaseRequest($admin, $this->createPurchaseRequestDraft($admin));

    // El administrador puede anular directamente, así que no debe además
    // ofrecérsele "pedir anulación": son la misma intención duplicada.
    expect($admin->can('cancel', $request))->toBeTrue()
        ->and($admin->can('requestCancellation', $request))->toBeFalse();

    // Un solicitante común sólo puede pedirla.
    $owner = User::factory()->create();
    $theirs = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    expect($owner->can('cancel', $theirs))->toBeFalse()
        ->and($owner->can('requestCancellation', $theirs))->toBeTrue();
});
