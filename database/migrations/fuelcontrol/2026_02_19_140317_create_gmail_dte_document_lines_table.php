<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::connection('fuelcontrol')->hasTable('gmail_dte_document_lines')) {
            Schema::connection('fuelcontrol')->create('gmail_dte_document_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('document_id');
                $table->unsignedInteger('nro_linea')->nullable();
                $table->string('codigo')->nullable();
                $table->string('descripcion')->nullable();
                $table->decimal('cantidad', 14, 4)->default(0);
                $table->string('unidad', 20)->nullable();
                $table->decimal('precio_unitario', 14, 4)->default(0);
                $table->decimal('monto_item', 14, 2)->default(0);
                $table->timestamps();

                $table->index('document_id');
                $table->index('descripcion');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('fuelcontrol')->dropIfExists('gmail_dte_document_lines');
    }
};
