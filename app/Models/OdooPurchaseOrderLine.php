<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OdooPurchaseOrderLine extends Model
{
    protected $fillable = [
        'odoo_purchase_order_id', 'odoo_id', 'sequence', 'display_type', 'product_odoo_id', 'name',
        'product_qty', 'uom_name', 'price_unit', 'discount', 'price_subtotal', 'qty_received', 'qty_invoiced',
    ];

    protected $casts = [
        'odoo_id' => 'integer',
        'product_odoo_id' => 'integer',
        'product_qty' => 'float',
        'price_unit' => 'float',
        'discount' => 'float',
        'price_subtotal' => 'float',
        'qty_received' => 'float',
        'qty_invoiced' => 'float',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(OdooPurchaseOrder::class, 'odoo_purchase_order_id');
    }

    /** Las secciones y notas de Odoo son títulos dentro de la orden, no productos. */
    public function isNote(): bool
    {
        return in_array($this->display_type, ['line_section', 'line_note'], true);
    }
}
