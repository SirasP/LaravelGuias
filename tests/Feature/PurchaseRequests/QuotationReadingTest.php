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

it('drops a unit the image never mentioned', function () {
    // Caso real: leyendo una foto de solicitud, el modelo puso «Cajas» a unos
    // guantes cuyo documento decía «20 C/ TALLA», y a unas bolsas de basura.
    // Con una imagen no hay texto que contrastar, así que la unidad tiene que
    // estar respaldada por el texto de su propia línea.
    $reader = new App\Services\PurchaseRequests\Reading\LineVerifier;
    $metodo = new ReflectionMethod($reader, 'unidadRespaldadaPorLaLinea');

    // Inventadas: la línea nunca nombra cajas.
    expect($metodo->invoke($reader, 'Cajas', 'GUANTES NITRILO FLOCADO TALLAS M/L'))->toBeFalse();
    expect($metodo->invoke($reader, 'Cajas', 'BOLSA BASURA 50X55/50X70'))->toBeFalse();

    // Legítimas: la línea sí las nombra.
    expect($metodo->invoke($reader, 'Paquetes', 'PAPEL HIGIENICO INDUSTRIAL PAQUETE DE 06 UNIDADES'))->toBeTrue();
    expect($metodo->invoke($reader, 'Litros', 'CLORO LIQUIDO 2 LITROS'))->toBeTrue();
    expect($metodo->invoke($reader, 'Cada talla', 'GUANTES NITRILO FLOCADO TALLAS M/L'))->toBeTrue();
    expect($metodo->invoke($reader, 'Metros', 'PVC 200 mm 295 mtrs'))->toBeTrue();
});

it('splits a quantity that carries its unit stuck to it', function () {
    // Los documentos escriben «295 mtrs» dentro de la columna de cantidad.
    $reader = new App\Services\PurchaseRequests\Reading\LineVerifier;
    $separar = new ReflectionMethod($reader, 'separarCantidadYUnidad');

    expect($separar->invoke($reader, '295 mtrs'))->toBe(['295', 'mtrs']);
    expect($separar->invoke($reader, '1,5'))->toBe(['1,5', null]);
    expect($separar->invoke($reader, '2 metros'))->toBe(['2', 'metros']);
    expect($separar->invoke($reader, '16'))->toBe(['16', null]);
    // Sin número al principio se devuelve tal cual: no se inventa nada.
    expect($separar->invoke($reader, 'varios'))->toBe(['varios', null]);
});

it('maps document abbreviations onto the real catalog', function () {
    $reader = new App\Services\PurchaseRequests\Reading\LineVerifier;
    $mapear = new ReflectionMethod($reader, 'unidadDelCatalogo');
    $catalogo = ['Unidades', 'Metros', 'Litros', 'Kilos', 'Paquetes'];

    expect($mapear->invoke($reader, 'mtrs', $catalogo))->toBe('Metros');
    expect($mapear->invoke($reader, 'un', $catalogo))->toBe('Unidades');
    expect($mapear->invoke($reader, 'kg', $catalogo))->toBe('Kilos');
    expect($mapear->invoke($reader, 'litro', $catalogo))->toBe('Litros');

    // Lo que no calza se descarta en vez de aproximarse.
    expect($mapear->invoke($reader, 'bidones', $catalogo))->toBeNull();
});

it('tells the person the queue is stuck instead of leaving them guessing', function () {
    Storage::fake('local');
    lectorFalso(QuotationReading::of([['product_service' => 'Tubo', 'specification' => null, 'quantity' => '1', 'unit' => 'Unidades']]));

    $owner = User::factory()->create();

    // Un documento subido hace rato y todavía en espera: el worker no corre.
    PurchaseRequestIngestion::query()->create([
        'user_id' => $owner->id,
        'uploader_name_snapshot' => $owner->name,
        'disk' => 'local',
        'path' => 'purchase-requests/ingestions/atascado.pdf',
        'original_name' => 'atascado.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1024,
        'sha256' => str_repeat('a', 64),
        'status' => PurchaseRequestIngestion::PENDING,
    ]);

    // Eloquent pisa `created_at` al crear, así que se envejece después.
    PurchaseRequestIngestion::query()->where('original_name', 'atascado.pdf')
        ->update(['created_at' => now()->subMinutes(10)]);

    $this->actingAs($owner)
        ->get(route('purchase_requests.ingestions.index'))
        ->assertOk()
        ->assertSee('Hay un documento esperando hace rato')
        // Y se dice exactamente cómo arreglarlo.
        ->assertSee('php artisan queue:work')
        ->assertSee('El documento no se perdió', false);
});

