<?php

use App\Http\Controllers\FuelControl\ProductoController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

beforeEach(function () {
    config(['fuelcontrol.estanques' => true, 'fuelcontrol.web_estanques' => false]);
    $schema = Schema::connection('fuelcontrol');
    $schema->create('productos', function (Blueprint $t) {
        $t->integer('id');
        $t->string('nombre');
        $t->decimal('cantidad', 14, 2);
    });
    $schema->create('vehiculos', function (Blueprint $t) {
        $t->integer('id');
        $t->string('descripcion');
        $t->string('patente');
    });
    $schema->create('fuel_estanques', function (Blueprint $t) {
        $t->integer('id');
        $t->integer('producto_id');
        $t->string('nombre');
        $t->timestamp('created_at');
    });
    $schema->create('movimientos', function (Blueprint $t) {
        $t->integer('id');
        $t->integer('producto_id');
        $t->integer('vehiculo_id')->nullable();
        $t->decimal('cantidad', 14, 2);
        $t->string('tipo');
        $t->string('estado');
        $t->timestamp('fecha_movimiento');
        $t->decimal('odometro_bomba', 14, 2);
        $t->integer('estanque_id')->nullable();
        $t->string('usuario');
    });
    $db = DB::connection('fuelcontrol');
    $db->table('productos')->insert(['id' => 13, 'nombre' => 'Diesel', 'cantidad' => 1000]);
    $db->table('fuel_estanques')->insert([
        ['id' => 1, 'producto_id' => 13, 'nombre' => 'Estanque 1', 'created_at' => '2026-10-20 21:13:00'],
        ['id' => 2, 'producto_id' => 13, 'nombre' => 'Estanque 2', 'created_at' => '2026-10-20 21:13:00'],
    ]);
    foreach ([
        [846, 47, 53848, null, '2026-10-20 11:17:18'],
        [861, 23, 53871, 1, '2026-10-20 11:59:14'],
        [862, 100, 100, 2, '2026-10-20 12:05:00'],
        [847, 54, 53925, null, '2026-10-20 12:41:10'],
        [848, 17, 53942, null, '2026-10-20 12:43:48'],
        [855, 132, 54074, 1, '2026-10-21 03:00:00'],
        [863, 10, 9999, null, '2026-10-22 10:00:00'],
        [864, 1, 54075, 1, '2026-10-22 11:00:00'],
    ] as [$id, $litres, $reading, $tank, $date]) {
        $db->table('movimientos')->insert(['id' => $id, 'producto_id' => 13, 'cantidad' => $litres,
            'odometro_bomba' => $reading, 'estanque_id' => $tank, 'fecha_movimiento' => $date,
            'tipo' => 'salida', 'estado' => 'aprobado', 'usuario' => 'Prueba']);
    }
});

test('la auditoría enlaza el contador histórico con el primer estanque sin mezclar el segundo ni registros posteriores sin destino', function () {
    $method = new ReflectionMethod(ProductoController::class, 'getAuditData');
    $data = $method->invoke(app(ProductoController::class), 13);
    $rows = $data['auditData']->keyBy('id');
    expect($rows[861]['prev_odo'])->toBe(53848.0)
        ->and($rows[847]['prev_odo'])->toBe(53871.0)
        ->and($rows[847]['descuadrado'])->toBeFalse()
        ->and($rows[855]['prev_odo'])->toBe(53942.0)
        ->and($rows[862]['prev_odo'])->toBeNull()
        ->and($rows[863]['prev_odo'])->toBeNull()
        ->and($rows[864]['prev_odo'])->toBe(54074.0)
        ->and(DB::connection('fuelcontrol')->table('movimientos')->whereNull('estanque_id')->count())->toBe(4);
});

test('filtrar la auditoría conserva las lecturas de contexto sin mostrar movimientos de otro destino', function ($selectedTank, $expectedIds, $expectedPrevious) {
    config(['fuelcontrol.web_estanques' => true]);
    $method = new ReflectionMethod(ProductoController::class, 'getAuditData');
    $data = $method->invoke(app(ProductoController::class), 13, $selectedTank);
    $rows = $data['auditData']->keyBy('id');

    expect($rows->keys()->all())->toBe($expectedIds);
    foreach ($expectedPrevious as $id => $previous) {
        expect($rows[$id]['prev_odo'])->toBe($previous)
            ->and($rows[$id]['descuadrado'])->toBeFalse();
    }
    expect(DB::connection('fuelcontrol')->table('movimientos')->whereNull('estanque_id')->count())->toBe(4);
})->with([
    'Estanque 1' => ['1', [861, 855, 864], [861 => 53848.0, 855 => 53942.0, 864 => 54074.0]],
    'Estanque 2' => ['2', [862], [862 => null]],
    'Sin estanque' => ['sin_asignar', [846, 847, 848, 863], [847 => 53871.0, 848 => 53925.0, 863 => null]],
]);
