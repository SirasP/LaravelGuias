<?php

namespace App\Models;

use App\Models\Concerns\IsCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnitOfMeasure extends Model
{
    use HasFactory, IsCatalog;

    protected $table = 'units_of_measure';

    protected $fillable = ['company_code', 'code', 'name',
        'odoo_uom_id', 'slug', 'allows_decimals', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'allows_decimals' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
