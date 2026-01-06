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
        Schema::table('agrak_registros', function (Blueprint $table) {
            $table->string('patente_norm')->nullable()->after('patente_camion');
            $table->string('chofer_norm')->nullable()->after('nombre_chofer');
            $table->string('exportadora_norm')->nullable()->after('exportadora_2');
            $table->enum('estado_norm', ['OK', 'PENDIENTE'])->default('PENDIENTE')->after('exportadora_norm');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agrak_registros', function (Blueprint $table) {
            //
        });
    }
};
