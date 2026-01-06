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
        Schema::create('agrak_odoo_matches', function (Blueprint $table) {
            $table->id();

            // lado AGRak
            $table->date('agrak_fecha');
            $table->string('agrak_patente', 20);
            $table->time('agrak_hora_inicio')->nullable();
            $table->time('agrak_hora_fin')->nullable();
            $table->string('agrak_chofer')->nullable();

            // lado Odoo
            $table->foreignId('excel_out_transfer_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // matching
            $table->unsignedTinyInteger('score')->default(0);
            $table->enum('estado', ['ok', 'probable', 'manual', 'descartado'])->default('manual');

            $table->timestamps();

            // 🔑 evita duplicados por viaje
            $table->unique([
                'agrak_fecha',
                'agrak_patente',
                'agrak_hora_inicio',
                'agrak_hora_fin',
            ], 'agrak_trip_unique');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agrak_odoo_matches');
    }
};
