<?php

use App\Models\PurchaseRequestAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InteractsWithPurchaseRequests;

uses(InteractsWithPurchaseRequests::class);

it('stores attachments on the private disk and protects deletion by ownership', function () {
    Storage::fake('local');

    $owner = User::factory()->viewer()->create();
    $stranger = User::factory()->viewer()->create();
    $request = $this->createPurchaseRequestDraft($owner, [
        'attachments' => [
            UploadedFile::fake()->create('antecedente.pdf', 80, 'application/pdf'),
        ],
    ]);

    $attachment = PurchaseRequestAttachment::query()
        ->where('purchase_request_id', $request->getKey())
        ->sole();

    Storage::disk('local')->assertExists($attachment->path);
    expect($attachment->path)->not->toStartWith('public/');

    $this
        ->actingAs($stranger)
        ->delete(route('purchase_requests.attachments.destroy', [$request, $attachment]))
        ->assertForbidden();

    Storage::disk('local')->assertExists($attachment->path);

    $response = $this
        ->actingAs($owner)
        ->delete(route('purchase_requests.attachments.destroy', [$request, $attachment]));

    $response->assertRedirect();
    Storage::disk('local')->assertMissing($attachment->path);
    expect($attachment->fresh())->toBeNull();
});

it('serves a private pdf only to the owner or an admin', function () {
    $owner = User::factory()->viewer()->create();
    $stranger = User::factory()->viewer()->create();
    $admin = User::factory()->admin()->create();
    $request = $this->submitPurchaseRequest(
        $owner,
        $this->createPurchaseRequestDraft($owner),
    );

    $ownerResponse = $this
        ->actingAs($owner)
        ->get(route('purchase_requests.pdf', $request));

    $ownerResponse
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($ownerResponse->getContent())->toStartWith('%PDF');

    $this
        ->actingAs($stranger)
        ->get(route('purchase_requests.pdf', $request))
        ->assertForbidden();

    $adminResponse = $this
        ->actingAs($admin)
        ->get(route('purchase_requests.pdf', $request));

    $adminResponse
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
