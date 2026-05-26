<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OdooAnalyticPlan extends Model
{
    protected $fillable = [
        'odoo_id',
        'name',
        'complete_name',
        'parent_odoo_id',
        'parent_name',
        'color',
        'default_applicability',
    ];

    public function accounts()
    {
        return $this->hasMany(OdooAnalyticAccount::class, 'plan_odoo_id', 'odoo_id');
    }
}
