<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Google\Client as GoogleClient;
use Google\Service\Gmail;

class GmailAuthController extends Controller
{
    /**
     * Construye y devuelve un cliente Google listo para usar.
     */
    private function makeClient(): GoogleClient
    {
        $client = new GoogleClient();
        $client->setApplicationName('FuelControl Gmail Import');
        $client->setScopes([Gmail::GMAIL_MODIFY]);
        $client->setAuthConfig(storage_path('app/gmail/credentials.json'));
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');

        // ── Forzar siempre HTTPS, sin depender del request ──
        $redirectUri = str_replace('http://', 'https://', route('gmail.callback'));
        $client->setRedirectUri($redirectUri);

        $tokenPath = storage_path('app/gmail/token.json');
        if (file_exists($tokenPath)) {
            $client->setAccessToken(json_decode(file_get_contents($tokenPath), true));
        }

        return $client;
    }

    /**
     * Página principal — estado de la conexión Gmail.
     */
    public function index()
    {
        $client = $this->makeClient();
        $tokenPath = storage_path('app/gmail/token.json');
        $connected = file_exists($tokenPath) && !$client->isAccessTokenExpired();

        // Último proceso
        $lastRun = DB::connection('fuelcontrol')
            ->table('gmail_imports')
            ->latest('processed_at')
            ->first();

        // Últimos 10 movimientos importados por Gmail
        $recent = DB::connection('fuelcontrol')
            ->table('movimientos')
            ->where('usuario', 'gmail')
            ->latest('created_at')
            ->limit(10)
            ->get();

        // Contadores rápidos
        $stats = [
            'total' => DB::connection('fuelcontrol')->table('movimientos')->where('usuario', 'gmail')->count(),
            'pendientes' => DB::connection('fuelcontrol')->table('movimientos')->where('usuario', 'gmail')->where('estado', 'pendiente')->count(),
            'hoy' => DB::connection('fuelcontrol')->table('movimientos')->where('usuario', 'gmail')->whereDate('created_at', today())->count(),
        ];

        return view('gmail.auth', compact('connected', 'lastRun', 'recent', 'stats'));
    }

    /**
     * Redirige al usuario a la pantalla de autorización de Google.
     */
    public function redirect()
    {
        $client = $this->makeClient();
        $authUrl = $client->createAuthUrl();

        // Log temporal para debug
        \Log::info('Gmail OAuth URL: ' . $authUrl);
        \Log::info('Redirect URI configurada: ' . route('gmail.callback'));

        return redirect()->away($authUrl);
    }

    /**
     * Google redirige aquí después de que el usuario autoriza.
     * Guarda el token y redirige a la vista de estado.
     */
    public function callback(Request $request)
    {
        if ($request->has('error')) {
            return redirect()->route('gmail.index')
                ->with('error', 'Autorización cancelada: ' . $request->get('error'));
        }

        $code = $request->get('code');
        $client = $this->makeClient();

        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            return redirect()->route('gmail.index')
                ->with('error', 'Error al obtener token: ' . $token['error_description']);
        }

        // Guardar token
        $tokenPath = storage_path('app/gmail/token.json');
        @mkdir(dirname($tokenPath), 0755, true);
        file_put_contents($tokenPath, json_encode($token));

        return redirect()->route('gmail.index')
            ->with('success', '¡Gmail conectado correctamente!');
    }

    /**
     * Desconectar — elimina el token guardado.
     */
    public function disconnect()
    {
        $tokenPath = storage_path('app/gmail/token.json');

        if (file_exists($tokenPath)) {
            $client = $this->makeClient();
            $client->revokeToken();
            unlink($tokenPath);
        }

        return redirect()->route('gmail.index')
            ->with('success', 'Gmail desconectado.');
    }

    /**
     * Ejecutar el comando manualmente desde la UI.
     * Devuelve JSON para el polling desde el frontend.
     */
    public function runNow()
    {
        try {
            $exitCode = Artisan::call('gmail:leer-xml');
            $output = Artisan::output();

            return response()->json([
                'ok' => true,
                'output' => $output,
                'code' => $exitCode,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Estado actual en JSON — para el polling automático del frontend.
     */
    public function status()
    {
        $client = $this->makeClient();
        $tokenPath = storage_path('app/gmail/token.json');
        $connected = file_exists($tokenPath) && !$client->isAccessTokenExpired();

        $lastRun = DB::connection('fuelcontrol')
            ->table('gmail_imports')
            ->latest('processed_at')
            ->value('processed_at');

        $stats = [
            'total' => DB::connection('fuelcontrol')->table('movimientos')->where('usuario', 'gmail')->count(),
            'pendientes' => DB::connection('fuelcontrol')->table('movimientos')->where('usuario', 'gmail')->where('estado', 'pendiente')->count(),
            'hoy' => DB::connection('fuelcontrol')->table('movimientos')->where('usuario', 'gmail')->whereDate('created_at', today())->count(),
        ];

        $recent = DB::connection('fuelcontrol')
            ->table('movimientos')
            ->where('usuario', 'gmail')
            ->latest('created_at')
            ->limit(5)
            ->get(['id', 'tipo', 'cantidad', 'estado', 'referencia', 'created_at']);

        return response()->json([
            'connected' => $connected,
            'last_run' => $lastRun,
            'stats' => $stats,
            'recent' => $recent,
        ]);
    }
}