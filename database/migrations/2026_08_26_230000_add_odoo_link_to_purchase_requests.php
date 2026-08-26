<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El vínculo con la RFQ creada en Odoo.
 *
 * Sin esto la exportación no puede ser idempotente: al segundo clic se
 * crearía una segunda cotización para la misma solicitud, y nadie sabría
 * cuál de las dos vale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('odoo_order_id')->nullable()->after('currency');
            $table->string('odoo_reference', 64)->nullable()->after('odoo_order_id');
            $table->timestamp('odoo_exported_at')->nullable()->after('odoo_reference');

            $table->index('odoo_order_id', 'pr_odoo_order_idx');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->dropIndex('pr_odoo_order_idx');
            $table->dropColumn(['odoo_order_id', 'odoo_reference', 'odoo_exported_at']);
        });
    }
};
