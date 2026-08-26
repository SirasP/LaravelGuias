<?php

use App\Services\PurchaseRequests\Reading\TaxTreatment;

function partidas(array ...$lineas): array
{
    return array_map(fn (array $l): array => ['quantity' => $l[0], 'unit_price' => $l[1]], $lineas);
}

it('recognises net prices, the way Chilean quotations are written', function () {
    // Wurth, tal cual: neto 19.990 · IVA 3.798 · total 23.788
    $t = TaxTreatment::infer(partidas(['1', 19990.0]), 19990.0, 3798.0, 23788.0);

    expect($t->kind)->toBe(TaxTreatment::NETO)
        ->and($t->pricesIncludeTax())->toBeFalse()
        ->and($t->rate)->toBe(0.19);
});

it('recognises prices that already carry the tax', function () {
    // La misma plata, pero cotizada con el IVA dentro de cada línea.
    $t = TaxTreatment::infer(partidas(['1', 23788.0]), 19990.0, 3798.0, 23788.0);

    expect($t->kind)->toBe(TaxTreatment::CON_IVA)
        ->and($t->pricesIncludeTax())->toBeTrue();
});

it('trusts a declared tax line when the arithmetic does not add up', function () {
    // Motorman, tal cual: el documento trae un 5% de descuento por línea que
    // no leemos, así que la suma no cuadra con el neto. Pero el IVA aparece
    // desglosado, y eso ya dice que el precio va sin él: es la señal que usa
    // cualquiera que mire la cotización.
    $t = TaxTreatment::infer(partidas(['4', 73990.0]), 535686.0, 101780.0, 637466.0);

    expect($t->kind)->toBe(TaxTreatment::NETO)
        ->and($t->pricesIncludeTax())->toBeFalse()
        // Pero se avisa: la suma no cuadró y alguien tiene que mirarla.
        ->and($t->explanation)->toContain('revísala antes de enviar');
});

it('says it does not know when the document declares no tax at all', function () {
    // Sin IVA declarado y sin que la suma cuadre, no hay de dónde concluir.
    $t = TaxTreatment::infer(partidas(['1', 50000.0]), 19990.0, null, 23788.0);

    expect($t->kind)->toBe(TaxTreatment::SIN_DETERMINAR)
        ->and($t->pricesIncludeTax())->toBeNull()
        ->and($t->explanation)->toContain('no cuadra');
});

it('tolerates the rounding every document does its own way', function () {
    // Tres líneas con céntimos redondeados: no da exacto y da igual.
    $t = TaxTreatment::infer(
        partidas(['3', 31535.0], ['5', 32130.0]),
        255255.0,  // el documento declara 5 pesos menos que la suma exacta
        48498.0,
        303753.0,
    );

    expect($t->kind)->toBe(TaxTreatment::NETO);
});

it('has nothing to check when the quotation carries no prices', function () {
    $t = TaxTreatment::infer(partidas(['3', null]), null, null, null);

    expect($t->kind)->toBe(TaxTreatment::SIN_DETERMINAR)
        ->and($t->explanation)->toContain('no traen precio');
});
