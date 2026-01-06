<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('dte_detalles', function (Blueprint $table) {

            if (!Schema::hasColumn('dte_detalles', 'seleccionado_inventario')) {
                $table->boolean('seleccionado_inventario')->default(false);
            }

            if (!Schema::hasColumn('dte_detalles', 'qty_a_ingresar')) {
                $table->decimal('qty_a_ingresar', 12, 3)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('dte_detalles', function (Blueprint $table) {

            if (Schema::hasColumn('dte_detalles', 'qty_a_ingresar')) {
                $table->dropColumn('qty_a_ingresar');
            }

            if (Schema::hasColumn('dte_detalles', 'seleccionado_inventario')) {
                $table->dropColumn('seleccionado_inventario');
            }
        });
    }
};
