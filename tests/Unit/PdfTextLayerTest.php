<?php

use App\Services\PurchaseRequests\Reading\LocalQuotationReader;

it('does not take a text layer of blanks and symbols for text', function () {
    // Como la cotización de Climent hecha con «Microsoft Print to PDF».
    $vacia = str_repeat("    \n", 600).str_repeat("\u{F0B7} ", 300);
    $deVerdad = "COMERCIAL CLIMENT SPA  RUT 86.490.600-1\nCODO PVC-H CEM 200MM X 90º  4,00  23.429\nVALVULA DE MARIPOSA 200MM  1,00  61.716";

    expect(LocalQuotationReader::tieneTextoUtil($vacia))->toBeFalse()
        ->and(LocalQuotationReader::tieneTextoUtil(null))->toBeFalse()
        ->and(LocalQuotationReader::tieneTextoUtil($deVerdad))->toBeTrue();
});
