<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('perfiles_impuestos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');

            // IVA
            $table->decimal('tasa_iva', 6, 4)->default(0.1900);

            // Impuesto específico
            $table->boolean('aplica_impuesto_especifico')->default(false);
            $table->enum('tipo_impuesto_especifico', ['POR_LITRO', 'POR_UNIDAD', 'PORCENTAJE'])->nullable();
            $table->decimal('tasa_impuesto_especifico', 18, 6)->default(0);

            // Qué entra al costo FIFO
            $table->boolean('incluir_iva_en_costo_inventario')->default(false);
            $table->boolean('incluir_especifico_en_costo_inventario')->default(true);

            // Base de IVA incluye impuesto específico
            $table->boolean('base_iva_incluye_especifico')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perfiles_impuestos');
    }
};
