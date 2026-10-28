<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DtePurchaseReference extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array', 'evidence' => 'array', 'revoked_at' => 'datetime',
            'invoice_quantity' => 'decimal:6', 'request_quantity' => 'decimal:6',
        ];
    }

    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestItem::class, 'purchase_request_item_id');
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }
}
