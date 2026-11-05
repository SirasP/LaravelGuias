<?php

use App\Jobs\ReadQuotationDocument;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestIngestion;
use App\Models\User;
use App\Services\PurchaseRequests\Reading\QuotationReader;
use App\Services\PurchaseRequests\Reading\QuotationReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('reads again from the request a supplier quote that could not be read, and comes back to the request', function () {
    // La de la SC-2026-000114: oMLX no tenía memoria y la lectura falló.
    Queue::fake();
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

        public function read(string $absolutePath, string $mimeType, array $knownUnits = []): QuotationReading
        {
            throw new RuntimeException('no se usa');
        }
    });
    $admin = User::factory()->admin()->create();
    $solicitud = PurchaseRequest::factory()->approved()->create(['user_id' => $admin->id]);
    $lectura = PurchaseRequestIngestion::create([
        'company_code' => 'EHE', 'user_id' => $admin->id, 'uploader_name_snapshot' => $admin->name,
        'compared_request_id' => $solicitud->id, 'disk' => 'local', 'path' => 'cotizacion.pdf',
        'original_name' => 'fitting pvc 200mm y manometro EHE.pdf', 'mime_type' => 'application/pdf', 'size' => 1,
        'sha256' => str_repeat('c', 64), 'status' => PurchaseRequestIngestion::FAILED,
        'error_message' => 'El modelo no respondió: HTTP request returned status code 507',
    ]);

    $this->actingAs($admin)->get(route('purchase_requests.show', $solicitud))->assertOk()
        ->assertSee(route('purchase_requests.ingestions.reread', $lectura))->assertSee('Leer de nuevo');

    $this->post(route('purchase_requests.ingestions.reread', $lectura))
        ->assertRedirect(route('purchase_requests.show', $solicitud))
        ->assertSessionHas('success', fn (string $m) => str_contains($m, 'fitting pvc 200mm y manometro EHE.pdf'));

    expect($lectura->fresh()->status)->toBe(PurchaseRequestIngestion::PENDING);
    Queue::assertPushed(ReadQuotationDocument::class);
});
