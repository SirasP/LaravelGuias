<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pdf_imports', function (Blueprint $table) {
            $table->string('guia_no')->nullable()->after('template');
            $table->date('doc_fecha')->nullable()->after('guia_no');
            $table->string('productor')->nullable()->after('doc_fecha');
            $table->json('meta')->nullable()->after('productor');
        });
    }

    public function down(): void
    {
        Schema::table('pdf_imports', function (Blueprint $table) {
            $table->dropColumn(['guia_no', 'doc_fecha', 'productor', 'meta']);
        });
    }

};
