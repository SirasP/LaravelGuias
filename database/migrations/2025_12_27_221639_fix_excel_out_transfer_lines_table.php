<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('excel_out_transfer_lines', function (Blueprint $table) {

            if (!Schema::hasColumn('excel_out_transfer_lines', 'excel_out_transfer_id')) {
                $table->foreignId('excel_out_transfer_id')
                    ->after('id')
                    ->constrained('excel_out_transfers')
                    ->cascadeOnDelete();
            }

            if (!Schema::hasColumn('excel_out_transfer_lines', 'producto')) {
                $table->string('producto')->nullable()->after('excel_out_transfer_id');
            }

            if (!Schema::hasColumn('excel_out_transfer_lines', 'cantidad')) {
                $table->decimal('cantidad', 12, 3)->nullable()->after('producto');
            }

            if (!Schema::hasColumn('excel_out_transfer_lines', 'source_file')) {
                $table->string('source_file')->nullable()->after('cantidad');
            }

            if (!Schema::hasColumn('excel_out_transfer_lines', 'excel_row')) {
                $table->unsignedInteger('excel_row')->nullable()->after('source_file');
            }

            if (!Schema::hasColumn('excel_out_transfer_lines', 'raw')) {
                $table->json('raw')->nullable()->after('excel_row');
            }
        });
    }

    public function down(): void
    {
        // no hacemos rollback destructivo
    }
};
