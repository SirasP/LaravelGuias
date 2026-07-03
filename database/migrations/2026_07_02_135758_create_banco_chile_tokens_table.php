<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banco_chile_tokens', function (Blueprint $table) {
            $table->id();
            $table->text('token');              // El Bearer token completo
            $table->timestamp('expires_at')->nullable(); // Cuándo expira (si se conoce)
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banco_chile_tokens');
    }
};
