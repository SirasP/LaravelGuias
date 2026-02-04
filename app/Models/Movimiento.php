<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Movimiento extends Model
{
    protected $table = 'movimientos';

    public $timestamps = false;

    protected $fillable = [
        'producto_id',
        'vehiculo_id',
        'cantidad',
        'tipo',
        'usuario',
        'fecha_movimiento',
        'hash_unico',
    ];
}
