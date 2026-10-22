<?php

use App\Services\FuelConsumptionCharts;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

beforeEach(function () {
    config(['fuelcontrol.estanques' => true, 'fuelcontrol.web_estanques' => true]);
    $schema = Schema::connection('fuelcontrol');
    $schema->create('productos', function (Blueprint $table) {
        $table->integer('id');
        $table->string('nombre');
    });
    $schema->create('fuel_estanques', function (Blueprint $table) {
        $table->integer('id');
        $table->integer('producto_id');
        $table->string('nombre');
    });
    $schema->create('movimientos', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('producto_id');
        $table->integer('estanque_id')->nullable();
        $table->decimal('cantidad', 14, 2);
        $table->string('tipo');
        $table->string('estado')->nullable();
        $table->timestamp('fecha_movimiento');
    });
    $this->db = DB::connection('fuelcontrol');
    $this->db->table('productos')->insert([
        ['id' => 13, 'nombre' => 'Diesel'], ['id' => 14, 'nombre' => 'Gasolina'],
    ]);
    $this->db->table('fuel_estanques')->insert([
        ['id' => 1, 'producto_id' => 13, 'nombre' => 'Estanque 1'],
        ['id' => 2, 'producto_id' => 13, 'nombre' => 'Estanque 2'],
    ]);
    $this->movement = ['producto_id' => 13, 'estanque_id' => 1, 'cantidad' => 10,
        'tipo' => 'salida', 'estado' => 'aprobado', 'fecha_movimiento' => '2026-10-21 12:00:00'];
    $this->from = Carbon::parse('2026-09-23 00:00:00');
    $this->until = Carbon::parse('2026-10-22 16:00:00');
});

test('los gráficos separan estanques y mantienen históricos y cargas directas sólo en el total', function () {
    $this->db->table('movimientos')->insert([
        $this->movement,
        [...$this->movement, 'estanque_id' => 2, 'cantidad' => -20],
        [...$this->movement, 'estanque_id' => null, 'cantidad' => 30, 'estado' => null],
        [...$this->movement, 'estanque_id' => null, 'cantidad' => 40, 'tipo' => 'vehiculo'],
        [...$this->movement, 'cantidad' => 900, 'estado' => 'pendiente'],
        [...$this->movement, 'cantidad' => 800, 'estado' => 'rechazado'],
        [...$this->movement, 'cantidad' => 700, 'tipo' => 'entrada'],
        [...$this->movement, 'producto_id' => 14, 'estanque_id' => null, 'cantidad' => 12],
        // Un producto ajeno a un estanque no se convierte en consumo de diésel.
        [...$this->movement, 'producto_id' => 14, 'cantidad' => 3],
    ]);

    $series = app(FuelConsumptionCharts::class)->series($this->from, $this->until);
    expect(array_sum($series['diesel']))->toBe(100.0)
        ->and(array_sum($series['gasolina']))->toBe(15.0)
        ->and(array_column($series['estanques'], 'id'))->toBe([1, 2])
        ->and(array_sum($series['estanques'][0]['data']))->toBe(10.0)
        ->and(array_sum($series['estanques'][1]['data']))->toBe(20.0)
        ->and($this->db->table('movimientos')->whereNull('estanque_id')->count())->toBe(3);
});

test('comparte treinta fechas completas, rellena días sin consumo y excluye otros períodos', function () {
    $this->db->table('movimientos')->insert([
        [...$this->movement, 'fecha_movimiento' => '2026-09-22 23:59:59', 'cantidad' => 100],
        [...$this->movement, 'fecha_movimiento' => '2026-09-23 00:00:00', 'cantidad' => 2.5],
        [...$this->movement, 'fecha_movimiento' => '2026-10-22 23:59:59', 'cantidad' => 4.25],
        [...$this->movement, 'fecha_movimiento' => '2026-10-23 00:00:00', 'cantidad' => 200],
    ]);

    $series = app(FuelConsumptionCharts::class)->series($this->from, $this->until);
    expect($series['labels'])->toHaveCount(30)
        ->and($series['labels'][0])->toBe('2026-09-23')
        ->and($series['labels'][29])->toBe('2026-10-22')
        ->and($series['diesel'][0])->toBe(2.5)
        ->and($series['diesel'][1])->toBe(0.0)
        ->and($series['diesel'][29])->toBe(4.25)
        ->and($series['estanques'][1]['data'])->toBe(array_fill(0, 30, 0.0))
        ->and(array_sum($series['diesel']))->toBe(6.75)
        ->and($this->from->toDateTimeString())->toBe('2026-09-23 00:00:00');
});

test('el perfil anterior funciona sin la tabla ni la columna de estanques', function () {
    config(['fuelcontrol.web_estanques' => false]);
    Schema::connection('fuelcontrol')->drop('fuel_estanques');
    Schema::connection('fuelcontrol')->table('movimientos', fn (Blueprint $table) => $table->dropColumn('estanque_id'));
    $movement = $this->movement;
    unset($movement['estanque_id']);
    $this->db->table('movimientos')->insert($movement);

    $series = app(FuelConsumptionCharts::class)->series($this->from, $this->until);
    expect($series['estanques'])->toBe([])
        ->and(array_sum($series['diesel']))->toBe(10.0);
});
