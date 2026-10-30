<?php

use App\Services\PurchaseRequests\Quotes\QuoteMeasures;

it('flags the pairs that crossed in the Cooprinsem quotation', function (string $pedido, string $cotizado) {
    expect(QuoteMeasures::chocan($pedido, $cotizado))->toBeTrue();
})->with([
    'empaquetadura 5" con la de 75 mm' => ['empaquetadura plana 5"', 'EMPAQUETADURA GOMA 75 MM'],
    'empaquetadura 3" con la de 125 mm' => ['empaquetadura plana 3"', 'EMPAQUETADURA GOMA 125 MM'],
    'perfil 100 con el de 50' => ['perfil cuadrado fierro 100/100/5/6.000mm.', 'PERFIL 50x50x3MM - MT'],
    'perno ⅜ con el de ½' => ['pernos anclaje ⅜/3"', 'PERNO ANCLAJE 1/2x3"'],
    'tubo de 3 mm con el de 2' => ['TUB CUAD NEG 40 X 3.0 MM', 'TUB CUAD NEG 40 X 2.0 MM'],
]);

it('accepts the same measure written another way', function (string $pedido, string $cotizado) {
    expect(QuoteMeasures::chocan($pedido, $cotizado))->toBeFalse();
})->with([
    'pulgadas y milímetros' => ['empaquetadura plana 5"', 'EMPAQUETADURA GOMA 125 MM'],
    '10 pulgadas son 250 mm' => ['empaquetaduras 10"', 'EMPAQUETADURA GOMA 250 MM'],
    'fracción de un carácter' => ['tuercas¾" galvanizadas', 'TUERCA HEXAGONAL ZINC 3/4"'],
    'pegada a un número' => ['soldadura 7018/⅛', 'SOLDADURA 7018 1/8'],
    'en otro orden' => ['reducción concéntrica fierro 3"/8"', 'RED CONCENTRICA ACERO 8"x3'],
    'miles chilenos' => ['perfil cuadrado 50/50/3/6.000', 'PERFIL 50x50x3MM - MT'],
    'uno dice más' => ['punta de hilo 2" /10cms.', 'PUNTA DE HILO ACERO 2"'],
    'coma decimal' => ['tubos fierro 8"/4.8mm', 'CAÑERIA ACERO 8" 4,8 MM - MT SOLDAR'],
    'sin números' => ['golillas planas galvanizadas', 'GOLILLA PLANA ZINC 3/4"'],
]);
