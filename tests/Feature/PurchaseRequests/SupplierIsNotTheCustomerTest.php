<?php

use App\Services\PurchaseRequests\Reading\LocalQuotationReader;

/**
 * El error más repetido del modelo en documentos reales: devolver a quien
 * recibe la cotización como si fuera quien la emite. El nombre pasaba el
 * control anterior porque sí estaba escrito en el documento —como cliente—,
 * de modo que había que mirar dónde estaba escrito, no sólo si estaba.
 *
 * La regla es privada a propósito: es una decisión interna del lector, no
 * parte de su contrato. Se llega a ella por reflexión para poder probarla
 * sin levantar el modelo ni fabricar un PDF.
 */
function decideSiEsElCliente(string $proveedor, string $documento): bool
{
    $metodo = new ReflectionMethod(LocalQuotationReader::class, 'esElCliente');

    return $metodo->invoke(new LocalQuotationReader, $proveedor, $documento);
}

it('rejects the customer name that the model returned as the supplier', function () {
    // Presupuesto de IVAN RUDY CANCINO DIAZ. El modelo devolvió «EMPRESA
    // AGRICOLA EPPLE», que es a quien va dirigido.
    $rectificador = "IVAN RUDY CANCINO DIAZ\nRUT 10.855.569-6\nCONCEPCION 734 - RAHUE BAJO\n"
        ."OSORNO, SEPTIEMBRE 01 DE 2026\nEMPRESA   AGRICOLA EPPLE\nCOMUNA   RIO BUENO\nMOTOR   KUBOTA\n";

    expect(decideSiEsElCliente('EMPRESA AGRICOLA EPPLE', $rectificador))->toBeTrue();

    // Cotización de Würth. Mismo error, otra etiqueta.
    $wurth = "Wurth Chile Ltda. CL-9720232 Santiago\nCotización\n"
        ."Empresa\nAGRICO EPPLE HEINRICH Y ENFIELD SPA\nPILMAIQUEN LT A-3B\n5090000 RIO BUENO-VALDIVIA\n";

    expect(decideSiEsElCliente('AGRICO EPPLE HEINRICH Y ENFIELD SPA', $wurth))->toBeTrue();

    // Cotización de RODASERVIC, con la etiqueta «Cliente».
    $rodaservic = "R.U.T.:77.045.469-7\nCOTIZACION Nº 567\n"
        ."Cliente : AGRICOLA EPPLE, HEINRICH Y ENFILD SPA\nR.U.T. : 77.415.879-0\n"
        ."Giro : ACTIVIDADES DE APOYO A LA AGRICULTURA\n";

    expect(decideSiEsElCliente('AGRICOLA EPPLE, HEINRICH Y ENFILD SPA', $rodaservic))->toBeTrue();
});

it('keeps a real supplier whose name sits outside the customer block', function () {
    // El mismo documento de Electrosol que el modelo leyó bien. El proveedor
    // encabeza la página, lejos de «SEÑOR(ES)», y tiene que sobrevivir.
    $electrosol = "ELECTROSOL ENCENDIDOS SPA\nR.U.T.: 77.118.278-K\nCOTIZACIÓN Nº 13\n"
        ."SEÑOR(ES): AGRICOLA EPPLE, HEINRICH Y ENFIELD SPA   R.U.T.: 77.415.879-0\n"
        ."DIRECCIÓN PILMAIQUEN LT A-3B   CIUDAD: RIO BUENO\n";

    expect(decideSiEsElCliente('ELECTROSOL ENCENDIDOS SPA', $electrosol))->toBeFalse();

    $motorman = "MOTORMAN S.A   R.U.T. 77.591.550-1\nFACTURA ELECTRÓNICA N° 97351\n"
        ."SEÑOR(ES): AGRICOLA EPPLE HEINRICH Y ENFIELD SPA\nR.U.T.: 77.415.879-0\nCOMUNA: RIO BUENO\n";

    expect(decideSiEsElCliente('MOTORMAN S.A', $motorman))->toBeFalse();
});

