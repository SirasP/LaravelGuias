<?php

namespace App\Models;

// app/Models/Camion.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Camion extends Model
{
    protected $table = 'camiones';
    protected $primaryKey = 'patente_norm';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'patente_norm',
        'patente_original',
        'alias',
        'activo',
        'observaciones',
    ];
}
