<?php

use App\Services\FuelPendingQueueInbox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->row = ['uuid' => 'original', 'usuario' => 'Cristian', 'producto_id' => 13,
        'cantidad' => 132, 'odometro_bomba' => 54074];
    Storage::disk('local')->put('snapshot.json', json_encode(['salidas' => [$this->row]]));
    config(['fuelcontrol.recuperacion_salidas.cola_revision' => 'snapshot.json']);
});

test('recibe 660 filas con sus identificadores sin fusionar cargas ni afectar existencias', function () {
    $rows = array_map(fn ($i) => [...$this->row, 'uuid' => $i === 0 ? 'original' : 'copia-'.$i], range(0, 659));
    $request = Request::create('/sync_salida.php', 'POST', ['salidas' => $rows]);
    $response = app(FuelPendingQueueInbox::class)->receive($request);
    $data = $response->getData(true);
    $saved = json_decode(Storage::disk('local')->get('fuelcontrol/received-sync/'.$data['recibo'].'.json'), true);
    expect($response->getStatusCode())->toBe(200)
        ->and($data['estado'])->toBe('recibido_para_revision')
        ->and($data['registros_recibidos'])->toBe(660)
        ->and($data['stock_modificado'])->toBeFalse()
        ->and($saved['salidas'])->toBe($rows)
        ->and($saved['status'])->toBe('pendiente_revision');
    // No existe ninguna tabla de stock en esta prueba: recibir no consulta ni modifica existencias.
});

test('el mismo reintento devuelve el mismo recibo y conserva diferencias para revisión', function () {
    $rows = [$this->row, [...$this->row, 'uuid' => 'otro', 'cantidad' => 133]];
    $request = Request::create('/sync_salida.php', 'POST', ['salidas' => $rows]);
    $inbox = app(FuelPendingQueueInbox::class);
    $first = $inbox->receive($request)->getData(true);
    $second = $inbox->receive($request)->getData(true);
    expect($first)->toBe($second)
        ->and(Storage::disk('local')->files('fuelcontrol/received-sync'))->toHaveCount(1);
    $saved = json_decode(Storage::disk('local')->get('fuelcontrol/received-sync/'.$first['recibo'].'.json'), true);
    expect(array_column($saved['salidas'], 'cantidad'))->toBe([132, 133]);
});

test('las salidas nuevas ajenas a la cola siguen pasando por las validaciones normales', function () {
    $request = Request::create('/sync_salida.php', 'POST', ['salidas' => [[...$this->row, 'uuid' => 'nueva']]]);
    expect(app(FuelPendingQueueInbox::class)->receive($request))->toBeNull()
        ->and(Storage::disk('local')->files('fuelcontrol/received-sync'))->toBeEmpty();
});

test('no confirma al teléfono si el contenido guardado no coincide aunque conserve el hash declarado', function () {
    $request = Request::create('/sync_salida.php', 'POST', ['salidas' => [$this->row]]);
    $inbox = app(FuelPendingQueueInbox::class);
    $data = $inbox->receive($request)->getData(true);
    $path = 'fuelcontrol/received-sync/'.$data['recibo'].'.json';
    $saved = json_decode(Storage::disk('local')->get($path), true);
    $saved['salidas'][0]['cantidad'] = 999;
    Storage::disk('local')->put($path, json_encode($saved));

    expect(fn () => $inbox->receive($request))->toThrow(RuntimeException::class, 'El respaldo no coincide');
});

test('no confirma una cola mezclada con registros de otro operador', function () {
    $request = Request::create('/sync_salida.php', 'POST', ['salidas' => [
        $this->row, [...$this->row, 'uuid' => 'otro', 'usuario' => 'Otro operador'],
    ]]);

    expect(fn () => app(FuelPendingQueueInbox::class)->receive($request))
        ->toThrow(Illuminate\Validation\ValidationException::class);
    expect(Storage::disk('local')->files('fuelcontrol/received-sync'))->toBeEmpty();
});
