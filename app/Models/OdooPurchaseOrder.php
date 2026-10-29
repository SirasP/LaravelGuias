<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una orden de compra que alguien hizo directo en Odoo, sin solicitud.
 *
 * La copia `odoo:sync-purchase-orders` cada noche. Sólo lectura: lo que se
 * corrija, se corrige en Odoo y llega en la sincronización siguiente.
 */
class OdooPurchaseOrder extends Model
{
    /** Odoo llama «presupuesto» a la orden hasta que se confirma. */
    public const QUOTATION_STATES = ['draft', 'sent', 'to approve'];

    public const CONFIRMED_STATES = ['purchase', 'done'];

    public const STATE_LABELS = [
        'draft' => 'Cotización', 'sent' => 'Cotización enviada', 'to approve' => 'Por aprobar',
        'purchase' => 'Orden de compra', 'done' => 'Bloqueada', 'cancel' => 'Cancelada',
    ];

    protected $fillable = [
        'odoo_id', 'name', 'state', 'partner_odoo_id', 'partner_name', 'partner_vat', 'partner_ref', 'origin', 'purchase_group_id', 'currency',
        'amount_untaxed', 'amount_total', 'created_by_name', 'buyer_name', 'date_order',
        'invoice_status', 'receipt_status', 'notes', 'odoo_created_at', 'odoo_updated_at', 'synced_at',
    ];

    protected $casts = [
        'odoo_id' => 'integer',
        'partner_odoo_id' => 'integer',
        'purchase_group_id' => 'integer',
        'amount_untaxed' => 'float',
        'amount_total' => 'float',
        'date_order' => 'datetime',
        'odoo_created_at' => 'datetime',
        'odoo_updated_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(OdooPurchaseOrderLine::class)->orderBy('sequence')->orderBy('id');
    }

    public function scopeQuotations(Builder $query): Builder
    {
        return $query->whereIn('state', self::QUOTATION_STATES);
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->whereIn('state', self::CONFIRMED_STATES);
    }

    /** Cotizaciones que siguen pendientes: ni su grupo ya se compró a otro proveedor. */
    public function scopePendingQuotations(Builder $query): Builder
    {
        return $query->quotations()->where(fn (Builder $q) => $q->whereNull('purchase_group_id')
            ->orWhereNotIn('purchase_group_id', self::boughtGroups()));
    }

    /**
     * Cotizaciones que quedaron abiertas en Odoo aunque la compra ya se hizo:
     * BIOLIFE FOLIAR se compró a Florin y las de Copeval y la Cooperativa
     * siguen «enviadas». Conviene cancelarlas allá.
     */
    public function scopeLeftOpen(Builder $query): Builder
    {
        return $query->quotations()->whereIn('purchase_group_id', self::boughtGroups());
    }

    /** Los grupos donde alguna orden ya se confirmó. */
    public static function boughtGroups(): \Illuminate\Database\Query\Builder
    {
        return \Illuminate\Support\Facades\DB::table('odoo_purchase_orders')->whereIn('state', self::CONFIRMED_STATES)
            ->whereNotNull('purchase_group_id')->select('purchase_group_id');
    }

    public function stateLabel(): string
    {
        return self::STATE_LABELS[$this->state] ?? $this->state;
    }

    public function isQuotation(): bool
    {
        return in_array($this->state, self::QUOTATION_STATES, true);
    }

    /** Hecha en la última semana: lo que nadie avisó todavía. */
    public function isRecent(): bool
    {
        return $this->odoo_created_at?->gt(now()->subWeek()) ?? false;
    }

    public function odooUrl(): ?string
    {
        $url = rtrim((string) config('purchase_requests.odoo.url'), '/');

        return $url === '' ? null : $url.'/odoo/purchase/'.$this->odoo_id;
    }
}
