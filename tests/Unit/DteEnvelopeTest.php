<?php

use App\Support\DteEnvelope;

function envelopeDocument(string $xml): SimpleXMLElement
{
    $document = simplexml_load_string($xml);
    $document->registerXPathNamespace('sii', DteEnvelope::NAMESPACE);

    return $document;
}

it('splits an envelope with two invoices into one XML each, with their own lines', function () {
    $documents = DteEnvelope::documents(file_get_contents(__DIR__.'/../Fixtures/dte/implementos-dos-facturas.xml'));

    expect($documents)->toHaveCount(2);
    [$first, $second] = array_map(envelopeDocument(...), $documents);
    expect((string) $first->xpath('//sii:Encabezado/sii:IdDoc/sii:Folio')[0])->toBe('7100539')
        ->and($first->xpath('//sii:Detalle'))->toHaveCount(1)
        ->and((string) $first->xpath('//sii:Detalle/sii:QtyItem')[0])->toBe('15')
        ->and((string) $first->xpath('//sii:Totales/sii:MntNeto')[0])->toBe('40210')
        ->and((string) $second->xpath('//sii:Encabezado/sii:IdDoc/sii:Folio')[0])->toBe('7100545')
        ->and((string) $second->xpath('//sii:Detalle/sii:QtyItem')[0])->toBe('13')
        ->and((string) $second->xpath('//sii:Emisor/sii:RUTEmisor')[0])->toBe('78924030-2');
});

it('leaves a single document, or something unreadable, exactly as it came', function () {
    $single = DteEnvelope::documents(file_get_contents(__DIR__.'/../Fixtures/dte/implementos-dos-facturas.xml'))[0];

    expect(DteEnvelope::documents($single))->toBe([$single])
        ->and(DteEnvelope::documents('esto no es XML'))->toBe(['esto no es XML'])
        ->and(DteEnvelope::documents('<DTE><Documento/></DTE>'))->toBe(['<DTE><Documento/></DTE>']);
});
