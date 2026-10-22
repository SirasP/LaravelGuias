<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Recibe una cola identificada para revisión, sin convertir filas ni copias en consumos. */
class FuelPendingQueueInbox
{
    public function receive(Request $request): ?JsonResponse
    {
        $source = config('fuelcontrol.recuperacion_salidas.cola_revision');
        $rows = $request->input('salidas');
        $disk = Storage::disk('local');
        if (! $source || ! is_array($rows) || ! $disk->exists($source)) {
            return null;
        }
        $snapshot = json_decode($disk->get($source), true, flags: JSON_THROW_ON_ERROR);
        $known = array_fill_keys(array_column($snapshot['salidas'] ?? [], 'uuid'), true);
        $matches = array_filter($rows, fn ($r) => is_array($r) && isset($known[$r['uuid'] ?? '']));
        if ($matches === []) {
            return null;
        }
        if (count($rows) > 2000) {
            throw ValidationException::withMessages(['salidas' => 'La cola de revisión supera el tamaño admitido.']);
        }
        $operators = array_unique(array_column($snapshot['salidas'], 'usuario'));
        foreach ($rows as $row) {
            if (! is_array($row) || ! is_string($row['uuid'] ?? null) || ! in_array($row['usuario'] ?? null, $operators, true)) {
                throw ValidationException::withMessages(['salidas' => 'La cola contiene registros que requieren identificar al operador.']);
            }
        }
        // Se conserva cada fila e identificador, incluso las copias y las variantes.
        $payload = json_encode(['salidas' => $rows], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (strlen($payload) > 2 * 1024 * 1024) {
            throw ValidationException::withMessages(['salidas' => 'La cola de revisión supera el tamaño admitido.']);
        }
        $hash = hash('sha256', $payload);
        $path = 'fuelcontrol/received-sync/'.$hash.'.json';
        if (! $disk->exists($path)) {
            $record = json_encode([
                'received_at' => now()->toIso8601String(), 'status' => 'pendiente_revision',
                'stock_modificado' => false, 'registros_recibidos' => count($rows),
                'payload_sha256' => $hash, 'source_snapshot' => $source,
                'salidas' => $rows,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $temp = $path.'.tmp-'.bin2hex(random_bytes(4));
            if (! $disk->put($temp, $record) || ! $disk->move($temp, $path)) {
                throw new \RuntimeException('No se pudo respaldar la cola. El teléfono debe conservarla pendiente.');
            }
        }
        $saved = json_decode($disk->get($path), true, flags: JSON_THROW_ON_ERROR);
        $savedPayload = json_encode(['salidas' => $saved['salidas'] ?? []], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (($saved['payload_sha256'] ?? null) !== $hash || hash('sha256', $savedPayload) !== $hash) {
            throw new \RuntimeException('El respaldo no coincide. La cola no se confirma al teléfono.');
        }

        return response()->json([
            'status' => 'ok', 'estado' => 'recibido_para_revision',
            'registros_recibidos' => count($rows), 'stock_modificado' => false, 'recibo' => $hash,
        ]);
    }
}
