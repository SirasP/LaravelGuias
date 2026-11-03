<?php

use App\Jobs\ReadSupplierInvoice;
use App\Models\SupplierInvoiceUpload;
use App\Models\User;
use App\Services\SupplierInvoices\InvoiceToOdoo;
use App\Services\SupplierInvoices\LocalInvoiceReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** La factura 3748 de Electrosol, tal como la leyó qwen3-vl-8b. */
function electrosol(array $cambios = []): array
{
    return array_replace([
        'tipo' => '33', 'rut' => '77118278-K', 'razon_social' => 'ELECTROSOL ENCENDIDOS SPA',
        'folio' => '3748', 'fecha' => '2026-09-22', 'vencimiento' => '2026-10-22', 'neto' => 138822.0, 'exento' => null, 'iva' => 26375.0, 'total' => 165197.0,
        'lineas' => [
            ['detalle' => 'SOLENOIDE 12V KUBOTA', 'cantidad' => 1.0, 'precio' => 96638.66],
            ['detalle' => 'CONECTOR SELLADO 3 PINES MACHO', 'cantidad' => 10.0, 'precio' => 1680.67],
            ['detalle' => 'CONECTOR SELLADO 3 PINES HEMBRA', 'cantidad' => 7.0, 'precio' => 1680.67],
            ['detalle' => 'CONECTOR SELLADO 3 PINES HEMBRA C/CABLE', 'cantidad' => 3.0, 'precio' => 2521.01],
            ['detalle' => 'PINES ESPECIALES', 'cantidad' => 12.0, 'precio' => 504.0],
        ],
    ], $cambios);
}

function subida(array $data, string $estado = SupplierInvoiceUpload::READ): SupplierInvoiceUpload
{
    Storage::fake('local');
    Storage::disk('local')->put('facturas-subidas/f.pdf', '%PDF-1.4 factura');

    return SupplierInvoiceUpload::query()->create([
        'disk' => 'local', 'path' => 'facturas-subidas/f.pdf', 'original_name' => 'FACTURA 3748.pdf',
        'mime_type' => 'application/pdf', 'size' => 16, 'sha256' => str_repeat('b', 64),
        'status' => $estado, 'data' => $data,
    ]);
}

/**
 * Un Odoo de pruebas de mentira. `$yaEsta` simula que la factura ya existe.
 *
 * @param  list<array<string, mixed>>  $ultimaLinea  la última línea de factura del proveedor
 */
function odooDeFacturas(bool $proveedor = true, bool $yaEsta = false, array $ultimaLinea = []): void
{
    config([
        'supplier_invoices.odoo.enabled' => true,
        'supplier_invoices.odoo.url' => 'https://agricolaehe-prueba.ejemplo.cl',
        'supplier_invoices.odoo.db' => 'prueba',
        'supplier_invoices.odoo.user' => 'quien@ejemplo.cl',
        'supplier_invoices.odoo.password' => 'secreta',
    ]);

    Http::preventStrayRequests();
    Http::fake(['*/jsonrpc' => function ($request) use ($proveedor, $yaEsta, $ultimaLinea) {
        $p = $request['params'];
        $r = fn ($x) => Http::response(['jsonrpc' => '2.0', 'result' => $x]);

        if ($p['service'] === 'common') {
            return $r(7);
        }

        [, , , $modelo, $metodo] = $p['args'];

        return $r(match ("$modelo.$metodo") {
            'res.partner.search_read' => $proveedor ? [['id' => 4100, 'name' => 'Electrosol, ventas', 'commercial_partner_id' => [1738, 'ELECTROSOL ENCENDIDOS SPA']]] : [],
            // Odoo no filtra por folio: devuelve todas las del proveedor.
            'account.move.search_read' => [
                ['id' => 14700, 'name' => 'FAC 003701', 'state' => 'posted', 'l10n_latam_document_number' => '003701'],
                ['id' => 14701, 'name' => false, 'state' => 'draft', 'l10n_latam_document_number' => false],
                ...($yaEsta ? [['id' => 14805, 'name' => 'FAC 003748', 'state' => 'draft', 'l10n_latam_document_number' => '003748']] : []),
            ],
            'account.move.line.search_read' => $ultimaLinea,
            'account.journal.search' => [2],
            'l10n_latam.document.type.search' => [1],
            'account.tax.search' => [2],
            'account.move.create' => 14900,
            'ir.attachment.create' => 777,
            'account.move.write' => true,
            'account.move.read' => [['id' => 14900, 'name' => 'FAC 003748', 'amount_total' => 165197.0]],
            default => throw new RuntimeException("Odoo de prueba no sabe contestar $modelo.$metodo"),
        });
    }]);
}

