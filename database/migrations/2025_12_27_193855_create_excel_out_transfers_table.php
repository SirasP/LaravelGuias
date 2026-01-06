<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;



return new class extends Migration {
    public function up(): void
    {
        Schema::create('excel_out_transfers', function (Blueprint $table) {
            $table->id();

            // Origen (archivo)
            $table->string('source_file')->nullable();
            $table->unsignedInteger('excel_row')->nullable();

            // Campos principales (los que te importan)
            $table->string('contacto')->nullable();
            $table->dateTime('fecha_prevista')->nullable();
            $table->string('patente')->nullable();
            $table->string('guia_entrega')->nullable(); // NORMALIZADA (sin ceros)

            // Campos extra que aparecen en el Excel
            $table->string('prioridad')->nullable();
            $table->string('referencia')->nullable();
            $table->string('ubicacion_origen')->nullable();
            $table->string('ubicacion_destino')->nullable();
            $table->dateTime('fecha_traslado')->nullable();
            $table->string('documento_origen')->nullable();
            $table->string('estado')->nullable();
            $table->string('archivo_dte')->nullable();

            // Para no perder nada
            $table->json('raw')->nullable();

            // Índices para cruces/búsqueda
            $table->index('guia_entrega');
            $table->index('contacto');
            $table->index('fecha_prevista');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excel_out_transfers');
    }
};

