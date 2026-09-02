<?php

use App\Jobs\ReadQuotationDocument;
use App\Models\PurchaseRequestIngestion;
use App\Models\User;
use App\Services\PurchaseRequests\Reading\QuotationReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

beforeEach(function () {
    Storage::fake('local');
    config(['purchase_requests.reader.enabled' => true]);

    app()->bind(QuotationReader::class, fn () => new class implements QuotationReader
    {
        public function isEnabled(): bool
        {
            return true;
        }

        public function describe(): string
        {
            return 'lector de pruebas';
        }

        public function read(string $absolutePath, string $mimeType, array $knownUnits = []): \App\Services\PurchaseRequests\Reading\QuotationReading
        {
            return \App\Services\PurchaseRequests\Reading\QuotationReading::of(items: []);
        }
    });
});

it('takes the supplier quotation and leaves it linked to the request', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $solicitud = $this->createPurchaseRequestDraft($owner);

    $this->actingAs($owner)
        ->post(route('purchase_requests.quotes.store', $solicitud), [
            'quote' => UploadedFile::fake()->create('cotizacion.pdf', 40, 'application/pdf'),
        ])
        ->assertRedirect(route('purchase_requests.show', $solicitud));

    $lectura = PurchaseRequestIngestion::query()->first();

    expect($lectura->compared_request_id)->toBe($solicitud->getKey())
        // Lo importante: no nace ninguna solicitud de aquí. Se compara.
        ->and($lectura->purchase_request_id)->toBeNull();

    Queue::assertPushed(ReadQuotationDocument::class);
});

it('shows the differences on the screen of the request', function () {
    $owner = User::factory()->create();
    $solicitud = $this->createPurchaseRequestDraft($owner);
    $solicitud->items()->delete();
    $solicitud->items()->create([
        'sort_order' => 1, 'product_service' => 'CANDADO GRIPPLE',
        'quantity' => 3, 'unit' => 'Unidades', 'unit_price' => null,
    ]);

    PurchaseRequestIngestion::query()->create([
        'user_id' => $owner->getKey(),
        'uploader_name_snapshot' => $owner->name,
        'compared_request_id' => $solicitud->getKey(),
        'disk' => 'local', 'path' => 'x.pdf', 'original_name' => 'cotizacion-sodimac.pdf',
        'mime_type' => 'application/pdf', 'size' => 10, 'sha256' => str_repeat('a', 64),
        'status' => PurchaseRequestIngestion::COMPLETED,
        'extracted' => ['items' => [
            ['product_service' => 'CANDADO TIPO GRIPPLE', 'specification' => null,
                'quantity' => '2', 'unit' => 'Unidades', 'unit_price' => 4500],
        ]],
    ]);

    $this->actingAs($owner)
        ->get(route('purchase_requests.show', $solicitud))
        ->assertOk()
        ->assertSee('Cotización del proveedor')
        ->assertSee('cotizacion-sodimac.pdf')
        ->assertSee('Pediste 3 y cotizaron 2.');
});

it('refuses to compare the same file against two requests at once', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $primera = $this->createPurchaseRequestDraft($owner);
    $segunda = $this->createPurchaseRequestDraft($owner);

    $archivo = UploadedFile::fake()->create('cotizacion.pdf', 40, 'application/pdf');

    $this->actingAs($owner)->post(route('purchase_requests.quotes.store', $primera), ['quote' => $archivo]);

    $this->actingAs($owner)
        ->post(route('purchase_requests.quotes.store', $segunda), [
            'quote' => UploadedFile::fake()->createWithContent('cotizacion.pdf', file_get_contents($archivo->getRealPath())),
        ])
        ->assertRedirect();

    expect(PurchaseRequestIngestion::query()->count())->toBe(1)
        ->and(PurchaseRequestIngestion::query()->first()->compared_request_id)->toBe($primera->getKey());
});

it('lets the person take a quotation off without losing the document', function () {
    $owner = User::factory()->create();
    $solicitud = $this->createPurchaseRequestDraft($owner);

    $lectura = PurchaseRequestIngestion::query()->create([
        'user_id' => $owner->getKey(), 'uploader_name_snapshot' => $owner->name,
        'compared_request_id' => $solicitud->getKey(),
        'disk' => 'local', 'path' => 'x.pdf', 'original_name' => 'c.pdf',
        'mime_type' => 'application/pdf', 'size' => 10, 'sha256' => str_repeat('b', 64),
        'status' => PurchaseRequestIngestion::COMPLETED,
    ]);

    $this->actingAs($owner)
        ->delete(route('purchase_requests.quotes.destroy', [$solicitud, $lectura]))
        ->assertRedirect();

    expect($lectura->fresh()->compared_request_id)->toBeNull()
        ->and(PurchaseRequestIngestion::query()->count())->toBe(1);
});

it('keeps someone else out of a request that is not theirs', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $ajeno = User::factory()->create();
    $solicitud = $this->createPurchaseRequestDraft($owner);

    $this->actingAs($ajeno)
        ->post(route('purchase_requests.quotes.store', $solicitud), [
            'quote' => UploadedFile::fake()->create('cotizacion.pdf', 40, 'application/pdf'),
        ])
        ->assertForbidden();

    expect(PurchaseRequestIngestion::query()->count())->toBe(0);
});
