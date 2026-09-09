<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_request_id',
        'sort_order',
        'product_service',
        'specification',
        'quantity',
        'unit',
        'unit_price',
        'quantity_note',
        'destination',
        // En qué orden de Odoo quedó esta partida. No llega de ningún
        // formulario: lo escribe la exportación, y `replaceItems` recorta lo
        // que viene del usuario a una lista explícita que no los incluye.
        'odoo_order_id',
        'odoo_line_id',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'quantity' => 'decimal:6',
            'unit_price' => 'decimal:2',
            'odoo_order_id' => 'integer',
            'odoo_line_id' => 'integer',
        ];
    }

    /**
     * ¿Esta partida ya se compró, o sigue esperando proveedor?
     *
     * Una solicitud puede repartirse entre dos proveedores: unas partidas se
     * van en la orden de uno, otras esperan a que llegue la cotización del
     * otro. Antes esto lo decía la solicitud entera y no había dónde anotar
     * media compra.
     */
    public function estaEnOdoo(): bool
    {
        return $this->odoo_order_id !== null;
    }

    /**
     * Lo que cuesta esta partida.
     *
     * Se calcula, no se guarda: un total almacenado se queda desfasado en
     * cuanto alguien corrige la cantidad y nadie se entera.
     */
    public function lineTotal(): ?float
    {
        if (blank($this->unit_price)) {
            return null;
        }

        return round((float) $this->quantity * (float) $this->unit_price, 2);
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }
}
