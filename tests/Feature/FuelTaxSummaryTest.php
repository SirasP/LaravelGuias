<?php

use App\Http\Controllers\GmailDteDocumentController;

function fuelSummary(string $totales, float $iva): array
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?><EnvioDTE xmlns="http://www.sii.cl/SiiDte"><DTE><Documento><Encabezado><Totales>'
        .$totales.'</Totales></Encabezado></Documento></DTE></EnvioDTE>';
    $document = (object) ['monto_iva' => $iva, 'xml_raw' => $xml];
    $method = new ReflectionMethod(GmailDteDocumentController::class, 'buildTaxSummary');

    return collect($method->invoke(app(GmailDteDocumentController::class), $document, collect()))->keyBy('label')->all();
}

it('shows the diesel specific tax with the amount that adds up, even when the XML says 1', function () {
    // Coagra 2306359: the real −$1.590.233 only travels in the line text.
    $summary = fuelSummary('<MntNeto>7210755</MntNeto><TasaIVA>19.00</TasaIVA><IVA>1370043</IVA>'
        .'<ImptoReten><TipoImp>28</TipoImp><MontoImp>1</MontoImp></ImptoReten><MntTotal>6990565</MntTotal>', 1370043);

    expect($summary)->toHaveKey('Impuesto específico diésel')
        ->and($summary['Impuesto específico diésel']['monto'])->toEqual(-1590233)
        ->and(array_keys($summary))->not->toContain('Impuesto retenido 28');
});

it('keeps the amount the XML declares when it adds up', function () {
    $summary = fuelSummary('<MntNeto>100000</MntNeto><TasaIVA>19</TasaIVA><IVA>19000</IVA>'
        .'<ImptoReten><TipoImp>35</TipoImp><MontoImp>5126</MontoImp></ImptoReten><MntTotal>124126</MntTotal>', 19000);

    expect($summary['Impuesto específico gasolina 93']['monto'])->toEqual(5126);
});
