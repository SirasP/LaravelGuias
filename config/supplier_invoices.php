<?php

/*
|--------------------------------------------------------------------------
| Facturas de proveedor subidas a mano
|--------------------------------------------------------------------------
|
| Un PDF o una foto de una factura que no llegó a Odoo por el SII. La lee la
| IA local de `purchase_requests.reader` y, revisada, se crea en Odoo como
| borrador. Nunca se confirma desde aquí: eso lo hace Contabilidad en Odoo.
|
*/

return [

    /*
     * Variables propias, con las compartidas como respaldo, igual que en
     * Solicitudes de Compra: así esta pantalla se puede apuntar a un Odoo de
     * pruebas sin mover a dónde sincronizan OdooSyncMoves y compañía.
     */
    'odoo' => [
        'enabled' => (bool) env('SUPPLIER_INVOICES_ODOO', true),
        'url' => env('SUPPLIER_INVOICES_ODOO_URL', env('ODOO_URL')),
        'db' => env('SUPPLIER_INVOICES_ODOO_DB', env('ODOO_DB')),
        'user' => env('SUPPLIER_INVOICES_ODOO_USER', env('ODOO_USER')),
        'password' => env('SUPPLIER_INVOICES_ODOO_PASSWORD', env('ODOO_PASSWORD')),
        'timeout' => (int) env('SUPPLIER_INVOICES_ODOO_TIMEOUT', 30),
    ],

    // Diferencia en pesos que se acepta entre lo que suman las líneas y el
    // neto: cantidad × precio unitario con decimales redondea distinto.
    'tolerancia' => (int) env('SUPPLIER_INVOICES_TOLERANCE', 5),
];
