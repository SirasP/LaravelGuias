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
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'quantity' => 'decimal:6',
            'unit_price' => 'decimal:2',
        ];
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
