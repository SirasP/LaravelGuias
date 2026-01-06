<?php

// database/migrations/xxxx_xx_xx_create_dte_detalles_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dte_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dte_id')->constrained('dtes')->cascadeOnDelete();

            $table->unsignedInteger('nro_lin_det')->nullable();
            $table->string('nmb_item')->nullable();
            $table->decimal('qty', 14, 3)->nullable();
            $table->string('unmd_item', 20)->nullable();
            $table->unsignedBigInteger('prc_item')->nullable();
            $table->unsignedBigInteger('monto_item')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dte_detalles');
    }
};
