<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('odoo_analytic_plans', function (Blueprint $table) {
            $table->string('name_es')->nullable()->after('name');
        });

        Schema::table('odoo_analytic_accounts', function (Blueprint $table) {
            $table->string('name_es')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('odoo_analytic_plans', function (Blueprint $table) {
            $table->dropColumn('name_es');
        });
        Schema::table('odoo_analytic_accounts', function (Blueprint $table) {
            $table->dropColumn('name_es');
        });
    }
};
