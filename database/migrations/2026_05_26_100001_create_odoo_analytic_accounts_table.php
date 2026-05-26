<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odoo_analytic_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('odoo_id')->unique();
            $table->string('name');
            $table->string('code')->nullable();
            $table->unsignedInteger('plan_odoo_id');
            $table->string('plan_name');
            $table->string('plan_complete_name');
            $table->tinyInteger('color')->default(0);
            $table->timestamps();

            $table->index('plan_odoo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_analytic_accounts');
    }
};
