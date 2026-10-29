<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Odoo une en un grupo las cotizaciones de una misma compra enviadas a
        // varios proveedores; la pestaña Odoo las muestra juntas por este id.
        Schema::table('odoo_purchase_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_group_id')->nullable()->after('origin')->index();
        });
    }

    public function down(): void
    {
        Schema::table('odoo_purchase_orders', function (Blueprint $table) {
            $table->dropIndex(['purchase_group_id']);
            $table->dropColumn('purchase_group_id');
        });
    }
};
