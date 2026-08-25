<?php

use App\Enums\PurchaseRequestStatus;
use App\Jobs\ReadQuotationDocument;
use App\Models\PurchaseRequestEvent;
use App\Models\PurchaseRequestIngestion;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\PurchaseRequests\Reading\NullQuotationReader;
use App\Services\PurchaseRequests\Reading\QuotationReader;
use App\Services\PurchaseRequests\Reading\QuotationReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

/** Lector de mentira: devuelve lo que se le indique, sin tocar ningún modelo. */
function lectorFalso(QuotationReading $lectura): void
{
    app()->bind(QuotationReader::class, fn () => new class($lectura) implements QuotationReader
    {
        public function __construct(private readonly QuotationReading $lectura) {}

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
            return $this->lectura;
        }
    });
}

it('answers immediately and leaves the reading for the background', function () {
    Storage::fake('local');
    Queue::fake();
    lectorFalso(QuotationReading::of([['product_service' => 'Tubo PVC', 'specification' => null, 'quantity' => '3', 'unit' => 'Unidades']]));

    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->post(route('purchase_requests.ingestions.store'), [
            'document' => UploadedFile::fake()->create('cotizacion.pdf', 120, 'application/pdf'),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('purchase_requests.ingestions.index'));

    // La petición no lee nada: sólo guarda y encola.
    Queue::assertPushed(ReadQuotationDocument::class);

    $ingestion = PurchaseRequestIngestion::query()->firstOrFail();
    expect($ingestion->status)->toBe(PurchaseRequestIngestion::PENDING)
        ->and($ingestion->purchase_request_id)->toBeNull();
});

it('creates a draft that nobody sent to review', function () {
    Storage::fake('local');
    UnitOfMeasure::query()->create(['company_code' => 'EHE', 'code' => 'm', 'name' => 'Metros', 'is_active' => true]);
    lectorFalso(QuotationReading::of(
        items: [
            ['product_service' => 'PVC 200 mm', 'specification' => null, 'quantity' => '295', 'unit' => 'Metros'],
            ['product_service' => 'Cubos de Bolones', 'specification' => null, 'quantity' => '1,5', 'unit' => 'Metros'],
        ],
        supplier: 'Sodimac',
        reason: 'Materiales Casa n°2',
        model: 'modelo-de-prueba',
        sourceKind: 'pdf_text',
    ));

    $owner = User::factory()->create();

    $this->actingAs($owner)->post(route('purchase_requests.ingestions.store'), [
        'document' => UploadedFile::fake()->create('cotizacion.pdf', 120, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $ingestion = PurchaseRequestIngestion::query()->firstOrFail();
    $borrador = $ingestion->fresh()->purchaseRequest;

    // Lo que produce es SIEMPRE un borrador: una lectura equivocada se corrige
    // antes de que exista una solicitud formal.
    expect($borrador)->not->toBeNull()
        ->and($borrador->status)->toBe(PurchaseRequestStatus::DRAFT)
        ->and($borrador->reason)->toBe('Materiales Casa n°2')
        ->and($borrador->suggested_suppliers)->toBe(['Sodimac'])
        ->and($borrador->items)->toHaveCount(2);

    // La coma decimal del documento sobrevive.
    expect((float) $borrador->items()->where('sort_order', 2)->value('quantity'))->toBe(1.5);
});

it('records where the draft came from', function () {
    Storage::fake('local');
    lectorFalso(QuotationReading::of(
        items: [['product_service' => 'Tubo', 'specification' => null, 'quantity' => '2', 'unit' => 'Unidades']],
        model: 'qwen-de-prueba',
        sourceKind: 'pdf_text',
    ));

    $owner = User::factory()->create();

    $this->actingAs($owner)->post(route('purchase_requests.ingestions.store'), [
        'document' => UploadedFile::fake()->create('cotizacion-abril.pdf', 90, 'application/pdf'),
    ]);

    $ingestion = PurchaseRequestIngestion::query()->firstOrFail()->fresh();
    $evento = $ingestion->purchaseRequest->events()
        ->where('event_type', PurchaseRequestEvent::AI_DRAFTED)
        ->firstOrFail();

    // Siempre se puede responder de dónde salió una solicitud del asistente.
    expect($evento->metadata['documento'])->toBe('cotizacion-abril.pdf')
        ->and($evento->metadata['modelo'])->toBe('qwen-de-prueba')
        ->and($evento->metadata['partidas'])->toBe(1)
        ->and($ingestion->status)->toBe(PurchaseRequestIngestion::COMPLETED);
});

it('marks a doubtful reading instead of pretending it went well', function () {
    Storage::fake('local');
    lectorFalso(QuotationReading::of(
        items: [['product_service' => 'Codos de 75mm', 'specification' => null, 'quantity' => null, 'unit' => null]],
        warnings: ['Partida N° 1: falta la cantidad.'],
    ));

    $owner = User::factory()->create();

    $this->actingAs($owner)->post(route('purchase_requests.ingestions.store'), [
        'document' => UploadedFile::fake()->create('borrosa.pdf', 90, 'application/pdf'),
    ]);

    $ingestion = PurchaseRequestIngestion::query()->firstOrFail()->fresh();

    expect($ingestion->status)->toBe(PurchaseRequestIngestion::NEEDS_REVIEW)
        ->and($ingestion->warnings)->toContain('Partida N° 1: falta la cantidad.');

    // Sin cantidad legible queda en cero, y enviar exige una cantidad mayor
    // que cero: el borrador no puede escaparse a medias.
    $item = $ingestion->purchaseRequest->items()->firstOrFail();
    expect((float) $item->quantity)->toBe(0.0);

    $this->actingAs($owner)
        ->post(route('purchase_requests.submit', $ingestion->purchaseRequest))
        ->assertSessionHasErrors('items');
});

it('reports a failed reading without leaving a half-made draft', function () {
    Storage::fake('local');
    lectorFalso(QuotationReading::failed('El modelo no respondió.'));

    $owner = User::factory()->create();

    $this->actingAs($owner)->post(route('purchase_requests.ingestions.store'), [
        'document' => UploadedFile::fake()->create('ilegible.pdf', 90, 'application/pdf'),
    ]);

    $ingestion = PurchaseRequestIngestion::query()->firstOrFail()->fresh();

    expect($ingestion->status)->toBe(PurchaseRequestIngestion::FAILED)
        ->and($ingestion->error_message)->toContain('El modelo no respondió')
        ->and($ingestion->purchase_request_id)->toBeNull();
});

it('does not read the same document twice', function () {
    Storage::fake('local');
    Queue::fake();
    lectorFalso(QuotationReading::of([['product_service' => 'Tubo', 'specification' => null, 'quantity' => '1', 'unit' => 'Unidades']]));

    $owner = User::factory()->create();
    $archivo = UploadedFile::fake()->create('repetida.pdf', 100, 'application/pdf');

    foreach (range(1, 3) as $intento) {
        $this->actingAs($owner)->post(route('purchase_requests.ingestions.store'), ['document' => $archivo]);
    }

    // Leer cuesta minutos de CPU y crearía borradores duplicados.
    expect(PurchaseRequestIngestion::query()->count())->toBe(1);
    Queue::assertPushed(ReadQuotationDocument::class, 1);
});

it('refuses a file that is not really a pdf or an image', function () {
    Storage::fake('local');
    lectorFalso(QuotationReading::of([]));

    $owner = User::factory()->create();

    $ruta = tempnam(sys_get_temp_dir(), 'sc-falso-');
    file_put_contents($ruta, "<?php echo 'no soy una cotizacion';");
    $disfrazado = new UploadedFile($ruta, 'cotizacion.pdf', 'application/pdf', null, true);

    $this->actingAs($owner)
        ->post(route('purchase_requests.ingestions.store'), ['document' => $disfrazado])
        ->assertSessionHasErrors('document');

    expect(PurchaseRequestIngestion::query()->count())->toBe(0);

    @unlink($ruta);
});

it('keeps each persons documents private', function () {
    Storage::fake('local');
    lectorFalso(QuotationReading::of([['product_service' => 'Tubo', 'specification' => null, 'quantity' => '1', 'unit' => 'Unidades']]));

    $owner = User::factory()->create();
    $curioso = User::factory()->admin()->create();

    $this->actingAs($owner)->post(route('purchase_requests.ingestions.store'), [
        'document' => UploadedFile::fake()->create('privada.pdf', 90, 'application/pdf'),
    ]);

    $ingestion = PurchaseRequestIngestion::query()->firstOrFail();

    $this->actingAs($owner)
        ->get(route('purchase_requests.ingestions.download', $ingestion))->assertOk();

    // Ni siquiera un administrador descarga el documento de otra persona.
    $this->actingAs($curioso)
        ->get(route('purchase_requests.ingestions.download', $ingestion))->assertForbidden();

    $this->actingAs($curioso)
        ->get(route('purchase_requests.ingestions.index'))
        ->assertOk()
        ->assertDontSee('privada.pdf');
});

it('stays out of the way when the assistant is switched off', function () {
    app()->bind(QuotationReader::class, fn () => new NullQuotationReader);

    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->post(route('purchase_requests.ingestions.store'), [
            'document' => UploadedFile::fake()->create('cotizacion.pdf', 90, 'application/pdf'),
        ])
        ->assertSessionHas('error');

    expect(PurchaseRequestIngestion::query()->count())->toBe(0);

    // Y el formulario manual sigue funcionando igual.
    $this->createPurchaseRequestDraft($owner);
});
