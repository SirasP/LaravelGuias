<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('odoo_account_moves', function (Blueprint $table) {
            // Folio numérico extraído del campo name ("FAC 106723" → 106723)
            $table->unsignedBigInteger('folio')->nullable()->index()->after('ref');
        });
    }

    public function down(): void
    {
        Schema::table('odoo_account_moves', function (Blueprint $table) {
            $table->dropColumn('folio');
        });
    }
};
