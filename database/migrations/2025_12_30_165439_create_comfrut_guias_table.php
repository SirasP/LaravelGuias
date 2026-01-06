<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('comfrut_guias', function (Blueprint $table) {
            $table->id();

            // Identificación guía
            $table->string('guia_numero')->index();
            $table->date('fecha_guia')->nullable();
            $table->string('tipo_dte', 5)->default('52');

            // Productor / receptor
            $table->string('productor')->nullable();
            $table->string('rut_productor')->nullable();

            // Transporte / datos extra
            $table->string('patente')->nullable();

            // Valores
            $table->decimal('kilos', 12, 2)->default(0);
            $table->unsignedInteger('monto_total')->nullable();

            // XML
            $table->string('xml_path');
            $table->string('xml_hash', 64)->unique();

            // Metadata flexible
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['guia_numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comfrut_guias');
    }
};
