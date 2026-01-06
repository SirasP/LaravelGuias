<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgrakOdooMatch extends Model
{
    protected $fillable = [
        'agrak_fecha',
        'agrak_patente',
        'agrak_hora_inicio',
        'agrak_hora_fin',
        'agrak_chofer',
        'excel_out_transfer_id',
        'score',
        'estado',
    ];

    public function transfer()
    {
        return $this->belongsTo(ExcelOutTransfer::class, 'excel_out_transfer_id');
    }
}
