<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

it('shows each person only their own notices', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $curioso = User::factory()->create();

    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    // El admin recibió el aviso del envío.
    $this->actingAs($admin)
        ->get(route('purchase_notifications.index'))
        ->assertOk()
        ->assertSee($request->folio);

    // Un tercero no ve nada de eso.
    $this->actingAs($curioso)
        ->get(route('purchase_notifications.index'))
        ->assertOk()
        ->assertDontSee($request->folio)
        ->assertSee('No tienes avisos por ahora');
});

it('cannot mark someone elses notification as read', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $intruso = User::factory()->create();

    $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    $aviso = $admin->notifications()->firstOrFail();

    // Conociendo el identificador, un tercero tampoco puede tocarlo.
    $this->actingAs($intruso)
        ->post(route('purchase_notifications.read', $aviso->id))
        ->assertNotFound();

    expect($aviso->fresh()->read_at)->toBeNull();
});

it('marks a notice as read and lands on the request', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));
    $aviso = $admin->notifications()->firstOrFail();

    expect($aviso->read_at)->toBeNull()
        ->and($admin->unreadPurchaseNotificationsCount())->toBe(1);

    $this->actingAs($admin)
        ->post(route('purchase_notifications.read', $aviso->id))
        ->assertRedirect(route('purchase_requests.show', $request->public_id));

    expect($aviso->fresh()->read_at)->not->toBeNull()
        ->and($admin->fresh()->unreadPurchaseNotificationsCount())->toBe(0);
});

it('marks every notice as read at once', function () {
    $admin = User::factory()->admin()->create();

    foreach (range(1, 3) as $i) {
        $owner = User::factory()->create();
        $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));
    }

    expect($admin->unreadPurchaseNotificationsCount())->toBe(3);

    $this->actingAs($admin)
        ->post(route('purchase_notifications.read_all'))
        ->assertRedirect(route('purchase_notifications.index'));

    expect($admin->fresh()->unreadPurchaseNotificationsCount())->toBe(0);
});

it('shows the unread counter in the module navigation', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    $this->actingAs($admin)
        ->get(route('purchase_requests.index'))
        ->assertOk()
        ->assertSee('Avisos')
        ->assertSee('avisos sin leer', false);
});

it('requires authentication', function () {
    $this->get(route('purchase_notifications.index'))->assertRedirect(route('login'));
});

it('does not let the notices route be swallowed by the request route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/solicitudes-compra/avisos')
        ->assertOk()
        ->assertSee('Avisos de solicitudes');
});