/** Lo que se le mandó a Odoo con ese modelo y método: params.args[5]. */
function mandado(string $modelo, string $metodo): ?array
{
    foreach (Http::recorded() as [$r]) {
        $a = $r['params']['args'] ?? [];

        if (($a[3] ?? null) === $modelo && ($a[4] ?? null) === $metodo) {
            return $a[5];
        }
    }

    return null;
}

it('takes the supplier RUT from the text and never our own', function () {
    $texto = "ELECTROSOL ENCENDIDOS SPA  R.U.T.: 77.118.278-K\nFACTURA ELECTRONICA N° 3748\nSEÑOR(ES): AGRICOLA EPPLE RUT: 77.415.879-0\n"
        ."Neto 138.822  IVA 26.375  Total 165.197";

    // El modelo se confunde de RUT y escribe el nuestro: manda el texto.
    [$data, $avisos] = (new LocalInvoiceReader)->verificar(
        ['rut' => '77.415.879-0', 'folio' => '3748', 'fecha' => '2026-09-22', 'neto' => '138822', 'iva' => '26375', 'total' => '165197',
            'lineas' => [['detalle' => 'TUERCA 1/2-13 HC G-2', 'codigo' => '500034', 'cantidad' => '200,00', 'precio' => '84,035']]],
        $texto,
    );

    expect($data['rut'])->toBe('77118278-K')
        ->and($data['folio'])->toBe('3748')
        ->and($data['total'])->toBe(165197.0)
        ->and($data['lineas'][0])->toBe(['detalle' => 'TUERCA 1/2-13 HC G-2 · 500034', 'cantidad' => 200.0, 'precio' => 84.035])
        ->and($avisos)->toBe([]);
});

it('warns about a folio or an amount that is not in the document', function () {
    [, $avisos] = (new LocalInvoiceReader)->verificar(
        ['rut' => '', 'folio' => '9999', 'fecha' => '22 de septiembre', 'neto' => '138822', 'iva' => '26375', 'total' => '999999', 'lineas' => []],
        "RUT 77.118.278-K FACTURA N° 3748 Neto 138,822 IVA 26,375 Total 165,197",
    );

    expect($avisos)->toContain('El folio 9999 no aparece en el documento. Revísalo.')
        ->toContain('El total de $999.999 no aparece en el documento. Revísalo.')
        ->toContain('No se pudo leer la fecha de emisión.')
        ->not->toContain('El neto de $138.822 no aparece en el documento. Revísalo.');
});

it('stores the upload and sends it to the reader', function () {
    Storage::fake('local');
    Queue::fake();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('odoo.invoice_uploads.store'), ['documento' => UploadedFile::fake()->create('FACTURA 3748.pdf', 40, 'application/pdf')])
        ->assertRedirect();

    $factura = SupplierInvoiceUpload::query()->sole();
    expect($factura->status)->toBe(SupplierInvoiceUpload::QUEUED);
    Storage::disk('local')->assertExists($factura->path);
    Queue::assertPushed(ReadSupplierInvoice::class);
});

it('keeps the upload for whoever can create invoices in Odoo', function () {
    $this->actingAs(User::factory()->bodeguero()->create())
        ->get(route('odoo.invoice_uploads.index'))
        ->assertForbidden();
});

