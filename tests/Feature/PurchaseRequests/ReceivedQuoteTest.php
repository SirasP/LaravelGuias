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
        ->assertSee('Cotizaciones')
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

it('keeps the short @php form out of the request screen', function () {
    // Blade empareja cada `@php` con el siguiente `@endphp` del archivo. La
    // forma corta `@php($x = 1)` no tiene cierre propio, así que se traga todo
    // lo que haya hasta el próximo `@endphp` y el resto de la plantilla queda
    // sin compilar: la página revienta con un error de sintaxis.
    //
    // Pasó de verdad al reordenar las secciones el 03-09-2026: mientras el
    // bloque de Odoo iba antes, el emparejamiento salía bien por casualidad.
    $vista = file_get_contents(resource_path('views/purchase_requests/show.blade.php'));

    // Sin los comentarios, que sí pueden nombrar la forma corta para advertir.
    $sinComentarios = preg_replace('/\{\{--.*?--\}\}/s', '', $vista);

    expect($sinComentarios)->not->toMatch('/@php\s*\(/');
});

it('takes several quotations for the same request and compares each one', function () {
    // Pedir a tres proveedores y contrastar las tres contra lo mismo es el
    // caso normal de una cotización, no la excepción.
    Queue::fake();

    $owner = User::factory()->create();
    $solicitud = $this->createPurchaseRequestDraft($owner);

    foreach (['sodimac.pdf', 'construmart.pdf', 'easy.pdf'] as $i => $nombre) {
        $this->actingAs($owner)
            ->post(route('purchase_requests.quotes.store', $solicitud), [
                'quote' => UploadedFile::fake()->createWithContent($nombre, 'contenido distinto '.$i),
            ])
            ->assertSessionHasNoErrors();
    }

    expect($solicitud->receivedQuotes()->count())->toBe(3);

    Queue::assertPushed(ReadQuotationDocument::class, 3);
});

it('shows one comparison per quotation on the screen', function () {
    $owner = User::factory()->create();
    $solicitud = $this->createPurchaseRequestDraft($owner);
    $solicitud->items()->delete();
    $solicitud->items()->create([
        'sort_order' => 1, 'product_service' => 'CEMENTO 25 KG',
        'quantity' => 10, 'unit' => 'Unidades', 'unit_price' => null,
    ]);

    $cotizacion = function (string $proveedor, string $archivo, int $precio, string $hash) use ($owner, $solicitud) {
        PurchaseRequestIngestion::query()->create([
            'user_id' => $owner->getKey(), 'uploader_name_snapshot' => $owner->name,
            'compared_request_id' => $solicitud->getKey(),
            'disk' => 'local', 'path' => $archivo, 'original_name' => $archivo,
            'mime_type' => 'application/pdf', 'size' => 10, 'sha256' => str_repeat($hash, 64),
            'status' => PurchaseRequestIngestion::COMPLETED,
            'supplier_name' => $proveedor,
            'extracted' => ['items' => [[
                'product_service' => 'CEMENTO 25 KG', 'specification' => null,
                'quantity' => '10', 'unit' => 'Unidades', 'unit_price' => $precio,
            ]]],
        ]);
    };

    $cotizacion('SODIMAC S.A.', 'sodimac.pdf', 4500, 'a');
    $cotizacion('CONSTRUMART S.A.', 'construmart.pdf', 3900, 'b');

    $this->actingAs($owner)
        ->get(route('purchase_requests.show', $solicitud))
        ->assertOk()
        // Cada una con su tabla, para poder elegir mirando los dos precios.
        ->assertSee('Cotizó SODIMAC S.A.')
        ->assertSee('Cotizó CONSTRUMART S.A.')
        ->assertSee('4.500')
        ->assertSee('3.900')
        // Y el contador de la columna derecha las cuenta.
        ->assertSee('Cotizaciones');
});