it('refreshes on its own while something is being read', function () {
    Storage::fake('local');
    lectorFalso(QuotationReading::of([['product_service' => 'Tubo', 'specification' => null, 'quantity' => '1', 'unit' => 'Unidades']]));

    $owner = User::factory()->create();

    PurchaseRequestIngestion::query()->create([
        'user_id' => $owner->id,
        'uploader_name_snapshot' => $owner->name,
        'disk' => 'local',
        'path' => 'purchase-requests/ingestions/reciente.pdf',
        'original_name' => 'reciente.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1024,
        'sha256' => str_repeat('b', 64),
        'status' => PurchaseRequestIngestion::PROCESSING,
    ]);

    $this->actingAs($owner)
        ->get(route('purchase_requests.ingestions.index'))
        ->assertOk()
        ->assertSee('Estamos leyendo un documento')
        ->assertSee('window.location.reload()', false)
        // Recién subido no es motivo de alarma.
        ->assertDontSee('Hay un documento esperando hace rato');
});

it('records who issued the quotation and who it was addressed to', function () {
    Storage::fake('local');
    lectorFalso(QuotationReading::of(
        items: [['product_service' => 'Rodamiento 6202', 'specification' => null, 'quantity' => '5', 'unit' => 'Unidades']],
        supplier: 'Derco Repuestos',
        supplierTaxId: '77045469-7',
        customerTaxId: '77415879-0',
    ));

    $owner = User::factory()->create();

    $this->actingAs($owner)->post(route('purchase_requests.ingestions.store'), [
        'document' => UploadedFile::fake()->create('cotizacion-549.pdf', 90, 'application/pdf'),
    ]);

    $ingestion = PurchaseRequestIngestion::query()->firstOrFail()->fresh();

    expect($ingestion->supplier_tax_id)->toBe('77045469-7')
        ->and($ingestion->customer_tax_id)->toBe('77415879-0')
        // El documento va dirigido a esta empresa.
        ->and($ingestion->customer_matches_company)->toBeTrue();

    // El proveedor queda con su RUT: el nombre se escribe de mil formas.
    expect($ingestion->purchaseRequest->suggested_suppliers)
        ->toBe(['Derco Repuestos (RUT 77.045.469-7)']);
});

it('warns when the quotation was addressed to a different company', function () {
    Storage::fake('local');
    lectorFalso(QuotationReading::of(
        items: [['product_service' => 'Tubo', 'specification' => null, 'quantity' => '1', 'unit' => 'Unidades']],
        supplierTaxId: '77045469-7',
        // Un RUT válido que no es el de la empresa.
        customerTaxId: '77045469-7',
    ));

    $owner = User::factory()->create();

    $this->actingAs($owner)->post(route('purchase_requests.ingestions.store'), [
        'document' => UploadedFile::fake()->create('ajena.pdf', 90, 'application/pdf'),
    ]);

    $ingestion = PurchaseRequestIngestion::query()->firstOrFail()->fresh();

    expect($ingestion->customer_matches_company)->toBeFalse();
});

it('puts the product name in the name and the code in the specification', function () {
    // En la factura de un proveedor real, el modelo dejaba el código pegado al
    // nombre además de en su propia columna: «KU0214-014047 ANILLO PISTON STD»
    // con especificación «KU0214-014047».
    $reader = new App\Services\PurchaseRequests\Reading\LineVerifier;
    $separar = new ReflectionMethod($reader, 'separarCodigoDelNombre');

    expect($separar->invoke($reader, 'KU0214-014047 ANILLO PISTON STD', 'KU0214-014047'))
        ->toBe(['ANILLO PISTON STD', 'KU0214-014047']);

    // También si el código va al final.
    expect($separar->invoke($reader, 'METAL BIELA STD KU0214-180020', 'KU0214-180020'))
        ->toBe(['METAL BIELA STD', 'KU0214-180020']);

    // Nombre y especificación idénticos: la especificación no aporta nada.
    expect($separar->invoke($reader, 'Tubo PVC 75mm', 'Tubo PVC 75mm'))
        ->toBe(['Tubo PVC 75mm', null]);

    // Ante la duda no se toca: una especificación legítima se conserva.
    expect($separar->invoke($reader, 'Tubo PVC 75mm', 'Sanitario'))
        ->toBe(['Tubo PVC 75mm', 'Sanitario']);

    // Y no se recorta si lo que quedaría no es un nombre legible.
    expect($separar->invoke($reader, 'ABCD', 'ABC'))->toBe(['ABCD', 'ABC']);
});

