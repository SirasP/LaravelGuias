<?php

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\PurchaseRequests\Odoo\PurchaseRequestExporter;
use App\Services\PurchaseRequests\Odoo\SimulatedPurchaseRequestExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

it('binds the odoo port to the simulated adapter and keeps it disabled', function () {
    $exporter = app(PurchaseRequestExporter::class);

    expect($exporter)->toBeInstanceOf(SimulatedPurchaseRequestExporter::class)
        ->and($exporter->isEnabled())->toBeFalse();
});

it('never performs a real call when exporting', function () {
    // Cualquier salida HTTP durante la prueba haría fallar este test.
    Http::preventStrayRequests();
    Http::fake();

    $owner = User::factory()->create();
    $request = PurchaseRequest::factory()->forUser($owner)->approved()->create();

    $result = app(PurchaseRequestExporter::class)->exportApproved($request);

    expect($result->performed)->toBeFalse()
        ->and($result->status)->toBe('simulated')
        ->and($result->remoteReference)->toBe('SIMULADO-RFQ-'.$request->folio);

    Http::assertNothingSent();
});

it('refuses to export anything that is not approved', function () {
    Http::preventStrayRequests();
    Http::fake();

    $owner = User::factory()->create();
    $request = PurchaseRequest::factory()->forUser($owner)->submitted()->create();

    $result = app(PurchaseRequestExporter::class)->exportApproved($request);

    expect($result->performed)->toBeFalse()
        ->and($result->status)->toBe('skipped')
        ->and($result->remoteReference)->toBeNull();

    Http::assertNothingSent();
});

it('completes the whole flow without ever touching odoo', function () {
    Http::preventStrayRequests();
    Http::fake();

    $owner = User::factory()->create();
    $reviewer = User::factory()->admin()->create();

    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    $this->actingAs($reviewer)
        ->post(route('purchase_requests.approve', $request), ['lock_version' => $request->lock_version])
        ->assertSessionHasNoErrors();

    $this->actingAs($owner)->get(route('purchase_requests.pdf', $request))->assertOk();

    expect($request->fresh()->status)->toBe(PurchaseRequestStatus::APPROVED);
    Http::assertNothingSent();
});
