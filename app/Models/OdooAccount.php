<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OdooAccount extends Model
{
    protected $fillable = [
        'odoo_id',
        'code',
        'name',
        'name_es',
        'account_type',
        'reconcile',
    ];

    protected $casts = [
        'reconcile' => 'boolean',
    ];

    /** Etiqueta legible del tipo de cuenta */
    public function getAccountTypeLabelAttribute(): string
    {
        return match ($this->account_type) {
            'asset_receivable' => 'Activo · Cuentas por cobrar',
            'asset_cash' => 'Activo · Caja y bancos',
            'asset_current' => 'Activo · Corriente',
            'asset_non_current' => 'Activo · No corriente',
            'asset_prepayments' => 'Activo · Anticipos',
            'asset_fixed' => 'Activo · Fijo',
            'liability_payable' => 'Pasivo · Cuentas por pagar',
            'liability_credit_card' => 'Pasivo · Tarjeta de crédito',
            'liability_current' => 'Pasivo · Corriente',
            'liability_non_current' => 'Pasivo · No corriente',
            'equity' => 'Patrimonio',
            'equity_unaffected' => 'Patrimonio · No distribuido',
            'income' => 'Ingresos',
            'income_other' => 'Otros ingresos',
            'expense' => 'Gastos',
            'expense_depreciation' => 'Gastos · Depreciación',
            'expense_direct_cost' => 'Gastos · Costo directo',
            'off_balance' => 'Fuera de balance',
            default => $this->account_type ?? '—',
        };
    }
}
