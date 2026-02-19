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
        if (!Schema::connection('fuelcontrol')->hasColumn('vehiculos', 'is_active')) {
            Schema::connection('fuelcontrol')->table('vehiculos', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('tipo');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::connection('fuelcontrol')->hasColumn('vehiculos', 'is_active')) {
            Schema::connection('fuelcontrol')->table('vehiculos', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }
};
