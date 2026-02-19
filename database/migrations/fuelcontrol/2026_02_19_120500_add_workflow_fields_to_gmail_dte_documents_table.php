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
                if (!Schema::connection('fuelcontrol')->hasColumn('gmail_dte_documents', 'payment_status')) {
                    $table->string('payment_status', 20)->default('sin_pagar')->after('monto_total')->index();
                }
                if (!Schema::connection('fuelcontrol')->hasColumn('gmail_dte_documents', 'workflow_status')) {
                    $table->string('workflow_status', 30)->default('borrador')->after('payment_status')->index();
                }
                if (!Schema::connection('fuelcontrol')->hasColumn('gmail_dte_documents', 'inventory_status')) {
                    $table->string('inventory_status', 20)->default('pendiente')->after('workflow_status')->index();
                }
                if (!Schema::connection('fuelcontrol')->hasColumn('gmail_dte_documents', 'paid_at')) {
                    $table->timestamp('paid_at')->nullable()->after('inventory_status');
                }
                if (!Schema::connection('fuelcontrol')->hasColumn('gmail_dte_documents', 'stock_posted_at')) {
                    $table->timestamp('stock_posted_at')->nullable()->after('paid_at');
                }
                if (!Schema::connection('fuelcontrol')->hasColumn('gmail_dte_documents', 'stock_movement_id')) {
                    $table->unsignedBigInteger('stock_movement_id')->nullable()->after('stock_posted_at')->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('fuelcontrol')->hasTable('gmail_dte_documents')) {
            Schema::connection('fuelcontrol')->table('gmail_dte_documents', function (Blueprint $table) {
                $drops = [];
                foreach (['payment_status', 'workflow_status', 'inventory_status', 'paid_at', 'stock_posted_at', 'stock_movement_id'] as $col) {
                    if (Schema::connection('fuelcontrol')->hasColumn('gmail_dte_documents', $col)) {
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
