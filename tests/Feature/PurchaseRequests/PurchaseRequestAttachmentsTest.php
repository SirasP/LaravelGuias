<?php

use App\Models\PurchaseRequestAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

it('rejects a file whose real content is not a pdf or an image', function () {
    Storage::fake('local');
    $owner = User::factory()->create();

    // Archivo real en disco: `UploadedFile::fake()` no sirve aquí porque
    // declara el MIME en vez de deducirlo del contenido, que es justamente lo
    // que esta prueba debe ejercitar.
    $path = tempnam(sys_get_temp_dir(), 'sc-test-');
    file_put_contents($path, "<?php echo 'contenido ejecutable disfrazado'; ?>");

    // Extensión y MIME declarados inocentes; el contenido no lo es.
    $disguised = new UploadedFile($path, 'inocente.pdf', 'application/pdf', null, true);

    $this->actingAs($owner)
        ->post(route('purchase_requests.store'), $this->validPurchaseRequestPayload([
            'attachments' => [$disguised],
        ]))
        ->assertSessionHasErrors('attachments.0');

    expect(PurchaseRequestAttachment::query()->count())->toBe(0);

    @unlink($path);
});

it('rejects a file that exceeds the size limit', function () {
    Storage::fake('local');
    $owner = User::factory()->create();

    $huge = UploadedFile::fake()->create('enorme.pdf', 11 * 1024, 'application/pdf');

    $this->actingAs($owner)
        ->post(route('purchase_requests.store'), $this->validPurchaseRequestPayload([
            'attachments' => [$huge],
        ]))
        ->assertSessionHasErrors('attachments.0');

    expect(PurchaseRequestAttachment::query()->count())->toBe(0);
});

it('accepts a valid image and records its hash, size and author', function () {
    Storage::fake('local');
    $owner = User::factory()->create();

    $photo = UploadedFile::fake()->image('lista.jpg', 400, 300);

    $request = $this->createPurchaseRequestDraft($owner, ['attachments' => [$photo]]);

    $attachment = $request->attachments()->firstOrFail();

    expect($attachment->original_name)->toBe('lista.jpg')
        ->and($attachment->uploaded_by)->toBe($owner->id)
        ->and($attachment->disk)->toBe('local')
        ->and($attachment->sha256)->toHaveLength(64)
        ->and($attachment->size)->toBeGreaterThan(0);

    Storage::disk('local')->assertExists($attachment->path);
});

it('never exposes an attachment through a public url', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $request = $this->createPurchaseRequestDraft($owner, [
        'attachments' => [UploadedFile::fake()->image('privado.png')],
    ]);
    $attachment = $request->attachments()->firstOrFail();

    // La ruta interna no se filtra en el HTML de la página.
    $this->actingAs($owner)
        ->get(route('purchase_requests.show', $request))
        ->assertOk()
        ->assertDontSee($attachment->path);

    // El dueño la descarga por el controlador autorizado.
    $this->actingAs($owner)
        ->get(route('purchase_requests.attachments.download', [$request, $attachment]))
        ->assertOk();

    // Un tercero no, aunque conozca los identificadores.
    $this->actingAs($stranger)
        ->get(route('purchase_requests.attachments.download', [$request, $attachment]))
        ->assertForbidden();

    // Y un anónimo tampoco.
    auth()->logout();
    $this->get(route('purchase_requests.attachments.download', [$request, $attachment]))
        ->assertRedirect(route('login'));
});

it('does not let an attachment be fetched through another request id', function () {
    Storage::fake('local');
    $owner = User::factory()->create();

    $withFile = $this->createPurchaseRequestDraft($owner, [
        'attachments' => [UploadedFile::fake()->image('uno.png')],
    ]);
    $other = $this->createPurchaseRequestDraft($owner);
    $attachment = $withFile->attachments()->firstOrFail();

    // El adjunto pertenece a otra solicitud del mismo usuario: aun teniendo
    // permiso sobre ambas, la combinación es inválida.
    $this->actingAs($owner)
        ->get(route('purchase_requests.attachments.download', [$other, $attachment]))
        ->assertNotFound();
});
