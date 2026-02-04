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
        'fecha_registro',
        'usuario',
    ];
}
