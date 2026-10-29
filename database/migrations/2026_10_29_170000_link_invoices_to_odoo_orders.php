<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El RUT del proveedor de la orden: es lo que la une con sus facturas.
        Schema::table('odoo_purchase_orders', function (Blueprint $table) {
            $table->string('partner_vat', 20)->nullable()->after('partner_name')->index();
        });

        // Qué factura pagó qué orden hecha directo en Odoo. Va por el id de Odoo
        // y no por el local: la copia local se rehace cada noche.
        Schema::create('dte_odoo_order_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id')->index();
            $table->unsignedBigInteger('odoo_order_id')->index();
            $table->string('order_name', 40);
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('source', 20);
            $table->string('source_key')->nullable()->unique();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('revoked_by')->nullable();
            $table->text('revocation_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dte_odoo_order_links');
        Schema::table('odoo_purchase_orders', function (Blueprint $table) {
            $table->dropIndex(['partner_vat']);
            $table->dropColumn('partner_vat');
        });
    }
};
