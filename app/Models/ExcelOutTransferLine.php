<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExcelOutTransferLine extends Model
{
    protected $fillable = [
        'excel_out_transfer_id',
        'producto',
        'cantidad',
        'source_file',
        'excel_row',
        'raw',
    ];

    protected $casts = [
        'raw' => 'array',
    ];
}
