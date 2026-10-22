<?php

use App\Services\FuelTankService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

beforeEach(function () {
    $schema = Schema::connection('fuelcontrol');
    $schema->create('productos', function (Blueprint $t) {
        $t->increments('id');
        $t->string('nombre');
        $t->decimal('cantidad', 14, 2);
    });
    $schema->create('movimientos', function (Blueprint $t) {
        $t->increments('id');
        $t->integer('producto_id');
        $t->integer('vehiculo_id')->nullable();
        $t->decimal('cantidad', 14, 2);
        $t->string('tipo');
        $t->string('usuario');
        $t->timestamp('fecha_movimiento');
        $t->string('hash_unico')->nullable();
        $t->string('estado');
        $t->string('origen');
        $t->boolean('requiere_revision')->default(false);
        $t->decimal('odometro', 14, 2)->default(0);
        $t->decimal('odometro_bomba', 14, 2)->default(0);
        $t->string('xml_path')->nullable();
        $t->string('referencia')->nullable();
        $t->timestamps();
    });
    (require database_path('migrations/fuelcontrol/2026_10_20_190000_create_fuel_estanques.php'))->up();
    $db = DB::connection('fuelcontrol');
    $db->table('productos')->insert([['id' => 13, 'nombre' => 'diesel', 'cantidad' => 1000], ['id' => 14, 'nombre' => 'Gasolina', 'cantidad' => 32.27]]);
    $db->table('fuel_estanques')->insert([
        ['id' => 1, 'nombre' => 'Estanque 1', 'producto_id' => 13, 'cantidad' => 600, 'inicializado' => true, 'ultimo_contador' => 10000],
        ['id' => 2, 'nombre' => 'Estanque 2', 'producto_id' => 13, 'cantidad' => 400, 'inicializado' => true, 'ultimo_contador' => 200],
    ]);
    $this->fuel = new FuelTankService;
    $this->db = $db;
    $this->row = ['uuid' => (string) Str::uuid(), 'producto_id' => 13, 'vehiculo_id' => 7, 'estanque_id' => 2, 'cantidad' => 50, 'usuario' => 'Prueba', 'odometro' => 1000, 'odometro_bomba' => 250, 'fecha_movimiento' => '2026-10-20T15:00:00.000Z'];
});

test('descuenta sólo del estanque elegido y del total, con contador propio', function () {
    $this->fuel->salidas([$this->row]);
    expect((float) $this->db->table('fuel_estanques')->where('id', 1)->value('cantidad'))->toBe(600.0)
        ->and((float) $this->db->table('fuel_estanques')->where('id', 2)->value('cantidad'))->toBe(350.0)
        ->and((float) $this->db->table('fuel_estanques')->where('id', 1)->value('ultimo_contador'))->toBe(10000.0)
        ->and((float) $this->db->table('productos')->where('id', 13)->value('cantidad'))->toBe(950.0)
        ->and((float) $this->db->table('productos')->where('id', 14)->value('cantidad'))->toBe(32.27);
});

test('un reintento con el mismo UUID descuenta sólo una vez', function () {
    $this->fuel->salidas([$this->row]);
    $response = $this->fuel->salidas([$this->row]);
    expect($this->db->table('movimientos')->count())->toBe(1)
        ->and($response[0]['status'])->toBe('duplicado')
        ->and((float) $this->db->table('fuel_estanques')->where('id', 2)->value('cantidad'))->toBe(350.0);
});

test('el endpoint existente entrega estanques sólo a clientes que los solicitan', function () {
    config(['fuelcontrol.estanques' => true]);
    $adapter = app(\App\Services\FuelTankLegacyAdapter::class);
    expect($adapter->handle('catalogo', \Illuminate\Http\Request::create('/get_producto.php')))->toBeNull();
    $response = $adapter->handle('catalogo', \Illuminate\Http\Request::create('/get_producto.php?estanques=1'));
    $catalog = json_decode($response->getContent(), true);
    expect(array_column($catalog['estanques'], 'id'))->toBe([1, 2])
        ->and(array_column($catalog['productos'], 'id'))->toEqualCanonicalizing([13, 14]);
    config(['fuelcontrol.estanques' => false]);
    expect($adapter->handle('catalogo', \Illuminate\Http\Request::create('/get_producto.php?estanques=1')))->toBeNull();
});

