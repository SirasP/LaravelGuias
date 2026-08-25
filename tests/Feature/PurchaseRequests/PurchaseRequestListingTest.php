<?php

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

it('stores and prints more than twenty three lines without losing any', function () {
    $owner = User::factory()->create();

    $items = [];
    for ($i = 1; $i <= 30; $i++) {
        $items[] = $this->validPurchaseRequestItem([
            'product_service' => 'Partida número '.$i,
            'quantity' => (string) $i,
            'unit' => 'Unidades',
        ]);
    }

    $request = $this->createPurchaseRequestDraft($owner, ['items' => $items]);

    expect($request->items()->count())->toBe(30);

    // Ni la primera ni la última se pierden, y el orden se respeta.
    expect($request->items()->orderBy('sort_order')->pluck('product_service')->first())
        ->toBe('Partida número 1');
    expect($request->items()->orderBy('sort_order')->pluck('product_service')->last())
        ->toBe('Partida número 30');

    $submitted = $this->submitPurchaseRequest($owner, $request);

    // El PDF de la revisión conserva las 30 partidas.
    expect($submitted->revisions()->firstOrFail()->item_count)->toBe(30);

    $pdf = $this->actingAs($owner)->get(route('purchase_requests.pdf', $submitted));
    $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');

    $contents = $pdf->getContent();
    expect(substr($contents, 0, 4))->toBe('%PDF')
        ->and(strlen($contents))->toBeGreaterThan(1000);

    // Con 30 partidas la grilla ya no cabe en una carta: debe continuar en
    // una segunda página en vez de truncar.
    expect(preg_match_all('/\/Type\s*\/Page[^s]/', $contents))->toBeGreaterThan(1);
});

it('filters the listing on the server across every record', function () {
    $buyer = User::factory()->comprador()->create();
    $ana = User::factory()->create(['name' => 'Ana Silva']);
    $luis = User::factory()->create(['name' => 'Luis Torres']);

    $fromAna = $this->createPurchaseRequestDraft($ana, ['reason' => 'Repuestos para el tractor']);
    $fromLuis = $this->createPurchaseRequestDraft($luis, ['reason' => 'Insumos de aseo']);

    // Por texto libre: encuentra por motivo.
    $this->actingAs($buyer)
        ->get(route('purchase_requests.index', ['search' => 'tractor']))
        ->assertOk()
        ->assertSee($fromAna->folio)
        ->assertDontSee($fromLuis->folio);

    // Por nombre del solicitante.
    $this->actingAs($buyer)
        ->get(route('purchase_requests.index', ['requester' => 'Luis']))
        ->assertOk()
        ->assertSee($fromLuis->folio)
        ->assertDontSee($fromAna->folio);

    // Por folio exacto.
    $this->actingAs($buyer)
        ->get(route('purchase_requests.index', ['search' => $fromAna->folio]))
        ->assertOk()
        ->assertSee($fromAna->folio)
        ->assertDontSee($fromLuis->folio);
});

it('filters by required date range on the server', function () {
    $buyer = User::factory()->comprador()->create();
    $owner = User::factory()->create();

    $soon = $this->createPurchaseRequestDraft($owner, [
        'required_date' => now()->addDays(3)->toDateString(),
    ]);
    $later = $this->createPurchaseRequestDraft($owner, [
        'required_date' => now()->addDays(40)->toDateString(),
    ]);

    $this->actingAs($buyer)
        ->get(route('purchase_requests.index', [
            'required_from' => now()->addDays(1)->toDateString(),
            'required_to' => now()->addDays(10)->toDateString(),
        ]))
        ->assertOk()
        ->assertSee($soon->folio)
        ->assertDontSee($later->folio);
});

it('paginates on the server instead of loading everything', function () {
    $owner = User::factory()->create();

    PurchaseRequest::factory()->count(25)->forUser($owner)->create();

    $response = $this->actingAs($owner)->get(route('purchase_requests.index'));
    $response->assertOk();

    $paginator = $response->viewData('requests');

    expect($paginator->total())->toBe(25)
        ->and($paginator->perPage())->toBe(20)
        ->and($paginator->count())->toBe(20)
        ->and($paginator->hasPages())->toBeTrue();

    // La segunda página trae el resto, sin perder ninguna.
    $second = $this->actingAs($owner)->get(route('purchase_requests.index', ['page' => 2]));
    expect($second->viewData('requests')->count())->toBe(5);
});

it('keeps filters applied when paginating', function () {
    $owner = User::factory()->create();

    PurchaseRequest::factory()->count(3)->forUser($owner)->create(['reason' => 'Mantención de bombas']);
    PurchaseRequest::factory()->count(4)->forUser($owner)->create(['reason' => 'Otra cosa']);

    $response = $this->actingAs($owner)
        ->get(route('purchase_requests.index', ['search' => 'bombas']));

    expect($response->viewData('requests')->total())->toBe(3);
});

it('keeps a corrected request visible in the pending-review counter', function () {
    // El bug: el revisor devuelve una solicitud, el solicitante la corrige y
    // la reenvía, y desaparecía del contador "Por revisar" porque éste sólo
    // contaba `submitted`. Quedaba esperando decisión sin que nadie la viera.
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    $this->actingAs($admin)->post(route('purchase_requests.request_changes', $request), [
        'lock_version' => (string) $request->lock_version,
        'comment' => 'Corrige la especificación.',
    ])->assertSessionHasNoErrors();

    // Devuelta: ya no espera decisión del revisor.
    $counts = $this->actingAs($admin)->get(route('purchase_requests.index'))->viewData('counts');
    expect($counts['por_revisar'])->toBe(0)
        ->and($counts['changes_requested'])->toBe(1);

    // El solicitante corrige y reenvía.
    $this->actingAs($owner)->put(route('purchase_requests.update', $request),
        $this->validPurchaseRequestPayload(['reason' => 'Motivo corregido']))->assertSessionHasNoErrors();
    $this->submitPurchaseRequest($owner, $request->fresh());

    expect($request->fresh()->status)->toBe(PurchaseRequestStatus::RESUBMITTED);

    // Y vuelve a contar como pendiente de decisión.
    $counts = $this->actingAs($admin)->get(route('purchase_requests.index'))->viewData('counts');
    expect($counts['por_revisar'])->toBe(1)
        ->and($counts['resubmitted'])->toBe(1);
});

it('lists both submitted and resubmitted under the pending-review filter', function () {
    $admin = User::factory()->admin()->create();
    $enviada = User::factory()->create();
    $corregida = User::factory()->create();

    $nueva = $this->submitPurchaseRequest($enviada, $this->createPurchaseRequestDraft($enviada));

    $vuelta = $this->submitPurchaseRequest($corregida, $this->createPurchaseRequestDraft($corregida));
    $this->actingAs($admin)->post(route('purchase_requests.request_changes', $vuelta), [
        'lock_version' => (string) $vuelta->lock_version,
        'comment' => 'Falta detalle.',
    ]);
    $this->actingAs($corregida)->put(route('purchase_requests.update', $vuelta),
        $this->validPurchaseRequestPayload(['reason' => 'Ya corregido']));
    $this->submitPurchaseRequest($corregida, $vuelta->fresh());

    $this->actingAs($admin)
        ->get(route('purchase_requests.index', ['status' => 'por_revisar']))
        ->assertOk()
        ->assertSee($nueva->folio)
        ->assertSee($vuelta->folio);
});
