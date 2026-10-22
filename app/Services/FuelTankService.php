<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FuelTankService
{
    private function db()
    {
        return DB::connection('fuelcontrol');
    }

    public static function ordenesCompra(\SimpleXMLElement $xml): array
    {
        $xml->registerXPathNamespace('sii', 'http://www.sii.cl/SiiDte');
        $numbers = [];
        foreach ($xml->xpath('//sii:Referencia') ?: [] as $reference) {
            if (trim((string) $reference->TpoDocRef) === '801') {
                $number = strtoupper(trim((string) $reference->FolioRef));
                if ($number !== '') {
                    $numbers[] = $number;
                }
            }
        }

        return array_values(array_unique($numbers));
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['combustible' => $message]);
    }

    public function isManagedProduct(int $productId): bool
    {
        return config('fuelcontrol.estanques') && $this->db()->table('fuel_estanques')->where('producto_id', $productId)->exists();
    }

    public function rejectUnassignedStockChange(int $productId): void
    {
        if ($this->isManagedProduct($productId)) {
            $this->fail('El diésel se registra por estanque desde la app o mediante una factura con OC y destino definido.');
        }
    }

    public function initialize(array $balances): void
    {
        $this->db()->transaction(function () use ($balances) {
            $ids = $this->db()->table('fuel_estanques')->pluck('producto_id')->unique();
            if ($ids->count() !== 1) {
                $this->fail('Primero configura los dos estanques de diésel.');
            }
            $product = $this->db()->table('productos')->where('id', $ids->first())->lockForUpdate()->first();
            $tanks = $this->db()->table('fuel_estanques')->orderBy('id')->lockForUpdate()->get();
            if ($tanks->contains(fn ($tank) => (bool) $tank->inicializado)) {
                $this->fail('El saldo inicial ya fue confirmado.');
            }
            $sum = array_sum(array_map(fn ($b) => (int) round($b['cantidad'] * 100), $balances));
            if ($sum !== (int) round($product->cantidad * 100) || count($balances) !== $tanks->count()) {
                $this->fail('La suma de ambos estanques debe coincidir con el saldo general de diésel.');
            }
            foreach ($tanks as $tank) {
                $balance = $balances[$tank->id] ?? null;
                if (! $balance || $balance['cantidad'] < 0 || ($balance['contador'] ?? 0) < 0) {
                    $this->fail('Falta una cantidad válida para cada estanque.');
                }
                $this->db()->table('fuel_estanques')->where('id', $tank->id)->update([
                    'cantidad' => $balance['cantidad'], 'saldo_inicial' => $balance['cantidad'],
                    'ultimo_contador' => $balance['contador'] ?? null,
                    'inicializado' => true, 'updated_at' => now(),
                ]);
            }
        });
    }

    /** El lote completo se confirma o se revierte; cada UUID tiene una respuesta estable. */
    public function salidas(array $rows): array
    {
        return $this->db()->transaction(function () use ($rows) {
            $results = [];
            foreach ($rows as $row) {
                $original = $row;
                $product = $this->db()->table('productos')->where('id', $row['producto_id'])->lockForUpdate()->first();
                if (! $product) {
                    $this->fail('Producto no encontrado.');
                }
                $isDiesel = str_contains(strtolower($product->nombre), 'diesel') || str_contains(mb_strtolower($product->nombre), 'diésel');
                $recoveryTankId = $isDiesel ? $this->recoveryTankId($row) : null;
                $recovered = $recoveryTankId !== null && ! isset($row['estanque_id']);
                if ($recovered) {
                    $row['estanque_id'] = $recoveryTankId;
                }
                $payload = [
                    'producto_id' => (int) $row['producto_id'], 'vehiculo_id' => (int) $row['vehiculo_id'],
                    'cantidad' => number_format($row['cantidad'], 2, '.', ''),
                    'estanque_id' => isset($row['estanque_id']) ? (int) $row['estanque_id'] : null,
                    'odometro' => number_format($row['odometro'] ?? 0, 2, '.', ''),
                    'odometro_bomba' => number_format($row['odometro_bomba'] ?? 0, 2, '.', ''),
                    'usuario' => $row['usuario'],
                ];
                $hash = hash('sha256', json_encode($payload));
                $key = 'salida:'.$row['uuid'];
                $previous = $this->db()->table('fuel_operaciones')->where('clave', $key)->first();
                if ($previous) {
                    if ($previous->payload_hash !== $hash) {
                        $this->fail('El identificador de esta salida ya tiene otros datos.');
                    }
                    $receipt = json_decode($previous->respuesta, true);
                    $results[] = ['uuid' => $receipt['uuid'], 'movimiento_id' => $receipt['movimiento_id'],
                        'estanque_id' => $receipt['estanque_id'], 'status' => 'duplicado'];

                    continue;
                }
                $legacy = $this->db()->table('movimientos')->where('hash_unico', md5($row['uuid']))->first();
                if ($legacy) {
                    $results[] = ['uuid' => $row['uuid'], 'movimiento_id' => $legacy->id, 'estanque_id' => $legacy->estanque_id, 'status' => 'duplicado'];

                    continue;
                }
                $tank = null;
                if ($isDiesel) {
                    $tank = $this->db()->table('fuel_estanques')->where('id', $row['estanque_id'] ?? 0)->lockForUpdate()->first();
                    if (! $tank || (int) $tank->producto_id !== (int) $product->id || ! $tank->inicializado) {
                        $this->fail('Selecciona un estanque con saldo inicial confirmado.');
                    }
                    if ($row['cantidad'] > $tank->cantidad) {
                        $this->fail('Stock insuficiente en '.$tank->nombre.'.');
                    }
                    $lateCounter = $tank->ultimo_contador !== null && $row['odometro_bomba'] < $tank->ultimo_contador;
                    if ($lateCounter && $recoveryTankId === (int) $tank->id) {
                        $recovered = true;
                    }
                    if (($row['odometro_bomba'] ?? 0) <= 0 || ($lateCounter && ! $recovered)) {
                        $this->fail('La lectura debe corresponder al contador de '.$tank->nombre.'.');
                    }
                } elseif (isset($row['estanque_id'])) {
                    $this->fail('La gasolina conserva su salida directa, sin estanque de diésel.');
                }
                if ($row['cantidad'] > $product->cantidad) {
                    $this->fail('Stock general insuficiente.');
                }
                $movement = $this->db()->table('movimientos')->insertGetId([
                    'producto_id' => $product->id, 'estanque_id' => $tank?->id,
                    'vehiculo_id' => $row['vehiculo_id'], 'cantidad' => $row['cantidad'],
                    'tipo' => 'salida', 'usuario' => mb_substr($row['usuario'], 0, 50),
                    'fecha_movimiento' => isset($row['fecha_movimiento']) ? \Carbon\Carbon::parse($row['fecha_movimiento'])->utc()->format('Y-m-d H:i:s') : now(),
                    'hash_unico' => md5($row['uuid']), 'estado' => 'aprobado', 'origen' => $recovered ? 'app_estanque_recuperado' : 'app_estanque',
                    'odometro' => $row['odometro'] ?? 0, 'odometro_bomba' => $row['odometro_bomba'] ?? 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->db()->table('productos')->where('id', $product->id)->decrement('cantidad', $row['cantidad']);
                if ($tank) {
                    $this->db()->table('fuel_estanques')->where('id', $tank->id)->update([
                        'cantidad' => round($tank->cantidad - $row['cantidad'], 2),
                        'ultimo_contador' => max((float) ($tank->ultimo_contador ?? 0), (float) $row['odometro_bomba']), 'updated_at' => now(),
                    ]);
                }
                $result = ['uuid' => $row['uuid'], 'movimiento_id' => $movement, 'estanque_id' => $tank?->id, 'status' => 'ok'];
                $this->db()->table('fuel_operaciones')->insert([
                    'clave' => $key, 'payload_hash' => $hash,
                    'respuesta' => json_encode([...$result, 'solicitud_original' => $original, 'recuperado' => $recovered], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]);
                $results[] = $result;
            }

            return $results;
        });
    }

    /** Recupera sólo el operador, destino y período autorizados, sin bajar el contador vigente. */
    private function recoveryTankId(array $row): ?int
    {
        $settings = config('fuelcontrol.recuperacion_salidas', []);
        $tankId = (int) ($settings['estanque_id'] ?? 0);
        $normalize = fn ($name) => mb_strtolower(Str::ascii(trim(preg_replace('/\s+/u', ' ', (string) $name))));
        $users = array_map($normalize, $settings['usuarios'] ?? []);
        if (! $tankId || ! in_array($normalize($row['usuario']), $users, true)) {
            return null;
        }
        // Las salidas sin fecha son el contrato antiguo; su destino fue confirmado por el usuario.
        if (! empty($row['fecha_movimiento'])) {
            $limit = $settings['fecha_limite'] ?? null;
            if (! $limit || \Carbon\Carbon::parse($row['fecha_movimiento'])->greaterThan(\Carbon\Carbon::parse($limit))) {
                return null;
            }
        }

        return $tankId;
    }

    /** Una entrada sin destino inequívoco queda pendiente y no suma litros. */
    public function entrada(int $productId, float $litres, array $orders, string $sourceHash, array $attributes, bool $allowAutomatic = true): int
    {
        return $this->db()->transaction(function () use ($productId, $litres, $orders, $sourceHash, $attributes, $allowAutomatic) {
            if ($litres <= 0) {
                $this->fail('Los litros recibidos deben ser positivos.');
            }
            $product = $this->db()->table('productos')->where('id', $productId)->lockForUpdate()->first();
            if (! $product) {
                $this->fail('Producto no encontrado.');
            }
            $existing = $this->db()->table('movimientos')->where('hash_unico', $sourceHash)->first();
            if ($existing) {
                return (int) $existing->id;
            }
            $orders = array_values(array_unique(array_filter(array_map(fn ($oc) => strtoupper(trim($oc)), $orders))));
            $destinations = $this->db()->table('fuel_oc_destinos')->whereIn('numero_oc', $orders)->get();
            $tank = null;
            if ($allowAutomatic && count($orders) > 0 && $destinations->count() === count($orders) && $destinations->pluck('estanque_id')->unique()->count() === 1) {
                $tank = $this->db()->table('fuel_estanques')->where('id', $destinations->first()->estanque_id)->where('producto_id', $productId)->where('inicializado', true)->lockForUpdate()->first();
            }
            if ($tank) {
                $this->db()->table('fuel_estanques')->where('id', $tank->id)->increment('cantidad', $litres);
                $this->db()->table('productos')->where('id', $productId)->increment('cantidad', $litres);
            }

            return $this->db()->table('movimientos')->insertGetId([
                ...$attributes, 'producto_id' => $productId, 'cantidad' => $litres, 'tipo' => 'entrada',
                'estanque_id' => $tank?->id, 'orden_compra' => count($orders) === 1 ? $orders[0] : null,
                'hash_unico' => $sourceHash, 'estado' => $tank ? 'aprobado' : 'pendiente',
                'origen' => 'xml_oc_estanque', 'requiere_revision' => $tank ? 0 : 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function confirmarEntrada(int $movementId): void
    {
        $this->db()->transaction(function () use ($movementId) {
            $candidate = $this->db()->table('movimientos')->where('id', $movementId)->first();
            if (! $candidate || $candidate->origen !== 'xml_oc_estanque') {
                $this->fail('Esta recepción no pertenece al flujo de estanques.');
            }
            $this->db()->table('productos')->where('id', $candidate->producto_id)->lockForUpdate()->first();
            $movement = $this->db()->table('movimientos')->where('id', $movementId)->lockForUpdate()->first();
            if ($movement->estado === 'aprobado') {
                return;
            }
            if ($movement->estado !== 'pendiente' || ! $movement->orden_compra) {
                $this->fail('La recepción necesita una OC con destino definido.');
            }
            $destination = $this->db()->table('fuel_oc_destinos')->where('numero_oc', $movement->orden_compra)->first();
            $tank = $this->db()->table('fuel_estanques')->where('id', $destination?->estanque_id ?? 0)->where('producto_id', $movement->producto_id)->where('inicializado', true)->lockForUpdate()->first();
            if (! $tank) {
                $this->fail('Asigna el destino de la OC y confirma el saldo inicial del estanque.');
            }
            $this->db()->table('productos')->where('id', $movement->producto_id)->increment('cantidad', $movement->cantidad);
            $this->db()->table('fuel_estanques')->where('id', $tank->id)->increment('cantidad', $movement->cantidad);
            $this->db()->table('movimientos')->where('id', $movementId)->update(['estado' => 'aprobado', 'estanque_id' => $tank->id, 'requiere_revision' => false, 'updated_at' => now()]);
        });
    }
}