test('el adaptador móvil rechaza diésel antiguo sin descontar y acepta el estanque con UUID', function () {
    config(['fuelcontrol.estanques' => true]);
    Schema::connection('fuelcontrol')->create('vehiculos', fn (Blueprint $t) => $t->increments('id'));
    $this->db->table('vehiculos')->insert(['id' => 7]);
    $adapter = app(\App\Services\FuelTankLegacyAdapter::class);
    $legacy = $this->row;
    unset($legacy['estanque_id']);
    $request = \Illuminate\Http\Request::create('/sync_salida.php', 'POST', ['salidas' => [$legacy]]);
    expect(fn () => $adapter->handle('salidas', $request))->toThrow(ValidationException::class);
    expect($this->db->table('movimientos')->count())->toBe(0);
    $request = \Illuminate\Http\Request::create('/sync_salida.php', 'POST', ['salidas' => [$this->row]]);
    $first = json_decode($adapter->handle('salidas', $request)->getContent(), true);
    $repeat = json_decode($adapter->handle('salidas', $request)->getContent(), true);
    expect($first['detalle'][0]['estanque_id'])->toBe(2)
        ->and($repeat['detalle'][0]['status'])->toBe('duplicado')
        ->and((float) $this->db->table('fuel_estanques')->where('id', 2)->value('cantidad'))->toBe(350.0)
        ->and($this->db->table('movimientos')->count())->toBe(1);
});

test('rechaza reutilizar el UUID para otro estanque', function () {
    $this->fuel->salidas([$this->row]);
    $this->fuel->salidas([[...$this->row, 'estanque_id' => 1, 'odometro_bomba' => 10050]]);
})->throws(ValidationException::class);

test('stock de otro estanque no cubre una salida insuficiente', function () {
    expect(fn () => $this->fuel->salidas([[...$this->row, 'cantidad' => 450, 'odometro_bomba' => 650]]))->toThrow(ValidationException::class);
    expect($this->db->table('movimientos')->count())->toBe(0)
        ->and((float) $this->db->table('productos')->where('id', 13)->value('cantidad'))->toBe(1000.0);
});

test('un lote fallido revierte incluso sus primeras salidas', function () {
    expect(fn () => $this->fuel->salidas([$this->row, [...$this->row, 'uuid' => (string) Str::uuid(), 'cantidad' => 500, 'odometro_bomba' => 750]]))->toThrow(ValidationException::class);
    expect($this->db->table('movimientos')->count())->toBe(0)
        ->and($this->db->table('fuel_operaciones')->count())->toBe(0)
        ->and((float) $this->db->table('fuel_estanques')->where('id', 2)->value('cantidad'))->toBe(400.0);
});

