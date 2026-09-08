<?php

use App\Services\PurchaseRequests\Reading\LocalQuotationReader;

/**
 * Las pulgadas parten el JSON del modelo.
 *
 * En este país las medidas de ferretería se escriben así: «SIFON LAVAPLATO
 * 1 1/2"-1 1/4"». El modelo copia el nombre tal cual dentro de una cadena
 * JSON y esa comilla la cierra a media palabra. Una cotización entera de
 * SOCIEDAD COMERCIAL S&M se perdía por un carácter.
 */
function reparado(string $json): string
{
    $metodo = new ReflectionMethod(LocalQuotationReader::class, 'conLasComillasEscapadas');

    return $metodo->invoke(new LocalQuotationReader, $json);
}

it('rescues a quotation broken by an inch mark', function () {
    $roto = '{"items":[{"product_service":"SIFON LAVAPLATO 1 1/2"-1 1/4" TAUMM 40906205","quantity":"2"}]}';

    expect(json_decode($roto, true))->toBeNull();

    $sano = json_decode(reparado($roto), true);

    expect($sano['items'][0]['product_service'])->toBe('SIFON LAVAPLATO 1 1/2"-1 1/4" TAUMM 40906205')
        ->and($sano['items'][0]['quantity'])->toBe('2');
});

it('handles several inch marks in the same line', function () {
    $roto = '{"items":[{"product_service":"REGULADOR GAS KIT 1/2"X3/8" IZQ. HOFFENS 62653","unit_price":"15882,35"}]}';

    expect(json_decode(reparado($roto), true)['items'][0]['product_service'])
        ->toBe('REGULADOR GAS KIT 1/2"X3/8" IZQ. HOFFENS 62653');
});

it('leaves a healthy document exactly as it was', function () {
    // El reparador corre sólo tras un fallo, pero aun así no puede estropear
    // lo que ya estaba bien: aquí se comprueba carácter por carácter.
    $sano = '{"supplier":{"name":"SOCIEDAD COMERCIAL S&M LIMITADA","vat":"76569041-2"},'
        .'"items":[{"product_service":"CAMARA CIRCULAR 30X60CM HORMIGON","quantity":"2","unit_price":"23512,61"}]}';

    expect(reparado($sano))->toBe($sano)
        ->and(json_decode(reparado($sano), true)['supplier']['name'])->toBe('SOCIEDAD COMERCIAL S&M LIMITADA');
});

it('does not touch a quote the model did escape properly', function () {
    $bien = '{"items":[{"product_service":"TERMINAL SO HE 1/2\"(F-4-8)","quantity":"1"}]}';

    expect(json_decode($bien, true))->not->toBeNull()
        ->and(reparado($bien))->toBe($bien);
});

it('gives up loudly instead of inventing a reading it cannot be sure of', function () {
    // «PERFIL 2", LARGO: 3 M» es irrecuperable y hay que decirlo: esa comilla
    // va seguida de una coma, igual que el cierre de verdad de cualquier
    // cadena, y ningún reparador puede distinguirlas. Lo que no puede pasar es
    // que salga un JSON válido con el nombre equivocado.
    $ambiguo = '{"items":[{"product_service":"PERFIL 2", LARGO: 3 M","quantity":"8"}]}';

    expect(json_decode(reparado($ambiguo), true))->toBeNull();
});

it('does not take a field label for a company name', function () {
    $metodo = new ReflectionMethod(LocalQuotationReader::class, 'nombreDelEmisorSegunElTexto');
    $lector = new LocalQuotationReader;

    // La razón social va en su línea y debajo vienen los campos. Tomar el de
    // más arriba del RUT dejaba la cotización a nombre de «Giro: VENTA
    // ARTICULOS DE FERRETERIA».
    $texto = "Cotización N°: 5936\nSOCIEDAD COMERCIAL S&M LIMITADA\n"
        ."Giro:        VENTA ARTICULOS DE FERRETERIA\nRut:         76.569.041-2\n"
        ."Dirección:   PEDRO LAGOS 1003\nCiudad:      RIO BUENO\n";

    expect($metodo->invoke($lector, $texto, '76569041-2'))->toBe('SOCIEDAD COMERCIAL S&M LIMITADA');
});

it('would rather name nobody than name a block heading', function () {
    $metodo = new ReflectionMethod(LocalQuotationReader::class, 'nombreDelEmisorSegunElTexto');

    // «ENVIADA POR» anuncia quién viene después, no es quien viene. Sin
    // nombre el RUT sigue resolviendo el proveedor en Odoo; con un nombre
    // falso, en cambio, nadie se entera de que está mal.
    expect($metodo->invoke(new LocalQuotationReader, "COTIZACION\nENVIADA POR\nRUT:77.084.730-3\n", '77084730-3'))
        ->toBeNull();
});
