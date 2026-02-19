<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('fuelcontrol')->hasTable('gmail_dte_document_lines')) {
            Schema::connection('fuelcontrol')->table('gmail_dte_document_lines', function (Blueprint $table) {
                if (!Schema::connection('fuelcontrol')->hasColumn('gmail_dte_document_lines', 'impuesto_codigo')) {
                    $table->string('impuesto_codigo', 20)->nullable()->after('monto_item');
                }
                if (!Schema::connection('fuelcontrol')->hasColumn('gmail_dte_document_lines', 'impuesto_tasa')) {
                    $table->decimal('impuesto_tasa', 8, 4)->nullable()->after('impuesto_codigo');
                }
                if (!Schema::connection('fuelcontrol')->hasColumn('gmail_dte_document_lines', 'impuesto_label')) {
                    $table->string('impuesto_label', 120)->nullable()->after('impuesto_tasa');
                }
                if (!Schema::connection('fuelcontrol')->hasColumn('gmail_dte_document_lines', 'es_exento')) {
                    $table->boolean('es_exento')->default(false)->after('impuesto_label');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('fuelcontrol')->hasTable('gmail_dte_document_lines')) {
            Schema::connection('fuelcontrol')->table('gmail_dte_document_lines', function (Blueprint $table) {
                $drops = [];
                foreach (['impuesto_codigo', 'impuesto_tasa', 'impuesto_label', 'es_exento'] as $col) {
                    if (Schema::connection('fuelcontrol')->hasColumn('gmail_dte_document_lines', $col)) {
                        $drops[] = $col;
                    }
                }
                if (!empty($drops)) {
                    $table->dropColumn($drops);
                }
            });
        }
    }
};
