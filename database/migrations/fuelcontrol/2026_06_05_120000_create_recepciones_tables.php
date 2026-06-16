<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'fuelcontrol';

    public function up(): void
    {
        if (!Schema::connection('fuelcontrol')->hasTable('recepciones')) {
            Schema::connection('fuelcontrol')->create('recepciones', function (Blueprint $table) {
                $table->id();

                // Origen: orden de compra (cotización confirmada). Nullable: recepción sin OC.
                $table->unsignedBigInteger('purchase_order_id')->nullable()->index();

                // Snapshot del proveedor
                $table->string('proveedor_rut', 20)->nullable();
                $table->string('proveedor_nombre')->nullable();

                $table->unsignedBigInteger('bodega_id')->nullable()->index();

                $table->string('estado', 20)->default('BORRADOR')->index(); // BORRADOR | CONFIRMADA | ANULADA
                $table->dateTime('fecha_recepcion')->nullable();

                // Movimiento de inventario generado al confirmar (gmail_inventory_movements.id)
                $table->unsignedBigInteger('stock_movement_id')->nullable()->index();

                // Factura vinculada (gmail_dte_documents.id) — conciliación 3 vías
                $table->unsignedBigInteger('gmail_document_id')->nullable()->index();

                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->text('notas')->nullable();

                $table->timestamps();
            });
        }

        if (!Schema::connection('fuelcontrol')->hasTable('recepcion_lineas')) {
            Schema::connection('fuelcontrol')->create('recepcion_lineas', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('recepcion_id')->index();

                // Vínculo a lo pedido (purchase_order_items.id) — nullable si la línea no estaba en la OC
                $table->unsignedBigInteger('purchase_order_item_id')->nullable()->index();

                // Producto de inventario (gmail_inventory_products.id)
                $table->unsignedBigInteger('inventory_product_id')->nullable()->index();

                $table->string('product_name');
                $table->string('unidad', 30)->default('UN');

                $table->decimal('cantidad_pedida', 18, 6)->nullable();   // snapshot de lo pedido
                $table->decimal('cantidad_recibida', 18, 6)->default(0);
                $table->decimal('costo_unitario', 18, 6)->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('fuelcontrol')->dropIfExists('recepcion_lineas');
        Schema::connection('fuelcontrol')->dropIfExists('recepciones');
    }
};
