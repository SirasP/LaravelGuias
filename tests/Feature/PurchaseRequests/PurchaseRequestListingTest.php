<?php

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

it('stores and prints more than twenty three lines without losing any', function () {
    $owner = User::factory()->create();

    $items = [];
    for ($i = 1; $i <= 30; $i++) {
        $items[] = $this->validPurchaseRequestItem([
            'product_service' => 'Partida número '.$i,
            'quantity' => (string) $i,
            'unit' => 'Unidades',
        ]);
    }

    $request = $this->createPurchaseRequestDraft($owner, ['items' => $items]);

    expect($request->items()->count())->toBe(30);

    // Ni la primera ni la última se pierden, y el orden se respeta.
    expect($request->items()->orderBy('sort_order')->pluck('product_service')->first())
        ->toBe('Partida número 1');
    expect($request->items()->orderBy('sort_order')->pluck('product_service')->last())
        ->toBe('Partida número 30');

    $submitted = $this->submitPurchaseRequest($owner, $request);

    // El PDF de la revisión conserva las 30 partidas.
    expect($submitted->revisions()->firstOrFail()->item_count)->toBe(30);

    $pdf = $this->actingAs($owner)->get(route('purchase_requests.pdf', $submitted));
    $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');

    $contents = $pdf->getContent();
    expect(substr($contents, 0, 4))->toBe('%PDF')
        ->and(strlen($contents))->toBeGreaterThan(1000);

    // Con 30 partidas la grilla ya no cabe en una carta: debe continuar en
    // una segunda página en vez de truncar.
    expect(preg_match_all('/\/Type\s*\/Page[^s]/', $contents))->toBeGreaterThan(1);
});

it('filters the listing on the server across every record', function () {
    $buyer = User::factory()->comprador()->create();
    $ana = User::factory()->create(['name' => 'Ana Silva']);
    $luis = User::factory()->create(['name' => 'Luis Torres']);

    $fromAna = $this->createPurchaseRequestDraft($ana, ['reason' => 'Repuestos para el tractor']);
    $fromLuis = $this->createPurchaseRequestDraft($luis, ['reason' => 'Insumos de aseo']);

    // Por texto libre: encuentra por motivo.
    $this->actingAs($buyer)
        ->get(route('purchase_requests.index', ['search' => 'tractor']))
        ->assertOk()
        ->assertSee($fromAna->folio)
        ->assertDontSee($fromLuis->folio);

    // Por nombre del solicitante.
    $this->actingAs($buyer)
        ->get(route('purchase_requests.index', ['requester' => 'Luis']))
        ->assertOk()
        ->assertSee($fromLuis->folio)
        ->assertDontSee($fromAna->folio);

    // Por folio exacto.
    $this->actingAs($buyer)
        ->get(route('purchase_requests.index', ['search' => $fromAna->folio]))
        ->assertOk()
        ->assertSee($fromAna->folio)
        ->assertDontSee($fromLuis->folio);
});

it('filters by required date range on the server', function () {
    $buyer = User::factory()->comprador()->create();
    $owner = User::factory()->create();

    $soon = $this->createPurchaseRequestDraft($owner, [
        'required_date' => now()->addDays(3)->toDateString(),
    ]);
    $later = $this->createPurchaseRequestDraft($owner, [
        'required_date' => now()->addDays(40)->toDateString(),
    ]);

    $this->actingAs($buyer)
        ->get(route('purchase_requests.index', [
            'required_from' => now()->addDays(1)->toDateString(),
            'required_to' => now()->addDays(10)->toDateString(),
        ]))
        ->assertOk()
        ->assertSee($soon->folio)
        ->assertDontSee($later->folio);
});

it('paginates on the server instead of loading everything', function () {
    $owner = User::factory()->create();

    PurchaseRequest::factory()->count(25)->forUser($owner)->create();

    $response = $this->actingAs($owner)->get(route('purchase_requests.index'));
    $response->assertOk();

    $paginator = $response->viewData('requests');

    expect($paginator->total())->toBe(25)
        ->and($paginator->perPage())->toBe(20)
        ->and($paginator->count())->toBe(20)
        ->and($paginator->hasPages())->toBeTrue();

    // La segunda página trae el resto, sin perder ninguna.
    $second = $this->actingAs($owner)->get(route('purchase_requests.index', ['page' => 2]));
    expect($second->viewData('requests')->count())->toBe(5);
});

it('keeps filters applied when paginating', function () {
    $owner = User::factory()->create();

    PurchaseRequest::factory()->count(3)->forUser($owner)->create(['reason' => 'Mantención de bombas']);
    PurchaseRequest::factory()->count(4)->forUser($owner)->create(['reason' => 'Otra cosa']);

    $response = $this->actingAs($owner)
        ->get(route('purchase_requests.index', ['search' => 'bombas']));

    expect($response->viewData('requests')->total())->toBe(3);
});

