<?php

use App\Models\PurchaseProductLink;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\PurchaseRequests\Products\ProductSimilarity;
use App\Services\PurchaseRequests\Quotes\QuotationComparison;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithPurchaseRequests;

uses(RefreshDatabase::class, InteractsWithPurchaseRequests::class);

/**
 * El emparejado, medido contra las dos cotizaciones reales de la SC-2026-000031.
 *
 * Las dos contestan a las mismas diecinueve partidas y fallan distinto: MARYUN
 * escribe los nombres completos pero con otras palabras, y MAX SERVICE los
 * imprime cortados a veintiocho caracteres, con lo que cuatro overoles de
 * tallas distintas salen con el nombre idéntico. Si algo va a romper el
 * emparejado, rompe aquí.
 */

/** Las diecinueve partidas, tal como Paola las escribió. */
function partidasDeLaSolicitud(): array
{
    return [
        ['Respirador Media cara 2 VIAS 3M 6200 GRIS CLARA Talla M', 2],
        ['Filtro 3M 6003 VAPORES ORG./GASES ACIDOS', 6],
        ['Casco de seguridad MSA V-GARD BLANCO con barbiquejo', 1],
        ['Guante Cabretilla', 36],
        ['Guante Nitrilo Verde talla 7', 10],
        ['Guante Nitrilo Verde talla 8', 10],
        ['Guante Nitrilo Verde talla 9', 10],
        ['Bloqueador solar 1000 ml', 7],
        ['Lente Oscuro Con Protector UV basic', 300],
        ['Gorro Legionario', 200],
        ['Fonos 3m Cintillo Peltor', 25],
        ['overol naranjo poplin S', 2],
        ['overol naranjo poplin M', 5],
        ['overol naranjo poplin L', 10],
        ['overol naranjo poplin XL', 5],
        ['overol naranjo poplin XXL', 5],
        ['overol naranjo poplin XXXL', 2],
        ['Traje de agua XXL', 2],
        ['Traje de agua XL', 2],
    ];
}

function solicitudDeEpp(User $owner): PurchaseRequest
{
    $solicitud = test()->createPurchaseRequestDraft($owner);
    $solicitud->items()->delete();

    foreach (partidasDeLaSolicitud() as $n => [$texto, $cantidad]) {
        $solicitud->items()->create([
            'sort_order' => $n + 1, 'product_service' => $texto,
            'quantity' => $cantidad, 'unit' => 'Unidades',
        ]);
    }

    return $solicitud->fresh();
}

/** @param list<array{0: string, 1: int|string, 2: int}> $lineas */
function comoLasLeyoElModelo(array $lineas): array
{
    return array_map(fn (array $l): array => [
        'product_service' => $l[0], 'specification' => null,
        'quantity' => (string) $l[1], 'unit' => $l[2] === 0 ? 'UN' : 'UN', 'unit_price' => $l[2],
    ], $lineas);
}

/** La cotización de MARYUN: mismos productos, otras palabras, mismo orden salvo tres overoles. */
function cotizacionDeMaryun(): array
{
    return comoLasLeyoElModelo([
        ['RESPIRADOR 2 VIAS 3M 6200 GRIS CLARA (TALLA M) -', 2, 12162],
        ['FILTRO 3M 6003 VAPORES ORG./GASES ACIDOS -', 3, 14590],
        ['CASCO MSA V-GARD - (BLANCO)', 1, 10900],
        ['GUANTE CABRITILLA S/FORRO - (9)', 36, 1283],
        ['GUANTE NITRILO VERDE FLOCADO - (7)', 10, 1010],
        ['GUANTE NITRILO VERDE FLOCADO - (8)', 10, 1010],
        ['GUANTE NITRILO VERDE FLOCADO - (9)', 10, 1010],
        ['BLOQUEADOR SOLAR 1 KG C/VAL FPS 50 SAFEPRO -', 7, 10876],
        ['ANTEOJO POLICARB. BASIC GRIS -', 300, 510],
        ['GORRO LEGIONARIO - (BEIGE)', 200, 2299],
        ['FONO CINTILLO PELTOR 3M H510A -', 25, 17950],
        ['OVEROL POPLIN - (NARANJO)(L)', 10, 6064],
        ['OVEROL POPLIN - (NARANJO)(M)', 5, 6064],
        ['OVEROL POPLIN - (NARANJO)(S)', 2, 6064],
        ['OVEROL POPLIN - (NARANJO)(XL)', 5, 6064],
        ['OVEROL POPLIN - (NARANJO)(XXL)', 5, 6064],
        ['OVEROL POPLIN - (NARANJO)(XXXL)', 2, 6064],
        ['TRAJE DE AGUA PU MY SAFEGUARD - (VERDE)(XXL)', 2, 14678],
        ['TRAJE DE AGUA PU MY SAFEGUARD - (VERDE)(XL)', 2, 14678],
    ]);
}

