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
        'quantity_note',
        'destination',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'quantity' => 'decimal:6',
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }
}
