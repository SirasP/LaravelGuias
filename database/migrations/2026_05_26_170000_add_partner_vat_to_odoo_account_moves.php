<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('odoo_account_moves', function (Blueprint $table) {
            $table->string('partner_vat', 30)->nullable()->after('partner_name');
        });
    }

    public function down(): void
    {
        Schema::table('odoo_account_moves', function (Blueprint $table) {
            $table->dropColumn('partner_vat');
        });
    }
};
