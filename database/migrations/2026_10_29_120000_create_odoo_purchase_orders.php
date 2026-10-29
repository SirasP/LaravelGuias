<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Copia local de las órdenes de compra hechas directamente en Odoo, sin
        // solicitud en Laravel. Las que salen de una solicitud no se guardan aquí.
        Schema::create('odoo_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->string('name', 40);
            $table->string('state', 20)->index();
            $table->unsignedBigInteger('partner_odoo_id')->nullable();
            $table->string('partner_name')->nullable();
            $table->string('partner_ref')->nullable();
            $table->string('origin')->nullable();
            $table->string('currency', 10)->nullable();
            $table->decimal('amount_untaxed', 18, 2)->default(0);
            $table->decimal('amount_total', 18, 2)->default(0);
            $table->string('created_by_name')->nullable();
            $table->string('buyer_name')->nullable();
            $table->dateTime('date_order')->nullable();
            $table->string('invoice_status', 20)->nullable();
            $table->string('receipt_status', 20)->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('odoo_created_at')->nullable();
            $table->dateTime('odoo_updated_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('odoo_purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('odoo_purchase_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('odoo_id')->unique();
            $table->integer('sequence')->default(0);
            $table->string('display_type', 20)->nullable();
            $table->unsignedBigInteger('product_odoo_id')->nullable();
            $table->text('name')->nullable();
            $table->decimal('product_qty', 18, 4)->default(0);
            $table->string('uom_name', 60)->nullable();
            $table->decimal('price_unit', 18, 4)->default(0);
            $table->decimal('discount', 8, 4)->nullable();
            $table->decimal('price_subtotal', 18, 2)->default(0);
            $table->decimal('qty_received', 18, 4)->nullable();
            $table->decimal('qty_invoiced', 18, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_purchase_order_lines');
        Schema::dropIfExists('odoo_purchase_orders');
    }
};
