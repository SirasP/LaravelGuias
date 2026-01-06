<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('agrak_registros', function (Blueprint $table) {
            $table->id();

            $table->string('codigo_bin', 64)->index(); // AAAA041805
            $table->string('nombre_cosecha')->nullable();

            $table->string('nombre_campo')->nullable();
            $table->string('ceco_campo')->nullable();
            $table->string('etiquetas_campo')->nullable();

            $table->string('cuartel')->nullable();
            $table->string('ceco_cuartel')->nullable();
            $table->string('etiquetas_cuartel')->nullable();

            $table->string('especie')->nullable();
            $table->string('variedad')->nullable();

            $table->date('fecha_registro')->nullable();
            $table->time('hora_registro')->nullable();

            $table->string('coordenadas')->nullable();

            $table->string('usuario')->nullable();
            $table->string('id_usuario')->nullable();
            $table->string('cuadrilla')->nullable();

            $table->integer('numero_bandejas_palet')->nullable();

            $table->string('maquina')->nullable();
            $table->string('nombre_chofer')->nullable();
            $table->string('patente_camion')->nullable();

            // En tu Excel aparecen 2 columnas EXPORTADORA (duplicadas)
            $table->string('exportadora_1')->nullable();
            $table->string('exportadora_2')->nullable();

            $table->integer('vuelta')->nullable();
            $table->text('observacion')->nullable();

            // En tu Excel aparecen 2 columnas NÚMERO DE SELLO (duplicadas)
            $table->string('numero_sello_1')->nullable();
            $table->string('numero_sello_2')->nullable();

            $table->string('source_file')->nullable();
            $table->integer('source_row')->nullable();

            $table->timestamps();

            // ✅ Dedupe recomendado: bin + fecha + hora (ajusta si tu negocio define otra clave)
            $table->unique(['codigo_bin', 'fecha_registro', 'hora_registro'], 'ux_bin_fecha_hora');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agrak_registros');
    }
};