it('keeps a corrected request visible in the pending-review counter', function () {
    // El bug: el revisor devuelve una solicitud, el solicitante la corrige y
    // la reenvía, y desaparecía del contador "Por revisar" porque éste sólo
    // contaba `submitted`. Quedaba esperando decisión sin que nadie la viera.
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $request = $this->submitPurchaseRequest($owner, $this->createPurchaseRequestDraft($owner));

    $this->actingAs($admin)->post(route('purchase_requests.request_changes', $request), [
        'lock_version' => (string) $request->lock_version,
        'comment' => 'Corrige la especificación.',
    ])->assertSessionHasNoErrors();

    // Devuelta: ya no espera decisión del revisor.
    $counts = $this->actingAs($admin)->get(route('purchase_requests.index'))->viewData('counts');
    expect($counts['por_revisar'])->toBe(0)
        ->and($counts['changes_requested'])->toBe(1);

    // El solicitante corrige y reenvía.
    $this->actingAs($owner)->put(route('purchase_requests.update', $request),
        $this->validPurchaseRequestPayload(['reason' => 'Motivo corregido']))->assertSessionHasNoErrors();
    $this->submitPurchaseRequest($owner, $request->fresh());

    expect($request->fresh()->status)->toBe(PurchaseRequestStatus::RESUBMITTED);

    // Y vuelve a contar como pendiente de decisión.
    $counts = $this->actingAs($admin)->get(route('purchase_requests.index'))->viewData('counts');
    expect($counts['por_revisar'])->toBe(1)
        ->and($counts['resubmitted'])->toBe(1);
});

it('lists both submitted and resubmitted under the pending-review filter', function () {
    $admin = User::factory()->admin()->create();
    $enviada = User::factory()->create();
    $corregida = User::factory()->create();

    $nueva = $this->submitPurchaseRequest($enviada, $this->createPurchaseRequestDraft($enviada));

    $vuelta = $this->submitPurchaseRequest($corregida, $this->createPurchaseRequestDraft($corregida));
    $this->actingAs($admin)->post(route('purchase_requests.request_changes', $vuelta), [
        'lock_version' => (string) $vuelta->lock_version,
        'comment' => 'Falta detalle.',
    ]);
    $this->actingAs($corregida)->put(route('purchase_requests.update', $vuelta),
        $this->validPurchaseRequestPayload(['reason' => 'Ya corregido']));
    $this->submitPurchaseRequest($corregida, $vuelta->fresh());

    $this->actingAs($admin)
        ->get(route('purchase_requests.index', ['status' => 'por_revisar']))
        ->assertOk()
        ->assertSee($nueva->folio)
        ->assertSee($vuelta->folio);
});

it('turns every table row into a link to its request instead of showing action buttons', function () {
    $owner = User::factory()->create();
    $draft = $this->createPurchaseRequestDraft($owner);

    $listado = $this->actingAs($owner)->get(route('purchase_requests.index'));

    $listado->assertOk()
        // La fila entera lleva a la solicitud.
        ->assertSee('data-row-href="'.route('purchase_requests.show', $draft).'"', false)
        // Y el folio sigue siendo un enlace de verdad, para tabular o copiar.
        ->assertSee('href="'.route('purchase_requests.show', $draft).'"', false);

    // Dentro de la tabla ya no queda columna de acciones: ni la cabecera ni el
    // atajo a editar. Las tarjetas de móvil, que van aparte, sí los conservan.
    $html = $listado->getContent();
    $inicio = strpos($html, '<table');
    $tabla = substr($html, $inicio, strpos($html, '</table>') - $inicio);

    expect($tabla)->not->toContain('Acción');
    expect($tabla)->not->toContain(route('purchase_requests.edit', $draft));
});

it('keeps the side panel filters inside the form so they still reach the server', function () {
    $owner = User::factory()->create();
    $this->createPurchaseRequestDraft($owner);

    $listado = $this->actingAs($owner)->get(route('purchase_requests.index'));
    $html = $listado->getContent();

    // El panel es un solo formulario con la búsqueda: mover los campos a un
    // costado no puede dejarlos fuera, o el botón Filtrar los perdería.
    $inicio = strpos($html, '<form method="GET"');
    $formulario = substr($html, $inicio, strpos($html, '</form>', $inicio) - $inicio);

    foreach (['search', 'status', 'department', 'requester', 'requested_from', 'requested_to', 'required_from', 'required_to'] as $campo) {
        expect($formulario)->toContain('name="'.$campo.'"');
    }

    // Y el panel entra desde la derecha, no empujando la tabla hacia abajo.
    expect($formulario)->toContain('translate-x-full');

    // Fuera del panel sólo queda el botón que lo abre: nada de enviar el
    // formulario desde la barra, que era de donde venía el desorden.
    $barra = substr($formulario, 0, strpos($formulario, 'id="filtros-avanzados"'));

    expect($barra)->toContain('Filtros');
    expect($barra)->not->toContain('type="submit"');
});

