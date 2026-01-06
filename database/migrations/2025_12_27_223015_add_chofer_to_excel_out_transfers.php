<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('excel_out_transfers', function (Blueprint $table) {
            if (!Schema::hasColumn('excel_out_transfers', 'chofer')) {
                $table->string('chofer')->nullable()->after('patente');
            }
        });
    }

    public function down(): void
    {
        Schema::table('excel_out_transfers', function (Blueprint $table) {
            $table->dropColumn('chofer');
        });
    }
};