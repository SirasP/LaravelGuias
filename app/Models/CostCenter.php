<?php

namespace App\Models;

use App\Models\Concerns\IsCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CostCenter extends Model
{
    use HasFactory, IsCatalog;

    protected $table = 'cost_centers';

    protected $fillable = ['company_code', 'name', 'slug', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
