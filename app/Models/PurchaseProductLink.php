<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * «Cuando RODASERVIC escribe esto, se refiere a este producto.»
 *
 * Lo que guarda no es una coincidencia calculada, sino una decisión humana.
 * Por eso se consulta como un diccionario —o está o no está— y no admite
 * grados: el parecido sirve para proponer, nunca para concluir.
 */
class PurchaseProductLink extends Model
{
    /** Lo dijo una persona mirando la pantalla. */
    public const CONFIRMADO = 'confirmed';

    /** Heredado del diccionario del módulo de facturas. Sin proveedor. */
    public const HEREDADO = 'inherited';

    protected $fillable = [
        'company_code', 'odoo_partner_id', 'partner_name',
        'source_text', 'normalized_text',
        'odoo_product_id', 'odoo_product_name', 'fuelcontrol_product_id',
        'source', 'confirmed_by', 'confirmed_by_name', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'odoo_partner_id' => 'integer',
            'odoo_product_id' => 'integer',
            'fuelcontrol_product_id' => 'integer',
            'confirmed_by' => 'integer',
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * Busca la equivalencia de un texto, prefiriendo la de su proveedor.
     *
     * El orden importa: «jabón mano» de Unimarc puede no ser lo mismo que
     * «jabón mano» de otro proveedor. Sólo si no hay una específica se usa la
     * general, que vale menos porque nadie afirmó de quién venía.
     */
    public static function para(string $texto, ?int $odooPartnerId): ?self
    {
        $normalizado = self::normalizar($texto);

        if ($normalizado === '') {
            return null;
        }

        $base = static::query()
            ->where('company_code', 'EHE')
            ->where('normalized_text', $normalizado);

        if ($odooPartnerId !== null) {
            $delProveedor = (clone $base)->where('odoo_partner_id', $odooPartnerId)->first();

            if ($delProveedor !== null) {
                return $delProveedor;
            }
        }

        return (clone $base)->whereNull('odoo_partner_id')->first();
    }

    /**
     * El texto reducido a lo que lo hace comparable.
     *
     * Misma idea que `normalizeForSimilarity` del módulo de facturas: sin
     * tildes, sin mayúsculas, sin puntuación y sin espacios de más. Deliberada
     * copia y no préstamo: aquel módulo escribe en fuelcontrol y no se toca.
     */
    public static function normalizar(string $texto): string
    {
        $limpio = \Illuminate\Support\Str::of($texto)->ascii()->lower()->value();
        $limpio = preg_replace('/[^a-z0-9]+/', ' ', $limpio) ?? '';

        return trim(preg_replace('/\s+/', ' ', $limpio) ?? '');
    }

    /** ¿Apunta a un producto que Odoo todavía tiene? */
    public function productoVigente(): bool
    {
        if ($this->odoo_product_id === null) {
            return false;
        }

        return OdooProduct::query()
            ->where('odoo_id', $this->odoo_product_id)
            ->usable()
            ->exists();
    }
}
