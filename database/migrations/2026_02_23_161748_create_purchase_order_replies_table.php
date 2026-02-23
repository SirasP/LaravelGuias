<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'fuelcontrol';

    public function up(): void
    {
        Schema::connection('fuelcontrol')->create('purchase_order_replies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('supplier_name', 255);
            $table->text('notes')->nullable();
            $table->decimal('total_quoted', 15, 4)->nullable();
            $table->string('currency', 10)->nullable()->default('CLP');
            $table->string('pdf_path', 500)->nullable();
            $table->string('pdf_original_name', 255)->nullable();
            $table->timestamps();

            $table->index('purchase_order_id');
        });
    }

    public function down(): void
    {
        Schema::connection('fuelcontrol')->dropIfExists('purchase_order_replies');
    }
};
