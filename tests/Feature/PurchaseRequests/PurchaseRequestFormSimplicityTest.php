<?php

use App\Models\Department;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

it('creates a request with only the essentials', function () {
    // Un trabajador dijo que le daba pereza rellenar el formulario. Pedir algo
    // tiene que costar tres datos y una línea, no dieciséis campos.
    $owner = User::factory()->create();

    $this->actingAs($owner)->post(route('purchase_requests.store'), [
        'department' => 'Administración',
        'required_date' => now()->addWeek()->toDateString(),
        'reason' => 'Sin stock en bodega',
        'priority' => 'normal',
        'items' => [[
            'product_service' => 'Papel higiénico industrial',
            'quantity' => '10',
            'unit' => 'Paquetes',
        ]],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $request = PurchaseRequest::query()->latest('id')->firstOrFail();

    expect($request->reason)->toBe('Sin stock en bodega')
        ->and($request->items)->toHaveCount(1)
        // Nada opcional quedó exigido por la puerta de atrás.
        ->and($request->cost_center)->toBeNull()
        ->and($request->delivery_location)->toBeNull()
        ->and($request->requested_for_name)->toBeNull()
        ->and($request->internal_notes)->toBeNull()
        ->and($request->suggested_suppliers)->toBe([]);
});

it('arrives with sensible defaults instead of empty boxes', function () {
    $owner = User::factory()->create();
    Department::query()->create(['company_code' => 'EHE', 'name' => 'Administración', 'is_active' => true]);

    $response = $this->actingAs($owner)->get(route('purchase_requests.create'))->assertOk();

    $defaults = $response->viewData('defaults');

    // Una semana es el plazo típico de los formularios en papel revisados.
    expect($defaults['required_date'])->toBe(now()->addWeek()->toDateString());

    // Con una sola área en el catálogo, no tiene sentido preguntarla.
    expect($defaults['department'])->toBe('Administración');
});

it('remembers what the person used last time', function () {
    $owner = User::factory()->create();
    Department::query()->create(['company_code' => 'EHE', 'name' => 'Riego', 'is_active' => true]);

    $this->createPurchaseRequestDraft($owner, [
        'department' => 'Riego',
        'cost_center' => 'Nueva Matriz',
        'delivery_location' => 'Bodega Central',
    ]);

    $defaults = $this->actingAs($owner)
        ->get(route('purchase_requests.create'))
        ->assertOk()
        ->viewData('defaults');

    expect($defaults['department'])->toBe('Riego')
        ->and($defaults['cost_center'])->toBe('Nueva Matriz')
        ->and($defaults['delivery_location'])->toBe('Bodega Central');
});

it('keeps every optional field reachable, just folded away', function () {
    $owner = User::factory()->create();

    $html = $this->actingAs($owner)->get(route('purchase_requests.create'))->assertOk()->getContent();

    // Simplificar no es amputar: todo sigue disponible.
    foreach ([
        'requested_for_name', 'cost_center', 'priority',
        'delivery_location', 'internal_notes', 'suggested_suppliers', 'attachments',
    ] as $campo) {
        expect($html)->toContain('name="'.$campo);
    }

    // Pero el bloque llega cerrado.
    expect($html)->toContain('abierto: false');
});

it('opens the optional block when something inside it failed', function () {
    $owner = User::factory()->create();

    // Urgente sin justificación: el error vive dentro del bloque plegado, así
    // que el bloque tiene que abrirse solo o el error sería invisible.
    $this->actingAs($owner)->post(route('purchase_requests.store'),
        $this->validPurchaseRequestPayload(['priority' => 'urgent', 'urgent_reason' => '']))
        ->assertSessionHasErrors('urgent_reason');

    $html = $this->actingAs($owner)->get(route('purchase_requests.create'))->assertOk()->getContent();

    expect($html)->toContain('abierto: true');
});

it('still lets a full request be sent with everything filled in', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)->post(route('purchase_requests.store'), $this->validPurchaseRequestPayload([
        'requested_for_name' => 'Marco del Riego',
        'cost_center' => 'Nueva Matriz 2026',
        'delivery_location' => 'Bodega Central',
        'internal_notes' => 'Retira Jose.',
        'suggested_suppliers' => ['Sodimac', 'Construmart'],
    ]))->assertSessionHasNoErrors();

    $request = PurchaseRequest::query()->latest('id')->firstOrFail();

    expect($request->requested_for_name)->toBe('Marco del Riego')
        ->and($request->cost_center)->toBe('Nueva Matriz 2026')
        ->and($request->suggested_suppliers)->toBe(['Sodimac', 'Construmart']);
});
