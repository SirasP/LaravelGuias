<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$args = getopt('', ['expected-total:', 'expected-last-movement:', 'estanque2:']);
if (config('fuelcontrol.preview') || config('database.connections.fuelcontrol.database') !== 'inventariocombustible'
    || ! isset($args['expected-total'], $args['expected-last-movement'], $args['estanque2'])) {
    throw new RuntimeException('Se requiere la base real verificada y los tres valores de inicialización explícitos.');
}
$db = Illuminate\Support\Facades\DB::connection('fuelcontrol');
$db->transaction(function () use ($db, $args) {
    $product = $db->table('productos')->where('nombre', 'diesel')->lockForUpdate()->first();
    $expected = (int) round((float) $args['expected-total'] * 100);
    $second = (int) round((float) $args['estanque2'] * 100);
    if (! $product || (int) round($product->cantidad * 100) !== $expected
        || (int) $db->table('movimientos')->max('id') !== (int) $args['expected-last-movement']
        || $second < 0 || $second > $expected || $db->table('fuel_estanques')->exists()) {
        throw new RuntimeException('El saldo o el último movimiento cambió, o los estanques ya existen. No se asignó nada.');
    }
    $balances = [];
    foreach ([1 => $expected - $second, 2 => $second] as $number => $cents) {
        $id = $db->table('fuel_estanques')->insertGetId([
            'nombre' => 'Estanque '.$number, 'producto_id' => $product->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $balances[$id] = ['cantidad' => $cents / 100, 'contador' => null];
    }
    app(App\Services\FuelTankService::class)->initialize($balances);
});
echo json_encode([
    'estanques' => $db->table('fuel_estanques')->orderBy('id')->get(),
    'productos' => $db->table('productos')->orderBy('id')->get(['id', 'nombre', 'cantidad']),
    'movimientos' => $db->table('movimientos')->count(),
    'max_id' => $db->table('movimientos')->max('id'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
