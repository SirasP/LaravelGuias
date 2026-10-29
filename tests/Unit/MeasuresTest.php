<?php

use App\Services\DtePurchases\Measures;

it('tells measures apart only when both names disagree', function (string $invoice, string $request, ?array $difference) {
    expect(Measures::difference($invoice, $request))->toBe($difference);
})->with([
    'other thickness' => ['TUB CUAD NEG 40 X 2.0 MM', 'TUB CUAD NEG 40 X 3.0 MM', ['invoice' => ['2.0'], 'request' => ['3.0']]],
    'other fraction' => ['CODO PVC 1 1/2"', 'CODO PVC 2"', ['invoice' => ['1 1/2'], 'request' => ['2']]],
    'same value written otherwise' => ['TUB CUAD NEG  50 X 3.0 MM', 'TUB CUAD NEG 50 X 3 MM', null],
    'xml entities' => ['CODO PVC 1 1&#47;2&quot;', 'codo pvc 1 1/2', null],
    'chilean thousands' => ['BLOQUEADOR 1.000 ML', 'Bloqueador solar 1000 ml', null],
    'one side says more' => ['TUB 40 X 3.0 X 6 MTS', 'TUB 40 X 3.0', null],
    'no numbers on one side' => ['SPRAY BLANCO 400ML', 'SPRAY BLANCO', null],
]);
