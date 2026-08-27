<?php

use App\Services\PurchaseRequests\Products\ProductSimilarity;

function parecido(): ProductSimilarity
{
    return new ProductSimilarity;
}

it('unifies the abbreviations everyone writes differently', function () {
    expect(parecido()->normalize('CLORO GEL 5 LTS'))->toBe('cloro gel 5 lt')
        ->and(parecido()->normalize('Cloro Gel 5 litros'))->toBe('cloro gel 5 lt')
        ->and(parecido()->normalize('BOLSA (10 UNID.)'))->toBe('bolsa 10 un');
});

it('scores identical names at one and unrelated ones low', function () {
    expect(parecido()->score('RODAMIENTO 6202', 'rodamiento 6202'))->toBe(1.0)
        ->and(parecido()->score('Cemento', 'Guantes de latex'))->toBeLessThan(0.45);
});

it('refuses to call two sizes the same product', function () {
    // Idea tomada del módulo de facturas: XL no es «parecido» a XXL, es otra
    // prenda. Un puntaje ciego los daría casi idénticos.
    expect(parecido()->score('TRAJE AGUA VERDE PU XL', 'TRAJE AGUA VERDE PU XXL'))->toBe(0.0)
        ->and(parecido()->score('GUANTES ANTICORTE T8', 'GUANTES ANTICORTE T8'))->toBe(1.0);
});

it('refuses to call two measurements the same product', function () {
    // Lo que se agrega para este catálogo: un codo de 75 y uno de 110
    // comparten casi todas las letras y no sirven para lo mismo.
    expect(parecido()->score('Tubo PVC sanitario 75 mm', 'Tubo PVC sanitario 110 mm'))->toBe(0.0)
        ->and(parecido()->score('Motor 12V', 'Motor 24V'))->toBe(0.0);

    // Pero si comparten la medida, se comparan normalmente.
    expect(parecido()->score('Tubo PVC sanitario 75 mm', 'TUBO PVC SANIT 75'))->toBeGreaterThan(0.6);
});

it('does not disqualify when only one of them states a measurement', function () {
    // «Cloro gel» sin litros no contradice a «Cloro gel 5 lt»: puede que
    // simplemente no lo diga.
    expect(parecido()->score('Cloro gel', 'CLORO GEL 5 LT'))->toBeGreaterThan(0.45);
});

it('is not fooled by long names that share half the letters', function () {
    // El caso real: son dos grifos distintos y el texto se parece mucho.
    // No hace falta que dé cero; hace falta que no dé certeza.
    expect(parecido()->score('Monomando Lavamano', 'MONOMANDO LAVATORIO ECO - TAUMM 010100102'))
        ->toBeLessThan(0.88);
});
