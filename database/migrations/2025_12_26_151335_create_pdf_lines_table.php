<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pdf_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdf_import_id')->constrained('pdf_imports')->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->text('content');
            $table->timestamps();

            $table->index(['pdf_import_id', 'line_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_lines');
    }
};
