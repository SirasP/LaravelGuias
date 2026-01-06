<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdfLine extends Model
{
    protected $fillable = [
        'pdf_import_id',
        'line_no',
        'content',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(PdfImport::class, 'pdf_import_id');
    }
}
