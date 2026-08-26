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
        'source_row',
    ];

    protected $casts = [
        'fecha_registro' => 'date',
        'hora_registro' => 'string',
        'numero_bandejas_palet' => 'integer',
        'vuelta' => 'integer',
    ];

    /**
     * Filtros compartidos entre el listado y la exportación a Excel,
     * para que el Excel siempre traiga exactamente lo que se ve en pantalla.
     *
     * Acepta: q, campo, cuartel, especie, desde, hasta, season.
     */
    public function scopeFiltrado($query, array $f)
    {
        $campo = trim((string) ($f['campo'] ?? ''));
        $cuartel = trim((string) ($f['cuartel'] ?? ''));
        $especie = trim((string) ($f['especie'] ?? ''));
        $desde = trim((string) ($f['desde'] ?? ''));
        $hasta = trim((string) ($f['hasta'] ?? ''));
        $season = trim((string) ($f['season'] ?? ''));
        $q = trim((string) ($f['q'] ?? ''));

        if ($campo !== '') {
            $query->where('nombre_campo', $campo);
        }
        if ($cuartel !== '') {
            $query->where('cuartel', $cuartel);
        }
        if ($especie !== '') {
            $query->where('especie', $especie);
        }

        if ($desde !== '') {
            $query->whereDate('fecha_registro', '>=', $desde);
        }
        if ($hasta !== '') {
            $query->whereDate('fecha_registro', '<=', $hasta);
        }

        // Temporada de cosecha: junio a mayo, sobre la fecha de cosecha
        if ($season !== '' && str_contains($season, '/')) {
            [$startYear, $endYear] = explode('/', $season);
            $query->whereBetween('fecha_registro', [
                "{$startYear}-06-01",
                "{$endYear}-05-31",
            ]);
        }

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('codigo_bin', 'like', "%{$q}%")
                    ->orWhere('nombre_cosecha', 'like', "%{$q}%")
                    ->orWhere('nombre_campo', 'like', "%{$q}%")
                    ->orWhere('cuartel', 'like', "%{$q}%")
                    ->orWhere('especie', 'like', "%{$q}%")
                    ->orWhere('variedad', 'like', "%{$q}%")
                    ->orWhere('usuario', 'like', "%{$q}%")
                    ->orWhere('id_usuario', 'like', "%{$q}%")
                    ->orWhere('patente_camion', 'like', "%{$q}%")
                    ->orWhere('nombre_chofer', 'like', "%{$q}%");
            });
        }

        return $query;
    }

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
        if ($t === '') {
            return null;
        }

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
