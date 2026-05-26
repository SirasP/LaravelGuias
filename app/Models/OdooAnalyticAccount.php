<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OdooAnalyticAccount extends Model
{
    protected $fillable = [
        'odoo_id',
        'name',
        'code',
        'plan_odoo_id',
        'plan_name',
        'plan_complete_name',
        'color',
    ];

    public function plan()
    {
        return $this->belongsTo(OdooAnalyticPlan::class, 'plan_odoo_id', 'odoo_id');
    }
}
