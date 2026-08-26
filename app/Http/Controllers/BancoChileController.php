<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class BancoChileController extends Controller
{
    // ─── Vista principal ───────────────────────────────────────────────────────

    public function index()
    {
        $tokenRow = $this->getTokenRow();
        $tokenActivo = $tokenRow !== null;
        $tokenExpira = $tokenRow?->expires_at
                          ? Carbon::parse($tokenRow->expires_at)->diffForHumans()
                          : 'Desconocido';
        $tokenGuardado = $tokenActivo
                          ? substr($tokenRow->token, 0, 40).'...'
                          : null;

        // Cargar los últimos 15 logs de auditoría
        $logs = DB::table('banco_chile_sync_logs')
            ->orderBy('id', 'desc')
            ->limit(15)
            ->get()
            ->map(function ($log) {
                $log->formatted_date = Carbon::parse($log->created_at)->diffForHumans();

                return $log;
            });

        return view('bancochile.index', [
            'clientId' => config('services.banco_chile.client_id'),
            'clientSecret' => config('services.banco_chile.client_secret'),
            'tokenActivo' => $tokenActivo,
            'tokenExpira' => $tokenExpira,
            'tokenGuardado' => $tokenGuardado,
            'logs' => $logs,
        ]);
    }

    // ─── Guardar token desde la vista ─────────────────────────────────────────

    public function guardarToken(Request $request)
    {
        $token = trim($request->input('token'));

        if (str_starts_with(strtolower($token), 'bearer ')) {
            $token = substr($token, 7);
        }

        if (empty($token)) {
            return response()->json(['success' => false, 'error' => 'Token vacío.'], 422);
        }

        // Calcular expiración leyendo el JWT (sin verificar firma)
        $expiresAt = $this->extraerExpiracionJwt($token);

        // Guardar en DB (un solo registro activo)
        DB::table('banco_chile_tokens')->where('activo', true)->update(['activo' => false]);
        DB::table('banco_chile_tokens')->insert([
            'token' => $token,
            'expires_at' => $expiresAt,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::forget('bancochile_token');

        return response()->json([
            'success' => true,
            'expires_at' => $expiresAt ? Carbon::parse($expiresAt)->diffForHumans() : 'Desconocido',
        ]);
    }

    // ─── Consultar movimientos (usa token guardado o el del request) ───────────

    public function obtenerMovimientos(Request $request)
    {
        // Prioridad: token del body (form) > token guardado en DB
        $tokenInput = trim($request->input('token', ''));
        if (str_starts_with(strtolower($tokenInput), 'bearer ')) {
            $tokenInput = substr($tokenInput, 7);
        }

        $token = $tokenInput ?: $this->getTokenActivo();

        if (! $token) {
            return response()->json([
                'success' => false,
                'error' => 'No hay token guardado. Por favor, obtén uno desde el portal del banco y guárdalo.',
            ], 401);
        }

        $bchConfig = $this->getBancoChileConfig();

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                'client-id' => $bchConfig['client_id'],
                'client-secret' => $bchConfig['client_secret'],
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->withOptions([
                'cookies' => new \GuzzleHttp\Cookie\CookieJar,
                'allow_redirects' => ['strict' => true, 'referer' => true],
            ])->post($bchConfig['api_url'], [
                'rutOrigen' => '12312312-1',
                'productoCuenta' => 'CTD',
                'cuenta' => '123123123123',
                'rutApoderado' => [
                    ['value' => '12312312-1'],
                ],
                'cantidadRegistros' => '10',
                'paginacionDesde' => 0,
            ]);

            if ($response->status() === 401) {
                // Token expirado — marcarlo como inactivo
                DB::table('banco_chile_tokens')->where('activo', true)->update(['activo' => false]);
                Cache::forget('bancochile_token');

                return response()->json([
                    'success' => false,
                    'expired' => true,
                    'error' => 'El token expiró. Ve al portal del banco, genera uno nuevo y guárdalo aquí.',
                ], 401);
            }

            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Error API Banco de Chile ('.$response->status().'): '.$response->body(),
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => $response->json(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Excepción: '.$e->getMessage(),
            ], 500);
        }
    }

    // ─── Simular pago en Odoo Pruebas ─────────────────────────────────────────

    public function simularPago(Request $request)
    {
        $monto = (float) $request->input('monto');
        $tipo = $request->input('tipo'); // 'abono' o 'cargo'
        $desc = $request->input('descripcion', 'Simulación de transferencia');
        $rut = $request->input('rut', '12.345.678-9');
        $nombre = $request->input('nombre', 'Juan Perez Simulado');
        $cuentaOrig = $request->input('cuenta_origen', '004080123456');

        if ($tipo === 'cargo') {
            $monto = -abs($monto);
        } else {
            $monto = abs($monto);
        }

        $config = config('services.odoo');
        if (! $config) {
            return response()->json(['success' => false, 'error' => 'Configuración de Odoo no encontrada en servicios.'], 500);
        }

        try {
            // Intentar usar la cookie de sesión cacheada
            $sessionCookie = Cache::get('odoo_test_session_cookie');

            if (! $sessionCookie) {
                // 1. Autenticar en Odoo Pruebas para obtener la Cookie de Sesión
                $authResponse = Http::post("{$config['url']}/web/session/authenticate", [
                    'jsonrpc' => '2.0',
                    'method' => 'call',
                    'params' => [
                        'db' => $config['db'],
                        'login' => $config['user'],
                        'password' => $config['password'],
                    ],
                ]);

                if ($authResponse->failed()) {
                    return response()->json(['success' => false, 'error' => 'Fallo de conexión HTTP al autenticar con Odoo.'], 500);
                }

                $authData = $authResponse->json();
                if (isset($authData['error'])) {
                    return response()->json(['success' => false, 'error' => 'Error Odoo Auth: '.($authData['error']['data']['message'] ?? json_encode($authData['error']))], 400);
                }

                // Capturar la cookie session_id
                $cookies = $authResponse->header('Set-Cookie');
                preg_match('/session_id=[^;]+/', $cookies, $matches);
                $sessionCookie = $matches[0] ?? '';

                if (empty($sessionCookie)) {
                    return response()->json(['success' => false, 'error' => 'No se pudo capturar la cookie de sesión de Odoo.'], 500);
                }

                // Guardar la cookie en el cache por 45 minutos
                Cache::put('odoo_test_session_cookie', $sessionCookie, 2700);
            }

            // Generar UUID único y ref para la simulación
            $uuid = \Illuminate\Support\Str::uuid()->toString();
            $ref = 'SIM-'.mt_rand(100000000, 999999999);

            // Construir la narración / metadatos detallados
            $narration = "📋 Simulación en Vivo\n";
            $narration .= "👤 Cliente: {$nombre}\n";
            $narration .= "🆔 RUT Origen: {$rut}\n";
            $narration .= "🏦 Cuenta Origen: {$cuentaOrig}\n";
            $narration .= '🕐 Hora: '.now()->setTimezone('America/Santiago')->toDateTimeString()."\n";
            $narration .= "🔑 UUID: {$uuid}";

            $partnerInfo = "RUT Origen: {$rut} | Cuenta Origen: {$cuentaOrig}";

            $vals = [
                'journal_id' => (int) $config['journal_id'],
                'date' => now()->toDateString(),
                'payment_ref' => "SIMULADO: {$desc}",
                'amount' => $monto,
                'partner_name' => $nombre,
                'ref' => $ref,
                'narration' => $narration,
                'transaction_type' => $tipo === 'abono' ? '56C - ABONO SIMULADO' : '646 - CARGO SIMULADO',
                'online_transaction_identifier' => $uuid,
                'online_partner_information' => $partnerInfo,
                'unique_import_id' => 'BCH-'.$uuid,
            ];

            // 2. Crear el registro en Odoo usando JSON-RPC
            $createResponse = Http::withHeaders([
                'Cookie' => $sessionCookie,
            ])->post("{$config['url']}/web/dataset/call_kw", [
                'jsonrpc' => '2.0',
                'method' => 'call',
                'id' => time(),
                'params' => [
                    'model' => 'account.bank.statement.line',
                    'method' => 'create',
                    'args' => [
                        [$vals],
                    ],
                    'kwargs' => [
                        'context' => [
                            'lang' => 'es_CL',
                            'tz' => 'America/Santiago',
                        ],
                    ],
                ],
            ]);

            $createData = $createResponse->json();

            // Si la sesión expiró en el servidor (ej: Odoo reinició su sesión)
            if (isset($createData['error']) && strpos(json_encode($createData['error']), 'Session expired') !== false) {
                // Limpiar cache e intentar de nuevo una sola vez con una auth fresca
                Cache::forget('odoo_test_session_cookie');

                return $this->simularPago($request);
            }

            if (isset($createData['error'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Odoo Error al crear línea: '.($createData['error']['data']['message'] ?? json_encode($createData['error'])),
                ], 400);
            }

            return response()->json([
                'success' => true,
                'odoo_id' => $createData['result'],
                'ref' => $ref,
                'monto' => $monto,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Excepción en Laravel: '.$e->getMessage(),
            ], 500);
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function getOdooConfig(): array
    {
        $db = DB::connection('fuelcontrol');
        $hasTable = \Illuminate\Support\Facades\Schema::connection('fuelcontrol')->hasTable('gmail_inventory_settings');

        $bcEnv = 'qa';
        if ($hasTable) {
            $bcEnv = $db->table('gmail_inventory_settings')->where('key', 'banco_chile_env')->value('value') ?: 'qa';
        }

        $url = null;
        $database = null;
        $user = null;
        $password = null;
        $journalId = null;

        if ($hasTable) {
            $prefix = $bcEnv === 'production' ? 'prod_' : 'qa_';
            $url = $db->table('gmail_inventory_settings')->where('key', $prefix.'odoo_url')->value('value');
            $database = $db->table('gmail_inventory_settings')->where('key', $prefix.'odoo_db')->value('value');
            $user = $db->table('gmail_inventory_settings')->where('key', $prefix.'odoo_user')->value('value');
            $password = $db->table('gmail_inventory_settings')->where('key', $prefix.'odoo_password')->value('value');
            $journalId = $db->table('gmail_inventory_settings')->where('key', $prefix.'odoo_journal_id')->value('value');
        }

        // Fallbacks
        if ($bcEnv === 'production') {
            return [
                'url' => $url ?: config('services.odoo.url'),
                'db' => $database ?: config('services.odoo.db'),
                'user' => $user ?: config('services.odoo.user'),
                'password' => $password ?: config('services.odoo.password'),
                'journal_id' => $journalId ?: config('services.odoo.journal_id'),
            ];
        } else {
            return [
                'url' => $url ?: 'https://agricolaehe-prueba-31455293.dev.odoo.com',
                'db' => $database ?: 'agricolaehe-prueba-31455293',
                'user' => $user ?: 's.lopez.epple@gmail.com',
                'password' => $password ?: '1234',
                'journal_id' => $journalId ?: 22,
            ];
        }
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
            $clientId = $db->table('gmail_inventory_settings')->where('key', $prefix.'client_id')->value('value');
            $clientSecret = $db->table('gmail_inventory_settings')->where('key', $prefix.'client_secret')->value('value');
            $apiUrl = $db->table('gmail_inventory_settings')->where('key', $prefix.'api_url')->value('value');
        }

        if ($bcEnv === 'production') {
            return [
                'client_id' => $clientId ?: config('services.banco_chile.client_id'),
                'client_secret' => $clientSecret ?: config('services.banco_chile.client_secret'),
                'api_url' => $apiUrl ?: 'https://gw.apistore.bancochile.cl/banco-chile/v1/movimientos-cuenta/obtener',
            ];
        } else {
            return [
                'client_id' => $clientId ?: '721816d1e407fb656e73374a21bc9ebb',
                'client_secret' => $clientSecret ?: '93cac5b5a54a51d685aba881c6f2d872',
                'api_url' => $apiUrl ?: 'https://gw.apistore.bancochile.cl/banco-chile/sandbox/v1/movimientos-cuenta/obtener',
            ];
        }
    }

    private function getTokenRow()
    {
        return DB::table('banco_chile_tokens')
            ->where('activo', true)
            ->latest()
            ->first();
    }

    private function getTokenActivo(): ?string
    {
        return Cache::remember('bancochile_token', 300, function () {
            $row = $this->getTokenRow();
            if (! $row) {
                return null;
            }

            // Si ya sabemos que expiró, no lo devolvemos
            if ($row->expires_at && Carbon::parse($row->expires_at)->isPast()) {
                return null;
            }

            return $row->token;
        });
    }

    /**
     * Decodifica el payload del JWT para extraer el campo "exp"
     * sin verificar la firma (solo lectura del claim).
     */
    private function extraerExpiracionJwt(string $token): ?string
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        try {
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            if (! empty($payload['exp'])) {
                return Carbon::createFromTimestamp($payload['exp'])->toDateTimeString();
            }
        } catch (\Exception $e) {
            // ignorar
        }

        return null;
    }

    // ─── Vista de Pruebas de Lote ─────────────────────────────────────────────

    public function lotesTestView()
    {
        $tokenRow = $this->getTokenRow();
        $tokenActivo = $tokenRow !== null;
        $tokenExpira = $tokenRow?->expires_at
                          ? Carbon::parse($tokenRow->expires_at)->diffForHumans()
                          : 'Desconocido';

        return view('bancochile.lotes_test', [
            'tokenActivo' => $tokenActivo,
            'tokenExpira' => $tokenExpira,
        ]);
    }

    // ─── Despachar Lote al Sandbox ────────────────────────────────────────────

    public function enviarLoteTest(Request $request)
    {
        $token = $this->getTokenActivo();
        if (! $token) {
            return response()->json(['success' => false, 'error' => 'No hay token Bearer activo. Por favor, guárdalo en Ajustes.'], 401);
        }

        $bchConfig = $this->getBancoChileConfig();

        // 1. Recopilar datos de cabecera/cuerpo
        $cuentaOrigen = $request->input('cuentaOrigen', '902252590600');
        $rutOrigen = $request->input('rutOrigen', '90512020-3');
        $usuario = $request->input('usuario', 'userCli');
        $idLote = $request->input('idLote', 'lote-'.time());
        $rutApoderado = $request->input('rutApoderado', '1-9');

        // 2. Movimientos
        $movs = $request->input('movimientos', []);
        $totalLote = 0;
        $movimientosPayload = [];

        foreach ($movs as $idx => $m) {
            $monto = (int) ($m['monto'] ?? 0);
            $totalLote += $monto;

            $movimientosPayload[] = [
                'nroTrxCliente' => 'TRX-'.time().'-'.$idx,
                'monto' => $monto,
                'rutBeneficiarioClienteComercio' => $rutApoderado,
                'rutDestino' => trim($m['rutDestino']),
                'cuentaDestino' => trim($m['cuentaDestino']),
                'nombreClienteDestino' => trim($m['nombreClienteDestino']),
                'codigoBancoDestino' => trim($m['codigoBancoDestino']),
                'productoCuentaDestino' => trim($m['productoCuentaDestino']),
            ];
        }

        // 3. Payload Completo tal como exige el Banco
        $payload = [
            'cabecera' => [
                'consumidor' => [
                    'usuario' => $usuario,
                ],
            ],
            'cuerpo' => [
                'cuentaOrigen' => $cuentaOrigen,
                'rutOrigen' => $rutOrigen,
                'idLote' => $idLote,
                'montoTotalLote' => $totalLote,
                'rutApoderado' => [
                    ['value' => $rutApoderado],
                ],
            ],
            'movimientos' => $movimientosPayload,
        ];

        try {
            // URL de lotes en sandbox
            $urlLotes = 'https://gw.apistore.bancochile.cl/banco-chile/sandbox/v1/abono-rescate/servicios/transferencias/rescate/lotes';

            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                'client-id' => $bchConfig['client_id'],
                'client-secret' => $bchConfig['client_secret'],
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'application/json',
                'Accept' => '*/*',
            ])->withOptions([
                'cookies' => new \GuzzleHttp\Cookie\CookieJar,
                'allow_redirects' => ['strict' => true, 'referer' => true],
            ])->post($urlLotes, $payload);

            return response()->json([
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'sent_payload' => $payload,
                'response_body' => $response->json() ?: $response->body(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error en Laravel al despachar lote: '.$e->getMessage(),
            ], 500);
        }
    }

    // ─── Webhook de Pago desde Odoo ───────────────────────────────────────────

    public function procesarPagoWebhook(Request $request)
    {
        $token = $this->getTokenActivo();
        if (! $token) {
            \Illuminate\Support\Facades\Log::channel('bch_webhook')->error('Webhook falló: No hay token Bearer activo.');

            return response()->json(['success' => false, 'error' => 'No hay token Bearer activo en Laravel.'], 401);
        }

        // Obtener configuración del ambiente activo
        $bchConfig = $this->getBancoChileConfig();
        $db = DB::connection('fuelcontrol');
        $hasTable = \Illuminate\Support\Facades\Schema::connection('fuelcontrol')->hasTable('gmail_inventory_settings');

        $bcEnv = 'qa';
        if ($hasTable) {
            $bcEnv = $db->table('gmail_inventory_settings')->where('key', 'banco_chile_env')->value('value') ?: 'qa';
        }

        // Obtener datos del remitente desde base de datos según el ambiente
        $prefix = $bcEnv === 'production' ? 'prod_' : 'qa_';

        $cuentaOrigen = $hasTable ? $db->table('gmail_inventory_settings')->where('key', $prefix.'bc_cuenta_origen')->value('value') : null;
        $rutOrigen = $hasTable ? $db->table('gmail_inventory_settings')->where('key', $prefix.'bc_rut_origen')->value('value') : null;
        $usuario = $hasTable ? $db->table('gmail_inventory_settings')->where('key', $prefix.'bc_usuario')->value('value') : null;
        $rutApoderado = $hasTable ? $db->table('gmail_inventory_settings')->where('key', $prefix.'bc_rut_apoderado')->value('value') : null;

        // Fallbacks si están vacíos
        $cuentaOrigen = $cuentaOrigen ?: '902252590600';
        $rutOrigen = $rutOrigen ?: '90512020-3';
        $usuario = $usuario ?: 'userCli';
        $rutApoderado = $rutApoderado ?: '1-9';

        // ─── Resolver datos dinámicos desde Odoo si viene solo el _id ───
        if ($request->has('_id') && $request->input('_model') === 'account.payment') {
            $paymentId = (int) $request->input('_id');
            \Illuminate\Support\Facades\Log::channel('bch_webhook')->info("Webhook de Odoo detectado. Resolviendo datos para el pago ID: {$paymentId}");

            try {
                $odooConfig = $this->getOdooConfig();

                // A. Autenticar en Odoo
                $authResponse = Http::post("{$odooConfig['url']}/web/session/authenticate", [
                    'jsonrpc' => '2.0',
                    'method' => 'call',
                    'params' => [
                        'db' => $odooConfig['db'],
                        'login' => $odooConfig['user'],
                        'password' => $odooConfig['password'],
                    ],
                ]);

                if ($authResponse->failed()) {
                    throw new \Exception('Fallo al autenticar en Odoo.');
                }

                $authData = $authResponse->json();
                if (isset($authData['error'])) {
                    throw new \Exception('Error Odoo Auth: '.($authData['error']['data']['message'] ?? json_encode($authData['error'])));
                }

                $cookies = $authResponse->header('Set-Cookie');
                preg_match('/session_id=[^;]+/', $cookies, $matches);
                $sessionCookie = $matches[0] ?? '';

                if (empty($sessionCookie)) {
                    throw new \Exception('No se pudo capturar la cookie de sesión.');
                }

                // Helper local para llamar a la API RPC
                $odooCall = function (string $model, string $method, array $args, array $kwargs = []) use ($odooConfig, $sessionCookie) {
                    $res = Http::withHeaders(['Cookie' => $sessionCookie])->post("{$odooConfig['url']}/web/dataset/call_kw", [
                        'jsonrpc' => '2.0',
                        'method' => 'call',
                        'id' => time(),
                        'params' => [
                            'model' => $model,
                            'method' => $method,
                            'args' => $args,
                            'kwargs' => array_merge([
                                'context' => ['lang' => 'es_CL', 'tz' => 'America/Santiago'],
                            ], $kwargs),
                        ],
                    ]);
                    $data = $res->json();
                    if (isset($data['error'])) {
                        throw new \Exception("Odoo RPC Error ($model.$method): ".($data['error']['data']['message'] ?? json_encode($data['error'])));
                    }

                    return $data['result'];
                };

                // B. Leer los datos del pago
                $payment = $odooCall('account.payment', 'read', [
                    [$paymentId],
                    ['id', 'name', 'amount', 'partner_id'],
                ])[0] ?? null;

                if (! $payment) {
                    throw new \Exception('No se encontró el registro de pago en Odoo.');
                }

                $partnerId = $payment['partner_id'][0] ?? null;
                $partnerName = $payment['partner_id'][1] ?? 'Proveedor Desconocido';

                if (! $partnerId) {
                    throw new \Exception('El pago no tiene un proveedor (partner_id) asignado.');
                }

                // C. Leer el RUT y Cuentas Bancarias del Proveedor
                $partnerData = $odooCall('res.partner', 'read', [
                    [$partnerId],
                    ['vat', 'bank_ids'],
                ])[0] ?? null;

                $bankIds = $partnerData['bank_ids'] ?? [];
                if (empty($bankIds)) {
                    throw new \Exception("El proveedor $partnerName no tiene cuentas bancarias configuradas en Odoo.");
                }

                // D. Leer la cuenta bancaria principal
                $bankAccount = $odooCall('res.partner.bank', 'read', [
                    [$bankIds[0]],
                    ['acc_number', 'bank_id'],
                ])[0] ?? null;

                if (! $bankAccount) {
                    throw new \Exception('No se pudo leer la cuenta bancaria del proveedor.');
                }

                // E. Leer el código del banco
                $bankCode = '0001'; // Default Banco de Chile
                if (! empty($bankAccount['bank_id'])) {
                    $bankDetails = $odooCall('res.bank', 'read', [
                        [$bankAccount['bank_id'][0]],
                        ['bic'], // Cambiado de code a bic
                    ])[0] ?? null;
                    if ($bankDetails && ! empty($bankDetails['bic'])) {
                        $bankCode = $bankDetails['bic'];
                    }
                }

                // Inyectar datos en la petición para el flujo estándar
                $request->merge([
                    'monto' => (int) $payment['amount'],
                    'rutDestino' => trim($partnerData['vat'] ?? '1-9'),
                    'cuentaDestino' => trim($bankAccount['acc_number']),
                    'nombreClienteDestino' => trim($partnerName),
                    'codigoBancoDestino' => $bankCode,
                    'productoCuentaDestino' => 'CTD',
                ]);

                \Illuminate\Support\Facades\Log::channel('bch_webhook')->info('Datos del pago resueltos exitosamente de Odoo.', $request->all());

            } catch (\Exception $ex) {
                \Illuminate\Support\Facades\Log::channel('bch_webhook')->error('Error resolviendo datos en Odoo: '.$ex->getMessage());

                return response()->json([
                    'success' => false,
                    'error' => 'Error al obtener datos desde Odoo: '.$ex->getMessage(),
                ], 422);
            }
        }

        // Validar datos mínimos obligatorios del beneficiario enviados por Odoo
        $rules = [
            'rutDestino' => 'required|string',
            'cuentaDestino' => 'required|string',
            'nombreClienteDestino' => 'required|string',
            'codigoBancoDestino' => 'required|string',
            'monto' => 'required|numeric|min:1',
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            \Illuminate\Support\Facades\Log::channel('bch_webhook')->error('Webhook falló: Datos inválidos.', $validator->errors()->toArray());

            return response()->json(['success' => false, 'error' => 'Datos inválidos.', 'details' => $validator->errors()], 400);
        }

        $monto = (int) $request->input('monto');
        $idLote = 'odoo-'.time().'-'.rand(100, 999);

        // Compilar el Payload tal como lo confirmamos en las pruebas exitosas
        $payload = [
            'cabecera' => [
                'consumidor' => [
                    'usuario' => $usuario,
                ],
            ],
            'cuerpo' => [
                'cuentaOrigen' => $cuentaOrigen,
                'rutOrigen' => $rutOrigen,
                'idLote' => $idLote,
                'montoTotalLote' => $monto,
                'rutApoderado' => [
                    ['value' => $rutApoderado],
                ],
            ],
            'movimientos' => [
                [
                    'nroTrxCliente' => 'TRX-'.time().'-'.rand(100, 999),
                    'monto' => $monto,
                    'rutBeneficiarioClienteComercio' => $rutApoderado,
                    'rutDestino' => trim($request->input('rutDestino')),
                    'cuentaDestino' => trim($request->input('cuentaDestino')),
                    'nombreClienteDestino' => trim($request->input('nombreClienteDestino')),
                    'codigoBancoDestino' => str_pad(trim($request->input('codigoBancoDestino')), 4, '0', STR_PAD_LEFT), // Asegurar 4 dígitos
                    'productoCuentaDestino' => trim($request->input('productoCuentaDestino', 'CTD')),
                ],
            ],
        ];

        \Illuminate\Support\Facades\Log::channel('bch_webhook')->info('Enviando petición a la API del Banco de Chile...');
        \Illuminate\Support\Facades\Log::channel('bch_webhook')->info('Payload de Envío compilado:', $payload);

        try {
            // endpoint dinámico según ambiente
            $apiUrl = $bchConfig['api_url'];
            // Si termina en movimientos-cuenta/obtener, reemplazamos por el de lotes
            if (str_contains($apiUrl, 'movimientos-cuenta/obtener')) {
                $apiUrl = str_replace('movimientos-cuenta/obtener', 'abono-rescate/servicios/transferencias/rescate/lotes', $apiUrl);
            }

            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                'client-id' => $bchConfig['client_id'],
                'client-secret' => $bchConfig['client_secret'],
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'application/json',
                'Accept' => '*/*',
            ])->withOptions([
                'cookies' => new \GuzzleHttp\Cookie\CookieJar,
                'allow_redirects' => ['strict' => true, 'referer' => true],
            ])->post($apiUrl, $payload);

            $responseBody = $response->json() ?: $response->body();
            \Illuminate\Support\Facades\Log::channel('bch_webhook')->info('Respuesta recibida del Banco de Chile [HTTP '.$response->status().']:', is_array($responseBody) ? $responseBody : [$responseBody]);

            if ($response->successful()) {
                // Escribir en el chatter de Odoo si venía el payment_id
                if ($request->has('_id') && isset($sessionCookie) && isset($odooCall)) {
                    try {
                        $odooCall('account.payment', 'message_post', [
                            [(int) $request->input('_id')],
                        ], [
                            'body' => "✓ Pago enviado automáticamente al Banco de Chile. ID Lote: $idLote",
                        ]);
                        \Illuminate\Support\Facades\Log::channel('bch_webhook')->info('✓ Notificación registrada en el chatter del pago ID: '.$request->input('_id'));
                    } catch (\Exception $chatterEx) {
                        \Illuminate\Support\Facades\Log::channel('bch_webhook')->error('No se pudo escribir en el chatter de Odoo: '.$chatterEx->getMessage());
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Lote inyectado con éxito en el portal del banco.',
                    'id_lote' => $idLote,
                    'bch_response' => $responseBody,
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => 'El banco rechazó la nómina.',
                'status_code' => $response->status(),
                'bch_response' => $responseBody,
            ], 422);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::channel('bch_webhook')->error('Excepción al comunicarse con la API de Banco de Chile: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Error interno de comunicación con el banco: '.$e->getMessage(),
            ], 500);
        }
    }
}
