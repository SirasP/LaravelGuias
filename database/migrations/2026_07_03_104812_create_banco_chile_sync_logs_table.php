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
        Schema::create('banco_chile_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20); // 'success', 'error'
            $table->integer('new_movements')->default(0);
            $table->string('message', 255);
            $table->text('error_details')->nullable();
            $table->integer('runtime_ms')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banco_chile_sync_logs');
    }
};