/** La de MAX SERVICE: nombres cortados a 28 caracteres, otro orden, y una línea de más. */
function cotizacionDeMaxService(): array
{
    return comoLasLeyoElModelo([
        ['CASCO MSA TOP-GARD GORRA, BL', 1, 33350],
        ['GTE NITRILO VERDE, 7,0', 10, 680],
        ['GTE NITRILO MS VERDE, VERDE,', 10, 680],
        ['GTE NITRILO MS VERDE, VERDE,', 10, 680],
        ['PROTECTOR SOLAR FPS50 B.BOAT', 7, 10200],
        ['LENTE PILOT ONE PROTECCION U', 288, 399],
        ['JOCKEY LEGIONARIO MS, BEIGE', 200, 1290],
        ['FONO PELTOR H510A C/CINTILLO', 25, 15200],
        ['OVEROL PILOTO POPLIN CC, Nar', 2, 4790],
        ['OVEROL PILOTO POPLIN CC, Nar', 5, 4790],
        ['OVEROL PILOTO MS POP C/C, NA', 10, 4790],
        ['OVEROL PILOTO MS POP C/C, NA', 5, 4790],
        ['OVEROL PILOTO MS POP C/C, NA', 5, 4790],
        ['OVEROL PILOTO MS POP C/C, NA', 2, 4790],
        ['TRAJE PU-GOMA JUNKAL, AZUL,', 2, 10900],
        ['TRAJE PU-GOMA JUNKAL, AZUL,', 2, 10900],
        ['LENTE TIPO SPY GRIS', 12, 399],
        ['RESPIRADOR AIR S900 TPR (S60', 2, 5700],
        ['CARTUCHO AIR F600MP3 P3 MULT', 6, 13200],
    ]);
}

/** @return list<?string> lo que quedó cruzado con cada partida, en orden */
function loCruzado(PurchaseRequest $solicitud, array $lineas): array
{
    $resultado = app(QuotationComparison::class)->comparar($solicitud, $lineas);

    return array_map(fn ($fila): ?string => $fila->cotizada['product_service'] ?? null, $resultado->filas);
}

it('answers a nineteen-line request with nineteen rows, never more', function () {
    $solicitud = solicitudDeEpp(User::factory()->create());

    foreach ([cotizacionDeMaryun(), cotizacionDeMaxService()] as $lineas) {
        $resultado = app(QuotationComparison::class)->comparar($solicitud, $lineas);

        // Lo que el proveedor agregó existe, pero no infla la tabla: mezclarlo
        // convertía diecinueve partidas en treinta y tres filas.
        expect($resultado->partidas())->toBe(19)
            ->and($resultado->filas)->toHaveCount(19);
    }
});

it('crosses every line of the quotation that answers word for word', function () {
    $solicitud = solicitudDeEpp(User::factory()->create());
    $cruzado = loCruzado($solicitud, cotizacionDeMaryun());

    expect(array_filter($cruzado))->toHaveCount(19)
        ->and($cruzado[7])->toBe('BLOQUEADOR SOLAR 1 KG C/VAL FPS 50 SAFEPRO -')
        ->and($cruzado[8])->toBe('ANTEOJO POLICARB. BASIC GRIS -')
        // Los overoles vienen en otro orden que la solicitud: L, M, S.
        ->and($cruzado[11])->toBe('OVEROL POPLIN - (NARANJO)(S)')
        ->and($cruzado[12])->toBe('OVEROL POPLIN - (NARANJO)(M)')
        ->and($cruzado[13])->toBe('OVEROL POPLIN - (NARANJO)(L)')
        // Y las dos tallas del traje no se confunden entre ellas.
        ->and($cruzado[17])->toBe('TRAJE DE AGUA PU MY SAFEGUARD - (VERDE)(XXL)')
        ->and($cruzado[18])->toBe('TRAJE DE AGUA PU MY SAFEGUARD - (VERDE)(XL)');
});

it('tells four identically printed overalls apart by quantity and order', function () {
    $solicitud = solicitudDeEpp(User::factory()->create());
    $resultado = app(QuotationComparison::class)->comparar($solicitud, cotizacionDeMaxService());
    $filas = $resultado->filas;

    // El proveedor imprimió el mismo nombre cuatro veces; sólo la cantidad y el
    // orden dicen cuál es la L, cuál la XL, cuál la XXL y cuál la XXXL.
    expect((string) $filas[13]->cotizada['quantity'])->toBe('10')
        ->and((string) $filas[14]->cotizada['quantity'])->toBe('5')
        ->and((string) $filas[15]->cotizada['quantity'])->toBe('5')
        ->and((string) $filas[16]->cotizada['quantity'])->toBe('2')
        // Y las dos primeras, impresas igual entre ellas, no salen cruzadas.
        ->and((string) $filas[11]->cotizada['quantity'])->toBe('2')
        ->and((string) $filas[12]->cotizada['quantity'])->toBe('5');
});

