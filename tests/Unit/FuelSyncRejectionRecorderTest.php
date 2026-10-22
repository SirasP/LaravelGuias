<?php

use App\Services\FuelSyncRejectionRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

test('conserva el registro rechazado y el error sin tokens ni campos ajenos a la salida', function () {
    Storage::fake('local');
    $row = ['uuid' => 'uuid-original', 'producto_id' => 13, 'cantidad' => 10,
        'usuario' => 'Cristian', 'estanque_id' => 1, 'odometro_bomba' => 100, 'token' => 'no-guardar'];
    $request = Request::create('/sync_salida.php', 'POST', ['salidas' => [$row], 'device_token' => 'secreto']);
    $request->headers->set('Authorization', 'Bearer secreto');
    $exception = ValidationException::withMessages(['combustible' => 'Conflicto de UUID.']);
    $path = app(FuelSyncRejectionRecorder::class)->record($request, $exception);
    $contents = Storage::disk('local')->get($path);
    $saved = json_decode($contents, true);
    expect($saved['salidas'][0]['uuid'])->toBe('uuid-original')
        ->and($saved['salidas'][0]['cantidad'])->toBe(10)
        ->and($saved['errors']['combustible'])->toBe(['Conflicto de UUID.'])
        ->and($contents)->not->toContain('secreto')->not->toContain('no-guardar');
});

test('un cuerpo inválido no altera el rechazo ni produce registros inventados', function () {
    Storage::fake('local');
    $request = Request::create('/sync_salida.php', 'POST', ['salidas' => 'no es una cola']);
    expect(app(FuelSyncRejectionRecorder::class)->record($request, ValidationException::withMessages(['salidas' => 'Inválido.'])))->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
});
