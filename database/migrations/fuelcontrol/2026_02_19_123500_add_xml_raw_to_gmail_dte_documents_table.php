<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('fuelcontrol')->hasTable('gmail_dte_documents')) {
            Schema::connection('fuelcontrol')->table('gmail_dte_documents', function (Blueprint $table) {
                if (!Schema::connection('fuelcontrol')->hasColumn('gmail_dte_documents', 'xml_raw')) {
                    $table->longText('xml_raw')->nullable()->after('xml_path');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('fuelcontrol')->hasTable('gmail_dte_documents')) {
            Schema::connection('fuelcontrol')->table('gmail_dte_documents', function (Blueprint $table) {
                if (Schema::connection('fuelcontrol')->hasColumn('gmail_dte_documents', 'xml_raw')) {
                    $table->dropColumn('xml_raw');
                }
            });
        }
    }
};
