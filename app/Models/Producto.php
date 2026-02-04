<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'productos';

    public $timestamps = false; // 🔥 CLAVE

    protected $fillable = [
        'nombre',
        'cantidad',
        'fecha_registro',
        'usuario',
    ];
}