describe('recuperación autorizada de pendientes antiguos', function () {
    beforeEach(function () {
        config(['fuelcontrol.recuperacion_salidas' => [
            'estanque_id' => 1, 'usuarios' => ['Prueba'], 'fecha_limite' => '2026-10-22T15:00:00Z',
        ]]);
        $this->oldRow = [...$this->row, 'odometro_bomba' => 9900];
        unset($this->oldRow['estanque_id'], $this->oldRow['fecha_movimiento']);
    });

    test('recupera el destino confirmado, conserva el original y no retrocede el contador', function () {
        $result = $this->fuel->salidas([$this->oldRow]);
        $repeat = $this->fuel->salidas([$this->oldRow]);
        $receipt = json_decode($this->db->table('fuel_operaciones')->sole()->respuesta, true);
        $movement = $this->db->table('movimientos')->sole();
        expect($result[0]['estanque_id'])->toBe(1)
            ->and($repeat[0]['status'])->toBe('duplicado')
            ->and($repeat[0])->not->toHaveKey('solicitud_original')
            ->and($receipt['solicitud_original'])->toBe($this->oldRow)
            ->and($receipt['recuperado'])->toBeTrue()
            ->and($movement->origen)->toBe('app_estanque_recuperado')
            ->and((float) $movement->odometro_bomba)->toBe(9900.0)
            ->and((float) $this->db->table('fuel_estanques')->where('id', 1)->value('ultimo_contador'))->toBe(10000.0)
            ->and((float) $this->db->table('fuel_estanques')->where('id', 1)->value('cantidad'))->toBe(550.0)
            ->and((float) $this->db->table('fuel_estanques')->where('id', 2)->value('cantidad'))->toBe(400.0)
            ->and((float) $this->db->table('productos')->where('id', 13)->value('cantidad'))->toBe(950.0)
            ->and($this->db->table('movimientos')->count())->toBe(1);
    });

    test('no adivina el destino de otros operadores ni admite lecturas cero', function () {
        expect(fn () => $this->fuel->salidas([[...$this->oldRow, 'usuario' => 'Otro operador']]))->toThrow(ValidationException::class)
            ->and(fn () => $this->fuel->salidas([[...$this->oldRow, 'odometro_bomba' => 0]]))->toThrow(ValidationException::class)
            ->and($this->db->table('movimientos')->count())->toBe(0)
            ->and($this->db->table('fuel_operaciones')->count())->toBe(0);
    });

    test('admite el mismo UUID tras actualizar la app con el destino explícito sin descontar otra vez', function () {
        $this->fuel->salidas([$this->oldRow]);
        $result = $this->fuel->salidas([[...$this->oldRow, 'estanque_id' => 1]]);
        expect($result[0]['status'])->toBe('duplicado')
            ->and($this->db->table('movimientos')->count())->toBe(1)
            ->and((float) $this->db->table('productos')->where('id', 13)->value('cantidad'))->toBe(950.0);
    });

    test('no acepta reutilizar un UUID recuperado con otra cantidad o estanque', function () {
        $this->fuel->salidas([$this->oldRow]);
        expect(fn () => $this->fuel->salidas([[...$this->oldRow, 'cantidad' => 2]]))->toThrow(ValidationException::class)
            ->and(fn () => $this->fuel->salidas([[...$this->oldRow, 'estanque_id' => 2]]))->toThrow(ValidationException::class)
            ->and($this->db->table('movimientos')->count())->toBe(1);
    });

    test('sólo recupera lecturas explícitas atrasadas del destino y período confirmados', function () {
        $row = [...$this->oldRow, 'estanque_id' => 1, 'fecha_movimiento' => '2026-10-21T15:00:00Z'];
        $this->fuel->salidas([$row]);
        expect((float) $this->db->table('fuel_estanques')->where('id', 1)->value('ultimo_contador'))->toBe(10000.0)
            ->and(fn () => $this->fuel->salidas([[...$row, 'uuid' => (string) Str::uuid(), 'fecha_movimiento' => '2026-10-23T15:00:00Z']]))->toThrow(ValidationException::class)
            ->and(fn () => $this->fuel->salidas([[...$row, 'uuid' => (string) Str::uuid(), 'estanque_id' => 2, 'odometro_bomba' => 150]]))->toThrow(ValidationException::class);
    });

    test('revierte la recuperación y su respaldo si falla otra salida del mismo lote', function () {
        expect(fn () => $this->fuel->salidas([$this->oldRow, [...$this->oldRow, 'uuid' => (string) Str::uuid(), 'usuario' => 'Otro']]))->toThrow(ValidationException::class)
            ->and($this->db->table('movimientos')->count())->toBe(0)
            ->and($this->db->table('fuel_operaciones')->count())->toBe(0)
            ->and((float) $this->db->table('productos')->where('id', 13)->value('cantidad'))->toBe(1000.0);
    });

    test('el endpoint acepta una cola antigua de más de veinte filas sin perder registros', function () {
        config(['fuelcontrol.estanques' => true]);
        Schema::connection('fuelcontrol')->create('vehiculos', fn (Blueprint $t) => $t->increments('id'));
        $this->db->table('vehiculos')->insert(['id' => 7]);
        $rows = array_map(fn ($i) => [...$this->oldRow, 'uuid' => (string) Str::uuid(), 'cantidad' => 1, 'odometro_bomba' => 9800 + $i], range(1, 25));
        $request = \Illuminate\Http\Request::create('/sync_salida.php', 'POST', ['salidas' => $rows]);
        $adapter = app(\App\Services\FuelTankLegacyAdapter::class);
        expect($adapter->handle('salidas', $request)->getStatusCode())->toBe(200)
            ->and($adapter->handle('salidas', $request)->getStatusCode())->toBe(200)
            ->and($this->db->table('movimientos')->count())->toBe(25)
            ->and($this->db->table('fuel_operaciones')->count())->toBe(25)
            ->and((float) $this->db->table('productos')->where('id', 13)->value('cantidad'))->toBe(975.0)
            ->and((float) $this->db->table('fuel_estanques')->where('id', 1)->value('ultimo_contador'))->toBe(10000.0);
    });
});

