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
        if (!Schema::hasTable('gmail_imports')) {
            Schema::connection('fuelcontrol')->create('notificaciones', function (Blueprint $table) {
                $table->id();
                $table->string('tipo'); // xml_entrada
                $table->string('titulo');
                $table->text('mensaje');
                $table->boolean('leido')->default(false);
                $table->timestamps();
            });
        }
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};
