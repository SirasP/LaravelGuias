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
        // database/migrations/xxxx_xx_xx_create_camiones_table.php
        Schema::create('camiones', function (Blueprint $table) {
            $table->string('patente_norm')->primary();   // JJ7382
            $table->string('patente_original')->nullable(); // como llegó la 1ª vez
            $table->string('alias')->nullable();          // "Camión Sergio"
            $table->boolean('activo')->default(true);
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('camiones');
    }
};
