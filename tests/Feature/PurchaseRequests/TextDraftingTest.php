<?php

use App\Models\User;
use App\Services\PurchaseRequests\Drafting\DraftSuggestion;
use App\Services\PurchaseRequests\Drafting\NullPurchaseRequestDrafter;
use App\Services\PurchaseRequests\Drafting\PurchaseRequestDrafter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function asistenteDeTexto(DraftSuggestion $sugerencia): void
{
    app()->bind(PurchaseRequestDrafter::class, fn () => new class($sugerencia) implements PurchaseRequestDrafter
    {
        public function __construct(private readonly DraftSuggestion $sugerencia) {}

        public function isEnabled(): bool
        {
            return true;
        }

        public function draftFromText(string $text, array $knownUnits = []): DraftSuggestion
        {
            return $this->sugerencia;
        }
    });
}

it('turns a sentence into lines without saving anything', function () {
    asistenteDeTexto(DraftSuggestion::of(
        reason: null,
        requestedForName: null,
        items: [
            ['product_service' => 'pañuelos desechables', 'specification' => null, 'quantity' => '2', 'unit' => null],
            ['product_service' => 'confort', 'specification' => null, 'quantity' => '2', 'unit' => null],
        ],
    ));

    $owner = User::factory()->create();

    $respuesta = $this->actingAs($owner)
        ->postJson(route('purchase_requests.ingestions.draft'), ['text' => 'pañuelos desechables 2, confort 2'])
        ->assertOk();

    expect($respuesta->json('available'))->toBeTrue()
        ->and($respuesta->json('items'))->toHaveCount(2)
        ->and($respuesta->json('items.0.product_service'))->toBe('pañuelos desechables');

    // Es una sugerencia en pantalla: no crea nada.
    expect(App\Models\PurchaseRequest::query()->count())->toBe(0);
});

it('requires something to work with', function () {
    asistenteDeTexto(DraftSuggestion::of(null, null, []));

    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->postJson(route('purchase_requests.ingestions.draft'), ['text' => 'ab'])
        ->assertStatus(422);
});

it('says so plainly when the assistant is off', function () {
    app()->bind(PurchaseRequestDrafter::class, fn () => new NullPurchaseRequestDrafter);

    $owner = User::factory()->create();

    $respuesta = $this->actingAs($owner)
        ->postJson(route('purchase_requests.ingestions.draft'), ['text' => 'confort 2'])
        ->assertOk();

    expect($respuesta->json('available'))->toBeFalse()
        ->and($respuesta->json('error'))->toContain('no está habilitado');
});

it('keeps the auditor out', function () {
    asistenteDeTexto(DraftSuggestion::of(null, null, []));

    $auditor = User::factory()->auditor()->create();

    $this->actingAs($auditor)
        ->postJson(route('purchase_requests.ingestions.draft'), ['text' => 'confort 2'])
        ->assertForbidden();
});

it('carries the supplier through when the person named one', function () {
    asistenteDeTexto(DraftSuggestion::of(
        reason: null,
        requestedForName: 'Marco',
        items: [['product_service' => 'cloro', 'specification' => null, 'quantity' => '5', 'unit' => 'Litros']],
        supplier: 'Sodimac',
    ));

    $owner = User::factory()->create();

    $respuesta = $this->actingAs($owner)
        ->postJson(route('purchase_requests.ingestions.draft'), ['text' => '5 litros de cloro en Sodimac, lo pide Marco'])
        ->assertOk();

    expect($respuesta->json('supplier'))->toBe('Sodimac')
        ->and($respuesta->json('requested_for_name'))->toBe('Marco');
});

it('passes the warnings on so nothing is silently wrong', function () {
    asistenteDeTexto(DraftSuggestion::of(
        reason: null,
        requestedForName: null,
        items: [['product_service' => 'escobillones', 'specification' => null, 'quantity' => null, 'unit' => null]],
        warnings: ['Partida N° 1 («escobillones»): falta la cantidad.'],
    ));

    $owner = User::factory()->create();

    $respuesta = $this->actingAs($owner)
        ->postJson(route('purchase_requests.ingestions.draft'), ['text' => 'escobillones'])
        ->assertOk();

    expect($respuesta->json('warnings.0'))->toContain('falta la cantidad');
});

it('discards anything the model returned that the person never wrote', function () {
    // Encontrado en el servidor con un modelo pequeño: pidiendo «confort 2»
    // devolvió «cloro», con destinatario «Marco» y proveedor «Sodimac»,
    // restos de una petición anterior. Nada de eso estaba escrito.
    $verificador = new App\Services\PurchaseRequests\Drafting\LocalPurchaseRequestDrafter;
    $metodo = new ReflectionMethod($verificador, 'descartarProductosNoEscritos');

    [$items, $avisos] = $metodo->invoke($verificador, [
        ['product_service' => 'confort', 'specification' => null, 'quantity' => '2', 'unit' => null],
        ['product_service' => 'cloro', 'specification' => null, 'quantity' => '5', 'unit' => 'Litros'],
    ], 'confort 2');

    expect($items)->toHaveCount(1)
        ->and($items[0]['product_service'])->toBe('confort')
        ->and($avisos[0])->toContain('cloro');
});

