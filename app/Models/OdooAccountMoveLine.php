<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OdooAccountMoveLine extends Model
{
    protected $fillable = [
        'odoo_id',
        'move_odoo_id',
        'account_odoo_id',
        'account_code',
        'account_name',
        'account_name_es',
        'name',
        'debit',
        'credit',
        'analytic_distribution',
        'taxes',
    ];

    protected $casts = [
        'debit' => 'float',
        'credit' => 'float',
        'analytic_distribution' => 'array',
        'taxes' => 'array',
    ];

    public function move(): BelongsTo
    {
        return $this->belongsTo(OdooAccountMove::class, 'move_odoo_id', 'odoo_id');
    }
}