it('keeps what the supplier added and what they never quoted apart', function () {
    $solicitud = solicitudDeEpp(User::factory()->create());
    $resultado = app(QuotationComparison::class)->comparar($solicitud, cotizacionDeMaxService());

    // MAX SERVICE no cotizó el guante de cabritilla y agregó un lente que
    // nadie pidió. Las dos cosas hay que verlas, y son cosas distintas.
    expect($resultado->filas[3]->estado)->toBe('sin_cotizar')
        ->and($resultado->sobrantes)->toHaveCount(1)
        ->and($resultado->sobrantes[0]->cotizada['product_service'])->toBe('LENTE TIPO SPY GRIS')
        // El lente que sí contesta a la partida es el otro, aunque el nombre
        // del sobrante se parezca más.
        ->and($resultado->filas[8]->cotizada['product_service'])->toBe('LENTE PILOT ONE PROTECCION U');
});

it('proposes the pairings it cannot prove instead of asserting them', function () {
    $solicitud = solicitudDeEpp(User::factory()->create());
    $resultado = app(QuotationComparison::class)->comparar($solicitud, cotizacionDeMaxService());

    // Con los nombres cortados, el texto no alcanza para afirmar nada: se
    // proponen y una persona confirma. Lo que no se hace es callarlo.
    expect($resultado->porConfirmar())->toBeGreaterThan(0)
        ->and($resultado->filas[7]->esPropuesta())->toBeTrue()
        ->and($resultado->filas[7]->cotizada['product_service'])->toBe('PROTECTOR SOLAR FPS50 B.BOAT');
});

it('stops treating a number glued to letters as a measurement', function () {
    $similitud = app(ProductSimilarity::class);

    // El 3 de la marca 3M no contradice al 510 del modelo H510A, y unos mililitros
    // no contradicen un kilo. Las dos cosas daban 0,0000 y borraban la pareja.
    expect($similitud->score('Fonos 3m Cintillo Peltor', 'FONO PELTOR H510A C/CINTILLO'))->toBeGreaterThan(0.3)
        ->and($similitud->score('Bloqueador solar 1000 ml', 'BLOQUEADOR SOLAR 1 KG C/VAL FPS 50'))->toBeGreaterThan(0.4)
        // Y lo que sí es una medida sigue descalificando.
        ->and($similitud->score('Tubo PVC 75 mm', 'Tubo PVC 110 mm'))->toBe(0.0)
        ->and($similitud->score('Ampolleta 12V', 'Ampolleta 24V'))->toBe(0.0)
        ->and($similitud->score('Guante Nitrilo Verde talla 7', 'GUANTE NITRILO VERDE FLOCADO - (8)'))->toBe(0.0);
});

it('does not invent a pairing out of a quantity that matches by chance', function () {
    $owner = User::factory()->create();
    $solicitud = test()->createPurchaseRequestDraft($owner);
    $solicitud->items()->delete();
    $solicitud->items()->create([
        'sort_order' => 1, 'product_service' => 'valvula mariposa',
        'quantity' => 1, 'unit' => 'Unidades',
    ]);

    $resultado = app(QuotationComparison::class)->comparar($solicitud->fresh(), comoLasLeyoElModelo([
        ['FLETE A RIO BUENO', 1, 35000],
    ]));

    // Una unidad de algo y un flete de una unidad comparten la cantidad y nada
    // más. Sin otras líneas que respalden al documento, no se propone nada.
    expect($resultado->filas[0]->estado)->toBe('sin_cotizar')
        ->and($resultado->sobrantes)->toHaveCount(1);
});

it('crosses on what a person taught even when the words share nothing', function () {
    $solicitud = solicitudDeEpp(User::factory()->create());

    PurchaseProductLink::query()->create([
        'company_code' => 'EHE', 'odoo_partner_id' => null,
        'source_text' => 'PROTECTOR SOLAR FPS50 B.BOAT',
        'normalized_text' => PurchaseProductLink::normalizar('PROTECTOR SOLAR FPS50 B.BOAT'),
        'canonical_text' => PurchaseProductLink::normalizar('Bloqueador solar 1000 ml'),
        'source' => PurchaseProductLink::CONFIRMADO,
    ]);

    $resultado = app(QuotationComparison::class)->comparar($solicitud, cotizacionDeMaxService());

    expect($resultado->filas[7]->esPropuesta())->toBeFalse()
        ->and($resultado->filas[7]->confianza)->toBe(1.0)
        // Y la pantalla puede ofrecer deshacerlo, porque hay algo guardado.
        ->and($resultado->filas[7]->aprendida)->toBeTrue();
});
