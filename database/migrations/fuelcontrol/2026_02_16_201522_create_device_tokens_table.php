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
        Schema::connection('fuelcontrol')->create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('fcm_token');
            $table->enum('device_type', ['android', 'ios'])->default('android');
            $table->string('device_name')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            // Índices
            $table->index('user_id');
            $table->unique(['user_id', 'fcm_token'], 'unique_user_device');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('fuelcontrol')->dropIfExists('device_tokens');
    }
};
