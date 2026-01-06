<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lotes_inventario', function (Blueprint $table) {
            $table->id();

            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('bodega_id')->constrained('bodegas');

            $table->string('codigo_lote')->nullable();
            $table->dateTime('ingresado_el'); // clave FIFO
            $table->date('vence_el')->nullable();

            // Costo por unidad base (unidad_stock)
            $table->decimal('costo_unitario', 18, 6)->default(0);

            // Cantidades SIEMPRE en unidad base
            $table->decimal('cantidad_ingresada', 18, 6);
            $table->decimal('cantidad_salida', 18, 6)->default(0);
            $table->decimal('cantidad_disponible', 18, 6);

            // Origen (documento, ajuste, etc.)
            $table->string('origen_tipo')->nullable();
            $table->unsignedBigInteger('origen_id')->nullable();

            // Si entró por guía sin costos
            $table->boolean('costo_pendiente')->default(false);

            $table->enum('estado', ['ABIERTO', 'CERRADO'])->default('ABIERTO');

            $table->timestamps();

            $table->index(['producto_id', 'bodega_id', 'ingresado_el', 'id']);
            $table->index(['bodega_id', 'producto_id']);
        });

        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();

            $table->enum('tipo', [
                'ENTRADA', 'SALIDA',
                'TRASPASO_SALIDA', 'TRASPASO_ENTRADA',
                'AJUSTE_ENTRADA', 'AJUSTE_SALIDA',
                'DEVOLUCION_ENTRADA', 'DEVOLUCION_SALIDA',
                'AJUSTE_COSTO'
            ]);

            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('bodega_id')->constrained('bodegas');

            // Cantidad en unidad base
            $table->decimal('cantidad', 18, 6);

            // Costos del movimiento
            $table->decimal('costo_unitario', 18, 6)->nullable();
            $table->decimal('costo_total', 18, 6)->nullable();

            $table->dateTime('ocurrio_el');

            $table->string('documento_tipo')->nullable(); // DocumentoCompra, Venta, etc.
            $table->unsignedBigInteger('documento_id')->nullable();

            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->text('notas')->nullable();

            $table->timestamps();

            $table->index(['producto_id', 'bodega_id', 'ocurrio_el', 'id']);
        });

        Schema::create('lineas_movimiento_inventario', function (Blueprint $table) {
            $table->id();

            $table->foreignId('movimiento_id')->constrained('movimientos_inventario')->cascadeOnDelete();
            $table->foreignId('lote_id')->constrained('lotes_inventario');

            $table->decimal('cantidad', 18, 6);
            $table->decimal('costo_unitario', 18, 6);
            $table->decimal('costo_total', 18, 6);

            $table->timestamps();

            $table->index(['movimiento_id']);
            $table->index(['lote_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lineas_movimiento_inventario');
        Schema::dropIfExists('movimientos_inventario');
        Schema::dropIfExists('lotes_inventario');
    }
};
