<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('fuelcontrol')->hasTable('gmail_inventory_products')) {
            Schema::connection('fuelcontrol')->create('gmail_inventory_products', function (Blueprint $table) {
                $table->id();
                $table->string('codigo')->nullable()->index();
                $table->string('nombre')->index();
                $table->string('unidad', 20)->default('UN');
                $table->decimal('stock_actual', 18, 6)->default(0);
                $table->decimal('costo_promedio', 18, 6)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['nombre', 'unidad'], 'gmail_inv_products_nombre_unidad_unique');
            });
        }

        if (!Schema::connection('fuelcontrol')->hasTable('gmail_inventory_lots')) {
            Schema::connection('fuelcontrol')->create('gmail_inventory_lots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('document_id')->nullable()->index();
                $table->unsignedBigInteger('dte_line_id')->nullable()->index();
                $table->dateTime('ingresado_el')->index();
                $table->decimal('costo_unitario', 18, 6)->default(0);
                $table->decimal('cantidad_ingresada', 18, 6);
                $table->decimal('cantidad_salida', 18, 6)->default(0);
                $table->decimal('cantidad_disponible', 18, 6);
                $table->string('estado', 20)->default('ABIERTO')->index();
                $table->timestamps();

                $table->index(['product_id', 'ingresado_el', 'id'], 'gmail_inv_lots_fifo_idx');
            });
        }

        if (!Schema::connection('fuelcontrol')->hasTable('gmail_inventory_movements')) {
            Schema::connection('fuelcontrol')->create('gmail_inventory_movements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('document_id')->nullable()->index();
                $table->string('tipo', 20)->index();
                $table->string('estado', 20)->default('CONTABILIZADO')->index();
                $table->dateTime('ocurrio_el')->index();
                $table->unsignedBigInteger('usuario_id')->nullable()->index();
                $table->text('notas')->nullable();
                $table->decimal('cantidad_total', 18, 6)->default(0);
                $table->decimal('costo_total', 18, 6)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::connection('fuelcontrol')->hasTable('gmail_inventory_movement_lines')) {
            Schema::connection('fuelcontrol')->create('gmail_inventory_movement_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('movement_id')->index();
                $table->unsignedBigInteger('lot_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->decimal('cantidad', 18, 6);
                $table->decimal('costo_unitario', 18, 6)->default(0);
                $table->decimal('costo_total', 18, 6)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('fuelcontrol')->dropIfExists('gmail_inventory_movement_lines');
        Schema::connection('fuelcontrol')->dropIfExists('gmail_inventory_movements');
        Schema::connection('fuelcontrol')->dropIfExists('gmail_inventory_lots');
        Schema::connection('fuelcontrol')->dropIfExists('gmail_inventory_products');
    }
};
