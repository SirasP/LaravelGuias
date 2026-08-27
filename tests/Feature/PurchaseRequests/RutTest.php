<?php

use App\Support\Rut;

it('validates the chilean check digit', function () {
    // Reales, tomados de documentos de la empresa.
    expect(Rut::isValid('77.045.469-7'))->toBeTrue();
    expect(Rut::isValid('77.415.879-0'))->toBeTrue();
    expect(Rut::isValid('77415879-0'))->toBeTrue();

    // Inventados: un dígito verificador que no cuadra se rechaza.
    expect(Rut::isValid('12.345.678-9'))->toBeFalse();
    expect(Rut::isValid('77.415.879-1'))->toBeFalse();
    // El que figura como demo en la tabla de proveedores tampoco es válido.
    expect(Rut::isValid('76.123.456-7'))->toBeFalse();

    expect(Rut::isValid(null))->toBeFalse();
    expect(Rut::isValid('no soy un rut'))->toBeFalse();
});

it('normalizes and formats consistently', function () {
    expect(Rut::normalize('77.415.879-0'))->toBe('77415879-0')
        ->and(Rut::normalize('77415879-0'))->toBe('77415879-0')
        ->and(Rut::normalize('77 415 879 0'))->toBe('77415879-0');

    expect(Rut::format('77415879-0'))->toBe('77.415.879-0');
    expect(Rut::format('nada'))->toBeNull();
});

it('accepts the K check digit', function () {
    // 12.345.670-K es un RUT válido con dígito K.
    expect(Rut::normalize('12.345.670-k'))->toBe('12345670-K');
    expect(Rut::format('12345670-K'))->toBe('12.345.670-K');
});

it('finds every valid rut in a document, in order and without repeats', function () {
    $texto = <<<'TXT'
                                        R.U.T.:77.045.469-7
                                            COTIZACION Nº 549
    Cliente : AGRICOLA EPPLE, HEINRICH Y ENFILD SPA
    R.U.T.  : 77.415.879-0
    Repetido: 77.045.469-7
    Basura  : 12.345.678-9
    TXT;

    $encontrados = Rut::findAll($texto);

    // El emisor encabeza la página, el cliente va después. Los repetidos no
    // se cuentan dos veces y los inválidos no entran.
    expect(collect($encontrados)->pluck('rut')->all())->toBe(['77045469-7', '77415879-0']);
    expect($encontrados[0]['posicion'])->toBeLessThan($encontrados[1]['posicion']);
});
