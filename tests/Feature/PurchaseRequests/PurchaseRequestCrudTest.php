<?php

use App\Models\PurchaseRequest;
use App\Models\User;
use Tests\Support\InteractsWithPurchaseRequests;

uses(InteractsWithPurchaseRequests::class);

it('requires authentication for the purchase request screens', function () {
    $this->get(route('purchase_requests.index'))->assertRedirect(route('login'));
    $this->get(route('purchase_requests.create'))->assertRedirect(route('login'));
});

it('allows every authenticated legacy role to create its own draft', function (string $factoryState) {
    $factory = User::factory();
    $user = $factory->{$factoryState}()->create();

    $request = $this->createPurchaseRequestDraft($user);

    expect($request->user_id)->toBe($user->id)
        ->and($request->status->value)->toBe('draft');
})->with(['viewer', 'bodeguero', 'admin']);

it('normalizes a comma decimal without losing precision', function () {
    $user = User::factory()->viewer()->create();

    $request = $this->createPurchaseRequestDraft($user, [
        'items' => [
            $this->validPurchaseRequestItem([
                'quantity' => '1,5',
                'unit' => 'cubos',
            ]),
        ],
    ]);

    $item = $request->items()->sole();

    expect(number_format((float) $item->quantity, 3, '.', ''))->toBe('1.500')
        ->and($item->unit)->toBe('cubos');
});

it('stores twenty three lines without merging or truncating them', function () {
    $user = User::factory()->viewer()->create();
    $items = [];

    for ($position = 1; $position <= 23; $position++) {
        $items[] = $this->validPurchaseRequestItem([
            'product_service' => 'Producto '.$position,
            'quantity' => (string) $position,
            'unit' => 'UN',
        ]);
    }

    $request = $this->createPurchaseRequestDraft($user, ['items' => $items]);

    expect($request->items()->count())->toBe(23)
        ->and($request->items()->orderBy('sort_order')->pluck('product_service')->all())
        ->toBe(array_map(fn (int $position) => 'Producto '.$position, range(1, 23)));
});

it('allows the owner to update a draft', function () {
    $user = User::factory()->viewer()->create();
    $request = $this->createPurchaseRequestDraft($user);

    $response = $this
        ->actingAs($user)
        ->put(route('purchase_requests.update', $request), $this->validPurchaseRequestPayload([
            'reason' => 'Motivo corregido por el solicitante',
        ]));

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($request->fresh()->reason)->toBe('Motivo corregido por el solicitante');
});

it('forbids another authenticated user from reading or mutating the draft', function () {
    $owner = User::factory()->viewer()->create();
    $stranger = User::factory()->viewer()->create();
    $request = $this->createPurchaseRequestDraft($owner);

    $this->actingAs($stranger)
        ->get(route('purchase_requests.show', $request))
        ->assertForbidden();

    $this->actingAs($stranger)
        ->get(route('purchase_requests.edit', $request))
        ->assertForbidden();

    $this->actingAs($stranger)
        ->put(route('purchase_requests.update', $request), $this->validPurchaseRequestPayload([
            'reason' => 'Intento de modificación ajena',
        ]))
        ->assertForbidden();

    $this->actingAs($stranger)
        ->post(route('purchase_requests.submit', $request))
        ->assertForbidden();

    expect($request->fresh()->reason)->toBe('Reposición de materiales operacionales')
        ->and($request->fresh()->status->value)->toBe('draft');
});

it('submits a request idempotently', function () {
    $user = User::factory()->viewer()->create();
    $request = $this->createPurchaseRequestDraft($user);

    $first = $this
        ->actingAs($user)
        ->post(route('purchase_requests.submit', $request));

    $first
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $request->refresh();
    $eventCountAfterFirstSubmit = $request->events()->count();

    $second = $this
        ->actingAs($user)
        ->post(route('purchase_requests.submit', $request));

    $second
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($request->fresh()->status->value)->toBe('submitted')
        ->and($request->fresh()->events()->count())->toBe($eventCountAfterFirstSubmit)
        ->and(PurchaseRequest::query()->whereKey($request->getKey())->count())->toBe(1);
});
