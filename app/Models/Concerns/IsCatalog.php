<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Comportamiento común de los catálogos del módulo de solicitudes.
 *
 * El slug se deriva del nombre y es la clave real de unicidad, de modo que
 * "Administración", "ADMINISTRACION" y "Administracion" no puedan convivir
 * como tres áreas distintas.
 */
trait IsCatalog
{
    protected static function bootIsCatalog(): void
    {
        static::saving(function (self $model): void {
            $model->slug = static::slugFor((string) $model->name);
            $model->company_code ??= 'EHE';
        });
    }

    public static function slugFor(string $name): string
    {
        return Str::slug(Str::ascii(trim($name)));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForCompany(Builder $query, string $companyCode = 'EHE'): Builder
    {
        return $query->where('company_code', $companyCode);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
