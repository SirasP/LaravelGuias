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
    protected $description = 'Sincroniza todos los documentos contables (account.move) y sus apuntes desde Odoo a MySQL local';

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
        $this->syncPartnerVats();
        $this->syncMoveLines();
        $this->removeDeletedMoves();

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
    // Sincronizar todos los account.move (todas las tipos)
    // ──────────────────────────────────────────────────────────────────────────────
    private function syncMoves(): void
    {
        // Tipos a sincronizar (excluimos 'entry' — son asientos internos sin folio DTE)
        $moveTypes = ['in_invoice', 'out_invoice', 'in_refund', 'out_refund'];

        foreach ($moveTypes as $moveType) {
            $this->info("Sincronizando {$moveType}...");

            $offset = 0;
            $limit  = 100;
            $total  = 0;

            do {
                $records = $this->odooExecute('account.move', 'search_read',
                    [[['move_type', '=', $moveType]]],
                    [
                        'fields'  => ['id', 'name', 'ref', 'move_type', 'state', 'payment_state', 'partner_id', 'invoice_date', 'amount_total'],
                        'limit'   => $limit,
                        'offset'  => $offset,
                        'order'   => 'id asc',
                    ]
                );

                foreach ($records as $rec) {
                    // Extraer folio numérico del name: "FAC 106723" → 106723
                    $folio = null;
                    if (! empty($rec['name'])) {
                        $parts = explode(' ', trim($rec['name']));
                        $last  = end($parts);
                        if (ctype_digit($last)) {
                            $folio = (int) $last;
                        }
                    }

                    OdooAccountMove::updateOrCreate(
                        ['odoo_id' => $rec['id']],
                        [
                            'name'            => $rec['name'] ?? null,
                            'ref'             => $rec['ref']  ?: null,
                            'folio'           => $folio,
                            'move_type'       => $rec['move_type'],
                            'state'           => $rec['state'],
                            'payment_state'   => $rec['payment_state'] ?? 'not_paid',
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

                $this->line("  → {$total} registros procesados...");

            } while ($count === $limit);

            $this->info("  ✓ {$total} {$moveType} sincronizados");
        }
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Sincronizar RUT (VAT) de los partners a odoo_account_moves
    // ──────────────────────────────────────────────────────────────────────────────
    private function syncPartnerVats(): void
    {
        $this->info('Sincronizando RUT de proveedores...');

        // Traer todos los partner_odoo_id únicos que no tienen VAT aún
        $partnerIds = OdooAccountMove::whereNotNull('partner_odoo_id')
            ->whereNull('partner_vat')
            ->pluck('partner_odoo_id')
            ->unique()
            ->values()
            ->toArray();

        if (empty($partnerIds)) {
            $this->line('  ✓ RUT de partners ya sincronizados');
            return;
        }

        $this->line('  → Consultando ' . count($partnerIds) . ' partners a Odoo...');

        // Traer en chunks de 200
        $vatMap = [];
        foreach (array_chunk($partnerIds, 200) as $chunk) {
            $partners = $this->odooExecute('res.partner', 'search_read',
                [[['id', 'in', $chunk]]],
                ['fields' => ['id', 'vat'], 'limit' => 200]
            );
            foreach ($partners as $p) {
                if (! empty($p['vat'])) {
                    // Normalizar RUT: quitar puntos, guiones y espacios
                    $vatMap[$p['id']] = strtoupper(preg_replace('/[.\-\s]/', '', $p['vat']));
                }
            }
        }

        // Actualizar en MySQL
        $updated = 0;
        foreach ($vatMap as $partnerId => $vat) {
            OdooAccountMove::where('partner_odoo_id', $partnerId)
                ->update(['partner_vat' => $vat]);
            $updated++;
        }

        $this->info("  ✓ RUT actualizado para {$updated} partners");
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Eliminar de MySQL los moves que ya no existen en Odoo
    // ──────────────────────────────────────────────────────────────────────────────
    private function removeDeletedMoves(): void
    {
        $this->info('Verificando registros eliminados en Odoo...');

        $localIds = OdooAccountMove::pluck('odoo_id')->toArray();
        if (empty($localIds)) return;

        // Pedir a Odoo solo los IDs que SÍ existen
        $existingIds = $this->odooExecute('account.move', 'search',
            [[['id', 'in', $localIds], ['move_type', 'in', ['in_invoice','out_invoice','in_refund','out_refund']]]],
            ['limit' => 5000]
        );

        $toDelete = array_diff($localIds, $existingIds);

        if (! empty($toDelete)) {
            OdooAccountMove::whereIn('odoo_id', $toDelete)->delete();
            OdooAccountMoveLine::whereIn('move_odoo_id', $toDelete)->delete();
            $this->warn("  ✗ Eliminados " . count($toDelete) . " registros que ya no existen en Odoo");
        } else {
            $this->line("  ✓ Sin registros huérfanos");
        }
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

        // IDs de los moves que ya tenemos localmente (todos los tipos)
        $moveIds = OdooAccountMove::pluck('odoo_id')->toArray();

        if (empty($moveIds)) {
            $this->warn('  No hay facturas sincronizadas. Ejecuta primero syncMoves.');
            return;
        }

        // Pre-cargar nombres de impuestos desde Odoo para este sync
        $taxNamesMap = $this->fetchTaxNames();

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
                    'fields'  => ['id', 'move_id', 'account_id', 'name', 'debit', 'credit',
                                  'analytic_distribution', 'tax_ids'],
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

                // Resolver nombres de impuestos desde el mapa pre-cargado
                $taxIds   = is_array($rec['tax_ids']) ? $rec['tax_ids'] : [];
                $taxNames = array_values(array_filter(
                    array_map(fn($tid) => $taxNamesMap[$tid] ?? null, $taxIds)
                ));

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
                        'taxes'                 => empty($taxNames) ? null : $taxNames,
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
    // Obtener mapa id→nombre de todos los impuestos en Odoo
    // ──────────────────────────────────────────────────────────────────────────────
    private function fetchTaxNames(): array
    {
        $this->info('  Cargando impuestos desde Odoo...');
        $taxes = $this->odooExecute('account.tax', 'search_read',
            [[]],
            ['fields' => ['id', 'name'], 'limit' => 500]
        );
        $map = [];
        foreach ($taxes as $t) {
            $map[(int) $t['id']] = $t['name'];
        }
        $this->line('  → ' . count($map) . ' impuestos cargados');
        return $map;
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
