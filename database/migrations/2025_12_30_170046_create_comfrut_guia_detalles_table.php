<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('comfrut_guia_detalles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('comfrut_guia_id')
                ->constrained('comfrut_guias')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('linea');

            $table->string('codigo_tipo')->nullable();
            $table->string('codigo_valor')->nullable();

            $table->string('nombre_item');
            $table->decimal('cantidad', 10, 2);
            $table->string('unidad', 10)->nullable();
            $table->decimal('precio', 12, 2)->nullable();
            $table->decimal('monto', 12, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comfrut_guia_detalles');
    }
};
