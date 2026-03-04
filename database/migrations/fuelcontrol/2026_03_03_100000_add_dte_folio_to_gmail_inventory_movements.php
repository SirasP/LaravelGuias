<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('fuelcontrol')->table('gmail_inventory_movements', function (Blueprint $table) {
            if (!Schema::connection('fuelcontrol')->hasColumn('gmail_inventory_movements', 'dte_folio')) {
                $table->unsignedInteger('dte_folio')->nullable()->unique()->after('dte_xml_path');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('fuelcontrol')->table('gmail_inventory_movements', function (Blueprint $table) {
            if (Schema::connection('fuelcontrol')->hasColumn('gmail_inventory_movements', 'dte_folio')) {
                $table->dropColumn('dte_folio');
            }
        });
    }
};