it('accepts a product written with different casing or extra words', function () {
    $verificador = new App\Services\PurchaseRequests\Drafting\LocalPurchaseRequestDrafter;
    $metodo = new ReflectionMethod($verificador, 'apareceEnElTexto');

    // Lo escrito, en otra forma: se acepta.
    expect($metodo->invoke($verificador, 'CONFORT', 'confort 2'))->toBeTrue();
    expect($metodo->invoke($verificador, 'pañuelos desechables', 'necesito pañuelos 2'))->toBeTrue();
    expect($metodo->invoke($verificador, 'PVC 200mm', '295 metros de pvc 200mm'))->toBeTrue();

    // Lo no escrito: se rechaza.
    expect($metodo->invoke($verificador, 'cloro', 'confort 2'))->toBeFalse();
    expect($metodo->invoke($verificador, 'Marco', 'confort 2'))->toBeFalse();

    // Y una palabra corta suelta no basta para dar por bueno un invento.
    expect($metodo->invoke($verificador, 'gel', 'confort 2'))->toBeFalse();
});

it('only marks a request urgent when the person said it was', function () {
    $verificador = new App\Services\PurchaseRequests\Drafting\LocalPurchaseRequestDrafter;
    $metodo = new ReflectionMethod($verificador, 'prioridadVerificada');

    // Lo dijo: se respeta, con su explicación.
    [$prioridad, $motivo, $aviso] = $metodo->invoke(
        $verificador,
        ['priority' => 'urgente', 'urgent_reason' => 'se paró la bomba'],
        '2 correas urgente, se paró la bomba',
    );
    expect($prioridad)->toBe('urgent')
        ->and($motivo)->toBe('se paró la bomba')
        ->and($aviso)->toBeNull();

    // No lo dijo: el modelo lee urgencia en cualquier pedido de repuestos, y
    // una urgencia falsa hace que nadie crea en las de verdad.
    [$prioridad, $motivo, $aviso] = $metodo->invoke(
        $verificador,
        ['priority' => 'urgente', 'urgent_reason' => 'se necesitan pronto'],
        '2 correas para el tractor',
    );
    expect($prioridad)->toBe('normal')
        ->and($motivo)->toBeNull()
        ->and($aviso)->toContain('prioridad normal');

    // Y lo normal se queda normal sin ruido.
    expect($metodo->invoke($verificador, ['priority' => 'normal'], 'cemento 10'))
        ->toBe(['normal', null, null]);
});

it('hands the whole request over to the form, not just the lines', function () {
    asistenteDeTexto(DraftSuggestion::of(
        reason: 'Reponer correas de la bomba',
        requestedForName: 'Marco',
        items: [['product_service' => 'correas', 'specification' => null, 'quantity' => '2', 'unit' => 'Unidades']],
        supplier: 'Sodimac',
        priority: 'urgent',
        urgentReason: 'se paró la bomba del pozo',
        deliveryLocation: 'casa de operarios',
    ));

    $respuesta = $this->actingAs(User::factory()->create())
        ->postJson(route('purchase_requests.ingestions.draft'), ['text' => '2 correas urgente para Marco']);

    $respuesta->assertOk()
        ->assertJsonPath('reason', 'Reponer correas de la bomba')
        ->assertJsonPath('requested_for_name', 'Marco')
        ->assertJsonPath('supplier', 'Sodimac')
        ->assertJsonPath('priority', 'urgent')
        ->assertJsonPath('urgent_reason', 'se paró la bomba del pozo')
        ->assertJsonPath('delivery_location', 'casa de operarios');
});

it('opens the collapsed details section when the assistant writes inside it', function () {
    config(['purchase_requests.reader.enabled' => true]);

    $html = $this->actingAs(User::factory()->create())
        ->get(route('purchase_requests.create'))->getContent();

    // Rellenar esos campos con la sección plegada sería escribir a escondidas.
    expect($html)->toContain("\$dispatch('abrir-detalles')");
    expect($html)->toContain('@abrir-detalles.window="abierto = true"');
});

it('uses the urgency explanation as the purchase reason when nothing else says why', function () {
    $verificador = new App\Services\PurchaseRequests\Drafting\LocalPurchaseRequestDrafter;

    // Al agregar "urgent_reason" el modelo empezó a escribir ahí el porqué y a
    // dejar el motivo vacío, y el motivo es obligatorio: frenaba el guardado
    // por un dato que la persona sí había escrito.
    $metodo = new ReflectionMethod($verificador, 'prioridadVerificada');
    [$prioridad, $motivoUrgencia] = $metodo->invoke(
        $verificador,
        ['priority' => 'urgente', 'urgent_reason' => 'se paró la bomba del pozo'],
        '2 correas urgente, se paró la bomba del pozo',
    );

    expect($prioridad)->toBe('urgent')
        ->and($motivoUrgencia)->toBe('se paró la bomba del pozo');

    // Y lo que el asistente sí trae como motivo manda sobre ese respaldo.
    expect(App\Services\PurchaseRequests\Drafting\DraftSuggestion::of(
        reason: 'Reponer correas',
        requestedForName: null,
        items: [],
        urgentReason: 'se paró la bomba',
    )->reason)->toBe('Reponer correas');
});

it('says the reason is missing instead of letting it fail at save time', function () {
    config(['purchase_requests.reader.enabled' => true]);

    $html = $this->actingAs(User::factory()->create())
        ->get(route('purchase_requests.create'))->getContent();

    expect($html)->toContain('No dijiste para qué es. Escríbelo en Manual: es obligatorio.');
});
