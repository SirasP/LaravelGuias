<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('fuelcontrol')->hasTable('purchase_orders')) {
            Schema::connection('fuelcontrol')->create('purchase_orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_number')->unique();
                $table->string('supplier_name');
                $table->string('currency', 3)->default('CLP');
                $table->string('status', 20)->default('draft')->index();
                $table->text('notes')->nullable();
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('total', 14, 2)->default(0);
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::connection('fuelcontrol')->hasTable('purchase_order_items')) {
            Schema::connection('fuelcontrol')->create('purchase_order_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('purchase_order_id')->index();
                $table->unsignedBigInteger('inventory_product_id')->nullable()->index();
                $table->string('product_name');
                $table->string('unit', 30)->default('UN');
                $table->decimal('quantity', 14, 4)->default(0);
                $table->decimal('unit_price', 14, 4)->default(0);
                $table->decimal('line_total', 14, 2)->default(0);
                $table->boolean('is_custom')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::connection('fuelcontrol')->hasTable('purchase_order_recipients')) {
            Schema::connection('fuelcontrol')->create('purchase_order_recipients', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('purchase_order_id')->index();
                $table->string('email')->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('fuelcontrol')->dropIfExists('purchase_order_recipients');
        Schema::connection('fuelcontrol')->dropIfExists('purchase_order_items');
        Schema::connection('fuelcontrol')->dropIfExists('purchase_orders');
    }
};