it('leaves the invoice ready to review once the AI reads it', function () {
    config(['purchase_requests.reader.enabled' => true, 'purchase_requests.reader.base_url' => 'http://ia.local/v1', 'supplier_invoices.odoo.enabled' => false]);
    Http::preventStrayRequests();
    Http::fake(['ia.local/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
        'tipo' => 'FACTURA ELECTRONICA', 'proveedor' => 'ELECTROSOL ENCENDIDOS SPA', 'rut' => '77.118.278-K', 'folio' => '3748',
        'fecha' => '2026-09-22', 'neto' => '138822', 'exento' => '', 'iva' => '26375', 'total' => '165197',
        'lineas' => [['detalle' => 'PINES ESPECIALES', 'codigo' => '', 'cantidad' => '12', 'precio' => '504']],
    ])], 'finish_reason' => 'stop']]])]);

    $factura = subida([], SupplierInvoiceUpload::QUEUED);
    $factura->forceFill(['mime_type' => 'image/png'])->save();

    (new ReadSupplierInvoice($factura))->handle(new LocalInvoiceReader, new InvoiceToOdoo);

    $factura->refresh();
    expect($factura->status)->toBe(SupplierInvoiceUpload::READ)
        ->and($factura->dato('rut'))->toBe('77118278-K')
        ->and($factura->dato('folio'))->toBe('3748')
        ->and($factura->lineas())->toHaveCount(1);
});

it('waits for the AI when the Mac is asleep instead of failing the invoice', function () {
    config(['purchase_requests.reader.enabled' => true, 'purchase_requests.reader.base_url' => 'http://ia.local/v1', 'supplier_invoices.odoo.enabled' => false]);
    Http::preventStrayRequests();
    Http::fake(['ia.local/*' => fn () => throw new ConnectionException('Connection refused')]);

    $factura = subida([], SupplierInvoiceUpload::QUEUED);
    $factura->forceFill(['mime_type' => 'image/png'])->save();

    $job = new ReadSupplierInvoice($factura);
    $job->withFakeQueueInteractions();
    $job->handle(new LocalInvoiceReader, new InvoiceToOdoo);

    expect($factura->refresh()->status)->toBe(SupplierInvoiceUpload::WAITING);
    $job->assertReleased();
});

it('says right after reading that the invoice is already in Odoo', function () {
    odooDeFacturas(yaEsta: true);
    config(['purchase_requests.reader.enabled' => true, 'purchase_requests.reader.base_url' => 'http://ia.local/v1']);
    Http::fake(['ia.local/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
        'tipo' => 'FACTURA ELECTRONICA', 'proveedor' => 'ELECTROSOL ENCENDIDOS SPA', 'rut' => '77.118.278-K', 'folio' => '3748',
        'fecha' => '2026-09-22', 'neto' => '138822', 'iva' => '26375', 'total' => '165197',
        'lineas' => [['detalle' => 'PINES ESPECIALES', 'cantidad' => '12', 'precio' => '504']],
    ])]]]])]);

    $factura = subida([], SupplierInvoiceUpload::QUEUED);
    $factura->forceFill(['mime_type' => 'image/png'])->save();
    (new ReadSupplierInvoice($factura))->handle(new LocalInvoiceReader, new InvoiceToOdoo);

    expect($factura->refresh()->yaEnOdoo())->toMatchArray(['id' => 14805, 'name' => 'FAC 003748', 'state' => 'draft']);

    $admin = User::factory()->admin()->create();

    // En el listado, y en grande al abrirla, con el enlace a la que ya está y sin botón para crearla otra vez.
    $this->actingAs($admin)->get(route('odoo.invoice_uploads.index'))->assertSee('Ya está en Odoo · FAC 003748');
    $this->actingAs($admin)->get(route('odoo.invoice_uploads.show', $factura))
        ->assertSee('Esta factura ya está en Odoo')
        ->assertSee('https://agricolaehe-prueba.ejemplo.cl/web#id=14805', false)
        ->assertDontSee('Guardar y crear borrador en Odoo');
});

it('asks whether it is already in Odoo when the RUT and folio could not be read', function () {
    odooDeFacturas(yaEsta: true);
    // La 286429 de Carlos Rodríguez Soto: el comprobante de Transbank tapaba el encabezado.
    $factura = subida(electrosol(['rut' => null, 'folio' => null, 'razon_social' => null]));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('odoo.invoice_uploads.show', $factura))
        ->assertSee('¿Ya estará en Odoo?')
        ->assertSee('No se pudo leer el RUT ni el folio')
        ->assertSee('https://agricolaehe-prueba.ejemplo.cl/web#id=14805', false);

    $consulta = collect(Http::recorded())->map(fn ($par) => $par[0]['params']['args'] ?? [])
        ->first(fn ($a) => ($a[3] ?? null) === 'account.move' && ($a[4] ?? null) === 'search_read');
    expect($consulta[5][0])->toContain(['amount_total', '>=', 165196.0], ['invoice_date', '>=', '2026-09-15'], ['invoice_date', '>=', now()->subMonths(4)->toDateString()])
        ->and($factura->refresh()->posiblesEnOdoo()[0]['name'])->toBe('FAC 003701');
});

