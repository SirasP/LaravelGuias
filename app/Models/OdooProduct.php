<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un producto del catálogo de Odoo, copiado aquí para poder buscarlo.
 *
 * Es una copia de lectura: Odoo manda. Nada de lo que se escriba en esta
 * tabla vuelve allá.
 */
class OdooProduct extends Model
{
    protected $fillable = [
        'odoo_id', 'name', 'default_code', 'barcode',
        'uom_id', 'uom_name', 'type', 'is_storable', 'purchase_ok',
        'active_in_odoo', 'missing_since', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'odoo_id' => 'integer',
            'uom_id' => 'integer',
            'is_storable' => 'boolean',
            'purchase_ok' => 'boolean',
            'active_in_odoo' => 'boolean',
            'missing_since' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    /** Los que hoy siguen existiendo en Odoo y se pueden comprar. */
    public function scopeUsable($query)
    {
        return $query->whereNull('missing_since')
            ->where('active_in_odoo', true)
            ->where('purchase_ok', true);
    }

    /** ¿Sigue estando en Odoo? Si no, un alias que lo apunte ya no sirve. */
    public function stillInOdoo(): bool
    {
        return $this->missing_since === null && $this->active_in_odoo;
    }
}
