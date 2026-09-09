<?php

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestIngestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

function lecturaDe(User $owner, string $estado, array $extra = []): PurchaseRequestIngestion
{
    return PurchaseRequestIngestion::query()->create([
        'user_id' => $owner->getKey(), 'uploader_name_snapshot' => $owner->name,
        'disk' => 'local', 'path' => 'x.pdf', 'original_name' => 'COTIZACION 123.pdf',
        'mime_type' => 'application/pdf', 'size' => 2048,
        'sha256' => str_repeat((string) random_int(1, 9), 64),
        'status' => $estado,
        ...$extra,
    ]);
}

it('offers to read again what could not be read', function () {
    $owner = User::factory()->create();
    lecturaDe($owner, PurchaseRequestIngestion::FAILED, ['error_message' => 'El modelo no respondió.']);

    // La ruta y el controlador existían desde siempre; la pantalla nunca los
    // ofrecía, y una lectura fallida había que reencolarla por consola.
    $this->actingAs($owner)
        ->get(route('purchase_requests.ingestions.index'))
        ->assertOk()
        ->assertSee('Releer');
});

it('does not offer to read again what came out fine', function () {
    $owner = User::factory()->create();
    lecturaDe($owner, PurchaseRequestIngestion::COMPLETED);

    $this->actingAs($owner)
        ->get(route('purchase_requests.ingestions.index'))
        ->assertOk()
        ->assertDontSee('Releer');
});

it('does not claim a draft that was never created', function () {
    $owner = User::factory()->create();
    $solicitud = $this->createPurchaseRequestDraft($owner);

    // Una lectura completa puede haber sido una cotización para comparar.
    // Decir «Borrador creado» manda a buscar algo que no está en ningún sitio.
    $comparada = lecturaDe($owner, PurchaseRequestIngestion::COMPLETED, [
        'compared_request_id' => $solicitud->getKey(),
    ]);

    expect($comparada->statusLabel())->toBe('Leído');

    $this->actingAs($owner)
        ->get(route('purchase_requests.ingestions.index'))
        ->assertOk()
        ->assertDontSee('Borrador creado')
        // Y lleva a donde de verdad fue a parar.
        ->assertSee($solicitud->folio)
        ->assertSee('comparada');
});

it('says draft created when there really is one', function () {
    $owner = User::factory()->create();
    $solicitud = $this->createPurchaseRequestDraft($owner);
    $lectura = lecturaDe($owner, PurchaseRequestIngestion::COMPLETED, [
        'purchase_request_id' => $solicitud->getKey(),
    ]);

    expect($lectura->statusLabel())->toBe('Borrador creado');

    $this->actingAs($owner)
        ->get(route('purchase_requests.ingestions.index'))
        ->assertOk()
        ->assertSee($solicitud->folio)
        ->assertSee('borrador');
});

it('does not send you to edit a request that can no longer be edited', function () {
    $owner = User::factory()->create();
    $solicitud = PurchaseRequest::factory()->forUser($owner)->approved()->create();
    lecturaDe($owner, PurchaseRequestIngestion::COMPLETED, [
        'purchase_request_id' => $solicitud->getKey(),
    ]);

    // Una aprobada no se edita, y eso es a propósito. El enlace llevaba igual
    // a /editar, así que la fila terminaba en un 403 sin explicar nada.
    $this->actingAs($owner)
        ->get(route('purchase_requests.ingestions.index'))
        ->assertOk()
        ->assertDontSee(route('purchase_requests.edit', $solicitud), false)
        ->assertSee(route('purchase_requests.show', $solicitud), false)
        ->assertSee('aprobada');
});
