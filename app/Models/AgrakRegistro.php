<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgrakRegistro extends Model
{
    protected $table = 'agrak_registros';

    protected $fillable = [
        'codigo_bin',
        'nombre_cosecha',
        'nombre_campo',
        'ceco_campo',
        'etiquetas_campo',
        'cuartel',
        'ceco_cuartel',
        'etiquetas_cuartel',
        'especie',
        'variedad',
        'fecha_registro',
        'hora_registro',
        'coordenadas',
        'usuario',
        'id_usuario',
        'cuadrilla',
        'numero_bandejas_palet',
        'maquina',
        'nombre_chofer',
        'chofer_norm',
        'patente_camion',
        'patente_norm',
        'exportadora_1',
        'exportadora_2',
        'exportadora_norm',
        'estado_norm',
        'vuelta',
        'observacion',
        'numero_sello_1',
        'numero_sello_2',
        'source_file',
        'source_row'
    ];


    protected $casts = [
        'fecha_registro' => 'date',
        'hora_registro' => 'string',
        'numero_bandejas_palet' => 'integer',
        'vuelta' => 'integer',
    ];

    // 🔥 AQUÍ VA LA MAGIA
    protected static function booted()
    {
        static::saving(function (self $model) {
            // normaliza patente
            $model->patente_norm = self::normalizePatente($model->patente_camion);

            // (opcional) normaliza chofer / exportadora si quieres
            $model->chofer_norm = self::normalizeText($model->nombre_chofer);
            $model->exportadora_norm = self::normalizeText(
                $model->exportadora_1 ?: $model->exportadora_2
            );
        });
    }

    // =========================
    // Helpers de normalización
    // =========================

    public static function normalizePatente(?string $patente): ?string
    {
        $p = strtoupper(trim((string) $patente));

        // elimina espacios, guiones, puntos, etc.
        $p = preg_replace('/[^A-Z0-9]/', '', $p);

        // largo mínimo razonable
        return strlen($p) >= 5 ? $p : null;
    }

    public static function normalizeText(?string $text): ?string
    {
        $t = trim((string) $text);
        if ($t === '')
            return null;

        $t = mb_strtoupper($t);
        $t = preg_replace('/\s+/u', ' ', $t);

        return $t;
    }

    public function camion()
    {
        return $this->belongsTo(
            Camion::class,
            'patente_norm',
            'patente_norm'
        );
    }
}
