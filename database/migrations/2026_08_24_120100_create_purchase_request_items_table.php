<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order');
            $table->string('product_service', 1000);
            $table->text('specification')->nullable();
            $table->decimal('quantity', 18, 6);
            $table->string('unit', 80);
            $table->string('quantity_note')->nullable();
            $table->string('destination')->nullable();
            $table->timestamps();

            $table->index(['purchase_request_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_items');
    }
};