test('la gasolina conserva su salida sin estanque', function () {
    $row = [...$this->row, 'producto_id' => 14, 'cantidad' => 2];
    unset($row['estanque_id']);
    $this->fuel->salidas([$row]);
    expect((float) $this->db->table('productos')->where('id', 14)->value('cantidad'))->toEqualWithDelta(30.27, 0.00001)
        ->and((float) $this->db->table('fuel_estanques')->sum('cantidad'))->toBe(1000.0);
});

test('las dos aprobaciones web conservan el destino y no suman una recepción dos veces', function () {
    config(['fuelcontrol.estanques' => true]);
    foreach ([\App\Http\Controllers\FuelControl\MovimientoController::class, \App\Http\Controllers\FuelControl\DashboardController::class] as $i => $class) {
        $order = 'P-WEB-'.$i;
        $id = $this->fuel->entrada(13, 50, [$order], 'web-'.$i, ['usuario' => 'gmail', 'fecha_movimiento' => now()]);
        $this->db->table('fuel_oc_destinos')->insert(['numero_oc' => $order, 'estanque_id' => 2]);
        $response = app($class)->aprobar($id);
        expect($response->getStatusCode())->toBe(200)
            ->and($this->db->table('movimientos')->where('id', $id)->value('estado'))->toBe('aprobado')
            ->and((int) $this->db->table('movimientos')->where('id', $id)->value('estanque_id'))->toBe(2);
        expect(app($class)->aprobar($id)->getStatusCode())->toBe(400);
    }
    expect((float) $this->db->table('productos')->where('id', 13)->value('cantidad'))->toBe(1100.0)
        ->and((float) $this->db->table('fuel_estanques')->where('id', 2)->value('cantidad'))->toBe(500.0);
});

test('un cambio sin destino no puede alterar el diésel mientras gasolina mantiene su flujo', function () {
    config(['fuelcontrol.estanques' => true]);
    expect(fn () => $this->fuel->rejectUnassignedStockChange(13))->toThrow(ValidationException::class);
    $this->fuel->rejectUnassignedStockChange(14);
    config(['fuelcontrol.estanques' => false]);
    $this->fuel->rejectUnassignedStockChange(13);
    expect((float) $this->db->table('productos')->where('id', 13)->value('cantidad'))->toBe(1000.0);
});