it('does not reject a supplier that also appears in the customer block', function () {
    // Si el nombre aparece dentro y fuera del bloque del cliente, no hay
    // motivo para descartarlo: puede ser el emisor nombrado dos veces.
    $documento = "SODIMAC S.A. cotización\nCliente: SODIMAC S.A. sucursal Osorno\nPILMAIQUEN\n";

    expect(decideSiEsElCliente('SODIMAC S.A.', $documento))->toBeFalse();
});

it('says nothing when the document has no customer label at all', function () {
    expect(decideSiEsElCliente('FERRETERIA EL SOL', "FERRETERIA EL SOL\n2 correas a 12.500\n"))->toBeFalse();
});

/** El nombre del emisor tal como lo sacaría una persona mirando el papel. */
function nombreDelEmisor(string $documento, ?string $rut): ?string
{
    $metodo = new ReflectionMethod(LocalQuotationReader::class, 'nombreDelEmisorSegunElTexto');

    return $metodo->invoke(new LocalQuotationReader, $documento, $rut);
}

it('reads the supplier name off the document when the model did not give one', function () {
    // Encabezado en la misma línea que el RUT.
    $electrosol = "                 ELECTROSOL ENCENDIDOS                    R.U.T.: 77.118.278-K\n"
        ."                 Giro: SS. ELECTRIC. Y ELECTRÓNICA AUTOMIZ            COTIZACIÓN\n"
        ."SEÑOR(ES):  AGRICOLA EPPLE, HEINRICH Y ENFIELD SPA        R.U.T.:  77.415.879-0\n";

    expect(nombreDelEmisor($electrosol, '77118278-K'))->toBe('ELECTROSOL ENCENDIDOS');

    // Nombre en la línea de arriba: el RUT va solo.
    $rectificador = "Rectificación de Motores, Maestranza\nRepuestos-Transporte de carga por carretera\n"
        ."IVAN RUDY CANCINO DIAZ\n RUT 10.855.569-6\nCONCEPCION 734 – RAHUE BAJO\n"
        ."EMPRESA               AGRICOLA EPPLE\n";

    expect(nombreDelEmisor($rectificador, '10855569-6'))->toBe('IVAN RUDY CANCINO DIAZ');

    // Sin ningún RUT chileno: manda la primera línea con contenido, y el
    // código interno que la acompaña no forma parte del nombre.
    $wurth = "Wurth Chile Ltda. CL-9720232 Santiago\n                     Cotización\n"
        ."Empresa\nAGRICO EPPLE HEINRICH Y ENFIELD SPA\nPILMAIQUEN LT A-3B\n";

    expect(nombreDelEmisor($wurth, null))->toBe('Wurth Chile Ltda');
});

it('leaves the supplier name empty rather than inventing one', function () {
    // Cotización real cuyo encabezado es una imagen: en el texto no queda
    // más que el RUT. Vacío es la respuesta correcta; el nombre sale de Odoo
    // por RUT, o lo pone una persona una sola vez.
    $soloRut = "                                        R.U.T.:77.045.469-7\n"
        ."                                               COTIZACION\n                                                 Nº 567\n"
        ."Cliente     :   AGRICOLA EPPLE, HEINRICH Y ENFILD SPA\n";

    expect(nombreDelEmisor($soloRut, '77045469-7'))->toBeNull();
});

it('never takes the customer as the emitter when reading the header', function () {
    // El destinatario encabeza el documento y el emisor no aparece por
    // ninguna parte: no hay nombre que dar.
    $documento = "Cliente: AGRICOLA EPPLE, HEINRICH Y ENFIELD SPA\nPILMAIQUEN LT A-3B\nRIO BUENO\n";

    expect(nombreDelEmisor($documento, null))->toBeNull();
});
