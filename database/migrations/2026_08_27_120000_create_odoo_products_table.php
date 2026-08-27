<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Copia local del catálogo de productos de Odoo.
 *
 * Mismo patrón que odoo_account_moves y odoo_analytic_accounts: Odoo manda, y
 * aquí vive una copia para poder consultarla con SQL. Emparejar un texto
 * contra 2.347 candidatos por JSON-RPC en cada intento no es viable; contra
 * una tabla con índice, sí.
 *
 * Nada de esto se escribe nunca en Odoo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odoo_products', function (Blueprint $table): void {
            $table->id();

            // El identificador de Odoo. Es lo que viaja en la línea de la
            // cotización, así que tiene que venir de la MISMA instancia a la
            // que se exporta: un id de pruebas no existe en producción.
            $table->unsignedBigInteger('odoo_id')->unique();

            $table->string('name', 500);
            $table->string('default_code', 120)->nullable();
            $table->string('barcode', 120)->nullable();

            $table->unsignedBigInteger('uom_id')->nullable();
            $table->string('uom_name', 80)->nullable();

            $table->string('type', 20)->nullable();
            $table->boolean('is_storable')->default(false);
            $table->boolean('purchase_ok')->default(true);

            // Archivado en Odoo: no se borra aquí, se marca. Una solicitud
            // vieja puede seguir apuntándole.
            $table->boolean('active_in_odoo')->default(true);

            /*
             * Desde cuándo dejó de aparecer en la sincronización.
             *
             * Se marca en vez de borrar: si un alias apunta a este producto,
             * hay que poder decir «esto ya no está en Odoo» en lugar de
             * mandar un id muerto y que la cotización falle sin explicación.
             */
            $table->timestamp('missing_since')->nullable();

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('name');
            $table->index('default_code');
            $table->index(['purchase_ok', 'missing_since']);
        });

        /*
         * Cómo llama cada proveedor a cada producto: `product.supplierinfo`.
         *
         * Hoy las 267 filas de Odoo traen los nombres propios vacíos, pero el
         * día que se llenen es el mejor cruce que puede existir. Y aunque
         * estén vacías ya sirven: dicen QUÉ productos le compras a cada
         * proveedor, que reduce la búsqueda de 2.347 a unos pocos.
         */
        Schema::create('odoo_supplier_products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();

            $table->unsignedBigInteger('partner_id');
            $table->string('partner_name', 255)->nullable();

            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_tmpl_id')->nullable();

            $table->string('product_name', 500)->nullable();
            $table->string('product_code', 120)->nullable();
            $table->decimal('price', 14, 2)->nullable();

            $table->timestamp('missing_since')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('partner_id');
            $table->index('product_id');
            $table->index('product_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_supplier_products');
        Schema::dropIfExists('odoo_products');
    }
};
