<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (!Schema::hasColumn('productos', 'unidad_stock_id')) {
                $table->foreignId('unidad_stock_id')->nullable()->after('id')->constrained('unidades_medida');
            }
            if (!Schema::hasColumn('productos', 'unidad_compra_id')) {
                $table->foreignId('unidad_compra_id')->nullable()->constrained('unidades_medida');
            }
            if (!Schema::hasColumn('productos', 'unidad_venta_id')) {
                $table->foreignId('unidad_venta_id')->nullable()->constrained('unidades_medida');
            }
            if (!Schema::hasColumn('productos', 'categoria_unidad_id')) {
                $table->foreignId('categoria_unidad_id')->nullable()->constrained('categorias_unidad_medida');
            }
            if (!Schema::hasColumn('productos', 'perfil_impuesto_id')) {
                $table->foreignId('perfil_impuesto_id')->nullable()->constrained('perfiles_impuestos');
            }
            if (!Schema::hasColumn('productos', 'permite_fraccion')) {
                $table->boolean('permite_fraccion')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            // down opcional, lo dejamos simple
        });
    }
};
