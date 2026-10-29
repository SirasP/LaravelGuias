<?php

use App\Console\Commands\GmailLeerXml;
use App\Support\DteEnvelope;
use Carbon\Carbon;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

uses(RefreshDatabase::class);

/** Saves each document of the envelope the way `gmail:leer-xml` does. */
function importEnvelope(): void
{
    $command = app(GmailLeerXml::class);
    $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));
    $persist = new ReflectionMethod($command, 'persistNonFuelDte');
    foreach (DteEnvelope::documents(file_get_contents(base_path('tests/Fixtures/dte/implementos-dos-facturas.xml'))) as $document) {
        $xml = simplexml_load_string($document);
        $xml->registerXPathNamespace('sii', DteEnvelope::NAMESPACE);
        $persist->invoke($command, DB::connection('fuelcontrol'), 'mensaje', 'EnvioDTE.xml', $xml, Carbon::parse('2026-10-28'), false, $document);
    }
}

function envelopeLines(string $folio): array
{
    $id = DB::connection('fuelcontrol')->table('gmail_dte_documents')->where('folio', $folio)->value('id');

    return DB::connection('fuelcontrol')->table('gmail_dte_document_lines')->where('document_id', $id)->pluck('cantidad')->map(fn ($qty) => (float) $qty)->all();
}

it('registers each invoice of an envelope with only its own lines', function () {
    importEnvelope();

    expect(DB::connection('fuelcontrol')->table('gmail_dte_documents')->orderBy('folio')->pluck('folio')->all())->toBe(['7100539', '7100545'])
        ->and(envelopeLines('7100539'))->toBe([15.0])
        ->and(envelopeLines('7100545'))->toBe([13.0])
        ->and(DB::connection('fuelcontrol')->table('gmail_dte_documents')->where('folio', '7100539')->value('monto_neto'))->toEqual(40210);
});

it('does not register again an invoice that came in from Odoo with the RUT unformatted', function () {
    DB::connection('fuelcontrol')->table('gmail_dte_documents')->insert([
        'xml_filename' => 'odoo', 'hash_unico' => 'odoo_14494', 'tipo_dte' => 33, 'folio' => '7100545',
        'proveedor_rut' => '789240302', 'proveedor_nombre' => 'Implementos S.A.', 'fecha_factura' => '2026-10-28',
        'monto_neto' => 34849, 'monto_iva' => 6621, 'monto_total' => 41470, 'created_at' => now(), 'updated_at' => now(),
    ]);

    importEnvelope();

    expect(DB::connection('fuelcontrol')->table('gmail_dte_documents')->where('folio', '7100545')->count())->toBe(1)
        ->and(envelopeLines('7100539'))->toBe([15.0]);
});
