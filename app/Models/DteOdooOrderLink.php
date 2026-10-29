<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Una factura que pagó (toda o en parte) una orden hecha directo en Odoo. */
class DteOdooOrderLink extends Model
{
    public const SOURCES = ['reference' => 'Referencia del XML', 'auto' => 'Automática', 'manual' => 'Manual'];

    protected $fillable = [
        'document_id', 'odoo_order_id', 'order_name', 'amount', 'source', 'source_key', 'note',
        'actor_id', 'actor_name', 'revoked_at', 'revoked_by', 'revocation_note',
    ];

    protected $casts = [
        'document_id' => 'integer',
        'odoo_order_id' => 'integer',
        'amount' => 'float',
        'revoked_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }
}
