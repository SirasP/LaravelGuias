<?php

namespace App\Services\PurchaseRequests\Odoo;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente JSON-RPC de Odoo, lo justo para lo que hacemos.
 *
 * Se usa JSON-RPC y no XML-RPC —el camino habitual en Odoo— porque PHP 8 ya
 * no trae la extensión de XML-RPC, y porque así el tráfico pasa por el cliente
 * HTTP de Laravel: se puede simular en las pruebas y nunca sale una llamada
 * de verdad desde la suite.
 */
class OdooClient
{
    private ?int $uid = null;

    public function __construct(
        private readonly string $url,
        private readonly string $db,
        private readonly string $user,
        private readonly string $password,
        private readonly int $timeout = 30,
    ) {}

    /** Identifica al usuario y devuelve su uid. Se recuerda para la sesión. */
    public function uid(): int
    {
        if ($this->uid !== null) {
            return $this->uid;
        }

        $uid = $this->llamar('common', 'login', [$this->db, $this->user, $this->password]);

        if (! is_int($uid) || $uid <= 0) {
            throw new RuntimeException('Odoo rechazó las credenciales.');
        }

        return $this->uid = $uid;
    }

    /**
     * Ejecuta un método sobre un modelo de Odoo.
     *
     * @param  list<mixed>  $args
     * @param  array<string, mixed>  $kwargs
     */
    public function execute(string $modelo, string $metodo, array $args = [], array $kwargs = []): mixed
    {
        return $this->llamar('object', 'execute_kw', [
            $this->db, $this->uid(), $this->password, $modelo, $metodo, $args, (object) $kwargs,
        ]);
    }

    /**
     * @param  list<mixed>  $args
     */
    private function llamar(string $servicio, string $metodo, array $args): mixed
    {
        $respuesta = Http::timeout($this->timeout)
            ->acceptJson()
            ->post(rtrim($this->url, '/').'/jsonrpc', [
                'jsonrpc' => '2.0',
                'method' => 'call',
                'params' => ['service' => $servicio, 'method' => $metodo, 'args' => $args],
                'id' => random_int(1, PHP_INT_MAX),
            ])
            ->throw();

        $cuerpo = $respuesta->json();

        // Odoo devuelve 200 aunque haya fallado: el error viene en el cuerpo.
        if (isset($cuerpo['error'])) {
            throw new RuntimeException(
                (string) ($cuerpo['error']['data']['message'] ?? $cuerpo['error']['message'] ?? 'Odoo devolvió un error.'),
            );
        }

        return $cuerpo['result'] ?? null;
    }
}
