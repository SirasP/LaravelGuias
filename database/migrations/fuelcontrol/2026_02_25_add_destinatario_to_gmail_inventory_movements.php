<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('fuelcontrol')->table('gmail_inventory_movements', function (Blueprint $table) {
            $table->string('destinatario')->nullable()->after('notas');
        });
    }

    public function down(): void
    {
        Schema::connection('fuelcontrol')->table('gmail_inventory_movements', function (Blueprint $table) {
            $table->dropColumn('destinatario');
        });
    }
};
