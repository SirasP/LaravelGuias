<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('fuelcontrol')->table('gmail_inventory_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('bodega_id')->nullable()->after('document_id')
                  ->comment('Bodega destino (guias.bodegas)');
        });
    }

    public function down(): void
    {
        Schema::connection('fuelcontrol')->table('gmail_inventory_movements', function (Blueprint $table) {
            $table->dropColumn('bodega_id');
        });
    }
};
