<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComfrutGuiaDetalle extends Model
{
    protected $fillable = [
        'linea',
        'codigo_tipo',
        'codigo_valor',
        'nombre_item',
        'cantidad',
        'unidad',
        'precio',
        'monto',
        'rut_chofer',      // <-- agregado
        'nombre_chofer',   // <-- agregado
    ];

    public function guia()
    {
        return $this->belongsTo(ComfrutGuia::class, 'comfrut_guia_id');
    }
}