it('reads a hard photo in two steps when the first answer comes back empty', function () {
    config(['purchase_requests.reader.enabled' => true, 'purchase_requests.reader.base_url' => 'http://ia.local/v1', 'supplier_invoices.odoo.enabled' => false]);
    Http::preventStrayRequests();
    $respuesta = fn (string $contenido) => Http::response(['choices' => [['message' => ['content' => $contenido]]]]);
    Http::fake(['ia.local/*' => Http::sequence()
        ->pushResponse($respuesta(json_encode(['rut' => '', 'folio' => '', 'total' => '', 'lineas' => [['detalle' => '']]])))
        ->pushResponse($respuesta("Fecha: 26-10-2026\n3000 REMACHE ALA ANCHA 4.8X20 21RT 33,613 100.840\nTOTAL NETO 100.840\nIVA (19%) 19.160\nTOTAL A PAGAR 120.000"))
        ->pushResponse($respuesta(json_encode(['fecha' => '2026-10-26', 'neto' => '100840', 'iva' => '19160', 'total' => '120000',
            'lineas' => [['detalle' => 'REMACHE ALA ANCHA 4.8X20', 'codigo' => '21RT', 'cantidad' => '3000', 'precio' => '33,613']]])))]);

    $factura = subida([], SupplierInvoiceUpload::QUEUED);
    $factura->forceFill(['mime_type' => 'image/jpeg'])->save();
    (new ReadSupplierInvoice($factura))->handle(new LocalInvoiceReader, new InvoiceToOdoo);

    $factura->refresh();
    expect($factura->status)->toBe(SupplierInvoiceUpload::READ)
        ->and($factura->dato('total'))->toBe(120000)
        ->and($factura->lineas()[0])->toMatchArray(['detalle' => 'REMACHE ALA ANCHA 4.8X20 · 21RT', 'cantidad' => 3000, 'precio' => 33.613])
        ->and($factura->warnings[0])->toStartWith('La foto se leyó en dos pasos y es poco confiable')
        ->and($factura->raw_response)->toHaveKey('transcripcion');
});

it('creates the draft in Odoo like the bills already there, with the document attached', function () {
    odooDeFacturas(ultimaLinea: [['account_id' => [263, '332003 MANTENCION MAQUINARIAS'], 'analytic_distribution' => ['65,148' => 100], 'move_id' => [14700, 'FAC 003701']]]);
    $factura = subida(electrosol());

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('odoo.invoice_uploads.update', $factura), [...electrosol(), 'rut' => '77.118.278-K', 'accion' => 'crear'])
        ->assertSessionHas('success', 'Creada en Odoo como borrador: FAC 003748.');

    $move = mandado('account.move', 'create')[0];
    expect($move)->toMatchArray([
        'move_type' => 'in_invoice', 'partner_id' => 1738, 'journal_id' => 2, 'invoice_date' => '2026-09-22',
        'invoice_date_due' => '2026-10-22', 'l10n_latam_document_type_id' => 1, 'l10n_latam_document_number' => '3748',
    ])->and($move['invoice_line_ids'])->toHaveCount(6)
        ->and($move['invoice_line_ids'][0][2])->toBe([
            'name' => 'SOLENOIDE 12V KUBOTA', 'quantity' => 1.0, 'price_unit' => 96638.66,
            'tax_ids' => [[6, 0, [2]]], 'account_id' => 263, 'analytic_distribution' => ['65,148' => 100],
        ]);

    // Las líneas suman $138.821 y el neto es $138.822: el peso va en su propia línea.
    expect($move['invoice_line_ids'][5][2])->toMatchArray(['name' => 'Ajuste redondeo: neto factura $138.822', 'quantity' => 1.0, 'price_unit' => 1.0, 'account_id' => 263]);

    expect(mandado('ir.attachment', 'create')[0])->toMatchArray(['res_model' => 'account.move', 'res_id' => 14900, 'name' => 'FACTURA 3748.pdf'])
        ->and(mandado('account.move', 'write'))->toBe([[14900], ['message_main_attachment_id' => 777]]);

    expect($factura->refresh())
        ->status->toBe(SupplierInvoiceUpload::SENT)
        ->odoo_move_id->toBe(14900)
        ->odoo_move_name->toBe('FAC 003748')
        ->and($factura->dato('odoo_url'))->toBe('https://agricolaehe-prueba.ejemplo.cl');

    // Si después la pantalla apunta a otro Odoo, el enlace sigue llevando al suyo.
    config(['supplier_invoices.odoo.url' => 'https://agricolaehe.ejemplo.cl']);
    $this->get(route('odoo.invoice_uploads.show', $factura))
        ->assertSee('https://agricolaehe-prueba.ejemplo.cl/web#id=14900', false)
        ->assertSee('En Odoo de agricolaehe-prueba.ejemplo.cl como borrador');
});

