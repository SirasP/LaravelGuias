<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class BancoChileSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bancochile:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consulta API Banco de Chile Sandbox e inyecta los nuevos movimientos en Odoo Pruebas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startTime = microtime(true);
        $this->info('[' . now()->toDateTimeString() . '] Iniciando sincronización del Banco de Chile...');

        // 1. Obtener Token
        $token = $this->getTokenActivo();
        if (!$token) {
            $msg = 'Sincronización abortada: No hay token Bearer activo o está expirado.';
            $this->error($msg);
            $this->logExecution('error', 0, $msg, 'Por favor, obtén un token en el portal del banco y guárdalo en el panel.', $startTime);
            return Command::FAILURE;
        }

        $bchConfig = $this->getBancoChileConfig();

        try {
            // 2. Conectar a Odoo Pruebas (usando cookie de sesión cacheada)
            $sessionCookie = $this->getOdooSessionCookie();
            if (!$sessionCookie) {
                throw new \Exception('No se pudo autenticar con Odoo Pruebas.');
            }

            // 3. Consultar Banco de Chile
            $this->info('Consultando API del Banco de Chile...');
            $apiResponse = Http::withHeaders([
                'User-Agent'    => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                'client-id'     => $bchConfig['client_id'],
                'client-secret' => $bchConfig['client_secret'],
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ])->withOptions([
                'cookies'        => new \GuzzleHttp\Cookie\CookieJar(),
                'allow_redirects' => ['strict' => true, 'referer' => true],
            ])->post($bchConfig['api_url'], [
                'rutOrigen'         => '12312312-1',
                'productoCuenta'    => 'CTD',
                'cuenta'            => '123123123123',
                'rutApoderado'      => [
                    ['value' => '12312312-1'],
                ],
                'cantidadRegistros' => '10',
                'paginacionDesde'   => 0,
            ]);

            if ($apiResponse->status() === 401) {
                // Token expirado
                DB::table('banco_chile_tokens')->where('activo', true)->update(['activo' => false]);
                Cache::forget('bancochile_token');

                $msg = 'El token Bearer ha expirado.';
                $this->error($msg);
                $this->logExecution('error', 0, $msg, 'HTTP 401 Unauthorized de la API del banco.', $startTime);
                return Command::FAILURE;
            }

            if ($apiResponse->failed()) {
                throw new \Exception('Error API Banco de Chile (' . $apiResponse->status() . '): ' . $apiResponse->body());
            }

            $bankData = $apiResponse->json();
            if (($bankData['glosaRespuesta'] ?? '') !== 'EXITO') {
                throw new \Exception('Respuesta del banco no indica EXITO: ' . json_encode($bankData));
            }

            $movimientos = $bankData['movimientos'] ?? [];
            $this->info('API retornó ' . count($movimientos) . ' movimientos.');

            if (empty($movimientos)) {
                $msg = 'Sincronización completada. No se encontraron movimientos en el banco.';
                $this->info($msg);
                $this->logExecution('success', 0, $msg, null, $startTime);
                return Command::SUCCESS;
            }

            // 4. Inyectar nuevos en Odoo Pruebas
            $inyectados = 0;
            $odooConfig = config('services.odoo');

            foreach ($movimientos as $mov) {
                $uuid = $mov['secuencial'] ?? Str::uuid()->toString();
                $ref  = $mov['idInternoTransaccion'] ?? $uuid;

                // Verificar duplicados por unique_import_id
                $exists = $this->odooRpc($sessionCookie, 'account.bank.statement.line', 'search_read', [
                    [
                        ['journal_id', '=', (int) $odooConfig['journal_id']],
                        ['unique_import_id', '=', 'BCH-' . $uuid]
                    ]
                ], ['fields' => ['id'], 'limit' => 1]);

                if (!empty($exists)) {
                    $this->line("  ⏭  Saltando ya importado: \"{$mov['descripcionCorta']}\" (UUID: {$uuid})");
                    continue;
                }

                // Armar datos
                $esCredito = ($mov['indicadorCreditoDebito'] ?? 'C') === 'C';
                $monto     = $esCredito ? abs($mov['monto']) : -abs($mov['monto']);
                $desc      = implode(' — ', array_filter([$mov['descripcionCorta'] ?? '', ($mov['descripcionLarga'] ?? '') !== ($mov['descripcionCorta'] ?? '') ? $mov['descripcionLarga'] : null]));

                // Narration
                $narration = "📋 Movimiento Importado\n";
                if (!empty($mov['glosas'])) {
                    $narration .= "📋 Glosa: " . implode(' | ', $mov['glosas']) . "\n";
                }
                foreach ($mov['adicionales'] ?? [] as $a) {
                    $campos = $a['campos'] ?? [];
                    if (count($campos) >= 2 && ($campos[0]['importancia'] ?? '') !== 'N') {
                        $narration .= "🔹 {$campos[0]['dato']} {$campos[1]['dato']}\n";
                    }
                }
                $canales = ['BCAMOVIL01' => 'App Móvil Banco de Chile', 'CAJA' => 'Caja', 'ATM_BCH' => 'ATM Banco de Chile', 'INTERNET' => 'Internet'];
                $narration .= "🏦 Canal: " . ($canales[$mov['codigoOrigen']] ?? $mov['codigoOrigen']) . "\n";
                if (!empty($mov['descripcionOficina'])) {
                    $narration .= "📍 Sucursal: {$mov['descripcionOficina']}\n";
                }
                if (!empty($mov['numeroDocumento'])) {
                    $narration .= "📄 N° Documento: {$mov['numeroDocumento']}\n";
                }
                $narration .= "🔑 UUID Banco: {$uuid}\n";
                $narration .= "🕐 Hora Transacción: " . ($mov['fechaHoraRealTransaccion'] ?? $mov['fechaContable']);

                // Partner Info (RUT / Cuenta)
                $partnerInfo = [];
                foreach ($mov['adicionales'] ?? [] as $a) {
                    $campos = $a['campos'] ?? [];
                    if (count($campos) >= 2) {
                        $label = strtolower($campos[0]['dato'] ?? '');
                        if (str_contains($label, 'rut') || str_contains($label, 'cuenta')) {
                            $partnerInfo[] = "{$campos[0]['dato']}: {$campos[1]['dato']}";
                        }
                    }
                }
                $partnerInfoStr = implode(' | ', $partnerInfo) ?: null;

                $vals = [
                    'journal_id'                    => (int) $odooConfig['journal_id'],
                    'date'                          => $mov['fechaContable'],
                    'payment_ref'                   => $desc,
                    'amount'                        => $monto,
                    'partner_name'                  => $mov['campoComplementario1'] ?? false,
                    'ref'                           => $ref,
                    'narration'                     => $narration,
                    'transaction_type'              => ($mov['codigoTransaccionCore'] ?? '') . ' - ' . ($mov['descripcionTransaccionCore'] ?? ''),
                    'online_transaction_identifier' => $uuid,
                    'online_partner_information'    => $partnerInfoStr,
                    'unique_import_id'              => 'BCH-' . $uuid,
                ];

                $odooId = $this->odooRpc($sessionCookie, 'account.bank.statement.line', 'create', [[$vals]]);
                $idToShow = is_array($odooId) ? ($odooId[0] ?? 'N/A') : $odooId;
                $this->info("  ✅ Inyectado en Odoo: \"{$mov['descripcionCorta']}\" (ID: {$idToShow})");
                $inyectados++;
            }

            $msg = "Sincronización exitosa. {$inyectados} nuevos movimientos inyectados en Odoo Pruebas.";
            $this->info($msg);
            $this->logExecution('success', $inyectados, $msg, null, $startTime);
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $msg = 'Excepción ocurrida durante la sincronización.';
            $this->error($msg . ' ' . $e->getMessage());
            $this->logExecution('error', 0, $msg, $e->getMessage() . "\n" . $e->getTraceAsString(), $startTime);
            return Command::FAILURE;
        }
    }

    // ─── Helpers Odoo JSON-RPC ───────────────────────────────────────────────

    private function getOdooConfig(): array
    {
        $db = DB::connection('fuelcontrol');
        $hasTable = \Illuminate\Support\Facades\Schema::connection('fuelcontrol')->hasTable('gmail_inventory_settings');

        $bcEnv = 'qa';
        if ($hasTable) {
            $bcEnv = $db->table('gmail_inventory_settings')->where('key', 'banco_chile_env')->value('value') ?: 'qa';
        }

        $url = null; $database = null; $user = null; $password = null; $journalId = null;

        if ($hasTable) {
            $prefix = $bcEnv === 'production' ? 'prod_' : 'qa_';
            $url       = $db->table('gmail_inventory_settings')->where('key', $prefix . 'odoo_url')->value('value');
            $database  = $db->table('gmail_inventory_settings')->where('key', $prefix . 'odoo_db')->value('value');
            $user      = $db->table('gmail_inventory_settings')->where('key', $prefix . 'odoo_user')->value('value');
            $password  = $db->table('gmail_inventory_settings')->where('key', $prefix . 'odoo_password')->value('value');
            $journalId = $db->table('gmail_inventory_settings')->where('key', $prefix . 'odoo_journal_id')->value('value');
        }

        // Fallbacks
        if ($bcEnv === 'production') {
            return [
                'url'        => $url ?: config('services.odoo.url'),
                'db'         => $database ?: config('services.odoo.db'),
                'user'       => $user ?: config('services.odoo.user'),
                'password'   => $password ?: config('services.odoo.password'),
                'journal_id' => $journalId ?: config('services.odoo.journal_id'),
            ];
        } else {
            return [
                'url'        => $url ?: 'https://agricolaehe-prueba-31455293.dev.odoo.com',
                'db'         => $database ?: 'agricolaehe-prueba-31455293',
                'user'       => $user ?: 's.lopez.epple@gmail.com',
                'password'   => $password ?: '1234',
                'journal_id' => $journalId ?: 22,
            ];
        }
    }

    private function getOdooSessionCookie(): ?string
    {
        return Cache::remember('odoo_test_session_cookie', 2700, function () {
            $config = $this->getOdooConfig();
            $response = Http::post("{$config['url']}/web/session/authenticate", [
                'jsonrpc' => '2.0',
                'method' => 'call',
                'params' => [
                    'db'       => $config['db'],
                    'login'    => $config['user'],
                    'password' => $config['password'],
                ]
            ]);

            if ($response->failed() || isset($response->json()['error'])) {
                return null;
            }

            $cookies = $response->header('Set-Cookie');
            preg_match('/session_id=[^;]+/', $cookies, $matches);
            return $matches[0] ?? null;
        });
    }

    private function odooRpc(string $cookie, string $model, string $method, array $args, array $kwargs = [])
    {
        $config = $this->getOdooConfig();
        $response = Http::withHeaders([
            'Cookie' => $cookie
        ])->post("{$config['url']}/web/dataset/call_kw", [
            'jsonrpc' => '2.0',
            'method'  => 'call',
            'id'      => time(),
            'params'  => [
                'model'  => $model,
                'method' => $method,
                'args'   => $args,
                'kwargs' => array_merge([
                    'context' => ['lang' => 'es_CL', 'tz' => 'America/Santiago']
                ], $kwargs)
            ]
        ]);

        $data = $response->json();
        
        if (isset($data['error'])) {
            if (str_contains(json_encode($data['error']), 'Session expired')) {
                Cache::forget('odoo_test_session_cookie');
                $newCookie = $this->getOdooSessionCookie();
                if ($newCookie) {
                    return $this->odooRpc($newCookie, $model, $method, $args, $kwargs);
                }
            }
            throw new \Exception($data['error']['data']['message'] ?? json_encode($data['error']));
        }

        return $data['result'];
    }

    // ─── Helpers Token / Logs ────────────────────────────────────────────────

    private function getTokenActivo(): ?string
    {
        $row = DB::table('banco_chile_tokens')
            ->where('activo', true)
            ->latest()
            ->first();

        if (!$row) return null;

        if ($row->expires_at && Carbon::parse($row->expires_at)->isPast()) {
            DB::table('banco_chile_tokens')->where('id', $row->id)->update(['activo' => false]);
            Cache::forget('bancochile_token');
            return null;
        }

        return $row->token;
    }

    private function logExecution(string $status, int $newMovements, string $message, ?string $details, float $startTime)
    {
        $runtime = (int) round((microtime(true) - $startTime) * 1000);

        DB::table('banco_chile_sync_logs')->insert([
            'status'        => $status,
            'new_movements' => $newMovements,
            'message'       => substr($message, 0, 255),
            'error_details' => $details,
            'runtime_ms'    => $runtime,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function getBancoChileConfig(): array
    {
        $db = DB::connection('fuelcontrol');
        $hasTable = \Illuminate\Support\Facades\Schema::connection('fuelcontrol')->hasTable('gmail_inventory_settings');
        
        $bcEnv = 'qa';
        if ($hasTable) {
            $bcEnv = $db->table('gmail_inventory_settings')->where('key', 'banco_chile_env')->value('value') ?: 'qa';
        }

        $clientId = null;
        $clientSecret = null;
        $apiUrl = null;

        if ($hasTable) {
            $prefix = $bcEnv === 'production' ? 'prod_bc_' : 'qa_bc_';
            $clientId     = $db->table('gmail_inventory_settings')->where('key', $prefix . 'client_id')->value('value');
            $clientSecret = $db->table('gmail_inventory_settings')->where('key', $prefix . 'client_secret')->value('value');
            $apiUrl       = $db->table('gmail_inventory_settings')->where('key', $prefix . 'api_url')->value('value');
        }

        if ($bcEnv === 'production') {
            return [
                'client_id'     => $clientId ?: config('services.banco_chile.client_id'),
                'client_secret' => $clientSecret ?: config('services.banco_chile.client_secret'),
                'api_url'       => $apiUrl ?: 'https://gw.apistore.bancochile.cl/banco-chile/v1/movimientos-cuenta/obtener',
            ];
        } else {
            return [
                'client_id'     => $clientId ?: '721816d1e407fb656e73374a21bc9ebb',
                'client_secret' => $clientSecret ?: '93cac5b5a54a51d685aba881c6f2d872',
                'api_url'       => $apiUrl ?: 'https://gw.apistore.bancochile.cl/banco-chile/sandbox/v1/movimientos-cuenta/obtener',
            ];
        }
    }
}
