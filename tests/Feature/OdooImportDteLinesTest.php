<?php

use App\Models\OdooAccountMove;
use App\Models\OdooAccountMoveLine;
use Illuminate\Support\Facades\DB;

function odooInvoice(int $odooId = 14426, float $total = 164800, array $products = []): OdooAccountMove
{
    $move = OdooAccountMove::create([
        'odoo_id' => $odooId, 'name' => 'FAC 003914', 'folio' => 3914, 'move_type' => 'in_invoice', 'state' => 'posted',
        'payment_state' => 'paid', 'partner_name' => 'ELECTROSOL ENCENDIDOS SPA', 'partner_vat' => '77118278K',
        'invoice_date' => '2026-10-19', 'amount_total' => $total,
    ]);
    $id = $odooId * 10;
    foreach ($products ?: [
        ['ALARMA RETROCESO INDUSTRIAL', 16, 7563, 121008],
        ['[6510] HUINCHA DE TELA NEGRA 3/4X10MTS', 4, 4369.75, 17479],
    ] as $i => [$name, $qty, $price, $subtotal]) {
        OdooAccountMoveLine::create([
            'odoo_id' => ++$id, 'move_odoo_id' => $odooId, 'name' => $name, 'display_type' => 'product', 'quantity' => $qty,
            'uom_name' => 'Units', 'price_unit' => $price, 'discount' => 0, 'price_subtotal' => $subtotal,
            'debit' => $subtotal, 'credit' => 0, 'taxes' => [$i === 1 ? '19% VAT' : 'IVA 19% Compra'],
        ]);
    }
    OdooAccountMoveLine::create(['odoo_id' => ++$id, 'move_odoo_id' => $odooId, 'name' => 'IVA 19% Compra', 'display_type' => 'tax', 'quantity' => 0, 'debit' => 26313, 'credit' => 0]);
    OdooAccountMoveLine::create(['odoo_id' => ++$id, 'move_odoo_id' => $odooId, 'name' => null, 'display_type' => 'payment_term', 'quantity' => 0, 'debit' => 0, 'credit' => $total]);

    return $move;
}

it('builds the detail of an invoice typed in Odoo, with exact net and VAT', function () {
    odooInvoice();
    $this->artisan('odoo:import-dte')->assertSuccessful();
    $document = DB::connection('fuelcontrol')->table('gmail_dte_documents')->where('hash_unico', 'odoo_14426')->sole();
    $lines = DB::connection('fuelcontrol')->table('gmail_dte_document_lines')->where('document_id', $document->id)->orderBy('nro_linea')->get();
    expect((float) $document->monto_neto)->toBe(138487.0)
        ->and((float) $document->monto_iva)->toBe(26313.0)
        ->and($lines)->toHaveCount(2)
        ->and($lines[0]->descripcion)->toBe('ALARMA RETROCESO INDUSTRIAL')
        ->and((float) $lines[0]->cantidad)->toBe(16.0)
        ->and($lines[0]->unidad)->toBe('UN')
        ->and($lines[0]->impuesto_label)->toBe('IVA 19%')
        ->and($lines[1]->impuesto_label)->toBe('IVA 19%')
        ->and($lines[1]->codigo)->toBe('6510')
        ->and($lines[1]->descripcion)->toBe('HUINCHA DE TELA NEGRA 3/4X10MTS');

    // A second night does not duplicate anything.
    $this->artisan('odoo:import-dte')->assertSuccessful();
    expect(DB::connection('fuelcontrol')->table('gmail_dte_document_lines')->count())->toBe(2);
});

it('fills invoices imported before the detail was synced', function () {
    odooInvoice();
    OdooAccountMoveLine::query()->update(['quantity' => null]);
    $this->artisan('odoo:import-dte')->assertSuccessful();
    expect(DB::connection('fuelcontrol')->table('gmail_dte_document_lines')->count())->toBe(0);
    OdooAccountMoveLine::where('display_type', 'product')->get()->each(fn ($line) => $line->update(['quantity' => 1]));
    $this->artisan('odoo:import-dte')->assertSuccessful();
    expect(DB::connection('fuelcontrol')->table('gmail_dte_document_lines')->count())->toBe(2);
});

it('leaves the header alone when the Odoo detail does not add up to the total', function () {
    odooInvoice(total: 200000);
    $this->artisan('odoo:import-dte')->assertSuccessful();
    $document = DB::connection('fuelcontrol')->table('gmail_dte_documents')->sole();
    expect(DB::connection('fuelcontrol')->table('gmail_dte_document_lines')->count())->toBe(0)
        ->and((float) $document->monto_neto)->toBe(round(200000 / 1.19));
});

it('never touches an invoice that arrived with its XML', function () {
    $id = DB::connection('fuelcontrol')->table('gmail_dte_documents')->insertGetId([
        'xml_filename' => 'DTE_33_3914.xml', 'xml_raw' => '<DTE/>', 'hash_unico' => 'gmail', 'tipo_dte' => 33, 'folio' => '3914',
        'proveedor_rut' => '77118278-K', 'proveedor_nombre' => 'ELECTROSOL', 'fecha_factura' => '2026-10-19',
        'monto_neto' => 1, 'monto_iva' => 1, 'monto_total' => 164800, 'created_at' => now(), 'updated_at' => now(),
    ]);
    odooInvoice();
    $this->artisan('odoo:import-dte')->assertSuccessful();
    expect(DB::connection('fuelcontrol')->table('gmail_dte_documents')->count())->toBe(1)
        ->and(DB::connection('fuelcontrol')->table('gmail_dte_document_lines')->count())->toBe(0)
        ->and((float) DB::connection('fuelcontrol')->table('gmail_dte_documents')->find($id)->monto_neto)->toBe(1.0);
});

it('subtracts the withholding of a fee receipt instead of adding it as VAT', function () {
    $move = odooInvoice(odooId: 500, total: 700000, products: [['Servicio de asesoría', 1, 825959, 825959]]);
    OdooAccountMoveLine::where('move_odoo_id', 500)->where('display_type', 'tax')->update(['name' => '15.25% Ret. 2da 2026', 'debit' => 0, 'credit' => 125959]);
    OdooAccountMoveLine::where('move_odoo_id', 500)->where('display_type', 'product')->update(['taxes' => ['15.25% Ret. 2da 2026']]);
    $this->artisan('odoo:import-dte')->assertSuccessful();
    $document = DB::connection('fuelcontrol')->table('gmail_dte_documents')->sole();
    $line = DB::connection('fuelcontrol')->table('gmail_dte_document_lines')->sole();
    expect((float) $document->monto_neto)->toBe(825959.0)
        ->and((float) $document->monto_iva)->toBe(0.0)
        ->and($line->impuesto_label)->toBe('15.25% Ret. 2da 2026')
        ->and($move->odoo_id)->toBe(500);
});
