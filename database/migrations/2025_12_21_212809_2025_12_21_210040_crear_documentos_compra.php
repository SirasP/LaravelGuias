<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('rut')->nullable();
            $table->timestamps();
        });

        Schema::create('documentos_compra', function (Blueprint $table) {
            $table->id();

            $table->foreignId('proveedor_id')->constrained('proveedores');
            $table->foreignId('bodega_id')->constrained('bodegas');

            $table->enum('tipo_documento', ['FACTURA', 'GUIA']);
            $table->string('numero_documento');
            $table->date('fecha_documento');
            $table->dateTime('fecha_ocurrencia')->nullable(); // fecha efectiva ingreso/posteo

            $table->enum('estado', ['BORRADOR', 'CONTABILIZADO', 'ANULADO'])->default('BORRADOR');

            // Config tributaria del documento
            $table->decimal('tasa_iva', 6, 4)->default(0.1900);
            $table->boolean('iva_recuperable')->default(true);
            $table->boolean('precios_incluyen_iva')->default(false);

            // Vincular guía <-> factura (opcional)
            $table->foreignId('documento_relacionado_id')->nullable()->constrained('documentos_compra');

            // Totales persistidos
            $table->decimal('total_neto', 18, 6)->default(0);
            $table->decimal('total_impuesto_especifico', 18, 6)->default(0);
            $table->decimal('total_iva', 18, 6)->default(0);
            $table->decimal('total_general', 18, 6)->default(0);

            $table->text('notas')->nullable();
            $table->timestamps();

            $table->unique(['proveedor_id', 'tipo_documento', 'numero_documento'], 'ux_doc_comp_prov_tipo_num');

        });

        Schema::create('lineas_documento_compra', function (Blueprint $table) {
            $table->id();

            $table->foreignId('documento_compra_id')->constrained('documentos_compra')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');

            // Cantidad en unidad de línea
            $table->decimal('cantidad', 18, 6);
            $table->foreignId('unidad_medida_id')->constrained('unidades_medida');

            // Cantidad convertida a unidad base del producto (unidad_stock)
            $table->decimal('cantidad_base', 18, 6)->default(0);

            // Precio unitario (recomendado neto por unidad de línea)
            $table->decimal('precio_unitario', 18, 6)->nullable(); // guía puede ser null
            $table->decimal('descuento', 18, 6)->default(0);

            // Impuestos calculados
            $table->decimal('tasa_iva', 6, 4)->default(0.1900);
            $table->decimal('monto_neto', 18, 6)->default(0);
            $table->decimal('monto_impuesto_especifico', 18, 6)->default(0);
            $table->decimal('monto_iva', 18, 6)->default(0);
            $table->decimal('monto_total', 18, 6)->default(0);

            // Costo que entra a inventario (FIFO)
            $table->decimal('costo_inventario_total', 18, 6)->default(0);
            $table->decimal('costo_unitario_base', 18, 6)->default(0);

            // Datos de lote
            $table->string('codigo_lote')->nullable();
            $table->date('vence_el')->nullable();

            $table->timestamps();

            $table->index(['documento_compra_id', 'producto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lineas_documento_compra');
        Schema::dropIfExists('documentos_compra');
        Schema::dropIfExists('proveedores');
    }
};
