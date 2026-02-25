<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('fuelcontrol')->table('gmail_inventory_movements', function (Blueprint $table) {
            $table->string('tipo_salida', 30)->nullable()->after('destinatario');  // Venta, EPP, Salida
            $table->decimal('precio_venta', 14, 2)->nullable()->after('tipo_salida');
        });
    }

    public function down(): void
    {
        Schema::connection('fuelcontrol')->table('gmail_inventory_movements', function (Blueprint $table) {
            $table->dropColumn(['tipo_salida', 'precio_venta']);
        });
    }
};
