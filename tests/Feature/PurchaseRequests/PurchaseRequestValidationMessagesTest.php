<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

it('reports a duplicate supplier in Spanish and by its position', function () {
    $owner = User::factory()->create();

    $response = $this->actingAs($owner)->post(route('purchase_requests.store'),
        $this->validPurchaseRequestPayload([
            'suggested_suppliers' => ['Sodimac', 'Sodimac', 'Construmart'],
        ]));

    $response->assertSessionHasErrors('suggested_suppliers.1');

    $mensaje = session('errors')->first('suggested_suppliers.1');

    // Ni inglés ni nombres técnicos de campo.
    expect($mensaje)->not->toContain('field')
        ->and($mensaje)->not->toContain('duplicate')
        ->and($mensaje)->not->toContain('suggested_suppliers')
        ->and($mensaje)->toContain('proveedor');
});

it('names the offending line when a quantity is missing', function () {
    $owner = User::factory()->create();

    $response = $this->actingAs($owner)->post(route('purchase_requests.store'),
        $this->validPurchaseRequestPayload([
            'items' => [
                $this->validPurchaseRequestItem(),
                $this->validPurchaseRequestItem(['quantity' => '']),
            ],
        ]));

    $response->assertSessionHasErrors('items.1.quantity');

    $mensaje = session('errors')->first('items.1.quantity');

    expect($mensaje)->not->toContain('items.1')
        ->and($mensaje)->toContain('partida')
        // La segunda partida se nombra como N° 2, no como índice 1.
        ->and($mensaje)->toContain('2');
});

it('translates the core validation messages instead of falling back to English', function () {
    $owner = User::factory()->create();

    $response = $this->actingAs($owner)->post(route('purchase_requests.store'),
        $this->validPurchaseRequestPayload(['reason' => '']));

    $response->assertSessionHasErrors('reason');

    $mensaje = session('errors')->first('reason');

    expect($mensaje)->not->toContain('field is required')
        ->and($mensaje)->toContain('obligatorio')
        ->and($mensaje)->toContain('motivo');
});

it('explains a rejected attachment in Spanish', function () {
    Storage::fake('local');
    $owner = User::factory()->create();

    $path = tempnam(sys_get_temp_dir(), 'sc-msg-');
    file_put_contents($path, "<?php echo 'no soy un pdf';");
    $falso = new UploadedFile($path, 'documento.pdf', 'application/pdf', null, true);

    $response = $this->actingAs($owner)->post(route('purchase_requests.store'),
        $this->validPurchaseRequestPayload(['attachments' => [$falso]]));

    $response->assertSessionHasErrors('attachments.0');

    $mensaje = session('errors')->first('attachments.0');
    expect($mensaje)->not->toContain('must be a file of type')
        ->and(mb_strtolower($mensaje))->toContain('pdf');

    @unlink($path);
});

it('surfaces every validation error through the toast, not just the first', function () {
    $owner = User::factory()->create();

    // Dos errores a la vez: el aviso debe mencionar ambos.
    $this->actingAs($owner)
        ->post(route('purchase_requests.store'), $this->validPurchaseRequestPayload([
            'reason' => '',
            'required_date' => '',
        ]))
        ->assertSessionHasErrors(['reason', 'required_date']);

    $response = $this->actingAs($owner)
        ->get(route('purchase_requests.create'))
        ->assertOk();

    // El componente de toast compone un único aviso con los errores.
    $response->assertSee('motivo', false)
        ->assertSee('fecha requerida', false);
});
