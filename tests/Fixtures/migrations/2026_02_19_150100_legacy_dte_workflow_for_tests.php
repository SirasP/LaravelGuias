<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Production predates the base-table migration. Its guarded 12:05
        // alteration runs before the 14:03 CREATE on an empty test database.
        if (! Schema::connection('fuelcontrol')->hasColumn('gmail_dte_documents', 'workflow_status')) {
            Schema::connection('fuelcontrol')->table('gmail_dte_documents', function (Blueprint $table) {
                $table->string('workflow_status')->default('aceptado');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('fuelcontrol')->table('gmail_dte_documents', fn (Blueprint $table) => $table->dropColumn('workflow_status'));
    }
};