it('does not create an invoice that is already in Odoo', function () {
    odooDeFacturas(yaEsta: true);
    $factura = subida(electrosol());

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('odoo.invoice_uploads.update', $factura), [...electrosol(), 'accion' => 'crear'])
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'Ya está en Odoo: FAC 003748'));

    expect(mandado('account.move', 'create'))->toBeNull()
        ->and($factura->refresh()->status)->toBe(SupplierInvoiceUpload::READ);
});

it('takes the IVA out of prices that come with it, as Accounting does', function () {
    odooDeFacturas();
    // La 11660 de N.D.F Rodamientos: 3 correas a $7.450 con IVA, neto $18.782.
    $ndf = electrosol(['folio' => '11660', 'neto' => 18782.0, 'iva' => 3568.0, 'total' => 22350.0,
        'lineas' => [['detalle' => 'CORREA · CORR', 'cantidad' => 3.0, 'precio' => 7450.0]]]);
    $factura = subida($ndf);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('odoo.invoice_uploads.show', $factura))
        ->assertSee('Los precios de las líneas traen el IVA incluido (suman $22.350, el total)')
        ->assertDontSee('falta o sobra algo');

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('odoo.invoice_uploads.update', $factura), [...$ndf, 'accion' => 'crear'])
        ->assertSessionHas('success');

    $lineas = mandado('account.move', 'create')[0]['invoice_line_ids'];

    // 7.450 / 1,19, sin línea de ajuste: Odoo redondea el IVA de la línea y da $22.350.
    expect($lineas)->toHaveCount(1)
        ->and($lineas[0][2]['price_unit'])->toBe(6260.504202);
});

it('does not round away a difference that is a missing line', function () {
    odooDeFacturas();
    $sinLaUltima = electrosol();
    array_pop($sinLaUltima['lineas']);
    $factura = subida($sinLaUltima);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('odoo.invoice_uploads.update', $factura), [...$sinLaUltima, 'accion' => 'crear'])
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'falta o sobra algo'));

    expect(mandado('account.move', 'create'))->toBeNull();
});

it('does not create one whose numbers do not add up', function () {
    odooDeFacturas();
    $factura = subida(electrosol());

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('odoo.invoice_uploads.update', $factura), [...electrosol(['total' => 170000]), 'accion' => 'crear'])
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'no da el total'));

    expect(mandado('account.move', 'create'))->toBeNull();
});

it('asks to create the supplier in Odoo first when its RUT is not there', function () {
    odooDeFacturas(proveedor: false);
    $factura = subida(electrosol());

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('odoo.invoice_uploads.show', $factura))
        ->assertOk()
        ->assertSee('El RUT 77.118.278-K no está en Odoo')
        ->assertSee('agricolaehe-prueba.ejemplo.cl');
});

it('creates it only once even if the button is pressed twice', function () {
    odooDeFacturas();
    $factura = subida(electrosol(), SupplierInvoiceUpload::SENDING);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('odoo.invoice_uploads.update', $factura), [...electrosol(), 'accion' => 'crear'])
        ->assertStatus(409);

    expect(mandado('account.move', 'create'))->toBeNull();
});
