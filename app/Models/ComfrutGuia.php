<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComfrutGuia extends Model
{
    protected $fillable = [
        'guia_numero',
        'fecha_guia',
        'tipo_dte',
        'productor',
        'rut_productor',
        'patente',
        'cantidad_total',
        'monto_total',
        'xml_path',
        'xml_hash',
        'meta',
    ];

    protected $casts = [
        'fecha_guia' => 'date',
        'meta' => 'array',
    ];

    public function detalles()
    {
        return $this->hasMany(ComfrutGuiaDetalle::class, 'comfrut_guia_id');
    }
}

