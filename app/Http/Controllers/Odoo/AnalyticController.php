<?php

namespace App\Http\Controllers\Odoo;

use App\Http\Controllers\Controller;
use App\Models\OdooAccount;
use App\Models\OdooAnalyticAccount;
use App\Models\OdooAnalyticPlan;
use Illuminate\Http\Request;

class AnalyticController extends Controller
{
    public function index()
    {
        $plans = OdooAnalyticPlan::with('accounts')
            ->whereNull('parent_odoo_id')
            ->orderBy('name_es')
            ->get();

        $allPlans = OdooAnalyticPlan::orderBy('complete_name')->get();
        $totalPlans = OdooAnalyticPlan::count();
        $totalAccounts = OdooAnalyticAccount::count();
        $lastSync = OdooAnalyticAccount::max('updated_at');

        // Plan de cuentas contables agrupado por categoría
        $accountGroups = [
            'Activo' => ['asset_receivable', 'asset_cash', 'asset_current', 'asset_non_current', 'asset_prepayments', 'asset_fixed'],
            'Pasivo' => ['liability_payable', 'liability_credit_card', 'liability_current', 'liability_non_current'],
            'Patrimonio' => ['equity', 'equity_unaffected'],
            'Ingresos' => ['income', 'income_other'],
            'Gastos' => ['expense', 'expense_depreciation', 'expense_direct_cost'],
            'Fuera de balance' => ['off_balance'],
        ];

        $chartOfAccounts = [];
        foreach ($accountGroups as $groupLabel => $types) {
            $accounts = OdooAccount::whereIn('account_type', $types)
                ->orderBy('code')
                ->get();
            if ($accounts->isNotEmpty()) {
                $chartOfAccounts[$groupLabel] = $accounts;
            }
        }

        $totalChartAccounts = OdooAccount::count();

        return view('odoo.analytics.index', compact(
            'plans',
            'allPlans',
            'totalPlans',
            'totalAccounts',
            'lastSync',
            'chartOfAccounts',
            'totalChartAccounts',
        ));
    }

    public function storeAccount(Request $request)
    {
        $data = $request->validate([
            'name_es' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'plan_odoo_id' => 'required|integer|exists:odoo_analytic_plans,odoo_id',
        ]);

        $plan = OdooAnalyticPlan::where('odoo_id', $data['plan_odoo_id'])->firstOrFail();

        // ID local negativo para diferenciar de los de Odoo
        $minId = OdooAnalyticAccount::where('odoo_id', '<', 0)->min('odoo_id') ?? 0;

        OdooAnalyticAccount::create([
            'odoo_id' => $minId - 1,
            'name' => $data['name'],
            'name_es' => $data['name_es'],
            'code' => $data['code'] ?? null,
            'plan_odoo_id' => $plan->odoo_id,
            'plan_name' => $plan->name,
            'plan_complete_name' => $plan->complete_name,
            'color' => $plan->color,
        ]);

        return back()->with('success', 'Cuenta analítica creada correctamente.');
    }

    public function storePlan(Request $request)
    {
        $data = $request->validate([
            'name_es' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'parent_odoo_id' => 'nullable|integer|exists:odoo_analytic_plans,odoo_id',
        ]);

        $minId = OdooAnalyticPlan::where('odoo_id', '<', 0)->min('odoo_id') ?? 0;

        $parent = $data['parent_odoo_id']
            ? OdooAnalyticPlan::where('odoo_id', $data['parent_odoo_id'])->first()
            : null;

        OdooAnalyticPlan::create([
            'odoo_id' => $minId - 1,
            'name' => $data['name'],
            'name_es' => $data['name_es'],
            'complete_name' => $parent ? $parent->complete_name.' / '.$data['name'] : $data['name'],
            'parent_odoo_id' => $data['parent_odoo_id'] ?? null,
            'parent_name' => $parent?->name,
            'color' => $parent?->color ?? 0,
            'default_applicability' => 'optional',
        ]);

        return back()->with('success', 'Plan analítico creado correctamente.');
    }
}
