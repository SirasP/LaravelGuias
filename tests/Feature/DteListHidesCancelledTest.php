<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('hides from the invoice list the copy that Odoo cancelled', function () {
    $base = ['xml_filename' => 'FAC 007676', 'tipo_dte' => 33, 'folio' => '7676', 'proveedor_rut' => '766263747', 'fecha_factura' => '2026-03-02',
        'monto_neto' => 72508, 'monto_iva' => 13777, 'inventory_status' => 'pendiente', 'created_at' => now(), 'updated_at' => now()];
    DB::connection('fuelcontrol')->table('gmail_dte_documents')->insert([
        $base + ['hash_unico' => 'odoo_11828', 'proveedor_nombre' => 'Riego Bueno cancelada', 'monto_total' => 0, 'payment_status' => 'sin_pagar', 'workflow_status' => 'cancelado'],
        $base + ['hash_unico' => 'odoo_11829', 'proveedor_nombre' => 'Riego Bueno buena', 'monto_total' => 86285, 'payment_status' => 'pagado', 'workflow_status' => 'aceptado'],
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('gmail.dtes.facturas.list'))
        ->assertOk()
        ->assertSee('Riego Bueno buena')
        ->assertDontSee('Riego Bueno cancelada');
});
