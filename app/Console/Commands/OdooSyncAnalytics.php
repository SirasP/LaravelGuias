<?php

namespace App\Console\Commands;

use App\Models\OdooAccount;
use App\Models\OdooAnalyticAccount;
use App\Models\OdooAnalyticPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class OdooSyncAnalytics extends Command
{
    protected $signature   = 'odoo:sync-analytics';
    protected $description = 'Sincroniza planes y cuentas analíticas desde Odoo a MySQL local';

    private string $url;
    private string $db;
    private string $user;
    private string $password;
    private int $uid;

    public function handle(): int
    {
        $this->url      = rtrim(config('odoo.url'), '/');
        $this->db       = config('odoo.db');
        $this->user     = config('odoo.user');
        $this->password = config('odoo.password');

        $this->info('Conectando a Odoo...');

        $this->uid = $this->jsonRpc('common', 'login', [$this->db, $this->user, $this->password]);

        if (! $this->uid) {
            $this->error('Login a Odoo falló. Revisa ODOO_URL, ODOO_DB, ODOO_USER y ODOO_PASSWORD en .env');
            return self::FAILURE;
        }

        $this->info("✓ Login OK (UID: {$this->uid})");

        $this->syncPlans();
        $this->syncAccounts();
        $this->syncChartOfAccounts();

        $this->info('');
        $this->info('✅ Sincronización completada.');
        $this->table(
            ['Tabla', 'Registros'],
            [
                ['odoo_analytic_plans',    OdooAnalyticPlan::count()],
                ['odoo_analytic_accounts', OdooAnalyticAccount::count()],
                ['odoo_accounts',          OdooAccount::count()],
            ]
        );

        return self::SUCCESS;
    }

    private function syncPlans(): void
    {
        $this->info('Sincronizando planes analíticos...');

        $plans = $this->odooExecute('account.analytic.plan', 'search_read', [[]], [
            'fields' => ['id', 'name', 'complete_name', 'parent_id', 'color', 'default_applicability'],
            'limit'  => 200,
            'order'  => 'id asc',
        ]);

        $bar = $this->output->createProgressBar(count($plans));
        $bar->start();

        foreach ($plans as $plan) {
            OdooAnalyticPlan::updateOrCreate(
                ['odoo_id' => $plan['id']],
                [
                    'name'                  => $plan['name'],
                    'complete_name'         => $plan['complete_name'],
                    'parent_odoo_id'        => $plan['parent_id'] ? $plan['parent_id'][0] : null,
                    'parent_name'           => $plan['parent_id'] ? $plan['parent_id'][1] : null,
                    'color'                 => $plan['color'] ?? 0,
                    'default_applicability' => $plan['default_applicability'] ?? 'optional',
                ]
            );
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("  → {$bar->getMaxSteps()} planes sincronizados");
    }

    private function syncAccounts(): void
    {
        $this->info('Sincronizando cuentas analíticas...');

        // Cargar el complete_name de los planes para enriquecer las cuentas
        $plansMap = OdooAnalyticPlan::pluck('complete_name', 'odoo_id')->toArray();

        $accounts = $this->odooExecute('account.analytic.account', 'search_read',
            [[['active', '=', true]]],
            [
                'fields' => ['id', 'name', 'code', 'plan_id', 'color'],
                'limit'  => 500,
                'order'  => 'plan_id asc, name asc',
            ]
        );

        $bar = $this->output->createProgressBar(count($accounts));
        $bar->start();

        foreach ($accounts as $acc) {
            $planOdooId    = $acc['plan_id'] ? $acc['plan_id'][0] : null;
            $planName      = $acc['plan_id'] ? $acc['plan_id'][1] : null;
            $planComplete  = $planOdooId ? ($plansMap[$planOdooId] ?? $planName) : null;

            OdooAnalyticAccount::updateOrCreate(
                ['odoo_id' => $acc['id']],
                [
                    'name'               => $acc['name'],
                    'code'               => $acc['code'] ?: null,
                    'plan_odoo_id'       => $planOdooId,
                    'plan_name'          => $planName,
                    'plan_complete_name' => $planComplete,
                    'color'              => $acc['color'] ?? 0,
                ]
            );
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("  → {$bar->getMaxSteps()} cuentas sincronizadas");
    }

    private function syncChartOfAccounts(): void
    {
        $this->info('Sincronizando plan de cuentas contables...');

        $accounts = $this->odooExecute('account.account', 'search_read',
            [[['deprecated', '=', false]]],
            [
                'fields' => ['id', 'code', 'name', 'account_type', 'reconcile'],
                'limit'  => 500,
                'order'  => 'code asc',
            ]
        );

        $bar = $this->output->createProgressBar(count($accounts));
        $bar->start();

        foreach ($accounts as $acc) {
            OdooAccount::updateOrCreate(
                ['odoo_id' => $acc['id']],
                [
                    'code'         => $acc['code'],
                    'name'         => $acc['name'],
                    'account_type' => $acc['account_type'] ?? null,
                    'reconcile'    => $acc['reconcile'] ?? false,
                ]
            );
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("  → {$bar->getMaxSteps()} cuentas contables sincronizadas");
    }

    private function jsonRpc(string $service, string $method, array $args): mixed
    {
        $response = Http::timeout(20)->post("{$this->url}/jsonrpc", [
            'jsonrpc' => '2.0',
            'method'  => 'call',
            'params'  => compact('service', 'method', 'args'),
            'id'      => 1,
        ]);

        $data = $response->json();

        if (isset($data['error'])) {
            $msg = $data['error']['data']['message'] ?? $data['error']['message'] ?? 'Error Odoo';
            throw new \RuntimeException("Odoo RPC error: {$msg}");
        }

        return $data['result'];
    }

    private function odooExecute(string $model, string $method, array $domain, array $kwargs = []): array
    {
        return $this->jsonRpc('object', 'execute_kw', [
            $this->db,
            $this->uid,
            $this->password,
            $model,
            $method,
            $domain,
            $kwargs,
        ]);
    }
}