it('filters the list by every field the panel offers', function () {
    $admin = User::factory()->create(['name' => 'Sebastian Lopez', 'role' => 'admin']);
    $otro = User::factory()->create(['name' => 'Jose Perez', 'role' => 'user']);

    $this->createPurchaseRequestDraft($admin, [
        'reason' => 'AGUJA-MIA',
        'department' => 'Administración',
    ]);
    $this->createPurchaseRequestDraft($otro, [
        'reason' => 'AGUJA-SUYA',
        'department' => 'Taller',
        'requested_for_name' => 'Katherin Asencio',
    ]);

    $ve = function (array $params) use ($admin): string {
        $html = $this->actingAs($admin)->get(route('purchase_requests.index', $params))->getContent();

        return trim((str_contains($html, 'AGUJA-MIA') ? 'MIA ' : '').(str_contains($html, 'AGUJA-SUYA') ? 'SUYA' : ''));
    };

    // Sin filtros se ven las dos, que es la línea base de la comparación.
    expect($ve([]))->toBe('MIA SUYA');

    expect($ve(['search' => 'AGUJA-SUYA']))->toBe('SUYA');
    expect($ve(['department' => 'Taller']))->toBe('SUYA');
    expect($ve(['requested_to' => now()->subDay()->toDateString()]))->toBe('');
    expect($ve(['requested_from' => now()->toDateString()]))->toBe('MIA SUYA');
    expect($ve(['required_to' => now()->subDay()->toDateString()]))->toBe('');
    expect($ve(['status' => 'draft']))->toBe('MIA SUYA');

    // Solicitante encuentra tanto a quien la creó como a la persona para
    // quien se pidió: desde la lista las dos se llaman igual.
    expect($ve(['requester' => 'Jose']))->toBe('SUYA');
    expect($ve(['requester' => 'Katherin']))->toBe('SUYA');
    expect($ve(['requester' => 'Sebastian']))->toBe('MIA');

    // Y dos filtros a la vez se acumulan en vez de pisarse.
    expect($ve(['requester' => 'Jose', 'department' => 'Administración']))->toBe('');
});

it('shows who asked for each request in the table', function () {
    $owner = User::factory()->create(['name' => 'Paola Jara']);
    $this->createPurchaseRequestDraft($owner, ['requested_for_name' => 'Luis Silva']);

    $html = $this->actingAs($owner)->get(route('purchase_requests.index'))->getContent();
    $inicio = strpos($html, '<table');
    $tabla = substr($html, $inicio, strpos($html, '</table>') - $inicio);

    // Sin esta columna no había forma de comprobar que el filtro hizo algo.
    expect($tabla)->toContain('Solicitante');
    expect($tabla)->toContain('Paola Jara');
    expect($tabla)->toContain('Luis Silva');
});

it('offers manual and AI modes when creating, and neither when editing', function () {
    config(['purchase_requests.reader.enabled' => true]);

    $owner = User::factory()->create();

    $crear = $this->actingAs($owner)->get(route('purchase_requests.create'));
    $crear->assertOk()
        ->assertSee('Manual')
        ->assertSee('Cuéntale qué necesitas');

    // Las secciones del formulario se esconden al pasar al asistente.
    expect($crear->getContent())->toContain("x-show=\"modo === 'manual'\"");

    // Editando no hay pestañas: sin la variable `modo` en la página, dejar
    // ese x-show puesto escondería el formulario entero.
    $borrador = $this->createPurchaseRequestDraft($owner);
    $editar = $this->actingAs($owner)->get(route('purchase_requests.edit', $borrador));

    $editar->assertOk()->assertSee('1. Lo básico');
    expect($editar->getContent())->not->toContain('modo ===');
    expect($editar->getContent())->not->toContain('Cuéntale qué necesitas');
});

it('shows what the assistant built as a table before anything is saved', function () {
    config(['purchase_requests.reader.enabled' => true]);

    $html = $this->actingAs(User::factory()->create())
        ->get(route('purchase_requests.create'))->getContent();

    // La tabla del resumen sale de las mismas partidas del formulario, así
    // que corregir en Manual y guardar operan sobre lo que se está viendo.
    expect($html)->toContain('Motivo propuesto');
    expect($html)->toContain('Guardar borrador');
    expect($html)->toContain('x-show="armado"');

    // Guardar tiene que devolver a Manual antes de enviar: con las secciones
    // ocultas el navegador no puede señalar un campo obligatorio vacío y el
    // formulario se queda callado sin enviarse.
    expect($html)->toContain("modo = 'manual'; \$nextTick(() => \$el.form.requestSubmit())");
});
