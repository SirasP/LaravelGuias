<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odoo_analytic_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('odoo_id')->unique();
            $table->string('name');
            $table->string('complete_name');
            $table->unsignedInteger('parent_odoo_id')->nullable();
            $table->string('parent_name')->nullable();
            $table->tinyInteger('color')->default(0);
            $table->string('default_applicability')->default('optional');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_analytic_plans');
    }
};
