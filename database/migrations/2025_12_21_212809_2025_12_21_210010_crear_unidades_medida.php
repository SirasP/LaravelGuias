<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('categorias_unidad_medida', function (Blueprint $table) {
            $table->id();
            $table->string('nombre'); // Peso, Volumen, Conteo, etc.
            $table->timestamps();
        });

        Schema::create('unidades_medida', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->constrained('categorias_unidad_medida');
            $table->string('codigo', 16)->unique(); // KG, L, UN, PAR...
            $table->string('nombre');
            $table->unsignedTinyInteger('precision')->default(3);
            $table->timestamps();
        });

        Schema::create('conversiones_unidad_medida', function (Blueprint $table) {
            $table->id();
            $table->foreignId('desde_unidad_id')->constrained('unidades_medida');
            $table->foreignId('hacia_unidad_id')->constrained('unidades_medida');
            $table->decimal('factor', 18, 8); // qty_hacia = qty_desde * factor
            $table->timestamps();

            $table->unique(
                ['desde_unidad_id', 'hacia_unidad_id'],
                'ux_conv_um_desde_hacia'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversiones_unidad_medida');
        Schema::dropIfExists('unidades_medida');
        Schema::dropIfExists('categorias_unidad_medida');
    }
};