test('la auditoría existente compara cada contador sólo con su propio estanque', function () {
    config(['fuelcontrol.estanques' => true, 'fuelcontrol.web_estanques' => true]);
    Schema::connection('fuelcontrol')->create('vehiculos', function (Blueprint $t) {
        $t->increments('id');
        $t->string('descripcion');
        $t->string('patente');
    });
    $this->db->table('vehiculos')->insert(['id' => 7, 'descripcion' => 'Tractor', 'patente' => 'Prueba']);
    $this->fuel->salidas([
        [...$this->row, 'estanque_id' => 1, 'odometro_bomba' => 10050],
        [...$this->row, 'uuid' => (string) Str::uuid()],
        [...$this->row, 'uuid' => (string) Str::uuid(), 'estanque_id' => 1, 'cantidad' => 10, 'odometro_bomba' => 10060],
    ]);
    $method = new ReflectionMethod(\App\Http\Controllers\FuelControl\ProductoController::class, 'getAuditData');
    $data = $method->invoke(app(\App\Http\Controllers\FuelControl\ProductoController::class), 13);
    expect($data['auditData']->pluck('descuadrado')->all())->toBe([false, false, false])
        ->and($data['auditData']->pluck('prev_odo')->all())->toBe([null, null, 10050.0]);
    $tank2 = $method->invoke(app(\App\Http\Controllers\FuelControl\ProductoController::class), 13, '2');
    expect($tank2['producto']->nombre)->toBe('Estanque 2 - Diesel')
        ->and($tank2['auditData']->count())->toBe(1)
        ->and($tank2['auditData']->first()['prev_odo'])->toBeNull()
        ->and($tank2['auditData']->first()['odometro'])->toBe(250.0)
        ->and($tank2['auditData']->first()['origen'])->toBe('Estanque 2 - Diesel');
});

test('la vista separa existencias del mismo diésel sin alterar productos ni duplicar su saldo', function () {
    config(['fuelcontrol.estanques' => true, 'fuelcontrol.web_estanques' => true, 'fuelcontrol.capacidades' => []]);
    $products = $this->db->table('productos')->get();
    $rows = app(\App\Services\FuelTankViewData::class)->inventory($products);
    expect($rows->pluck('nombre')->all())->toBe(['Estanque 1 - Diesel', 'Estanque 2 - Diesel', 'Gasolina'])
        ->and($rows->pluck('id')->all())->toBe([13, 13, 14])
        ->and($rows->where('id', 13)->sum('cantidad'))->toBe(1000.0)
        ->and($rows->where('id', 13)->pluck('porcentaje')->all())->toBe([null, null])
        ->and($products->firstWhere('id', 13)->nombre)->toBe('diesel')
        ->and((float) $products->firstWhere('id', 13)->cantidad)->toBe(1000.0)
        ->and($this->db->table('productos')->count())->toBe(2);
});

test('la vista sólo calcula llenado con capacidad confirmada y distingue saldo sin inicializar', function () {
    config(['fuelcontrol.estanques' => true, 'fuelcontrol.web_estanques' => true, 'fuelcontrol.capacidades' => [1 => 1200, 2 => 0]]);
    $this->db->table('fuel_estanques')->where('id', 2)->update(['inicializado' => false]);
    $rows = app(\App\Services\FuelTankViewData::class)->inventory($this->db->table('productos')->get());
    expect($rows[0]->porcentaje)->toEqual(50)
        ->and($rows[1]->cantidad)->toBeNull()
        ->and($rows[1]->capacidad)->toBeNull()
        ->and($rows[1]->porcentaje)->toBeNull();
});

test('la presentación web conserva históricos sin asignar y distingue bencineras y OC pendientes', function () {
    config(['fuelcontrol.estanques' => true, 'fuelcontrol.web_estanques' => true]);
    $base = ['producto_id' => 13, 'producto_nombre' => 'diesel', 'estanque_id' => null, 'tipo' => 'salida', 'origen' => 'app'];
    $rows = collect([
        (object) [...$base, 'estanque_id' => 2], (object) $base,
        (object) [...$base, 'tipo' => 'vehiculo'],
        (object) [...$base, 'tipo' => 'entrada', 'origen' => 'xml_oc_estanque'],
        (object) [...$base, 'producto_id' => 14, 'producto_nombre' => 'Gasolina'],
    ]);
    $display = app(\App\Services\FuelTankViewData::class)->movements($rows);
    expect($display->pluck('producto_nombre')->all())->toBe([
        'Estanque 2 - Diesel', 'Diesel · Histórico sin estanque', 'Diesel · Bencinera', 'Diesel · Destino pendiente', 'Gasolina',
    ])->and($rows[1]->estanque_id)->toBeNull()->and($rows[0]->producto_nombre)->toBe('diesel');
});

