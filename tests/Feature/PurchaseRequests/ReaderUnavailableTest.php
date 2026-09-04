<?php

use App\Jobs\ReadQuotationDocument;
use App\Models\PurchaseRequestIngestion;
use App\Models\User;
use App\Notifications\QuotationWaitingForReader;
use App\Services\PurchaseRequests\Reading\LocalQuotationReader;
use App\Services\PurchaseRequests\Reading\QuotationReader;
use App\Services\PurchaseRequests\Reading\QuotationReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** Un lector que siempre contesta lo mismo, sea lo que sea. */
function lectorQueResponde(QuotationReading $respuesta): void
{
    app()->bind(QuotationReader::class, fn () => new class($respuesta) implements QuotationReader
    {
        public function __construct(private readonly QuotationReading $respuesta) {}

        public function isEnabled(): bool
        {
            return true;
        }

        public function describe(): string
        {
            return 'lector-de-prueba';
        }

        public function read(string $absolutePath, string $mimeType, array $knownUnits = []): QuotationReading
        {
            return $this->respuesta;
        }
    });
}

function documentoSubido(User $dueno): PurchaseRequestIngestion
{
    Storage::fake('local');
    Storage::disk('local')->put('cotizaciones/prueba.pdf', 'contenido');

    return PurchaseRequestIngestion::create([
        'user_id' => $dueno->getKey(),
        'uploader_name_snapshot' => $dueno->name,
        'disk' => 'local',
        'path' => 'cotizaciones/prueba.pdf',
        'original_name' => 'cotizacion-549.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1024,
        'sha256' => hash('sha256', 'contenido'.uniqid()),
        'status' => PurchaseRequestIngestion::PENDING,
    ]);
}

it('leaves the document waiting instead of failing when the reader is unreachable', function () {
    Notification::fake();

    $dueno = User::factory()->create();
    $ingestion = documentoSubido($dueno);

    lectorQueResponde(QuotationReading::unreachable('No se pudo contactar al modelo: Connection refused'));

    $job = (new ReadQuotationDocument($ingestion))->withFakeQueueInteractions();
    $job->handle(app(QuotationReader::class));

    // Ni «no se pudo leer» ni terminado: sigue vivo, en pausa.
    $ingestion->refresh();
    expect($ingestion->status)->toBe(PurchaseRequestIngestion::WAITING)
        ->and($ingestion->isFinished())->toBeFalse()
        ->and($ingestion->finished_at)->toBeNull();

    // Y vuelve a la cola para intentarlo más tarde.
    $job->assertReleased();

    Notification::assertSentTo($dueno, QuotationWaitingForReader::class);
});

it('still gives up on a document the reader simply could not understand', function () {
    Notification::fake();

    $ingestion = documentoSubido(User::factory()->create());

    // Aquí sí hubo respuesta: el documento es el problema. Reintentar mil veces
    // no lo va a volver legible.
    lectorQueResponde(QuotationReading::failed('El documento no tiene texto legible.'));

    $job = (new ReadQuotationDocument($ingestion))->withFakeQueueInteractions();
    $job->handle(app(QuotationReader::class));

    expect($ingestion->refresh()->status)->toBe(PurchaseRequestIngestion::FAILED);
    $job->assertNotReleased();

    // Avisa del fallo, como siempre; lo que no hace es prometer una espera
    // que nunca va a terminar en nada.
    Notification::assertNotSentTo($ingestion->uploader, QuotationWaitingForReader::class);
});

it('warns only once, however long the wait lasts', function () {
    Notification::fake();

    $dueno = User::factory()->create();
    $ingestion = documentoSubido($dueno);

    lectorQueResponde(QuotationReading::unreachable('Connection refused'));

    foreach (range(1, 4) as $vuelta) {
        $job = (new ReadQuotationDocument($ingestion->refresh()))->withFakeQueueInteractions();
        $job->handle(app(QuotationReader::class));
    }

    // Cuatro reintentos, un solo aviso: nadie quiere treinta correos por una
    // Mac que estuvo dormida toda la tarde.
    Notification::assertSentToTimes($dueno, QuotationWaitingForReader::class, 1);
});

it('tells a connection problem apart from a bad answer', function () {
    $lector = new LocalQuotationReader;
    $metodo = new ReflectionMethod($lector, 'esProblemaDeConexion');

    // Ausencias: merecen esperar.
    expect($metodo->invoke($lector, new ConnectionException('cURL error 7: Connection refused')))->toBeTrue();
    expect($metodo->invoke($lector, new RuntimeException('Operation timed out after 30000 ms')))->toBeTrue();
    expect($metodo->invoke($lector, new RuntimeException('Failed to connect to 127.0.0.1 port 1234')))->toBeTrue();

    // Problemas de verdad: no mejoran esperando.
    expect($metodo->invoke($lector, new RuntimeException('El modelo no devolvió un JSON válido.')))->toBeFalse();
    expect($metodo->invoke($lector, new RuntimeException('No se pudo abrir el documento.')))->toBeFalse();
});

it('treats an unloaded model as something to wait for, not a failure', function () {
    // LM Studio responde 400 «Model is unloaded» cuando el servidor está vivo
    // pero soltó el modelo de memoria: es lo que provoca el `ttl` que le
    // pedimos para no dejar la Mac ocupada. Darlo por fallo perdía la lectura
    // de un documento perfectamente legible, y así se perdió la cotización
    // 11299 tras 52 intentos.
    $lector = new LocalQuotationReader;
    $metodo = new ReflectionMethod($lector, 'esProblemaDeConexion');

    $pasajeros = [
        'El modelo no respondió: HTTP request returned status code 400: {"error":"Model is unloaded."}',
        'no model loaded',
        'Loading model, please retry',
        'cURL error 7: Connection refused',
    ];

    foreach ($pasajeros as $mensaje) {
        expect($metodo->invoke($lector, new RuntimeException($mensaje)))
            ->toBeTrue("«{$mensaje}» debería esperarse, no fallar");
    }

    // Y lo que sí es un fallo de verdad sigue siéndolo: si se tratara como
    // pasajero, una lectura rota reintentaría doce horas para nada.
    foreach ([
        'El documento no tiene texto legible.',
        'HTTP request returned status code 401: unauthorized',
    ] as $definitivo) {
        expect($metodo->invoke($lector, new RuntimeException($definitivo)))
            ->toBeFalse("«{$definitivo}» no debería quedarse esperando");
    }
});