it('never passes off the suppliers line of business as the purchase reason', function () {
    Storage::fake('local');
    // El documento no declara motivo: el lector devuelve reason vacío.
    lectorFalso(QuotationReading::of(
        items: [['product_service' => 'Anillo pistón', 'specification' => null, 'quantity' => '4', 'unit' => 'Unidades']],
        supplier: 'MOTORMAN S.A',
        reason: null,
        supplierTaxId: '77591550-1',
        customerTaxId: '77415879-0',
    ));

    $owner = User::factory()->create();

    $this->actingAs($owner)->post(route('purchase_requests.ingestions.store'), [
        'document' => UploadedFile::fake()->create('factura.pdf', 90, 'application/pdf'),
    ]);

    $borrador = PurchaseRequestIngestion::query()->firstOrFail()->fresh()->purchaseRequest;

    // Un documento comercial dice a qué se dedica quien vende, no por qué
    // compras. Se deja constancia de con quién es la compra y se pide el resto.
    expect($borrador->reason)->toBe('Compra a MOTORMAN S.A (RUT 77.591.550-1). Completar el motivo.');
});

it('keeps a reason the document actually declares', function () {
    Storage::fake('local');
    lectorFalso(QuotationReading::of(
        items: [['product_service' => 'Tubo', 'specification' => null, 'quantity' => '1', 'unit' => 'Unidades']],
        reason: 'Materiales Casa n°2; Materiales Casino de Operarios',
    ));

    $owner = User::factory()->create();

    $this->actingAs($owner)->post(route('purchase_requests.ingestions.store'), [
        'document' => UploadedFile::fake()->create('solicitud.pdf', 90, 'application/pdf'),
    ]);

    $borrador = PurchaseRequestIngestion::query()->firstOrFail()->fresh()->purchaseRequest;

    expect($borrador->reason)->toBe('Materiales Casa n°2; Materiales Casino de Operarios');
});

it('tells the admin when a worker uploads a quotation', function () {
    // El caso que motiva todo: un trabajador cotiza en terreno y la cotización
    // se pierde porque nadie recuerda subirla. Si el aviso fuera sólo para
    // quien sube, seguiría perdiéndose igual.
    Storage::fake('local');
    Illuminate\Support\Facades\Notification::fake();

    $trabajador = User::factory()->create(['name' => 'José Ancacura']);
    $admin = User::factory()->admin()->create();

    lectorFalso(QuotationReading::of(
        items: [['product_service' => 'Anillo pistón', 'specification' => null, 'quantity' => '4', 'unit' => 'Unidades']],
        supplier: 'MOTORMAN S.A',
        supplierTaxId: '77591550-1',
    ));

    $this->actingAs($trabajador)->post(route('purchase_requests.ingestions.store'), [
        'document' => UploadedFile::fake()->create('cotizacion-terreno.pdf', 90, 'application/pdf'),
    ]);

    // Al trabajador, porque es su documento.
    Illuminate\Support\Facades\Notification::assertSentTo($trabajador, App\Notifications\QuotationDraftReady::class);
    // Y al administrador, aunque él no subió nada.
    Illuminate\Support\Facades\Notification::assertSentTo($admin, App\Notifications\QuotationDraftReady::class);
});

it('sends each recipient a link they can actually open', function () {
    Storage::fake('local');

    $trabajador = User::factory()->create(['name' => 'José Ancacura']);
    $admin = User::factory()->admin()->create();

    lectorFalso(QuotationReading::of(
        items: [['product_service' => 'Anillo pistón', 'specification' => null, 'quantity' => '4', 'unit' => 'Unidades']],
        supplier: 'MOTORMAN S.A',
    ));

    $this->actingAs($trabajador)->post(route('purchase_requests.ingestions.store'), [
        'document' => UploadedFile::fake()->create('cotizacion-terreno.pdf', 90, 'application/pdf'),
    ]);

    $borrador = PurchaseRequestIngestion::query()->firstOrFail()->fresh()->purchaseRequest;

    $avisoTrabajador = $trabajador->notifications()->firstOrFail()->data;
    $avisoAdmin = $admin->notifications()->firstOrFail()->data;

    // El autor edita su borrador; el administrador sólo puede verlo, así que
    // mandarlo a editar sería mandarlo a un «acceso denegado».
    expect($avisoTrabajador['url'])->toBe(route('purchase_requests.edit', $borrador->public_id));
    expect($avisoAdmin['url'])->toBe(route('purchase_requests.show', $borrador->public_id));

    // Y el texto habla de quién cotizó, que es lo que el administrador necesita.
    expect($avisoAdmin['title'])->toContain('José Ancacura')
        ->and($avisoAdmin['message'])->toContain('MOTORMAN S.A');

    // El enlace del administrador funciona de verdad.
    $this->actingAs($admin)->get($avisoAdmin['url'])->assertOk();
});

