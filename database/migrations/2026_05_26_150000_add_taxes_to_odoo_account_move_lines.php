<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('odoo_account_move_lines', function (Blueprint $table) {
            // Nombres de impuestos aplicados a la línea: ["IVA 19%", "IEC Diesel"]
            $table->json('taxes')->nullable()->after('analytic_distribution');
        });
    }

    public function down(): void
    {
        Schema::table('odoo_account_move_lines', function (Blueprint $table) {
            $table->dropColumn('taxes');
        });
    }
};
