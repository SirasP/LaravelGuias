<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('fuelcontrol')->table('gmail_inventory_products', function (Blueprint $table) {
            $table->decimal('stock_minimo', 18, 6)->nullable()->default(null)->after('stock_actual');
        });
    }

    public function down(): void
    {
        Schema::connection('fuelcontrol')->table('gmail_inventory_products', function (Blueprint $table) {
            $table->dropColumn('stock_minimo');
        });
    }
};