test('los filtros de estanque excluyen otros destinos, gasolina e históricos y rechazan destinos inválidos', function () {
    config(['fuelcontrol.estanques' => true, 'fuelcontrol.web_estanques' => true]);
    $this->fuel->salidas([$this->row, [...$this->row, 'uuid' => (string) Str::uuid(), 'estanque_id' => 1, 'odometro_bomba' => 10050]]);
    $historical = $this->db->table('movimientos')->first();
    $historical = (array) $historical;
    unset($historical['id'], $historical['uuid']);
    $historical['hash_unico'] = null;
    $historical['estanque_id'] = null;
    $historicalId = $this->db->table('movimientos')->insertGetId($historical);
    $this->db->table('movimientos')->insert([...$historical, 'tipo' => 'vehiculo']);
    $this->db->table('movimientos')->insert([...$historical, 'producto_id' => 14]);
    $view = app(\App\Services\FuelTankViewData::class);
    $query = fn () => $this->db->table('movimientos as m');
    expect($view->filter($query(), '2')->pluck('m.estanque_id')->all())->toBe([2])
        ->and($view->filter($query(), 'sin_asignar')->pluck('m.id')->all())->toBe([$historicalId])
        ->and(fn () => $view->filter($query(), '99'))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class)
        ->and(fn () => $view->filter($query(), ['1']))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class)
        ->and(fn () => $view->filter($query(), '1', 14))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('sin activar la vista de estanques las pantallas conservan el catálogo anterior', function () {
    config(['fuelcontrol.estanques' => true, 'fuelcontrol.web_estanques' => false]);
    $view = app(\App\Services\FuelTankViewData::class);
    $products = $this->db->table('productos')->get();
    expect($view->inventory($products))->toBe($products)
        ->and($view->tanks())->toBeEmpty()
        ->and($view->filter($this->db->table('movimientos as m'), '99')->toSql())->not->toContain('estanque_id');
});

test('lee FolioRef de OC y descarta referencias a otros documentos', function () {
    $xml = simplexml_load_string('<DTE xmlns="http://www.sii.cl/SiiDte"><Documento><Referencia><TpoDocRef>801</TpoDocRef><FolioRef>P00274</FolioRef></Referencia><Referencia><TpoDocRef>52</TpoDocRef><FolioRef>123</FolioRef></Referencia></Documento></DTE>');
    expect(FuelTankService::ordenesCompra($xml))->toBe(['P00274']);
});

test('la factura ingresa al destino de su OC sin duplicarse al reenviar', function () {
    $this->db->table('fuel_oc_destinos')->insert(['numero_oc' => 'P00274', 'estanque_id' => 2]);
    $id = $this->fuel->entrada(13, 5000, ['P00274'], 'factura-unica', ['usuario' => 'gmail', 'fecha_movimiento' => now()]);
    $repeat = $this->fuel->entrada(13, 5000, ['P00274'], 'factura-unica', ['usuario' => 'gmail', 'fecha_movimiento' => now()]);
    expect($repeat)->toBe($id)
        ->and((float) $this->db->table('fuel_estanques')->where('id', 2)->value('cantidad'))->toBe(5400.0)
        ->and((float) $this->db->table('fuel_estanques')->where('id', 1)->value('cantidad'))->toBe(600.0)
        ->and((float) $this->db->table('productos')->where('id', 13)->value('cantidad'))->toBe(6000.0);
});

test('una OC desconocida queda pendiente sin sumar litros y se confirma una sola vez', function () {
    $id = $this->fuel->entrada(13, 100, ['P00999'], 'pendiente', ['usuario' => 'gmail', 'fecha_movimiento' => now()]);
    expect((float) $this->db->table('productos')->where('id', 13)->value('cantidad'))->toBe(1000.0);
    $this->db->table('fuel_oc_destinos')->insert(['numero_oc' => 'P00999', 'estanque_id' => 1]);
    $this->fuel->confirmarEntrada($id);
    $this->fuel->confirmarEntrada($id);
    expect((float) $this->db->table('fuel_estanques')->where('id', 1)->value('cantidad'))->toBe(700.0)
        ->and((float) $this->db->table('productos')->where('id', 13)->value('cantidad'))->toBe(1100.0);
});

