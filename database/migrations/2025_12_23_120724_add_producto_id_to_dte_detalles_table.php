<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dte_detalles', function (Blueprint $table) {
            if (!Schema::hasColumn('dte_detalles', 'producto_id')) {
                $table->unsignedBigInteger('producto_id')->nullable()->index()->after('qty_a_ingresar');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dte_detalles', function (Blueprint $table) {
            if (Schema::hasColumn('dte_detalles', 'producto_id')) {
                $table->dropColumn('producto_id');
            }
        });
    }

};
