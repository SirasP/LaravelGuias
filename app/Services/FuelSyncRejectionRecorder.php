<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Respaldo privado del rechazo original; no modifica ni confirma ninguna salida. */
class FuelSyncRejectionRecorder
{
    public function record(Request $request, ValidationException $exception): ?string
    {
        $rows = $request->input('salidas');
        if (! is_array($rows) || count($rows) > 1000) {
            return null;
        }
        $keys = array_flip(['uuid', 'producto_id', 'vehiculo_id', 'cantidad', 'usuario',
            'odometro', 'odometro_bomba', 'estanque_id', 'fecha_movimiento']);
        $content = json_encode([
            'received_at' => now()->toIso8601String(),
            'errors' => $exception->errors(),
            'user_agent' => Str::limit((string) $request->userAgent(), 200, ''),
            'salidas' => array_map(fn ($row) => is_array($row) ? array_intersect_key($row, $keys) : null, $rows),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (strlen($content) > 1024 * 1024) {
            return null;
        }
        $path = 'fuelcontrol/rejected-sync/'.now()->format('Ymd_His_u').'-'.Str::random(8).'.json';
        if (! Storage::disk('local')->put($path, $content)) {
            throw new \RuntimeException('No se pudo guardar el diagnóstico privado de sincronización.');
        }

        return $path;
    }
}
