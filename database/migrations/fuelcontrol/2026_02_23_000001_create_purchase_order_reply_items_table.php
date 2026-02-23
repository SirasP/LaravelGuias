<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('fuelcontrol')->hasTable('purchase_order_reply_items')) {
            Schema::connection('fuelcontrol')->create('purchase_order_reply_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('reply_id')->index();
                $table->unsignedBigInteger('purchase_order_item_id')->index();
                $table->string('product_name');
                $table->string('unit', 30)->default('UN');
                $table->decimal('quantity', 14, 4)->default(0);
                $table->decimal('unit_price_quoted', 14, 4)->nullable();
                $table->decimal('line_total_quoted', 14, 2)->nullable();
                $table->timestamps();

                $table->unique(['reply_id', 'purchase_order_item_id'], 'po_reply_items_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('fuelcontrol')->dropIfExists('purchase_order_reply_items');
    }
};
