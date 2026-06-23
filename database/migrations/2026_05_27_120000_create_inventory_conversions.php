<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("inventory_conversions", function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger("product_id")->unique();
            $table->string("nombre", 200)->nullable();
            $table->decimal("factor", 10, 4);
            $table->string("unidad_consumo", 20);
            $table->string("unidad_compra", 40)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("inventory_conversions");
    }
};