it('does not take the salesperson for the supplier', function () {
    // La cotización 549 trae «Vendedor: M. FERNANDA MANSILLA TORR». El
    // asistente registró a esa persona como proveedor de la solicitud, en vez
    // de a DERCOMAQ S.P.A., que es la empresa que emite el documento.
    Storage::fake('local');
    lectorFalso(QuotationReading::of(
        items: [['product_service' => 'Rodamiento 6202', 'specification' => '07297', 'quantity' => '5', 'unit' => 'Unidades']],
        supplier: 'DERCOMAQ S.P.A.',
        supplierTaxId: '77045469-7',
    ));

    $owner = User::factory()->create();

    $this->actingAs($owner)->post(route('purchase_requests.ingestions.store'), [
        'document' => UploadedFile::fake()->create('cot-549.pdf', 90, 'application/pdf'),
    ]);

    $borrador = PurchaseRequestIngestion::query()->firstOrFail()->fresh()->purchaseRequest;

    expect($borrador->suggested_suppliers)->toBe(['DERCOMAQ S.P.A. (RUT 77.045.469-7)'])
        ->and($borrador->reason)->toContain('DERCOMAQ');
});

it('demands that a specific unit be backed by its own line', function () {
    // Leyendo una cotización de rodamientos, el modelo puso «Cada medida» a
    // dos líneas que no mencionaban ninguna medida. Como esa unidad existe en
    // el catálogo, pasaba el filtro: estar en el catálogo no es respaldo.
    $verificador = new App\Services\PurchaseRequests\Reading\LineVerifier;
    $catalogo = ['Unidades', 'Metros', 'Cada medida', 'Cajas'];

    [$items, $avisos] = $verificador->verificarContraElDocumento([
        ['product_service' => 'RODAMIENTO 6202 2RS2 C3 NKE', 'specification' => '07297', 'quantity' => '5', 'unit' => 'Cada medida'],
    ], 'RODAMIENTO 6202 2RS2 C3 NKE 07297 5', $catalogo, false);

    expect($items[0]['unit'])->toBeNull();
    expect(collect($avisos)->contains(fn ($a) => str_contains($a, 'Cada medida')))->toBeTrue();
});

it('accepts a unit written inside the quantity itself', function () {
    // Los documentos escriben «295 mtrs» en la columna de cantidad, así que
    // ese texto también respalda la unidad.
    $verificador = new App\Services\PurchaseRequests\Reading\LineVerifier;

    [$items] = $verificador->verificarContraElDocumento([
        ['product_service' => 'PVC 200 mm', 'specification' => null, 'quantity' => '295 mtrs', 'unit' => null],
    ], 'PVC 200 mm 295 mtrs', ['Metros', 'Unidades'], false);

    expect($items[0]['quantity'])->toBe('295')
        ->and($items[0]['unit'])->toBe('Metros');
});

it('lets the neutral unit through when the document declares none', function () {
    // «Unidades» es contar piezas: cuando el documento no declara nada, no es
    // una invención. Sí lo sería «Cajas» o «Litros», que afirman algo.
    $verificador = new App\Services\PurchaseRequests\Reading\LineVerifier;
    $catalogo = ['Unidades', 'Cajas', 'Litros'];

    [$items] = $verificador->verificarContraElDocumento([
        ['product_service' => 'Rodamiento 6202', 'specification' => null, 'quantity' => '5', 'unit' => 'Unidades'],
        ['product_service' => 'Rodamiento 6002', 'specification' => null, 'quantity' => '5', 'unit' => 'Cajas'],
    ], 'Rodamiento 6202 5 Rodamiento 6002 5', $catalogo, false);

    expect($items[0]['unit'])->toBe('Unidades');
    expect($items[1]['unit'])->toBeNull();
});
