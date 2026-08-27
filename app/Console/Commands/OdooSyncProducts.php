<?php

namespace App\Console\Commands;

use App\Models\OdooProduct;
use App\Models\OdooSupplierProduct;
use App\Services\PurchaseRequests\Odoo\OdooClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Trae el catálogo de productos de Odoo a una tabla local.
 *
 * Mismo patrón que `odoo:sync-moves` y `odoo:sync-analytics`. Existe para que
 * emparejar el texto de una cotización contra los productos no dependa de una
 * llamada remota por intento: en tabla local con índice es instantáneo, y
 * sigue funcionando aunque Odoo esté caído.
 *
 * Lee. No escribe nada en Odoo, nunca.
 */
class OdooSyncProducts extends Command
{
    protected $signature = 'odoo:sync-products
                            {--dry-run : Muestra lo que traería sin guardar nada}';

    protected $description = 'Copia el catálogo de productos de Odoo a la base local, para poder emparejarlos';

    public function handle(): int
    {
        /*
         * Se usa la configuración del módulo de solicitudes, no la compartida.
         *
         * No es un detalle: el id que se guarde aquí es el que después viaja
         * en la línea de la cotización. Si se sincroniza contra una instancia
         * y se exporta a otra, cada producto apuntaría a algo distinto —o a
         * nada— sin que ningún error lo delate.
         */
        $url = (string) config('purchase_requests.odoo.url');
        $db = (string) config('purchase_requests.odoo.db');

        if ($url === '' || $db === '') {
            $this->error('Falta la configuración de Odoo. Revisa PURCHASE_REQUESTS_ODOO_URL y _DB (o las ODOO_* compartidas).');

            return self::FAILURE;
        }

        $this->line('Odoo: <fg=cyan>'.$db.'</>');
        $this->line('      '.$url);

        $cliente = new OdooClient(
            $url,
            $db,
            (string) config('purchase_requests.odoo.user'),
            (string) config('purchase_requests.odoo.password'),
            (int) config('purchase_requests.odoo.timeout', 30),
        );

        try {
            $this->line('Identificando… uid '.$cliente->uid());
        } catch (Throwable $e) {
            $this->error('No se pudo entrar a Odoo: '.$e->getMessage());

            return self::FAILURE;
        }

        $seco = (bool) $this->option('dry-run');

        try {
            $productos = $this->traerProductos($cliente);
            $proveedores = $this->traerProveedorProducto($cliente);
        } catch (Throwable $e) {
            $this->error('Odoo devolvió un error: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->line(sprintf('  productos leídos:            %s', number_format(count($productos), 0, ',', '.')));
        $this->line(sprintf('  filas proveedor-producto:    %s', number_format(count($proveedores), 0, ',', '.')));

        if ($seco) {
            $this->newLine();
            $this->warn('  --dry-run: no se guardó nada.');
            $this->mostrarMuestra($productos);

            return self::SUCCESS;
        }

        $momento = now();

        $this->guardarProductos($productos, $momento);
        $this->guardarProveedorProducto($proveedores, $momento);

        // Lo que dejó de venir sigue en la tabla, marcado. Borrarlo dejaría
        // huérfano cualquier emparejado que lo apuntara, sin rastro de por qué.
        $desaparecidos = OdooProduct::query()
            ->where(fn ($q) => $q->where('synced_at', '<', $momento)->orWhereNull('synced_at'))
            ->whereNull('missing_since')
            ->update(['missing_since' => $momento]);

        // Y lo que volvió a aparecer deja de estarlo.
        $reaparecidos = OdooProduct::query()
            ->where('synced_at', '>=', $momento)
            ->whereNotNull('missing_since')
            ->update(['missing_since' => null]);

        $this->newLine();
        $this->table(['', 'Cantidad'], [
            ['productos en la base', number_format(OdooProduct::count(), 0, ',', '.')],
            ['  comprables y vigentes', number_format(OdooProduct::query()->usable()->count(), 0, ',', '.')],
            ['  ya no están en Odoo', number_format(OdooProduct::whereNotNull('missing_since')->count(), 0, ',', '.')],
            ['proveedor-producto', number_format(OdooSupplierProduct::count(), 0, ',', '.')],
        ]);

        if ($desaparecidos > 0) {
            $this->warn(sprintf('  %d producto(s) dejaron de aparecer en Odoo y quedaron marcados.', $desaparecidos));
        }

        if ($reaparecidos > 0) {
            $this->info(sprintf('  %d producto(s) volvieron a aparecer.', $reaparecidos));
        }

        return self::SUCCESS;
    }

    /** @return list<array<string, mixed>> */
    private function traerProductos(OdooClient $cliente): array
    {
        $filas = $cliente->execute('product.product', 'search_read', [[]], [
            'fields' => ['id', 'name', 'default_code', 'barcode', 'uom_id',
                'type', 'is_storable', 'purchase_ok', 'active'],
            // Los archivados también: una solicitud vieja puede apuntarles, y
            // hay que poder decir «existe pero está archivado».
            'context' => ['active_test' => false],
        ]);

        return is_array($filas) ? $filas : [];
    }

    /** @return list<array<string, mixed>> */
    private function traerProveedorProducto(OdooClient $cliente): array
    {
        $filas = $cliente->execute('product.supplierinfo', 'search_read', [[]], [
            'fields' => ['id', 'partner_id', 'product_id', 'product_tmpl_id',
                'product_name', 'product_code', 'price'],
        ]);

        return is_array($filas) ? $filas : [];
    }

    /** @param  list<array<string, mixed>>  $productos */
    private function guardarProductos(array $productos, \DateTimeInterface $momento): void
    {
        $barra = $this->output->createProgressBar(count($productos));
        $barra->start();

        foreach (array_chunk($productos, 200) as $lote) {
            $filas = array_map(fn (array $p): array => [
                'odoo_id' => (int) $p['id'],
                'name' => (string) ($p['name'] ?? ''),
                'default_code' => $this->texto($p['default_code'] ?? null),
                'barcode' => $this->texto($p['barcode'] ?? null),
                'uom_id' => is_array($p['uom_id'] ?? null) ? (int) $p['uom_id'][0] : null,
                'uom_name' => is_array($p['uom_id'] ?? null) ? (string) $p['uom_id'][1] : null,
                'type' => $this->texto($p['type'] ?? null),
                'is_storable' => (bool) ($p['is_storable'] ?? false),
                'purchase_ok' => (bool) ($p['purchase_ok'] ?? true),
                'active_in_odoo' => (bool) ($p['active'] ?? true),
                'synced_at' => $momento,
                'updated_at' => $momento,
                'created_at' => $momento,
            ], $lote);

            OdooProduct::query()->upsert($filas, ['odoo_id'], [
                'name', 'default_code', 'barcode', 'uom_id', 'uom_name',
                'type', 'is_storable', 'purchase_ok', 'active_in_odoo',
                'synced_at', 'updated_at',
            ]);

            $barra->advance(count($lote));
        }

        $barra->finish();
        $this->newLine();
    }

    /** @param  list<array<string, mixed>>  $filas */
    private function guardarProveedorProducto(array $filas, \DateTimeInterface $momento): void
    {
        foreach (array_chunk($filas, 200) as $lote) {
            OdooSupplierProduct::query()->upsert(array_map(fn (array $r): array => [
                'odoo_id' => (int) $r['id'],
                'partner_id' => is_array($r['partner_id'] ?? null) ? (int) $r['partner_id'][0] : 0,
                'partner_name' => is_array($r['partner_id'] ?? null) ? (string) $r['partner_id'][1] : null,
                'product_id' => is_array($r['product_id'] ?? null) ? (int) $r['product_id'][0] : null,
                'product_tmpl_id' => is_array($r['product_tmpl_id'] ?? null) ? (int) $r['product_tmpl_id'][0] : null,
                'product_name' => $this->texto($r['product_name'] ?? null),
                'product_code' => $this->texto($r['product_code'] ?? null),
                'price' => isset($r['price']) ? (float) $r['price'] : null,
                'synced_at' => $momento,
                'updated_at' => $momento,
                'created_at' => $momento,
            ], $lote), ['odoo_id'], [
                'partner_id', 'partner_name', 'product_id', 'product_tmpl_id',
                'product_name', 'product_code', 'price', 'synced_at', 'updated_at',
            ]);
        }
    }

    /** Odoo devuelve `false` en vez de null para los campos vacíos. */
    private function texto(mixed $valor): ?string
    {
        return is_string($valor) && trim($valor) !== '' ? trim($valor) : null;
    }

    /** @param  list<array<string, mixed>>  $productos */
    private function mostrarMuestra(array $productos): void
    {
        $this->table(
            ['id', 'Nombre', 'Código', 'UdM', 'Inventariable'],
            array_map(fn (array $p): array => [
                $p['id'],
                mb_strimwidth((string) ($p['name'] ?? ''), 0, 44, '…'),
                $p['default_code'] ?: '—',
                is_array($p['uom_id'] ?? null) ? $p['uom_id'][1] : '—',
                ($p['is_storable'] ?? false) ? 'sí' : 'no',
            ], array_slice($productos, 0, 8)),
        );
    }
}
