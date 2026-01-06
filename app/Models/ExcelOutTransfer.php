<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExcelOutTransfer extends Model
{
    protected $fillable = [
        'source_file',
        'excel_row',
        'contacto',
        'fecha_prevista',
        'patente',
        'chofer', // 👈
        'import_key',
        'guia_entrega',
        'prioridad',
        'referencia',
        'ubicacion_origen',
        'ubicacion_destino',
        'fecha_traslado',
        'documento_origen',
        'estado',
        'archivo_dte',
        'raw',
    ];

    protected $casts = [
        'fecha_prevista' => 'datetime',
        'fecha_traslado' => 'datetime',
        'raw' => 'array',
    ];
    public function lines()
    {
        return $this->hasMany(\App\Models\ExcelOutTransferLine::class);
    }
    /**
     * Alias para compatibilidad con exportExcelPhpSpreadsheet
     */
    public function comfrutDetalles()
    {
        return $this->lines();
    }
}
