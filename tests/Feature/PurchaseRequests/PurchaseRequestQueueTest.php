<?php

use App\Models\User;
use App\Notifications\PurchaseRequestReviewed;
use App\Notifications\PurchaseRequestSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

it('sends the in-app notice immediately and queues only the email', function () {
    // La razón de existir de esto: con el correo en línea, la página quedaba
    // cargando 1 a 3 segundos esperando a Gmail. Un trabajador no vio
    // respuesta, hizo clic de nuevo y pidió la anulación dos veces.
    $owner = User::factory()->create();
    $notificacion = new PurchaseRequestReviewed(
        $this->createPurchaseRequestDraft($owner),
        User::factory()->admin()->create(),
        'aprobada',
    );

    $conexiones = $notificacion->viaConnections();

    expect($conexiones['database'])->toBe('sync')
        ->and($conexiones['mail'])->toBe('database');
});

it('never blocks the response on the mail server', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    $this->actingAs($admin)
        ->post(route('purchase_requests.approve', $request), ['lock_version' => (string) $request->lock_version])
        ->assertSessionHasNoErrors();

    // La decisión quedó guardada sin esperar a ningún servidor externo.
    expect($request->fresh()->status->value)->toBe('approved');
});

it('writes the in-app notice on the spot, outside the queue', function () {
    // El canal `database` va por la conexión `sync`, así que el aviso se
    // escribe durante la petición y no depende de que haya worker. Queue::fake
    // no sirve aquí: intercepta incluso lo declarado como sync.
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    expect($admin->fresh()->unreadPurchaseNotificationsCount())->toBe(1);

    $this->actingAs($admin)->post(route('purchase_requests.approve', $request), [
        'lock_version' => (string) $request->lock_version,
    ]);

    expect($owner->fresh()->unreadPurchaseNotificationsCount())->toBe(1);

    // Y el aviso lleva el folio, para que se pueda abrir desde la bandeja.
    $aviso = $owner->fresh()->notifications()->firstOrFail();
    expect($aviso->data['folio'])->toBe($request->folio)
        ->and($aviso->data['url'])->toContain($request->public_id);
});

it('keeps both notifications queueable so a worker can pick them up', function () {
    expect(new PurchaseRequestSubmitted(
        $this->createPurchaseRequestDraft(User::factory()->create()),
        User::factory()->create(),
    ))->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldQueue::class);

    expect(new PurchaseRequestReviewed(
        $this->createPurchaseRequestDraft(User::factory()->create()),
        User::factory()->create(),
        'aprobada',
    ))->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldQueue::class);
});
