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
        Schema::connection('fuelcontrol')->create('stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('producto');
            $table->date('fecha');
            $table->timestamps();

            $table->unique(['producto', 'fecha']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_alerts');
    }
};
