<?php

namespace App\Console\Commands;

use App\Models\OdooAccount;
use App\Models\OdooAccountMove;
use App\Models\OdooAccountMoveLine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class OdooSyncMoves extends Command
{
    protected $signature   = 'odoo:sync-moves';
    protected $description = 'Sincroniza facturas de proveedor (account.move) y sus apuntes contables desde Odoo a MySQL local';

    private string $url;
    private string $db;
    private string $user;
    private string $password;
    private int    $uid;

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

        $this->syncMoves();
        $this->syncMoveLines();

        $this->info('');
        $this->info('✅ Sincronización completada.');
        $this->table(
            ['Tabla', 'Registros'],
            [
                ['odoo_account_moves',      OdooAccountMove::count()],
                ['odoo_account_move_lines', OdooAccountMoveLine::count()],
            ]
        );

        return self::SUCCESS;
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Sincronizar facturas de proveedor (account.move)
    // ──────────────────────────────────────────────────────────────────────────────
    private function syncMoves(): void
    {
        $this->info('Sincronizando facturas de proveedor...');

        $offset = 0;
        $limit  = 100;
        $total  = 0;

        do {
            $records = $this->odooExecute('account.move', 'search_read',
                [[['move_type', '=', 'in_invoice']]],
                [
                    'fields'  => ['id', 'name', 'ref', 'move_type', 'state', 'partner_id', 'invoice_date', 'amount_total'],
                    'limit'   => $limit,
                    'offset'  => $offset,
                    'order'   => 'id asc',
                ]
            );

            foreach ($records as $rec) {
                OdooAccountMove::updateOrCreate(
                    ['odoo_id' => $rec['id']],
                    [
                        'name'            => $rec['name'] ?? null,
                        'ref'             => $rec['ref']  ?: null,
                        'move_type'       => $rec['move_type'],
                        'state'           => $rec['state'],
                        'partner_odoo_id' => is_array($rec['partner_id']) ? $rec['partner_id'][0] : null,
                        'partner_name'    => is_array($rec['partner_id']) ? $rec['partner_id'][1] : null,
                        'invoice_date'    => $rec['invoice_date'] ?: null,
                        'amount_total'    => (float) ($rec['amount_total'] ?? 0),
                    ]
                );
            }

            $count   = count($records);
            $total  += $count;
            $offset += $limit;

            $this->line("  → {$total} facturas procesadas...");

        } while ($count === $limit);

        $this->info("  ✓ {$total} facturas de proveedor sincronizadas");
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Sincronizar líneas de apuntes contables (account.move.line)
    // ──────────────────────────────────────────────────────────────────────────────
    private function syncMoveLines(): void
    {
        $this->info('Sincronizando apuntes contables...');

        // Mapa de nombres en español para enriquecer las líneas
        $spanishNames = OdooAccount::whereNotNull('name_es')
            ->pluck('name_es', 'odoo_id')
            ->toArray();

        // IDs de los moves que ya tenemos localmente
        $moveIds = OdooAccountMove::pluck('odoo_id')->toArray();

        if (empty($moveIds)) {
            $this->warn('  No hay facturas sincronizadas. Ejecuta primero syncMoves.');
            return;
        }

        $offset = 0;
        $limit  = 200;
        $total  = 0;

        do {
            $records = $this->odooExecute('account.move.line', 'search_read',
                [[
                    ['move_id', 'in', $moveIds],
                    ['display_type', 'not in', ['line_section', 'line_note']],
                ]],
                [
                    'fields'  => ['id', 'move_id', 'account_id', 'name', 'debit', 'credit', 'analytic_distribution'],
                    'limit'   => $limit,
                    'offset'  => $offset,
                    'order'   => 'id asc',
                ]
            );

            foreach ($records as $rec) {
                $accOdooId = is_array($rec['account_id']) ? (int) $rec['account_id'][0] : null;
                $accRaw    = is_array($rec['account_id']) ? (string) $rec['account_id'][1] : null;

                // Separar código de nombre: "400001 Combustible" → code="400001", name="Combustible"
                $accCode  = null;
                $accName  = $accRaw;
                if ($accRaw && preg_match('/^(\S+)\s+(.+)$/', $accRaw, $m)) {
                    $accCode = $m[1];
                    $accName = $m[2];
                }

                $accNameEs = $accOdooId ? ($spanishNames[$accOdooId] ?? $accName) : $accName;

                // analytic_distribution puede venir como array o false
                $analytic = (is_array($rec['analytic_distribution']) && ! empty($rec['analytic_distribution']))
                    ? $rec['analytic_distribution']
                    : null;

                OdooAccountMoveLine::updateOrCreate(
                    ['odoo_id' => $rec['id']],
                    [
                        'move_odoo_id'          => is_array($rec['move_id']) ? $rec['move_id'][0] : $rec['move_id'],
                        'account_odoo_id'       => $accOdooId,
                        'account_code'          => $accCode,
                        'account_name'          => $accName,
                        'account_name_es'       => $accNameEs,
                        'name'                  => $rec['name'] ?: null,
                        'debit'                 => (float) ($rec['debit']  ?? 0),
                        'credit'                => (float) ($rec['credit'] ?? 0),
                        'analytic_distribution' => $analytic,
                    ]
                );
            }

            $count   = count($records);
            $total  += $count;
            $offset += $limit;

            $this->line("  → {$total} apuntes procesados...");

        } while ($count === $limit);

        $this->info("  ✓ {$total} apuntes contables sincronizados");
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Helpers JSON-RPC
    // ──────────────────────────────────────────────────────────────────────────────
    private function jsonRpc(string $service, string $method, array $args): mixed
    {
        $response = Http::timeout(30)->post("{$this->url}/jsonrpc", [
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
