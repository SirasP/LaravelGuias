<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('equipos', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('nombre');
            $t->string('tipo', 50);
            $t->string('marca', 100);
            $t->string('modelo', 100);
            $t->unsignedSmallInteger('anio');
            $t->string('identificador', 100);
            $t->string('ubicacion', 255);
            $t->string('status', 50)->default('operational');
            $t->unsignedInteger('horometro')->default(0);
            $t->string('responsable');
            $t->date('proxima_mant');
            $t->unsignedInteger('proximas_horas')->nullable();
            $t->text('notas')->nullable();
            $t->json('documentos')->nullable();
            $t->timestamps();
        });

        Schema::create('mantenciones', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('equipment_id');
            $t->string('tipo', 60);
            $t->date('fecha');
            $t->unsignedInteger('horas_servicio')->default(0);
            $t->string('tecnico');
            $t->text('descripcion');
            $t->decimal('costo_repuestos', 12, 2)->default(0);
            $t->decimal('costo_mano_obra', 12, 2)->default(0);
            $t->date('proxima_mant')->nullable();
            $t->unsignedInteger('proximas_horas')->nullable();
            $t->json('fotos')->nullable();
            $t->timestamps();
            $t->foreign('equipment_id')->references('id')->on('equipos')->onDelete('cascade');
        });

        Schema::create('part_consumptions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('maintenance_id');
            $t->string('part_id', 100);
            $t->string('part_name');
            $t->decimal('quantity', 10, 3);
            $t->decimal('unit_price', 12, 2)->default(0);
            $t->foreign('maintenance_id')->references('id')->on('mantenciones')->onDelete('cascade');
        });

        Schema::create('kits', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('equipment_id');
            $t->string('nombre');
            $t->string('emoji', 20)->default('🔧');
            $t->unsignedInteger('uso_count')->default(0);
            $t->timestamps();
            $t->foreign('equipment_id')->references('id')->on('equipos')->onDelete('cascade');
        });

        Schema::create('kit_items', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('kit_id');
            $t->unsignedInteger('product_id');
            $t->string('product_name');
            $t->string('unidad', 50);
            $t->decimal('quantity', 10, 3);
            $t->foreign('kit_id')->references('id')->on('kits')->onDelete('cascade');
        });

        Schema::create('work_orders', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('equipment_id');
            $t->string('titulo');
            $t->text('descripcion');
            $t->string('asignado');
            $t->date('fecha_limite');
            $t->string('status', 50)->default('newOrder');
            $t->string('prioridad', 50)->default('medium');
            $t->timestamps();
            $t->foreign('equipment_id')->references('id')->on('equipos')->onDelete('cascade');
        });

        Schema::create('checklist_items', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('work_order_id');
            $t->text('tarea');
            $t->string('status', 50)->default('pending');
            $t->text('nota')->nullable();
            $t->foreign('work_order_id')->references('id')->on('work_orders')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('kit_items');
        Schema::dropIfExists('kits');
        Schema::dropIfExists('part_consumptions');
        Schema::dropIfExists('mantenciones');
        Schema::dropIfExists('equipos');
    }
};
