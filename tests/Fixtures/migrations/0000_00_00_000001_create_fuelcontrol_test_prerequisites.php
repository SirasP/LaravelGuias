<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This table predates the repository migrations but later fuelcontrol
        // migrations alter it. Recreate only the minimal legacy prerequisite
        // inside the isolated testing connection.
        Schema::connection('fuelcontrol')->create('vehiculos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('fuelcontrol')->dropIfExists('vehiculos');
    }
};
