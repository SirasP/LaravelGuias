<?php

namespace App\Models;

use App\Support\Rut;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Un proveedor, identificado por su RUT.
 *
 * El RUT es la clave: el nombre puede venir escrito de mil maneras, o no venir
 * en absoluto cuando vive dentro de un logo.
 */
class PurchaseSupplier extends Model
{
    use HasFactory;

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_DOCUMENT = 'documento';

    protected $fillable = [
        'company_code', 'tax_id', 'name', 'trade_name',
        'email', 'phone', 'odoo_partner_id', 'source', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'odoo_partner_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $supplier): void {
            $supplier->company_code ??= 'EHE';
            // Siempre en formato canónico: así el mismo RUT escrito con o sin
            // puntos no crea dos proveedores.
            $supplier->tax_id = Rut::normalize($supplier->tax_id) ?? $supplier->tax_id;
        });
    }

    public function scopeForCompany($query, string $companyCode = 'EHE')
    {
        return $query->where('company_code', $companyCode);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Nombre para mostrar: el de fantasía si existe, si no la razón social. */
    public function displayName(): ?string
    {
        return $this->trade_name ?: $this->name;
    }

    public function formattedTaxId(): string
    {
        return Rut::format($this->tax_id) ?? $this->tax_id;
    }

    /** ¿Falta ponerle nombre? Entró por un documento y nadie lo completó. */
    public function needsName(): bool
    {
        return blank($this->name) && blank($this->trade_name);
    }

    /** Etiqueta completa para la solicitud: «RODASERVIC SPA (RUT 77.045.469-7)». */
    public function label(): string
    {
        $nombre = $this->displayName();

        return $nombre === null
            ? 'RUT '.$this->formattedTaxId()
            : $nombre.' (RUT '.$this->formattedTaxId().')';
    }
}
