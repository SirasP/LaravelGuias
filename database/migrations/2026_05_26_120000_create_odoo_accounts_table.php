<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odoo_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('odoo_id')->unique();
            $table->string('code', 30)->index();
            $table->string('name');
            $table->string('name_es')->nullable();
            $table->string('account_type', 50)->nullable();
            $table->boolean('reconcile')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_accounts');
    }
};
