<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pdf_imports', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('template')->nullable(); // A, B, C...
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_imports');
    }
};
