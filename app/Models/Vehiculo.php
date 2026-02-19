<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehiculo extends Model
{
    protected $table = 'vehiculos';

    public $timestamps = false;

    protected $fillable = [
        'patente',
        'descripcion',
        'tipo',
        'is_active',
        'fecha_registro',
        'usuario',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
