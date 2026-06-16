<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'fuelcontrol';

    /**
     * Columnas NUEVAS (nullable) sobre tablas existentes de producción.
     * Aditivo y reversible: no altera columnas ni datos existentes.
     */
    public function up(): void
    {
        if (Schema::connection('fuelcontrol')->hasTable('gmail_inventory_movements')) {
            Schema::connection('fuelcontrol')->table('gmail_inventory_movements', function (Blueprint $table) {
                if (!Schema::connection('fuelcontrol')->hasColumn('gmail_inventory_movements', 'recepcion_id')) {
                    $table->unsignedBigInteger('recepcion_id')->nullable()->index();
                }
            });
        }

        if (Schema::connection('fuelcontrol')->hasTable('gmail_dte_documents')) {
            Schema::connection('fuelcontrol')->table('gmail_dte_documents', function (Blueprint $table) {
                if (!Schema::connection('fuelcontrol')->hasColumn('gmail_dte_documents', 'purchase_order_id')) {
                    $table->unsignedBigInteger('purchase_order_id')->nullable()->index();
                }
                if (!Schema::connection('fuelcontrol')->hasColumn('gmail_dte_documents', 'recepcion_id')) {
                    $table->unsignedBigInteger('recepcion_id')->nullable()->index();
                }
            });
        }

        if (Schema::connection('fuelcontrol')->hasTable('purchase_orders')) {
            Schema::connection('fuelcontrol')->table('purchase_orders', function (Blueprint $table) {
                // Estado de recepción PARALELO al 'status' existente (que NO se toca).
                if (!Schema::connection('fuelcontrol')->hasColumn('purchase_orders', 'reception_status')) {
                    $table->string('reception_status', 20)->nullable()->index(); // null | parcial | recibida
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('fuelcontrol')->hasColumn('gmail_inventory_movements', 'recepcion_id')) {
            Schema::connection('fuelcontrol')->table('gmail_inventory_movements', function (Blueprint $table) {
                $table->dropColumn('recepcion_id');
            });
        }
        foreach (['purchase_order_id', 'recepcion_id'] as $col) {
            if (Schema::connection('fuelcontrol')->hasColumn('gmail_dte_documents', $col)) {
                Schema::connection('fuelcontrol')->table('gmail_dte_documents', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
        if (Schema::connection('fuelcontrol')->hasColumn('purchase_orders', 'reception_status')) {
            Schema::connection('fuelcontrol')->table('purchase_orders', function (Blueprint $table) {
                $table->dropColumn('reception_status');
            });
        }
    }
};
