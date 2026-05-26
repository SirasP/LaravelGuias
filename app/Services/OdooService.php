<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OdooService
{
    private string $url;
    private string $db;
    private string $user;
    private string $password;
    private ?int $uid = null;

    public function __construct()
    {
        $this->url      = rtrim(config('odoo.url'), '/');
        $this->db       = config('odoo.db');
        $this->user     = config('odoo.user');
        $this->password = config('odoo.password');
    }

    /**
     * Autenticar y obtener UID de sesión.
     */
    public function login(): int
    {
        if ($this->uid) {
            return $this->uid;
        }

        $uid = $this->jsonRpc('common', 'login', [$this->db, $this->user, $this->password]);

        if (! $uid) {
            throw new \RuntimeException('Login a Odoo falló. Revisa las credenciales en .env');
        }

        $this->uid = (int) $uid;

        return $this->uid;
    }

    /**
     * search_read sobre un modelo Odoo.
     *
     * @param  string  $model     Modelo Odoo (ej: 'account.move.line')
     * @param  array   $domain    Dominio de búsqueda
     * @param  array   $fields    Campos a retornar
     * @param  array   $options   Opciones adicionales (limit, order, offset…)
     * @return array
     */
    public function searchRead(string $model, array $domain, array $fields, array $options = []): array
    {
        $uid = $this->login();

        return $this->jsonRpc('object', 'execute_kw', [
            $this->db,
            $uid,
            $this->password,
            $model,
            'search_read',
            [$domain],
            array_merge(['fields' => $fields], $options),
        ]);
    }

    /**
     * Llamada JSON-RPC genérica.
     */
    public function jsonRpc(string $service, string $method, array $args): mixed
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
}
