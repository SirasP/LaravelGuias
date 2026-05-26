<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OdooAccountMove extends Model
{
    protected $fillable = [
        'odoo_id',
        'name',
        'ref',
        'move_type',
        'state',
        'partner_odoo_id',
        'partner_name',
        'invoice_date',
        'amount_total',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'amount_total' => 'float',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(OdooAccountMoveLine::class, 'move_odoo_id', 'odoo_id');
    }

    public function getStateLabel(): string
    {
        return match ($this->state) {
            'posted' => 'Confirmado',
            'draft'  => 'Borrador',
            'cancel' => 'Cancelado',
            default  => $this->state ?? '—',
        };
    }
}