test('dos OC con estanques distintos requieren revisión sin asignación arbitraria', function () {
    $this->db->table('fuel_oc_destinos')->insert([['numero_oc' => 'P1', 'estanque_id' => 1], ['numero_oc' => 'P2', 'estanque_id' => 2]]);
    $id = $this->fuel->entrada(13, 100, ['P1', 'P2'], 'ambiguo', ['usuario' => 'gmail', 'fecha_movimiento' => now()]);
    expect($this->db->table('movimientos')->where('id', $id)->value('estado'))->toBe('pendiente')
        ->and((float) $this->db->table('productos')->where('id', 13)->value('cantidad'))->toBe(1000.0);
});

test('el reparto inicial conserva exactamente el saldo copiado y sólo se realiza una vez', function () {
    $this->db->table('fuel_estanques')->update(['inicializado' => false, 'cantidad' => 0]);
    expect(fn () => $this->fuel->initialize([1 => ['cantidad' => 600], 2 => ['cantidad' => 500]]))->toThrow(ValidationException::class);
    $this->fuel->initialize([1 => ['cantidad' => 600, 'contador' => 10000], 2 => ['cantidad' => 400, 'contador' => 200]]);
    expect((float) $this->db->table('productos')->where('id', 13)->value('cantidad'))->toBe(1000.0);
    expect(fn () => $this->fuel->initialize([1 => ['cantidad' => 600], 2 => ['cantidad' => 400]]))->toThrow(ValidationException::class);
});

test('la marca de estanque en una OC se reconoce sin asumir destinos ambiguos', function () {
    expect(\App\Services\FuelOcResolver::tanqueEnTexto('Petróleo diésel · Estanque 1'))->toBe(1)
        ->and(\App\Services\FuelOcResolver::tanqueEnTexto('<p>Entrega Estanque N° 2</p>'))->toBe(2)
        ->and(\App\Services\FuelOcResolver::tanqueEnTexto('Estanque 1 y Estanque 2'))->toBeNull()
        ->and(\App\Services\FuelOcResolver::tanqueEnTexto('5000 litros puesto fundo'))->toBeNull();
});

function fixtureOdooCombustible(string $description, string $vat = 'CL96686870-8')
{
    $mock = Mockery::mock(\App\Services\OdooService::class);
    $mock->shouldReceive('searchRead')->andReturnUsing(function ($model, $domain, $fields) use ($description, $vat) {
        return match ($model) {
            'purchase.order' => [['id' => 300, 'name' => 'P00300', 'notes' => false, 'order_line' => [1], 'partner_id' => [7, 'Coagra'], 'company_id' => [1, 'EHE']]],
            'res.partner' => in_array('commercial_partner_id', $fields) ? [['commercial_partner_id' => [9, 'Coagra']]] : [['vat' => $vat]],
            'res.company' => [['vat' => 'CL77415879-0']],
            'purchase.order.line' => [['name' => $description]],
            default => [],
        };
    });

    return $mock;
}

test('la OC reconoce el estanque desde Odoo verificando proveedor y empresa', function () {
    $resolver = new \App\Services\FuelOcResolver(fixtureOdooCombustible('Diésel Estanque 2'));
    expect($resolver->resolve(['P00300'], 13, '96686870-8', '77415879-0'))->toBeTrue()
        ->and((int) $this->db->table('fuel_oc_destinos')->where('numero_oc', 'P00300')->value('estanque_id'))->toBe(2);
});

test('una OC sin marca o de otro proveedor queda pendiente sin asignación', function () {
    $resolver = new \App\Services\FuelOcResolver(fixtureOdooCombustible('5000 litros diésel'));
    expect($resolver->resolve(['P00300'], 13, '96686870-8', '77415879-0'))->toBeFalse();
    $resolver = new \App\Services\FuelOcResolver(fixtureOdooCombustible('Diésel Estanque 1', 'CL81290800-6'));
    expect($resolver->resolve(['P00300'], 13, '96686870-8', '77415879-0'))->toBeFalse()
        ->and($this->db->table('fuel_oc_destinos')->count())->toBe(0);
});
