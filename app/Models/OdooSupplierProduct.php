<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Qué productos le compras a cada proveedor, según Odoo.
 *
 * Es la pieza que hace tratable el emparejado: RODASERVIC tiene ocho
 * productos en su historial, no dos mil trescientos. Buscar primero entre los
 * suyos cambia por completo la fiabilidad de una sugerencia.
 */
class OdooSupplierProduct extends Model
{
    protected $fillable = [
        'odoo_id', 'partner_id', 'partner_name',
        'product_id', 'product_tmpl_id', 'product_name', 'product_code',
        'price', 'missing_since', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'odoo_id' => 'integer',
            'partner_id' => 'integer',
            'product_id' => 'integer',
            'product_tmpl_id' => 'integer',
            'price' => 'decimal:2',
            'missing_since' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }
}
