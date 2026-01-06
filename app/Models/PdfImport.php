<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PdfImport extends Model
{
    protected $fillable = [
        'original_name',
        'stored_path',
        'template',
        'guia_no',
        'doc_fecha',
        'productor',
        'meta',
    ];

    protected $casts = [
        'doc_fecha' => 'date',
        'meta' => 'array',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(PdfLine::class);
    }

}
