<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('excel_out_transfers', function (Blueprint $table) {
            if (!Schema::hasColumn('excel_out_transfers', 'import_key')) {
                $table->string('import_key')->nullable()->after('guia_entrega');
                $table->index('import_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('excel_out_transfers', function (Blueprint $table) {
            $table->dropIndex(['import_key']);
            $table->dropColumn('import_key');
        });
    }
};
